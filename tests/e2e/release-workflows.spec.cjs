const { test, expect } = require( '@playwright/test' );
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const stateFile = path.join( os.tmpdir(), 'dkbce-playwright-e2e', 'fixtures.json' );
const pageURL = 'wp-admin/edit.php?post_type=product&page=bulk-cogs-editor';
let fixtures;

test.skip( process.env.DKBCE_E2E_RELEASE !== '1', 'Set DKBCE_E2E_RELEASE=1 for disposable release fixtures.' );
test.setTimeout( 600000 );

function fixture( mode, values = {} ) {
	const output = execFileSync( 'wp', [ 'eval-file', path.join( __dirname, 'release-fixtures.php' ), `--path=${ process.env.DKBCE_WP_PATH || '/var/www/html/wcdev' }` ], {
		encoding: 'utf8', timeout: 120000,
		env: { ...process.env, DKBCE_E2E_STATE_FILE: stateFile, DKBCE_E2E_RELEASE_INPUT: JSON.stringify( { mode, ...values } ) },
	} );
	return JSON.parse( output.trim().split( '\n' ).findLast( ( line ) => line.startsWith( '{' ) || line.startsWith( '[' ) ) );
}

function remember( data ) {
	const state = JSON.parse( fs.readFileSync( stateFile, 'utf8' ) );
	state.cleanup_transients ||= [];
	for ( const [ field, prefix ] of [ [ 'preview_id', 'dkbce_preview_' ], [ 'preflight_id', 'dkbce_preflight_' ], [ 'review_token', 'dkbce_rollback_review_' ] ] ) {
		if ( data?.[ field ] ) { state.cleanup_transients.push( prefix + data[ field ] ); }
	}
	fs.writeFileSync( stateFile, JSON.stringify( state ), { mode: 0o600 } );
}

