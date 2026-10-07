const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

module.exports = async function globalTeardown() {
	const stateDirectory = path.join( os.tmpdir(), 'dkbce-playwright-e2e' );
	const stateFile = path.join( stateDirectory, 'fixtures.json' );
	if ( ! fs.existsSync( stateFile ) ) {
		return;
	}

	try {
		execFileSync(
			'wp',
			[
				'eval-file',
				path.join( __dirname, 'fixtures.php' ),
				`--path=${ process.env.DKBCE_WP_PATH || '/var/www/html/wcdev' }`,
			],
			{
				env: {
					...process.env,
					DKBCE_E2E_MODE: 'cleanup',
					DKBCE_E2E_STATE_FILE: stateFile,
				},
				stdio: 'inherit',
			}
		);
	} catch ( error ) {
		console.error( 'Playwright fixture cleanup failed. State file retained for recovery.' );
		throw error;
	}

	for ( const filename of [ 'manager.json', 'customer.json', 'fixtures.json' ] ) {
		const filePath = path.join( stateDirectory, filename );
		if ( fs.existsSync( filePath ) ) {
			fs.unlinkSync( filePath );
		}
	}
};
