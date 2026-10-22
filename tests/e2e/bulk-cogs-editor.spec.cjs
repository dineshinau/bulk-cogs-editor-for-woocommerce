const { test, expect } = require( '@playwright/test' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const stateDirectory = path.join( os.tmpdir(), 'dkbce-playwright-e2e' );
let fixtures;
const pageURL = 'wp-admin/edit.php?post_type=product&page=bulk-cogs-editor';

async function loadPage( page ) {
	await page.goto( pageURL );
	await expect(
		page.getByRole( 'heading', { name: 'Bulk COGS Editor', exact: true } )
	).toBeVisible();
	await expect( page.locator( '#dkbce-get-products' ) ).toBeEnabled();
}

async function setFilters( page, values = {} ) {
	await page.locator( '#dkbce-search' ).fill( values.search || '' );
	for ( const [ selector, value ] of [
		[ '#dkbce-category', values.category ],
		[ '#dkbce-type', values.type ],
		[ '#dkbce-stock', values.stock ],
		[ '#dkbce-brand', values.brand ],
	] ) {
		const select = page.locator( selector );
		if ( await select.count() ) {
			await select.evaluate( ( element, selected ) => {
				Array.from( element.options ).forEach( ( option ) => {
					option.selected = selected !== undefined && String( selected ) === option.value;
				} );
				element.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}, value );
		}
	}
	for ( const [ selector, value ] of [
		[ '#dkbce-price-min', values.priceMin ],
		[ '#dkbce-price-max', values.priceMax ],
		[ '#dkbce-cogs-min', values.cogsMin ],
		[ '#dkbce-cogs-max', values.cogsMax ],
	] ) {
		await page.locator( selector ).evaluate( ( input, nextValue ) => {
			input.value = undefined === nextValue ? '' : String( nextValue );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}, value );
	}
}

async function getProducts( page, values = {} ) {
	await setFilters( page, values );
	const responsePromise = page.waitForResponse( ( response ) =>
		response.url().includes( 'admin-ajax.php' ) &&
		response.request().postData()?.includes( 'dkbce_get_products' )
	);
	await page.locator( '#dkbce-get-products' ).click();
	await responsePromise;
	await expect( page.locator( '#dkbce-get-products' ) ).toBeEnabled();
}

async function requestAjax( page, action, values = {}, nonce ) {
	return page.evaluate(
		async ( request ) => {
			const data = new FormData();
			data.append( 'action', `dkbce_${ request.action }` );
			data.append( 'nonce', request.nonce || window.DKBCE?.nonce );
			for ( const [ key, value ] of Object.entries( request.values ) ) {
				if ( 'filters' === key && value && 'object' === typeof value ) {
					for ( const [ filter, filterValue ] of Object.entries( value ) ) {
						data.append( `filters[${ filter }]`, filterValue );
					}
				} else {
					data.append( key, value );
				}
			}
			const ajaxUrl =
				window.DKBCE?.ajaxUrl ||
				new URL( 'admin-ajax.php', window.location.href ).href;
			const response = await fetch( ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} );
			return {
				status: response.status,
				body: await response.json(),
			};
		},
		{ action, values, nonce }
	);
}

async function preview( page, action, value, filterValues = {} ) {
	await getProducts( page, filterValues );
	const responsePromise = page.waitForResponse( ( response ) =>
		response.url().includes( 'admin-ajax.php' ) &&
		response.request().postData()?.includes( 'dkbce_preview' )
	);
	const [ kind, direction, unit ] = {
		set: [ 'set', 'increase', 'percent' ],
		increase_percent: [ 'adjust', 'increase', 'percent' ],
		decrease_percent: [ 'adjust', 'decrease', 'percent' ],
		increase_fixed: [ 'adjust', 'increase', 'fixed' ],
		decrease_fixed: [ 'adjust', 'decrease', 'fixed' ],
		clear: [ 'clear', 'increase', 'percent' ],
	}[ action ];
	await page.locator( '#dkbce-action-kind' ).selectOption( kind );
	if ( 'adjust' === kind ) {
		await page.locator( '#dkbce-action-direction' ).selectOption( direction );
		await page.locator( '#dkbce-action-unit' ).selectOption( unit );
	}
	if ( 'clear' !== action ) {
		await page.locator( '#dkbce-value' ).fill( String( value ) );
	}
	const response = await responsePromise;
	const payload = await response.json();
	await expect( page.locator( '.dkbce-preview-table' ) ).toBeVisible();
	return payload.data;
}