async function ajax( page, action, values = {} ) {
	const result = await page.evaluate( async ( { action, values } ) => {
		const form = new FormData();
		form.append( 'action', 'dkbce_' + action ); form.append( 'nonce', DKBCE.nonce );
		const append = ( key, value ) => {
			if ( Array.isArray( value ) ) { value.forEach( ( v ) => append( key + '[]', v ) ); }
			else if ( value && typeof value === 'object' ) { Object.entries( value ).forEach( ( [ k, v ] ) => append( key + '[' + k + ']', v ) ); }
			else { form.append( key, String( value ) ); }
		};
		Object.entries( values ).forEach( ( [ key, value ] ) => append( key, value ) );
		const response = await fetch( DKBCE.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' } );
		return { status: response.status, body: await response.json() };
	}, { action, values } );
	remember( result.body.data );
	return result;
}

async function upload( page, buffer, name = 'release.csv' ) {
	const config = await page.evaluate( () => ( { url: DKBCE.ajaxUrl, nonce: DKBCE.nonce } ) );
	const response = await page.request.post( config.url, { multipart: {
		action: 'dkbce_csv_preview', nonce: config.nonce, mode: 'import',
		import_file: { name, mimeType: name.endsWith( '.xlsx' ) ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv', buffer: Buffer.isBuffer( buffer ) ? buffer : Buffer.from( buffer ) },
	} } );
	const body = await response.json();
	expect( body.success, JSON.stringify( body ) ).toBe( true );
	return body.data.import_id;
}

async function waitImport( page, id ) {
	let data;
	await expect.poll( async () => {
		const response = await ajax( page, 'import_status', { import_id: id } );
		expect( response.body.success ).toBe( true );
		data = response.body.data;
		return ! [ 'pending', 'processing' ].includes( data.status );
	}, { timeout: 480000, intervals: [ 500, 1000, 2000 ] } ).toBe( true );
	return data;
}

async function drive( id ) {
	let result;
	await expect.poll( () => {
		result = fixture( 'drive', { id } );
		return [ 'completed', 'completed_with_errors', 'cancelled', 'failed', 'ready' ].includes( result.state.status );
	}, { timeout: 300000, intervals: [ 500, 1000 ] } ).toBe( true );
	return result.state;
}

async function rollback( page, id ) {
	const review = await ajax( page, 'history_detail', { operation_id: id, review: '1', size: '100' } );
	expect( review.body.success, JSON.stringify( review.body ) ).toBe( true );
	expect( review.body.data.can_rollback ).toBe( true );
	const result = await ajax( page, 'rollback', { operation_id: id, review_token: review.body.data.review_token, confirmed: '1' } );
	expect( result.body.success, JSON.stringify( result.body ) ).toBe( true );
	return drive( result.body.data.operation_id );
}

const releaseSearch = () => 'DKBCE Release ' + fixtures.token;
const csvRows = () => fixtures.release_ids.map( ( id ) => `${ id },12.345678901234567` );
const isAjax = ( response, action ) => response.url().includes( 'admin-ajax.php' ) && response.request().postData()?.includes( 'dkbce_' + action );

test.beforeAll( () => {
	fixtures = JSON.parse( fs.readFileSync( stateFile, 'utf8' ) );
	expect( fixtures.release_ids ).toHaveLength( 260 );
	fs.writeFileSync( path.join( os.tmpdir(), 'dkbce-release-audit.json' ), JSON.stringify( { token: fixtures.token, manager_id: fixtures.manager_id, customer_id: fixtures.customer_id, product_ids: fixtures.product_ids } ), { mode: 0o600 } );
} );
test.beforeEach( async ( { page } ) => {
	const previous = fixture( 'current' );
	if ( [ 'pending', 'processing' ].includes( previous.status ) ) { await drive( previous.id ); }
	fixture( 'clear_current' );
	await page.goto( pageURL );
	await expect( page.getByRole( 'heading', { name: 'Bulk COGS (Cost of Goods Sold) Editor', exact: true } ) ).toBeVisible();
} );

test( 'rounds imported decimals at zero, two, and three configured decimal places', () => {
	const result = fixture( 'rounding' );
	for ( const [ precision, value ] of [ [ 0, '12' ], [ 2, '12.35' ], [ 3, '12.346' ] ] ) {
		expect( result[ precision ].error ).toBe( '' ); expect( result[ precision ].value ).toBe( value );
	}
} );

test( 'batches CSV validation, rejects cross-batch duplicates and invalid rows, and preserves costs', async ( { page } ) => {
	const before = fixture( 'costs', { ids: fixtures.release_ids } );
	const prefix = 'DKBCE-E2E-' + fixtures.token.toUpperCase();
	const rows = fixtures.release_ids.map( ( id, i ) => `${ i % 2 ? '' : id },${ prefix }-RELEASE-${ i + 1 },12.345678901234567` );
	rows.push( `${ fixtures.release_ids[0] },,12.3`, `${ fixtures.products.simple },,`, `${ fixtures.products.decimal },,-1`, `,MISSING-${ fixtures.token },5`, `${ fixtures.products.outofstock },${ prefix }-RELEASE-1,5` );
	const id = await upload( page, [ 'id,sku,new_cogs', ...rows ].join( '\r\n' ) );
	const data = await waitImport( page, id );
	expect( data.status ).toBe( 'ready' ); expect( data.preview.count ).toBe( 259 ); expect( data.preview.invalid_count ).toBe( 6 );
	expect( data.preview.rows.every( ( row ) => row.new === 12.35 ) ).toBe( true );
	expect( fixture( 'costs', { ids: fixtures.release_ids } ) ).toEqual( before );
	const report = await ajax( page, 'import_report', { import_id: id, results: '0' } );
	expect( report.body.success ).toBe( true );
	let download;
	await expect.poll( async () => { download = ( await ajax( page, 'download_status', { download_id: report.body.data.download_id } ) ).body.data; return download.status; }, { timeout: 90000 } ).toBe( 'completed' );
	const text = await ( await page.request.get( download.url ) ).text();
	expect( text ).toContain( 'Duplicate product' ); expect( text ).toContain( 'Blank values are not imported' ); expect( text.trim().split( '\n' ) ).toHaveLength( 7 );
} );

test( 'reads multi-batch XLSX shared strings and sparse worksheet rows without losing products', async ( { page } ) => {
	const workbook = fixture( 'xlsx' );
	const id = await upload( page, Buffer.from( workbook.base64, 'base64' ), 'release.xlsx' );
	const data = await waitImport( page, id );
	expect( data.status, data.message ).toBe( 'ready' ); expect( data.preview.count ).toBe( 260 ); expect( data.preview.invalid_count ).toBe( 0 );
	expect( data.preview.rows.every( ( row ) => row.new === 12.35 ) ).toBe( true );
} );

test( 'rejects XLSX formulas and invalid CSV headers before updating costs', async ( { page } ) => {
	const workbook = fixture( 'xlsx', { formula: true } );
	let data = await waitImport( page, await upload( page, Buffer.from( workbook.base64, 'base64' ), 'formula.xlsx' ) );
	expect( data.status ).toBe( 'failed' ); expect( data.message ).toMatch( /formula/i );
	data = await waitImport( page, await upload( page, 'sku,cost\r\nBAD,12\r\n', 'headers.csv' ) );
	expect( data.status ).toBe( 'failed' ); expect( data.message ).toMatch( /header/i );
} );

test( 'Cancel preparation requires confirmation, Escape keeps running, and tabs remain accessible', async ( { page } ) => {
	const { id } = fixture( 'seed', { kind: 'import' } );
	await page.reload();
	const button = page.locator( '#dkbce-import-progress button' );
	await expect( button ).toBeVisible();
	for ( const mode of [ 'settings', 'bulk', 'csv' ] ) { await page.locator( `[data-dkbce-mode="${ mode }"]` ).click(); await expect( page.locator( `[data-dkbce-mode="${ mode }"]` ) ).toHaveAttribute( 'aria-pressed', 'true' ); }
	await expect( page.locator( '#dkbce-csv-file' ) ).toBeDisabled();
	await button.click(); await expect( page.locator( '#dkbce-confirm-text' ) ).toContainText( 'Product costs have not been changed' );
	await page.keyboard.press( 'Escape' ); await expect( page.locator( '#dkbce-confirm' ) ).toBeHidden();
	expect( fixture( 'inspect', { id } ).state.cancel_requested ).toBe( false );
	await button.click(); await page.locator( '#dkbce-confirm-cancel' ).click(); expect( fixture( 'inspect', { id } ).state.cancel_requested ).toBe( false );
	await button.click(); await page.locator( '#dkbce-confirm-apply' ).click();
	await expect.poll( () => fixture( 'inspect', { id } ).state.cancel_requested ).toBe( true );
	expect( ( await drive( id ) ).status ).toBe( 'cancelled' );
} );

test( 'Stop update requires confirmation and allows returning from Settings to both editor tabs', async ( { page } ) => {
	const { id } = fixture( 'seed', { kind: 'active' } );
	await page.reload();
	const stop = page.locator( '#dkbce-operation [data-operation-control="cancel"]' );
	await expect( stop ).toBeVisible();
	for ( const mode of [ 'settings', 'csv', 'bulk' ] ) { await page.locator( `[data-dkbce-mode="${ mode }"]` ).click(); }
	await expect( page.locator( '#dkbce-get-products' ) ).toBeDisabled();
	await stop.click(); await expect( page.locator( '#dkbce-confirm-text' ) ).toContainText( 'Any changes already made will remain' );
	await page.locator( '#dkbce-confirm-cancel' ).click(); expect( fixture( 'inspect', { id } ).state.cancel_requested ).toBe( false );
	await stop.click(); await page.keyboard.press( 'Escape' ); expect( fixture( 'inspect', { id } ).state.cancel_requested ).toBe( false );
	await stop.click(); await page.locator( '#dkbce-confirm-apply' ).click();
	await expect.poll( () => fixture( 'inspect', { id } ).state.cancel_requested ).toBe( true );
	expect( ( await drive( id ) ).status ).toBe( 'cancelled' );
} );

test( 'force clear confirms first, requests cancellation, and keeps the operation record', async ( { page } ) => {
	const { id } = fixture( 'seed', { kind: 'active' } );
	await page.reload(); await page.locator( '[data-dkbce-mode="settings"]' ).click();
	const button = page.getByRole( 'button', { name: 'Force clear current operation', exact: true } );
	await button.click(); await expect( page.locator( '#dkbce-confirm-warning' ) ).toContainText( 'History and rollback data are kept' );
	await page.locator( '#dkbce-confirm-cancel' ).click(); expect( fixture( 'inspect', { id } ).state.cancel_requested ).toBe( false );
	await button.click();
	const reloaded = page.waitForEvent( 'load' );
	await page.locator( '#dkbce-confirm-apply' ).click(); await reloaded;
	await expect( page.getByRole( 'heading', { name: 'Bulk COGS (Cost of Goods Sold) Editor', exact: true } ) ).toBeVisible();
	await expect.poll( () => fixture( 'inspect', { id } ).state.cancel_requested ).toBe( true );
	const settings = await ajax( page, 'settings' ); expect( settings.body.data.dkbce_latest_operation ).toBe( '' );
	expect( fixture( 'inspect', { id } ).state.operation_id ).toBe( id );
} );

test( 'force clear rejects stale reviewed references without cancelling a newer operation', async ( { page } ) => {
	fixture( 'seed', { kind: 'active' } );
	const settings = ( await ajax( page, 'settings' ) ).body.data;
	const { id } = fixture( 'seed', { kind: 'active' } );
	const result = await ajax( page, 'force_clear', { confirmed: '1', dkbce_latest_operation: settings.dkbce_latest_operation, dkbce_latest_import: settings.dkbce_latest_import } );
	expect( result.status ).toBe( 409 ); expect( fixture( 'inspect', { id } ).state.cancel_requested ).toBe( false );
} );

test( 'applies five CSV rows after excluding 255, rejects replay, hides empty reports, and rolls back', async ( { page } ) => {
	const before = fixture( 'costs', { ids: fixtures.release_ids } );
	const id = await upload( page, [ 'id,new_cogs', ...csvRows() ].join( '\r\n' ) );
	const data = await waitImport( page, id ); expect( data.status ).toBe( 'ready' );
	const payload = { preview_id: data.preview.preview_id, selected_only: '1', selection_mode: 'all_except', excluded_ids: JSON.stringify( fixtures.release_ids.slice( 5 ) ), confirmed_count: '5' };
	const applied = await ajax( page, 'apply', payload ); expect( applied.body.success, JSON.stringify( applied.body ) ).toBe( true );
	const state = await drive( applied.body.data.operation_id ); expect( state.status ).toBe( 'completed' ); expect( state.succeeded ).toBe( 5 );
	const after = fixture( 'costs', { ids: fixtures.release_ids } );
	fixtures.release_ids.forEach( ( pid, index ) => { expect( after[ pid ].cogs ).toBe( index < 5 ? 12.35 : before[ pid ].cogs ); expect( after[ pid ].price ).toBe( before[ pid ].price ); } );
	expect( [ 400, 409 ] ).toContain( ( await ajax( page, 'apply', payload ) ).status );
	await page.reload(); await expect( page.locator( '#dkbce-operation' ).getByRole( 'button', { name: 'Download error report' } ) ).toHaveCount( 0 );
	expect( ( await rollback( page, state.operation_id ) ).succeeded ).toBe( 5 );
	expect( fixture( 'costs', { ids: fixtures.release_ids } ) ).toEqual( before );
} );

test( 'UI continues Apply beyond 250 reviewed products, reuses changed-cost selection, and rolls back all', async ( { page } ) => {
	const before = fixture( 'costs', { ids: fixtures.release_ids } );
	await page.locator( '#dkbce-search' ).fill( releaseSearch() );
	for ( const selector of [ '#dkbce-cogs-min', '#dkbce-cogs-max' ] ) { await page.locator( selector ).evaluate( ( input ) => { input.value = '10'; input.dispatchEvent( new Event( 'input', { bubbles: true } ) ); } ); }
	let response = page.waitForResponse( ( r ) => isAjax( r, 'get_products' ) ); await page.locator( '#dkbce-get-products' ).click(); await response;
	await page.locator( '#dkbce-action-kind' ).selectOption( 'adjust' ); await page.locator( '#dkbce-action-direction' ).selectOption( 'increase' ); await page.locator( '#dkbce-action-unit' ).selectOption( 'fixed' );
	response = page.waitForResponse( ( r ) => isAjax( r, 'preview' ) ); await page.locator( '#dkbce-value' ).fill( '5' ); await response;
	await expect( page.locator( '.dkbce-preview-table tbody tr' ).first().locator( '[data-column="6"]' ) ).toContainText( '15.00' );
	await expect( page.locator( '.dkbce-preview-table' ) ).toBeVisible();
	response = page.waitForResponse( ( r ) => isAjax( r, 'preview_page' ) ); await page.locator( '#dkbce-page-size' ).selectOption( '100' ); await response;
	await expect( page.locator( '.dkbce-preview-table tbody tr' ) ).toHaveCount( 100 );
	await expect( page.locator( '#dkbce-page-size' ) ).toBeEnabled();
	for ( const number of [ 2, 3 ] ) {
		response = page.waitForResponse( ( r ) => isAjax( r, 'preview_page' ) ); await page.locator( '#dkbce-jump-page' ).fill( String( number ) ); await page.locator( '.dkbce-page-jump button' ).click();
		const reply = ( await ( await response ).json() ).data;
		expect( reply.page ).toBe( number );
		await expect( page.locator( '.dkbce-pagination [aria-current="page"]' ) ).toHaveText( String( number ) );
		await expect( page.locator( '.dkbce-preview-table tbody tr' ) ).toHaveCount( number === 3 ? 60 : 100 );
		await expect( page.locator( '#dkbce-page-size' ) ).toBeEnabled();
	}
	const replies = [];
	page.on( 'response', async ( r ) => { if ( isAjax( r, 'apply' ) ) { replies.push( ( await r.json() ).data ); } } );
	await page.locator( '#dkbce-apply' ).click();
	const finalResponse = page.waitForResponse( async ( r ) => isAjax( r, 'apply' ) && Boolean( ( await r.json() ).data?.operation_id ), { timeout: 90000 } );
	await page.locator( '#dkbce-confirm-apply' ).click();
	const applied = ( await ( await finalResponse ).json() ).data;
	const state = await drive( applied.operation_id ); expect( state.status ).toBe( 'completed' ); expect( state.succeeded ).toBe( 260 );
	const updated = fixture( 'costs', { ids: fixtures.release_ids } );
	for ( const pid of fixtures.release_ids ) { expect( updated[ pid ].cogs, `COGS for ${ pid }` ).toBe( 15 ); expect( updated[ pid ].price, `Price for ${ pid }` ).toBe( before[ pid ].price ); }
	await expect( page.locator( '#dkbce-selection-context' ) ).toContainText( 'completed operation', { timeout: 30000 } );
	await expect( page.locator( '#dkbce-selection-count' ) ).toContainText( '260' );
	await page.setViewportSize( { width: 390, height: 844 } );
	expect( await page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ) ).toBe( true );
	expect( ( await rollback( page, state.operation_id ) ).succeeded ).toBe( 260 );
	expect( fixture( 'costs', { ids: fixtures.release_ids } ) ).toEqual( before );
	expect( replies.some( ( data ) => data?.preflight_pending ) ).toBe( true );
} );

test( 'pending and started retention cleanup reject new and existing selection reuse paths', async ( { page } ) => {
	const { id } = fixture( 'seed' );
	const initial = await ajax( page, 'preview', { previous_operation_id: id, filters: { search: releaseSearch() }, action_type: 'set', action_value: '15' } );
	expect( initial.body.success ).toBe( true ); expect( initial.body.data.count ).toBe( 3 );
	const token = initial.body.data.preview_id;
	fixture( 'patch', { id, fields: { history_cleanup_pending: true } } );
	for ( const [ action, payload ] of [
		[ 'preview', { previous_operation_id: id, filters: {}, action_type: 'set', action_value: '15' } ],
		[ 'preview', { previous_preview_id: token, filters: {}, action_type: 'set', action_value: '15', selection_mode: 'all_except', excluded_ids: '[]' } ],
		[ 'preview_page', { preview_id: token, page: '1', page_size: '20' } ],
		[ 'select_range', { preview_id: token, direction: 'first', count: '1' } ],
		[ 'apply', { preview_id: token, selected_only: '1', selection_mode: 'all_except', excluded_ids: '[]', confirmed_count: '3' } ],
	] ) {
		const result = await ajax( page, action, payload ); expect( result.status, action ).toBe( 409 ); expect( result.body.data.message ).toContain( 'Reset filters' );
	}
	fixture( 'patch', { id, fields: { history_cleanup_pending: false, history_cleanup_started: true } } );
	expect( ( await ajax( page, 'preview_page', { preview_id: token, page: '1', page_size: '20' } ) ).status ).toBe( 409 );
} );

test( 'rejects cleanup that begins after the preview precheck and before product reads return', () => {
	const { id } = fixture( 'seed' );
	const result = fixture( 'race_preview', { id } );
	expect( result.success ).toBe( false ); expect( result.data.refresh_preview ).toBe( true ); expect( result.data.message ).toContain( 'Reset filters' );
} );

test( 'cleanup persists its marker before each outcome deletion and retains it across bounded retries', () => {
	const { id } = fixture( 'seed', { outcomes: 760 } );
	fixture( 'patch', { id, fields: { cleanup_pending: true, history_cleanup_pending: true, completed_at: Math.floor( Date.now() / 1000 ) - 366 * 86400 } } );
	const first = fixture( 'cleanup_probe', { id } );
	expect( first.marker_checks ).toEqual( [ true ] ); expect( first.state.history_cleanup_started ).toBe( true ); expect( first.summary.processed ).toBe( 260 );
	fixture( 'patch', { id, fields: { completed_at: Math.floor( Date.now() / 1000 ) } } );
	const second = fixture( 'cleanup_probe', { id } );
	expect( second.marker_checks ).toEqual( [ true ] ); expect( second.state.history_cleanup_complete ).toBe( true ); expect( second.summary.processed ).toBe( 0 );
} );

test( 'retention recheck preserves outcomes when cleanup has not started and the source is still retained', async ( { page } ) => {
	const { id } = fixture( 'seed' ); fixture( 'patch', { id, fields: { cleanup_pending: true, history_cleanup_pending: true } } );
	const cleaned = fixture( 'cleanup_probe', { id } );
	expect( cleaned.marker_checks ).toEqual( [] ); expect( cleaned.summary.processed ).toBe( 3 ); expect( cleaned.state.history_cleanup_started ).toBeUndefined();
	const result = await ajax( page, 'preview', { previous_operation_id: id, filters: {}, action_type: 'set', action_value: '15' } );
	expect( result.body.success ).toBe( true ); expect( result.body.data.count ).toBe( 3 );
} );

test( 'exports selected columns across batches without changing product costs', async ( { page } ) => {
	const before = fixture( 'costs', { ids: fixtures.release_ids } );
	const preview = await ajax( page, 'csv_preview', { mode: 'export', filters: { search: releaseSearch() } } ); expect( preview.body.success ).toBe( true );
	const created = await ajax( page, 'csv_export', { preview_id: preview.body.data.preview_id, columns: [ 'id', 'sku' ], selected_only: '', excluded_ids: '[]' } );
	expect( created.body.success, JSON.stringify( created.body ) ).toBe( true );
	let data;
	await expect.poll( async () => { data = ( await ajax( page, 'download_status', { download_id: created.body.data.download_id } ) ).body.data; return data.status; }, { timeout: 90000 } ).toBe( 'completed' );
	const text = await ( await page.request.get( data.url ) ).text(); const lines = text.replace( /^\uFEFF/, '' ).trim().split( /\r?\n/ );
	expect( lines ).toHaveLength( 261 ); expect( lines[0] ).toBe( '"id","sku","new_cogs"' );
	expect( fixture( 'costs', { ids: fixtures.release_ids } ) ).toEqual( before );
} );
