const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		editor: path.resolve( process.cwd(), 'src/editor/index.tsx' ),
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
	},
};
