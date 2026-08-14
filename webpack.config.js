const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		container: path.resolve(
			process.cwd(),
			'src/blocks/container/index.tsx'
		),
		preview: path.resolve( process.cwd(), 'src/preview/index.tsx' ),
		'widget-control-enhancements': path.resolve(
			process.cwd(),
			'src/standalone/widget-control-enhancements.js'
		),
		'standalone-ai-bridge': path.resolve(
			process.cwd(),
			'src/standalone/standalone-ai-bridge.js'
		),
		'studio-responsive-inheritance': path.resolve(
			process.cwd(),
			'src/studio/index.tsx'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'build' ),
		filename: '[name].js',
		// Webpack owns only the entries above. The release build may delete the
		// whole output directory; scripts/build-runtime.mjs restores reviewed
		// runtime sources after webpack finishes.
		clean: false,
	},
};
