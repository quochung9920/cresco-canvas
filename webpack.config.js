const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Webpack owns only the entries listed here.
 *
 * `container`, `preview`, `widget-control-enhancements`, and
 * `standalone-ai-bridge` used to be entries too, but their shipped artifacts are
 * hand-authored, framework-free runtimes stored under `runtime-src/build/` and
 * restored by `scripts/build-runtime.mjs` after webpack finishes. Building them
 * here produced bundles that were overwritten moments later — pure waste, and
 * misleading besides, because it implied `src/` was the source of what ships.
 * Their TypeScript sources remain under `src/` as the migration target tracked
 * by the runtime consolidation work; they are simply not built today.
 */
module.exports = {
	...defaultConfig,
	entry: {
		'studio-responsive-inheritance': path.resolve(
			process.cwd(),
			'src/studio/index.tsx'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'build' ),
		filename: '[name].js',
		// The release build may delete the whole output directory;
		// scripts/build-runtime.mjs restores reviewed runtime sources afterwards.
		clean: false,
	},
};
