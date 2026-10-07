const path = require( 'node:path' );
const os = require( 'node:os' );
const { defineConfig } = require( '@playwright/test' );

const stateDirectory = path.join( os.tmpdir(), 'dkbce-playwright-e2e' );
const baseURL = process.env.DKBCE_BASE_URL
	? `${ process.env.DKBCE_BASE_URL.replace( /\/$/, '' ) }/`
	: undefined;

module.exports = defineConfig( {
	testDir: __dirname,
	testMatch: '*.spec.cjs',
	globalSetup: path.join( __dirname, 'global-setup.cjs' ),
	globalTeardown: path.join( __dirname, 'global-teardown.cjs' ),
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: 'list',
	use: {
		baseURL,
		browserName: 'chromium',
		channel: 'chrome',
		ignoreHTTPSErrors: true,
		storageState: path.join( stateDirectory, 'manager.json' ),
		trace: 'off',
	},
} );
