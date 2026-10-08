const { chromium } = require( '@playwright/test' );
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const crypto = require( 'node:crypto' );

const stateDirectory = path.join( os.tmpdir(), 'dkbce-playwright-e2e' );
	const stateFile = path.join( stateDirectory, 'fixtures.json' );
	const fixtureScript = path.join( __dirname, 'fixtures.php' );
	const adminCredentialsFile = path.join( stateDirectory, 'admin-credentials.json' );

module.exports = async function globalSetup() {
	const baseURL = process.env.DKBCE_BASE_URL
		? `${ process.env.DKBCE_BASE_URL.replace( /\/$/, '' ) }/`
		: '';
	const wordpressPath = process.env.DKBCE_WP_PATH || '/var/www/html/wcdev';
	if ( ! baseURL ) {
		throw new Error(
			'Set DKBCE_BASE_URL to the local WordPress URL before running Playwright tests.'
		);
	}

	fs.mkdirSync( stateDirectory, { recursive: true, mode: 0o700 } );
	fs.chmodSync( stateDirectory, 0o700 );
	const token = crypto.randomBytes( 8 ).toString( 'hex' );
	const environment = {
		...process.env,
		DKBCE_E2E_MODE: 'create',
		DKBCE_E2E_TOKEN: token,
		DKBCE_E2E_STATE_FILE: stateFile,
	};

	try {
		const fixtureOutput = execFileSync(
			'wp',
			[ 'eval-file', fixtureScript, `--path=${ wordpressPath }` ],
			{ env: environment, encoding: 'utf8' }
		);
		const fixtureLine = fixtureOutput
			.trim()
			.split( '\n' )
			.findLast( ( line ) => line.startsWith( '{' ) );
		const fixtures = JSON.parse( fixtureLine );
		fs.writeFileSync( stateFile, JSON.stringify( fixtures ), { mode: 0o600 } );
		fs.chmodSync( stateFile, 0o600 );
		const adminCredentials = JSON.parse(
			fs.readFileSync( adminCredentialsFile, 'utf8' )
		);
		const browser = await chromium.launch( {
			channel: 'chrome',
			headless: ! process.argv.includes( '--headed' ),
		} );
		try {
			for ( const user of [ 'admin', 'customer' ] ) {
				const context = await browser.newContext( {
					baseURL,
					ignoreHTTPSErrors: true,
				} );
				const page = await context.newPage();
				await page.goto( new URL( 'wp-login.php', baseURL ).href, {
					waitUntil: 'domcontentloaded',
					timeout: 60000,
				} );
				await page.locator( '#user_login' ).waitFor( { state: 'visible' } );
				await page
					.locator( '#user_login' )
					.fill( 'admin' === user ? adminCredentials.username : fixtures.customer_login );
				await page
					.locator( '#user_pass' )
					.fill( 'admin' === user ? adminCredentials.password : fixtures.customer_password );
				await page.locator( '#wp-submit' ).click();
				await page.waitForURL(
					'admin' === user
						? /wp-admin\//
						: ( url ) => ! url.pathname.endsWith( 'wp-login.php' ),
					{ timeout: 45000 }
				);
				if ( 'customer' === user ) {
					const loggedInCookie = ( await context.cookies( baseURL ) ).find(
						( cookie ) => cookie.name.startsWith( 'wordpress_logged_in_' )
					);
					if ( ! loggedInCookie ) {
						throw new Error( 'Could not read temporary customer login session.' );
					}
					const nonce = execFileSync(
						'wp',
						[ 'eval-file', fixtureScript, `--path=${ wordpressPath }` ],
						{
							encoding: 'utf8',
							env: {
								...environment,
								DKBCE_E2E_MODE: 'nonce',
								DKBCE_E2E_CUSTOMER_COOKIE: loggedInCookie.value,
							},
						}
					)
						.trim()
						.split( '\n' )
						.pop();
					fixtures.customer_nonce = nonce;
					fs.writeFileSync( stateFile, JSON.stringify( fixtures ), { mode: 0o600 } );
				}
				await context.storageState( {
					path: path.join(
						stateDirectory,
						'admin' === user ? 'manager.json' : 'customer.json'
					),
				} );
				fs.chmodSync(
					path.join(
						stateDirectory,
						'admin' === user ? 'manager.json' : 'customer.json'
					),
					0o600
				);
				await context.close();
			}
		} finally {
			await browser.close();
		}
	} catch ( error ) {
		try {
			execFileSync(
				'wp',
				[ 'eval-file', fixtureScript, `--path=${ wordpressPath }` ],
				{
					env: {
						...environment,
						DKBCE_E2E_MODE: 'cleanup',
					},
				}
			);
		} catch ( cleanupError ) {
			console.error( 'Fixture cleanup failed after setup error.' );
		}
		throw error;
	}
};