function fixtureSku( key ) {
	return `DKBCE-E2E-${ fixtures.token.toUpperCase() }-${ key }`;
}

function rowForSku( page, sku ) {
	return page.locator( '.dkbce-preview-table tbody tr' ).filter( { hasText: sku } );
}

test.beforeAll( () => {
	fixtures = JSON.parse(
		fs.readFileSync( path.join( stateDirectory, 'fixtures.json' ), 'utf8' )
	);
} );

test.beforeEach( async ( { page } ) => {
	await loadPage( page );
} );

test( 'loads admin page with six actions and accessible filter controls', async ( { page } ) => {
	await expect( page.getByLabel( 'Search', { exact: true } ) ).toBeVisible();
	await expect( page.locator( '#dkbce-category-summary' ) ).toBeVisible();
	await expect( page.locator( '#dkbce-type-summary' ) ).toBeVisible();
	await page.locator( 'details.dkbce-more-filters' ).evaluate( ( details ) => { details.open = true; } );
	await expect( page.locator( '#dkbce-stock-summary' ) ).toBeVisible();
	await expect( page.locator( '#dkbce-action-kind option' ) ).toHaveCount( 3 );
	await expect( page.locator( '#dkbce-action-direction option' ) ).toHaveCount( 2 );
	await expect( page.locator( '#dkbce-action-unit option' ) ).toHaveCount( 2 );
	await expect( page.locator( '#dkbce-apply' ) ).toBeDisabled();
	if ( fixtures.brand_id ) {
		await expect( page.locator( '#dkbce-brand-summary' ) ).toBeVisible();
	} else {
		await expect( page.locator( '#dkbce-brand' ) ).toHaveCount( 0 );
	}
} );

test( 'keeps the apply panel in document flow on mobile', async ( { page } ) => {
	await page.setViewportSize( { width: 390, height: 844 } );
	await expect( page.locator( '.dkbce-apply-section' ) ).toHaveCSS( 'position', 'static' );
	await expect( page.locator( '.dkbce-apply-section' ) ).toHaveCSS( 'box-shadow', 'none' );
} );

test( 'search matches product title and SKU and reset clears filters', async ( { page } ) => {
	await getProducts( page, { search: `Simple ${ fixtures.token }` } );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matches:' );
	await getProducts( page, { search: fixtureSku( 'SIMPLE' ) } );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matches:' );
	await page.locator( '#dkbce-reset' ).click();
	await expect( page.locator( '#dkbce-search' ) ).toHaveValue( '' );
	await expect( page.locator( '#dkbce-category-summary' ) ).toHaveText( /All categories/ );
} );

test( 'filters by category, price range, COGS range, and combined AND conditions', async ( { page } ) => {
	await getProducts( page, {
		search: fixtureSku( 'SIMPLE' ),
		category: fixtures.category_id,
		priceMin: '19',
		priceMax: '21',
		cogsMin: '9',
		cogsMax: '11',
	} );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matches:' );
	await getProducts( page, {
		search: fixtureSku( 'SIMPLE' ),
		priceMin: '21',
	} );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( 'No products match' );
} );

test( 'filters simple, variable, variation, grouped, and external products', async ( { page } ) => {
	for ( const [ type, key ] of [
		[ 'simple', 'SIMPLE' ],
		[ 'variable', 'VARIABLE' ],
		[ 'variation', 'VARIATION' ],
		[ 'grouped', 'GROUPED' ],
		[ 'external', 'EXTERNAL' ],
	] ) {
		await getProducts( page, { type, search: fixtureSku( key ) } );
		await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matches:' );
	}
} );

test( 'filters all stock states', async ( { page } ) => {
	for ( const [ stock, key ] of [
		[ 'instock', 'SIMPLE' ],
		[ 'outofstock', 'OUT' ],
		[ 'onbackorder', 'BACKORDER' ],
	] ) {
		await getProducts( page, { stock, search: fixtureSku( key ) } );
		await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matches:' );
	}
} );

test( 'filters by supported brand taxonomy when available', async ( { page } ) => {
	test.skip( ! fixtures.brand_id, 'No supported brand taxonomy is registered.' );
	await getProducts( page, {
		search: fixtureSku( 'SIMPLE' ),
		brand: fixtures.brand_id,
	} );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matches:' );
} );

