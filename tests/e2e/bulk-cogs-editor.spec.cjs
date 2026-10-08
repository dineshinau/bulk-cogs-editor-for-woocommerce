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
		page.getByRole( 'heading', { name: 'Bulk COGS Editor' } )
	).toBeVisible();
	await expect( page.locator( '#dkbce-get-products' ) ).toBeEnabled();
}

async function setFilters( page, values = {} ) {
	await page.locator( '#dkbce-search' ).fill( values.search || '' );
	if ( values.category ) {
		await page.locator( '#dkbce-category' ).selectOption( String( values.category ) );
	}
	if ( values.type ) {
		await page.locator( '#dkbce-type' ).selectOption( values.type );
	}
	if ( values.stock ) {
		await page.locator( '#dkbce-stock' ).selectOption( values.stock );
	}
	if ( values.brand && page.locator( '#dkbce-brand' ) ) {
		await page.locator( '#dkbce-brand' ).selectOption( String( values.brand ) );
	}
	for ( const [ selector, value ] of [
		[ '#dkbce-price-min', values.priceMin ],
		[ '#dkbce-price-max', values.priceMax ],
		[ '#dkbce-cogs-min', values.cogsMin ],
		[ '#dkbce-cogs-max', values.cogsMax ],
	] ) {
		if ( undefined !== value ) {
			await page.locator( selector ).fill( String( value ) );
		}
	}
}

async function getProducts( page, values = {} ) {
	await setFilters( page, values );
	await page.locator( '#dkbce-get-products' ).click();
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
	await page.locator( `input[name="dkbce-action"][value="${ action }"]` ).check();
	if ( 'clear' !== action ) {
		await page.locator( '#dkbce-value' ).fill( String( value ) );
	}
	const responsePromise = page.waitForResponse( ( response ) =>
		response.url().includes( 'admin-ajax.php' ) &&
		response.request().postData()?.includes( 'dkbce_preview' )
	);
	await page.locator( '#dkbce-preview' ).click();
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
	await expect( page.getByLabel( 'Product category' ) ).toBeVisible();
	await expect( page.getByLabel( 'Product type' ) ).toBeVisible();
	await expect( page.getByLabel( 'Stock status' ) ).toBeVisible();
	await expect( page.locator( 'input[name="dkbce-action"]' ) ).toHaveCount( 6 );
	await expect( page.locator( '#dkbce-preview' ) ).toBeDisabled();
	await expect( page.locator( '#dkbce-apply' ) ).toBeDisabled();
	if ( fixtures.brand_id ) {
		await expect( page.locator( '#dkbce-brand' ) ).toBeVisible();
	} else {
		await expect( page.locator( '#dkbce-brand' ) ).toHaveCount( 0 );
	}
} );

test( 'search matches product title and SKU and reset clears filters', async ( { page } ) => {
	await getProducts( page, { search: `Simple ${ fixtures.token }` } );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matching products' );
	await getProducts( page, { search: fixtureSku( 'SIMPLE' ) } );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matching products' );
	await page.locator( '#dkbce-reset' ).click();
	await expect( page.locator( '#dkbce-search' ) ).toHaveValue( '' );
	await expect( page.locator( '#dkbce-category' ) ).toHaveValue( '0' );
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
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matching products' );
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
		await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matching products' );
	}
} );

test( 'filters all stock states', async ( { page } ) => {
	for ( const [ stock, key ] of [
		[ 'instock', 'SIMPLE' ],
		[ 'outofstock', 'OUT' ],
		[ 'onbackorder', 'BACKORDER' ],
	] ) {
		await getProducts( page, { stock, search: fixtureSku( key ) } );
		await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matching products' );
	}
} );

test( 'filters by supported brand taxonomy when available', async ( { page } ) => {
	test.skip( ! fixtures.brand_id, 'No supported brand taxonomy is registered.' );
	await getProducts( page, {
		search: fixtureSku( 'SIMPLE' ),
		brand: fixtures.brand_id,
	} );
	await expect( page.locator( '#dkbce-product-count' ) ).toContainText( '1 matching products' );
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
		await expect( row.locator( 'td' ).nth( 5 ) ).toContainText( '10.00' );
		await expect( row.locator( 'td' ).nth( 6 ) ).toContainText( expected );
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
	await expect( row.locator( 'td:nth-child(2) a' ) ).toHaveAttribute(
		'href',
		new RegExp( `post\.php\\?post=${ fixtures.products.simple }&action=edit` )
	);
	await expect( row.locator( 'td:nth-child(3) img' ) ).toBeVisible();
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
	await expect( emptyRow.locator( 'td' ).nth( 5 ) ).toHaveText( 'Empty' );
	await expect( emptyRow.locator( 'td' ).nth( 6 ) ).toHaveText( '—' );
	await expect( emptyRow.locator( 'td' ).nth( 7 ) ).toContainText( 'Current COGS is not set' );

	await preview( page, 'increase_percent', '10', { search: fixtureSku( 'ZERO' ) } );
	const zeroRow = rowForSku( page, fixtureSku( 'ZERO' ) );
	await expect( zeroRow.locator( 'td' ).nth( 5 ) ).toHaveText( 'Empty' );
	await expect( zeroRow.locator( 'td' ).nth( 6 ) ).toHaveText( '—' );
	await expect( zeroRow.locator( 'td' ).nth( 7 ) ).toContainText( 'Current COGS is not set' );
} );

test( 'rounds calculated COGS to store precision', async ( { page } ) => {
	await preview( page, 'increase_percent', '4', { search: fixtureSku( 'DECIMAL' ) } );
	const row = rowForSku( page, fixtureSku( 'DECIMAL' ) );
	await expect( row.locator( 'td' ).nth( 5 ) ).toContainText( '9.99' );
	await expect( row.locator( 'td' ).nth( 6 ) ).toContainText( '10.39' );
} );

test( 'limits preview to 50 rows and reports total matching products', async ( { page } ) => {
	const data = await preview( page, 'set', '2', {
		search: `DKBCE E2E Batch ${ fixtures.token }`,
	} );
	expect( data.count ).toBe( 55 );
	expect( data.rows ).toHaveLength( 50 );
	await expect( page.locator( '#dkbce-preview-content' ) ).toContainText(
		'Showing first 50 of 55 matching products.'
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
	await expect( page.locator( '#dkbce-confirm-text' ) ).toContainText( 'modify COGS for 1 products' );
	await page.locator( '#dkbce-confirm-cancel' ).click();
	await expect( page.locator( '#dkbce-confirm' ) ).toBeHidden();

	await page.locator( '#dkbce-apply' ).click();
	await page.locator( '#dkbce-confirm-apply' ).click();
	await expect( page.locator( '#dkbce-operation' ) ).toContainText( 'Success: 1', {
		timeout: 45000,
	} );
	await expect( page.locator( '#dkbce-operation h3' ) ).toContainText( 'completed' );

	const replay = await requestAjax( page, 'apply', {
		preview_id: data.preview_id,
		selected_only: '1',
		selected_ids: String( fixtures.products.simple ),
	} );
	expect( [ 400, 409 ] ).toContain( replay.status );

	await getProducts( page, { search: fixtureSku( 'SIMPLE' ) } );
	await page.locator( 'input[name="dkbce-action"][value="set"]' ).check();
	await page.locator( '#dkbce-value' ).fill( '15' );
	await page.locator( '#dkbce-preview' ).click();
	const row = rowForSku( page, fixtureSku( 'SIMPLE' ) );
	await expect( row.locator( 'td' ).nth( 5 ) ).toContainText( '15.00' );
} );
