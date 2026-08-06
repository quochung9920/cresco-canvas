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
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'build' ),
		filename: '[name].js',
		// The editor application consists of reviewed, checked-in runtimes with
		// separate asset manifests. Do not delete or overwrite them when the
		// Container and Preview bundles are rebuilt.
		clean: false,
	},
};