test( 'previews all six actions without modifying the current COGS value', async ( { page } ) => {
	const actions = [
		[ 'set', '15', '15.00', null ],
		[ 'increase_percent', '10', '11.00', 'is-increase' ],
		[ 'decrease_percent', '10', '9.00', 'is-decrease' ],
		[ 'increase_fixed', '2', '12.00', 'is-increase' ],
		[ 'decrease_fixed', '2', '8.00', 'is-decrease' ],
		[ 'clear', '', 'Empty', 'is-decrease' ],
	];

	for ( const [ action, value, expected, direction ] of actions ) {
		const result = await preview( page, action, value, {
			search: fixtureSku( 'SIMPLE' ),
		} );
		expect( result.count ).toBe( 1 );
		const row = rowForSku( page, fixtureSku( 'SIMPLE' ) );
		await expect( row.locator( '[data-column="5"]' ) ).toContainText( '10.00' );
		await expect( row.locator( '[data-column="6"]' ) ).toContainText( expected );
		if ( direction ) {
			await expect( row.locator( '.dkbce-change-badge' ) ).toHaveClass(
				new RegExp( direction )
			);
		}
	}
} );

test( 'preview shows images, edit links, increase green and decrease red', async ( { page } ) => {
	await preview( page, 'increase_percent', '10', { search: fixtureSku( 'SIMPLE' ) } );
	const row = rowForSku( page, fixtureSku( 'SIMPLE' ) );
	await expect( row.locator( '[data-column="1"] a' ) ).toHaveAttribute(
		'href',
		new RegExp( `post\.php\\?post=${ fixtures.products.simple }&action=edit` )
	);
	await expect( row.locator( '[data-column="2"] img' ) ).toBeVisible();
	await expect( row.locator( '.dkbce-change-badge' ) ).toHaveCSS( 'color', 'rgb(0, 138, 32)' );

	await preview( page, 'decrease_fixed', '2', { search: fixtureSku( 'SIMPLE' ) } );
	await expect( rowForSku( page, fixtureSku( 'SIMPLE' ) ).locator( '.dkbce-change-badge' ) ).toHaveCSS(
		'color',
		'rgb(179, 45, 46)'
	);
} );

test( 'skips relative operations for empty COGS and WooCommerce-normalized zero', async ( { page } ) => {
	await preview( page, 'increase_percent', '10', { search: fixtureSku( 'EMPTY' ) } );
	const emptyRow = rowForSku( page, fixtureSku( 'EMPTY' ) );
	await expect( emptyRow.locator( '[data-column="5"]' ) ).toHaveText( 'Empty' );
	await expect( emptyRow.locator( '[data-column="6"]' ) ).toHaveText( '—' );
	await expect( emptyRow.locator( '[data-column="7"]' ) ).toContainText( 'Current COGS is not set' );

	await page.reload();
	await loadPage( page );
	await preview( page, 'increase_percent', '10', { search: fixtureSku( 'ZERO' ) } );
	const zeroRow = rowForSku( page, fixtureSku( 'ZERO' ) );
	await expect( zeroRow.locator( '[data-column="5"]' ) ).toHaveText( 'Empty' );
	await expect( zeroRow.locator( '[data-column="6"]' ) ).toHaveText( '—' );
	await expect( zeroRow.locator( '[data-column="7"]' ) ).toContainText( 'Current COGS is not set' );
} );

test( 'rounds calculated COGS to store precision', async ( { page } ) => {
	await preview( page, 'increase_percent', '4', { search: fixtureSku( 'DECIMAL' ) } );
	const row = rowForSku( page, fixtureSku( 'DECIMAL' ) );
	await expect( row.locator( '[data-column="5"]' ) ).toContainText( '9.99' );
	await expect( row.locator( '[data-column="6"]' ) ).toContainText( '10.39' );
} );

test( 'paginates large previews and reports total matching products', async ( { page } ) => {
	const data = await preview( page, 'set', '2', {
		search: `DKBCE E2E Batch ${ fixtures.token }`,
	} );
	expect( data.count ).toBe( 55 );
	expect( data.rows ).toHaveLength( 20 );
	await expect( page.locator( '#dkbce-preview-content' ) ).toContainText(
		'Showing 1–20 of 55 products.'
	);
} );

