const path = require( 'path' );
const wordpressConfig = require( '@wordpress/scripts/config/webpack.config' );

const cssPlugin = wordpressConfig.plugins.find(
	( plugin ) => 'MiniCssExtractPlugin' === plugin.constructor.name
);

if ( cssPlugin ) {
	cssPlugin.options.filename = 'css/[name].min.css';
}

module.exports = {
	...wordpressConfig,
	entry: {
		'bulk-cogs-editor': path.resolve( __dirname, 'src/index.js' ),
	},
	output: {
		...wordpressConfig.output,
		path: path.resolve( __dirname, 'assets' ),
		filename: 'js/[name].min.js',
		chunkFilename: 'js/[name].min.js',
		clean: false,
	},
};
