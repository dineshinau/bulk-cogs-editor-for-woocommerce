const { test, expect } = require( '@playwright/test' );
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const directory = path.join( os.tmpdir(), 'dkbce-playwright-e2e' );
const stateFile = path.join( directory, 'fixtures.json' );
const url = 'wp-admin/edit.php?post_type=product&page=bulk-cogs-editor';
let fixtures;

test.setTimeout( 120000 );

function wp( code ) {
	return execFileSync( 'wp', [ 'eval', code, `--path=${ process.env.DKBCE_WP_PATH || '/var/www/html/wcdev' }` ], { encoding: 'utf8', timeout: 60000 } ).trim();
}
function fixtureCode( code ) {
	return wp( `$f=json_decode(file_get_contents('${ stateFile }'),true); ${ code }` );
}
function remember( data ) {
	if ( ! data?.preview_id ) { return; }
	const saved = JSON.parse( fs.readFileSync( stateFile, 'utf8' ) );
	saved.cleanup_transients ||= [];
	saved.cleanup_transients.push( 'dkbce_preview_' + data.preview_id );
	fs.writeFileSync( stateFile, JSON.stringify( saved ), { mode: 0o600 } );
}
async function ajax( page, action, values = {}, nonce ) {
	const result = await page.evaluate( async ( { action, values, nonce } ) => {
		const form = new FormData();
		form.append( 'action', 'dkbce_' + action ); form.append( 'nonce', nonce || DKBCE.nonce );
		const append = ( key, value ) => {
			if ( Array.isArray( value ) ) { value.forEach( v => append( key + '[]', v ) ); }
			else if ( value && typeof value === 'object' ) { Object.entries( value ).forEach( ( [ k, v ] ) => append( key + '[' + k + ']', v ) ); }
			else { form.append( key, String( value ) ); }
		};
		Object.entries( values ).forEach( ( [ k, v ] ) => append( k, v ) );
		const response = await fetch( DKBCE.ajaxUrl, { method: 'POST', body: form } );
		return { status: response.status, body: await response.json() };
	}, { action, values, nonce } );
	remember( result.body.data );
	return result;
}
function responseFor( page, action ) {
	return page.waitForResponse( response => response.url().includes( 'admin-ajax.php' ) && response.request().postData()?.includes( 'name="action"\r\n\r\ndkbce_' + action + '\r\n' ) );
}
async function load( page ) {
	await page.goto( url );
	await expect( page.locator( '#dkbce-get-products' ) ).toBeEnabled();
}
async function find( page, search ) {
	await page.locator( '#dkbce-search' ).fill( search );
	const response = responseFor( page, 'preview' );
	await page.locator( '#dkbce-get-products' ).click();
	const data = ( await ( await response ).json() ).data;
	remember( data );
	await expect( page.locator( '#dkbce-get-products' ) ).toBeEnabled();
	return data;
}
async function setCost( page, value = '2' ) {
	const response = responseFor( page, 'preview' );
	await page.locator( '#dkbce-value' ).fill( value );
	const data = ( await ( await response ).json() ).data;
	remember( data );
	await expect( page.locator( '#dkbce-get-products' ) ).toBeEnabled();
	return data;
}
function row( page, id ) { return page.locator( '.dkbce-preview-table tbody tr' ).filter( { has: page.locator( `.dkbce-product-select[value="${ id }"]` ) } ); }
async function edit( page, id, value, keyboard = false ) {
	await row( page, id ).locator( '.dkbce-edit-cost' ).click();
	await row( page, id ).locator( '.dkbce-cost-editor input' ).fill( value );
	const response = responseFor( page, 'preview_edit' );
	if ( keyboard ) { await row( page, id ).locator( '.dkbce-cost-editor input' ).press( 'Enter' ); }
	else { await row( page, id ).getByRole( 'button', { name: 'Save', exact: true } ).click(); }
	const data = ( await ( await response ).json() ).data;
	remember( data );
	await expect( row( page, id ).locator( '.dkbce-custom-cost' ) ).toHaveText( 'Edited' );
	return data;
}