test( 'renders malicious product names as text', async ( { page } ) => {
	await preview( page, 'set', '2', { search: fixtureSku( 'XSS' ) } );
	await expect( page.locator( '.dkbce-preview-table tbody img[src="x"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '.dkbce-preview-table tbody' ) ).toContainText( '<img src=x onerror=alert(1)>' );
} );

test( 'requires valid filters, action values, nonce, and capability', async ( { page, browser } ) => {
	const invalidNonce = await requestAjax(
		page,
		'get_products',
		{ filters: {} },
		'invalid'
	);
	expect( invalidNonce.status ).toBe( 403 );

	const invalidAction = await requestAjax( page, 'preview', {
		filters: {},
		action_type: 'arbitrary',
		action_value: '1',
	} );
	expect( invalidAction.status ).toBe( 400 );

	const invalidValue = await requestAjax( page, 'preview', {
		filters: {},
		action_type: 'set',
		action_value: '-1',
	} );
	expect( invalidValue.status ).toBe( 400 );

	const customerContext = await browser.newContext( {
		baseURL: `${ process.env.DKBCE_BASE_URL.replace( /\/$/, '' ) }/`,
		ignoreHTTPSErrors: true,
		storageState: path.join( stateDirectory, 'customer.json' ),
	} );
	const customerPage = await customerContext.newPage();
	await customerPage.goto( pageURL );
	const denied = await requestAjax(
		customerPage,
		'get_products',
		{ filters: {} },
		fixtures.customer_nonce
	);
	expect( denied.status ).toBe( 403 );
	await customerContext.close();
} );

test( 'cancellation request is scoped to an existing authorized operation', async ( { page, browser } ) => {
	const progress = await requestAjax( page, 'progress', {
		operation_id: fixtures.operation_id,
	} );
	expect( progress.body.success ).toBe( true );
	const cancel = await requestAjax( page, 'cancel', {
		operation_id: fixtures.operation_id,
	} );
	expect( cancel.body.success ).toBe( true );
	expect( cancel.body.data.state.cancel_requested ).toBe( true );

	const customerContext = await browser.newContext( {
		baseURL: `${ process.env.DKBCE_BASE_URL.replace( /\/$/, '' ) }/`,
		ignoreHTTPSErrors: true,
		storageState: path.join( stateDirectory, 'customer.json' ),
	} );
	const customerPage = await customerContext.newPage();
	await customerPage.goto( pageURL );
	const hidden = await requestAjax( customerPage, 'progress', {
		operation_id: fixtures.operation_id,
	}, fixtures.customer_nonce );
	expect( hidden.status ).toBe( 403 );
	await customerContext.close();
} );

test( 'confirms apply, processes selected disposable product, and rejects replay', async ( { page } ) => {
	test.setTimeout( 90000 );
	const data = await preview( page, 'set', '15', {
		search: fixtureSku( 'SIMPLE' ),
	} );
	await page.locator( '#dkbce-selected-only' ).check();
	await page.locator( '#dkbce-apply' ).click();
	await expect( page.locator( '#dkbce-confirm' ) ).toBeVisible();
	await expect( page.locator( '#dkbce-confirm-text' ) ).toContainText( 'modify COGS for 1 product' );
	await page.locator( '#dkbce-confirm-cancel' ).click();
	await expect( page.locator( '#dkbce-confirm' ) ).toBeHidden();

	await page.locator( '#dkbce-apply' ).click();
	await page.locator( '#dkbce-confirm-apply' ).click();
	await expect( page.locator( '#dkbce-operation' ) ).toContainText( '1 of 1 products processed', {
		timeout: 80000,
	} );
	await expect( page.locator( '#dkbce-operation h3[role="status"]' ) ).toContainText( 'completed' );

	const replay = await requestAjax( page, 'apply', {
		preview_id: data.preview_id,
		selected_only: '1',
		selected_ids: String( fixtures.products.simple ),
	} );
	expect( [ 400, 409 ] ).toContain( replay.status );

	await page.reload();
	await loadPage( page );
	await preview( page, 'set', '15', { search: fixtureSku( 'SIMPLE' ) } );
	const row = rowForSku( page, fixtureSku( 'SIMPLE' ) );
	await expect( row.locator( '[data-column="5"]' ) ).toContainText( '15.00' );
} );