test.beforeAll( () => {
	fixtures = JSON.parse( fs.readFileSync( stateFile, 'utf8' ) );
	fixtureCode( `foreach(array('simple'=>15,'decimal'=>25,'variation'=>60,'external'=>30) as $key=>$cost){$p=wc_get_product($f['products'][$key]);$p->set_cogs_value($cost);if($key==='simple'){$p->set_sale_price('10');}if($key==='external'){$p->set_regular_price('');$p->set_price('');}$p->save();}update_post_meta($f['products']['zero'],'_cogs_total_value','0');` );
} );
test.beforeEach( async ( { page } ) => { await load( page ); } );

test( '1.3.1 tab links support direct URLs, Back, and Forward', async ( { page } ) => {
	for ( const tab of [ 'csv', 'history', 'presets', 'settings', 'bulk' ] ) {
		await page.locator( `[data-dkbce-mode="${ tab }"]` ).click();
		await expect( page.locator( `[data-dkbce-mode="${ tab }"]` ) ).toHaveAttribute( 'aria-pressed', 'true' );
		if ( tab === 'presets' ) { await expect( page.locator( '#dkbce-presets' ) ).not.toHaveAttribute( 'aria-busy', 'true' ); }
		if ( tab !== 'bulk' ) { expect( new URL( page.url() ).searchParams.get( 'tab' ) ).toBe( tab ); }
	}
	await page.goBack(); await expect( page.locator( '[data-dkbce-mode="settings"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
	await page.goForward(); await expect( page.locator( '[data-dkbce-mode="bulk"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
	await page.goto( url + '&tab=presets' ); await expect( page.locator( '[data-dkbce-mode="presets"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
} );

test( '1.3.1 filters distinguish empty, stored zero, positive, selling and regular prices', async ( { page } ) => {
	const ids = {};
	for ( const value of [ '', 'empty', 'zero', 'positive', 'empty_or_zero', 'above_price', 'above_regular_price' ] ) {
		const result = await ajax( page, 'preview', { filters: { category: [ fixtures.category_id ], cogs_value: value }, action_type: 'browse', action_value: '', page_size: '100' } );
		expect( result.body.success, JSON.stringify( result.body ) ).toBe( true );
		ids[ value ] = result.body.data.rows.map( item => item.id );
	}
	expect( ids.empty ).toContain( fixtures.products.empty ); expect( ids.empty ).not.toContain( fixtures.products.zero );
	expect( ids.zero ).toEqual( [ fixtures.products.zero ] );
	expect( ids.positive ).toContain( fixtures.products.simple ); expect( ids.positive ).not.toContain( fixtures.products.zero );
	expect( ids.empty_or_zero ).toEqual( expect.arrayContaining( [ fixtures.products.empty, fixtures.products.zero ] ) );
	expect( ids.above_price ).toEqual( expect.arrayContaining( [ fixtures.products.simple, fixtures.products.decimal, fixtures.products.variation ] ) );
	expect( ids.above_regular_price ).not.toContain( fixtures.products.simple );
	expect( ids.above_regular_price ).toEqual( expect.arrayContaining( [ fixtures.products.decimal, fixtures.products.variation ] ) );
	for ( const value of [ 'above_price', 'above_regular_price' ] ) { expect( ids[ value ] ).not.toContain( fixtures.products.external ); }
	const invalid = await ajax( page, 'preview', { filters: { cogs_value: 'invalid' }, action_type: 'browse', action_value: '' } );
	expect( invalid.status ).toBe( 400 );
} );

test( '1.3.1 browse prices without Step 2, manage columns, and contextual filter help', async ( { page } ) => {
	await find( page, `DKBCE-E2E-${ fixtures.token.toUpperCase() }-SIMPLE` );
	const product = row( page, fixtures.products.simple );
	await expect( product.locator( '[data-column="8"]' ) ).toContainText( '10.00' );
	await expect( product.locator( '[data-column="9"]' ) ).toContainText( '20.00' );
	await expect( product.locator( '[data-column="6"]' ) ).toHaveText( '—' );
	await expect( product.locator( '.dkbce-edit-cost' ) ).toHaveCount( 0 );
	await expect( page.locator( '#dkbce-apply' ) ).toBeDisabled();
	await page.locator( '.dkbce-columns summary' ).click();
	await page.locator( '#dkbce-column-options' ).getByLabel( 'Regular price', { exact: true } ).uncheck();
	await expect( product.locator( '[data-column="9"]' ) ).toBeHidden();
	await page.locator( '#dkbce-column-options' ).getByLabel( 'Regular price', { exact: true } ).check();
	await page.locator( '.dkbce-popover-close' ).click();
	await page.locator( '.dkbce-more-filters > summary' ).click();
	for ( const [ value, help ] of [ [ 'empty', 'no saved' ], [ 'zero', 'equal to 0' ], [ 'above_price', 'selling price' ], [ 'above_regular_price', 'regular price' ] ] ) {
		await page.locator( '#dkbce-cogs-value' ).selectOption( value );
		await expect( page.locator( '#dkbce-cogs-value-help' ) ).toContainText( help );
		if ( [ 'empty', 'zero' ].includes( value ) ) { await expect( page.locator( '#dkbce-cogs-min' ) ).toBeDisabled(); }
		else { await expect( page.locator( '#dkbce-cogs-min' ) ).toBeEnabled(); }
	}
} );

test( '1.3.1 stacked steps align on desktop and avoid overflow on mobile', async ( { page } ) => {
	await page.setViewportSize( { width: 1600, height: 1100 } );
	await find( page, `DKBCE-E2E-${ fixtures.token.toUpperCase() }-SIMPLE` );
	await setCost( page );
	const filters = await page.locator( '.dkbce-filters-section' ).boundingBox();
	const actions = await page.locator( '.dkbce-action-section' ).boundingBox();
	expect( Math.abs( filters.x - actions.x ) ).toBeLessThan( 2 ); expect( Math.abs( filters.width - actions.width ) ).toBeLessThan( 2 );
	expect( actions.y ).toBeGreaterThanOrEqual( filters.y + filters.height );
	await page.setViewportSize( { width: 390, height: 844 } );
	await row( page, fixtures.products.simple ).locator( '.dkbce-edit-cost' ).click();
	expect( await page.evaluate( () => document.documentElement.scrollWidth <= innerWidth ) ).toBe( true );
	await page.screenshot( { path: test.info().outputPath( 'inline-mobile.png' ), fullPage: true } );
	await page.locator( '.dkbce-cost-editor input' ).press( 'Escape' );
} );

test( '1.3.1 action-value debounce allows decimal entry before preview refresh', async ( { page } ) => {
	await find( page, `DKBCE-E2E-${ fixtures.token.toUpperCase() }-SIMPLE` );
	const replies = [];
	const listener = request => { if ( request.postData()?.includes( 'name="action"\r\n\r\ndkbce_preview\r\n' ) ) { replies.push( Date.now() ); } };
	page.on( 'request', listener );
	await page.locator( '#dkbce-value' ).fill( '2' );
	await page.waitForTimeout( 300 );
	expect( replies ).toHaveLength( 0 );
	const response = responseFor( page, 'preview' );
	await page.locator( '#dkbce-value' ).fill( '2.50' );
	const data = ( await ( await response ).json() ).data; remember( data );
	expect( data.rows[0].new ).toBe( 2.5 ); expect( replies ).toHaveLength( 1 );
	page.off( 'request', listener );
} );

test( '1.3.1 inline edits save, cancel, reset, validate, survive paging, and clear with action changes', async ( { page } ) => {
	await find( page, `DKBCE E2E Batch ${ fixtures.token }` );
	await setCost( page );
	const id = fixtures.products.batch_1;
	await edit( page, id, '3.25', true );
	await expect( row( page, id ).locator( '[data-column="7"]' ) ).toContainText( '2.25' );
	await row( page, id ).locator( '.dkbce-edit-cost' ).click();
	await expect( page.locator( '#dkbce-apply' ) ).toBeDisabled();
	await page.locator( '.dkbce-cost-editor input' ).fill( '-1' );
	await page.locator( '.dkbce-cost-editor input' ).press( 'Enter' );
	expect( await page.locator( '.dkbce-cost-editor input' ).evaluate( input => input.validity.valid ) ).toBe( false );
	await page.locator( '.dkbce-cost-editor input' ).press( 'Escape' );
	await expect( row( page, id ).locator( '[data-column="6"]' ) ).toContainText( '3.25' );
	let response = responseFor( page, 'preview_page' );
	await page.locator( '#dkbce-jump-page' ).fill( '2' ); await page.locator( '.dkbce-page-jump button' ).click(); await response;
	await expect( page.locator( '#dkbce-page-size' ) ).toBeEnabled();
	response = responseFor( page, 'preview_page' );
	await page.locator( '#dkbce-jump-page' ).fill( '1' ); await page.locator( '.dkbce-page-jump button' ).click(); await response;
	await expect( row( page, id ).locator( '[data-column="6"]' ) ).toContainText( '3.25' );
	await row( page, id ).locator( '.dkbce-edit-cost' ).click();
	response = responseFor( page, 'preview_edit' );
	await page.getByRole( 'button', { name: 'Reset to Step 2', exact: true } ).click();
	remember( ( await ( await response ).json() ).data );
	await expect( row( page, id ).locator( '.dkbce-custom-cost' ) ).toHaveCount( 0 );
	await expect( row( page, id ).locator( '[data-column="6"]' ) ).toContainText( '2.00' );
	await edit( page, id, '4' );
	await setCost( page, '5' );
	await expect( row( page, id ).locator( '.dkbce-custom-cost' ) ).toHaveCount( 0 );
	await expect( row( page, id ).locator( '[data-column="6"]' ) ).toContainText( '5.00' );
} );

test( '1.3.1 preview editing rejects invalid amounts, nonce, unreviewed IDs, and stale costs', async ( { page, browser } ) => {
	const preview = await ajax( page, 'preview', { filters: { search: `DKBCE-E2E-${ fixtures.token.toUpperCase() }-SIMPLE` }, action_type: 'set', action_value: '2', page_size: '20' } );
	const values = { preview_id: preview.body.data.preview_id, product_id: fixtures.products.simple, value: '3' };
	for ( const value of [ '-1', '1.001', '', '1e3', '1000000000000' ] ) { expect( ( await ajax( page, 'preview_edit', { ...values, value } ) ).status ).toBe( 400 ); }
	expect( ( await ajax( page, 'preview_edit', values, 'invalid' ) ).status ).toBe( 403 );
	expect( ( await ajax( page, 'preview_edit', { ...values, product_id: fixtures.products.decimal } ) ).status ).toBe( 400 );
	const customerContext = await browser.newContext( { storageState: path.join( directory, 'customer.json' ), ignoreHTTPSErrors: true } );
	try {
		const customerPage = await customerContext.newPage();
		await customerPage.goto( new URL( 'wp-login.php', process.env.DKBCE_BASE_URL + '/' ).href );
		const denied = await customerPage.evaluate( async ( { endpoint, nonce, values } ) => {
			const form = new FormData(); form.append( 'action', 'dkbce_preview_edit' ); form.append( 'nonce', nonce );
			Object.entries( values ).forEach( ( [ key, value ] ) => form.append( key, String( value ) ) );
			return ( await fetch( endpoint, { method: 'POST', body: form } ) ).status;
		}, { endpoint: new URL( 'wp-admin/admin-ajax.php', process.env.DKBCE_BASE_URL + '/' ).href, nonce: fixtures.customer_nonce, values } );
		expect( denied ).toBe( 403 );
	} finally { await customerContext.close(); }

	fixtureCode( `$p=wc_get_product($f['products']['simple']);$p->set_cogs_value(16);$p->save();` );
	try {
		const stale = await ajax( page, 'preview_edit', values );
		expect( stale.status ).toBe( 409 ); expect( stale.body.data.refresh_preview ).toBe( true );
	} finally { fixtureCode( `$p=wc_get_product($f['products']['simple']);$p->set_cogs_value(15);$p->save();` ); }
} );

test( '1.3.1 inline zero applies to a disposable product and remains visible after completion', async ( { page } ) => {
	await find( page, `DKBCE-E2E-${ fixtures.token.toUpperCase() }-SIMPLE` );
	await setCost( page );
	const edited = await edit( page, fixtures.products.simple, '0' );
	await row( page, fixtures.products.simple ).locator( '.dkbce-product-select' ).check();
	await page.locator( '#dkbce-apply' ).click();
	await expect( page.locator( '#dkbce-confirm-summary' ) ).toContainText( '1 selected products use individual costs.' );
	const response = responseFor( page, 'apply' );
	await page.locator( '#dkbce-confirm-apply' ).click();
	const applied = ( await ( await response ).json() ).data;
	expect( applied.operation_id ).toBeTruthy();
	fixtureCode( `$s=new DKBCE_Operation_Store();$o=$s->get('${ applied.operation_id }',true);if((int)$o['user_id']!==(int)$f['manager_id']){throw new Exception('Fixture ownership mismatch');}$p=new DKBCE_Bulk_Processor($s,new DKBCE_COGS_Service());$p->process_operation('${ applied.operation_id }');` );
	await expect( page.locator( '#dkbce-selection-context' ) ).toContainText( 'completed operation', { timeout: 45000 } );
	await expect( row( page, fixtures.products.simple ).locator( '[data-column="5"]' ) ).toContainText( 'Empty' );
	await expect( row( page, fixtures.products.simple ).locator( '[data-column="6"]' ) ).toContainText( 'Empty' );
	await expect( row( page, fixtures.products.simple ).locator( '.dkbce-custom-cost' ) ).toHaveText( 'Edited' );
	expect( JSON.parse( fixtureCode( `$p=wc_get_product($f['products']['simple']);echo wp_json_encode(array('cost'=>$p->get_cogs_value(),'price'=>$p->get_regular_price()));` ) ) ).toEqual( { cost: null, price: '20.00' } );
	expect( ( await ajax( page, 'preview_edit', { preview_id: edited.preview_id, product_id: fixtures.products.simple, value: '8' } ) ).status ).toBe( 409 );
	const notice = page.locator( '#dkbce-operation .notice.is-dismissible' );
	await expect( notice ).toBeVisible();
	await expect( notice.locator( 'a' ) ).toHaveAttribute( 'href', /wordpress.org\/support\/plugin\/bulk-cogs-editor-for-woocommerce\/reviews/ );
	const dismissed = responseFor( page, 'dismiss_review' );
	await notice.locator( '.notice-dismiss' ).click(); await dismissed;
	await expect( notice ).toHaveCount( 0 );
	await page.screenshot( { path: test.info().outputPath( 'completion-inline-zero.png' ), fullPage: true } );
} );

test( '1.3.1 feature enable action is idempotent and rejects an invalid nonce', async ( { page } ) => {
	expect( await page.evaluate( () => Boolean( DKBCE.cogsAvailable ) ) ).toBe( true );
	expect( ( await ajax( page, 'enable_cogs', {}, 'invalid' ) ).status ).toBe( 403 );
	expect( ( await ajax( page, 'enable_cogs' ) ).body.success ).toBe( true );
} );

test( '1.3.1 cached health notice dismisses for one hour per user', async ( { page } ) => {
	const cache = JSON.parse( wp( `echo wp_json_encode(array('value'=>get_transient('dkbce_cogs_health'),'expires'=>get_option('_transient_timeout_dkbce_cogs_health')));` ) );
	try {
		wp( `set_transient('dkbce_cogs_health',array('missing'=>2,'above'=>1),HOUR_IN_SECONDS);` );
		await page.reload();
		const notice = page.locator( '#dkbce-health-notice' );
		await expect( notice ).toContainText( '2 products and variations are missing COGS values.' );
		await expect( notice ).toContainText( 'Counts refresh hourly' );
		const dismissed = responseFor( page, 'dismiss_health' );
		await notice.locator( '.notice-dismiss' ).click(); await dismissed;
		await page.reload(); await expect( notice ).toHaveCount( 0 );
		fixtureCode( `delete_user_meta($f['manager_id'],'dkbce_health_dismissed_until');` );
		wp( `set_transient('dkbce_cogs_health',array('missing'=>0,'above'=>2),HOUR_IN_SECONDS);` );
		await page.reload(); await expect( notice ).toContainText( '2 products and variations have COGS values above their current selling prices.' );
	} finally {
		if ( cache.value === false ) { wp( `delete_transient('dkbce_cogs_health');` ); }
		else { wp( `set_transient('dkbce_cogs_health',json_decode('${ JSON.stringify( cache.value ) }',true),max(1,${ Number( cache.expires ) || 0 }-time()));` ); }
	}
} );

test( '1.3.1 saved-filter name and loading controls share a row on desktop', async ( { page } ) => {
	await page.setViewportSize( { width: 1600, height: 1100 } );
	const saved = await ajax( page, 'filter_presets', { preset_action: 'save', name: '1.3.1 fixture ' + fixtures.token, filters: { cogs_value: 'above_regular_price' } } );
	expect( saved.body.success ).toBe( true );
	const id = saved.body.data.presets.find( preset => preset.name === '1.3.1 fixture ' + fixtures.token ).id;
	try {
		await page.reload();
		await page.locator( '.dkbce-presets > summary' ).click();
		const rows = page.locator( '.dkbce-presets .dkbce-preset-row' );
		await expect( rows ).toHaveCount( 2 );
		await expect( rows.nth( 0 ) ).toBeVisible(); await expect( rows.nth( 1 ) ).toBeVisible();
		const left = await rows.nth( 0 ).boundingBox(); const right = await rows.nth( 1 ).boundingBox();
		expect( Math.abs( left.y - right.y ) ).toBeLessThan( 2 );
		expect( right.x ).toBeGreaterThan( left.x );
		await page.locator( '.dkbce-more-filters > summary' ).click();
		const minimum = await page.locator( '#dkbce-price-min' ).boundingBox();
		const value = await page.locator( '#dkbce-cogs-value' ).boundingBox();
		expect( Math.abs( minimum.y - value.y ) ).toBeLessThan( 3 );
	} finally {
		expect( ( await ajax( page, 'filter_presets', { preset_action: 'delete', ids: [ id ] } ) ).body.success ).toBe( true );
	}
} );

test( '1.3.1 disabled COGS notice enables the feature and reloads the editor', async ( { page } ) => {
	const key = 'woocommerce_feature_cost_of_goods_sold_enabled';
	const original = JSON.parse( wp( `echo wp_json_encode(get_option('${ key }',null));` ) );
	try {
		wp( `update_option('${ key }','no');` );
		await page.reload();
		await expect( page.locator( '#dkbce-enable-cogs' ) ).toBeVisible();
		const response = responseFor( page, 'enable_cogs' );
		await page.locator( '#dkbce-enable-cogs' ).click();
		expect( ( await response ).status() ).toBe( 200 );
		await expect( page.locator( '#dkbce-enable-cogs' ) ).toHaveCount( 0 );
		await expect( page.locator( '#dkbce-get-products' ) ).toBeEnabled();
	} finally {
		if ( original === null ) { wp( `delete_option('${ key }');` ); }
		else { wp( `update_option('${ key }',json_decode('${ JSON.stringify( original ) }',true));` ); }
	}
} );


test( '1.3.1 disabled COGS keeps Find products disabled after initialization', async ( { page } ) => {
	const key = 'woocommerce_feature_cost_of_goods_sold_enabled';
	const original = JSON.parse( wp( `echo wp_json_encode(get_option('${ key }',null));` ) );
	try {
		wp( `update_option('${ key }','no');` );
		await page.reload();
		await expect( page.locator( '#dkbce-enable-cogs' ) ).toBeVisible();
		const blocked = await ajax( page, 'get_products', { filters: { search: 'fixture' } } );
		expect( blocked.status ).toBe( 400 );
		await expect( page.locator( '#dkbce-get-products' ) ).toBeDisabled();
	} finally {
		if ( original === null ) { wp( `delete_option('${ key }');` ); }
		else { wp( `update_option('${ key }',json_decode('${ JSON.stringify( original ) }',true));` ); }
	}
} );
