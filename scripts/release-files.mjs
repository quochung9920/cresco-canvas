import { lstat, readdir } from 'node:fs/promises';
import path from 'node:path';

export const topLevelFiles = [
	'cresco-canvas.php',
	'uninstall.php',
	'README.md',
	'CHANGELOG.md',
	'LICENSE',
];

export const releaseDocs = [
	'docs/ACCESSIBILITY_AUDIT.md',
	'docs/COMMERCIAL_HARDENING.md',
	'docs/COMPATIBILITY_MATRIX.md',
	'docs/CRESCO_AI_CONTEXT_V1.md',
	'docs/CRESCO_PATCH_V1.md',
	'docs/CRESCO_SESSION_V1.md',
	'docs/GLOBAL_CONFIG_IMPORT.md',
	'docs/KNOWN_LIMITATIONS.md',
	'docs/PERFORMANCE_BASELINE.md',
	'docs/PRIVACY.md',
	'docs/PRODUCTION_HARDENING_VERIFICATION.md',
	'docs/RELEASE_CHECKLIST.md',
	'docs/RELEASE_ENGINEERING.md',
	'docs/SECURITY.md',
	'docs/UPGRADE_ROLLBACK.md',
	'docs/releases/1.0.0-rc.1.md',
];

export const blockFiles = [
	'blocks/container/block.json',
	'blocks/container/editor.css',
	'blocks/container/style.css',
];

export const assetFiles = [
	'assets/css/container-width.css',
	'assets/css/design-system.css',
	'assets/css/dynamic-advanced.css',
	'assets/css/dynamic-alpha4.css',
	'assets/css/dynamic-alpha5.css',
	'assets/css/dynamic-completion.css',
	'assets/css/dynamic.css',
	'assets/css/editor-app-shell-elements.css',
	'assets/css/editor-app-shell.css',
	'assets/css/forms-completion.css',
	'assets/css/forms.css',
	'assets/css/frontend.css',
	'assets/css/global-config-import.css',
	'assets/css/interactions-editor.css',
	'assets/css/interactions.css',
	'assets/css/native-preview-suppression.css',
	'assets/css/preview.css',
	'assets/css/standalone-ai-bridge.css',
	'assets/css/standalone-history.css',
	'assets/css/standalone-inspector-v2.css',
	'assets/css/standalone-page-settings.css',
	'assets/css/standalone-ui-v3.css',
	'assets/css/standalone-visual-editor.css',
	'assets/css/structure-navigator-actions.css',
	'assets/css/structure-navigator.css',
	'assets/css/style-engine.css',
	'assets/css/templates.css',
	'assets/css/theme-builder.css',
	'assets/css/viewport-shell.css',
	'assets/css/visual-canvas.css',
	'assets/css/widget-control-enhancements.css',
	'assets/css/widget-inspector-persistent.css',
];

export const buildFiles = [
	'build/container.asset.php',
	'build/container.js',
	'build/design-system.asset.php',
	'build/design-system.js',
	'build/dynamic-advanced.asset.php',
	'build/dynamic-advanced.js',
	'build/dynamic-alpha4.asset.php',
	'build/dynamic-alpha4.js',
	'build/dynamic-alpha5-frontend.asset.php',
	'build/dynamic-alpha5-frontend.js',
	'build/dynamic-alpha5.asset.php',
	'build/dynamic-alpha5.js',
	'build/dynamic-completion-frontend.asset.php',
	'build/dynamic-completion-frontend.js',
	'build/dynamic-completion.asset.php',
	'build/dynamic-completion.js',
	'build/dynamic.asset.php',
	'build/dynamic.js',
	'build/editor-app-shell.asset.php',
	'build/editor-app-shell.js',
	'build/editor-foundation.asset.php',
	'build/editor-foundation.js',
	'build/editor-rtl.css',
	'build/editor.asset.php',
	'build/editor.css',
	'build/editor.js',
	'build/forms-completion-editor.asset.php',
	'build/forms-completion-editor.js',
	'build/forms-completion.asset.php',
	'build/forms-completion.js',
	'build/forms-frontend.asset.php',
	'build/forms-frontend.js',
	'build/global-config-import.js',
	'build/interactions-editor.asset.php',
	'build/interactions-editor.js',
	'build/interactions-frontend.asset.php',
	'build/interactions-frontend.js',
	'build/native-preview-suppression.asset.php',
	'build/native-preview-suppression.js',
	'build/preview-foundation-bridge.asset.php',
	'build/preview-foundation-bridge.js',
	'build/preview.asset.php',
	'build/preview.js',
	'build/standalone-ai-bridge.js',
	'build/standalone-history.js',
	'build/standalone-inspector-v2.js',
	'build/standalone-page-settings.js',
	'build/standalone-ui-v3.js',
	'build/standalone-visual-editor.asset.php',
	'build/standalone-visual-editor.js',
	'build/structure-navigator.asset.php',
	'build/structure-navigator.js',
	'build/style-engine-editor.asset.php',
	'build/style-engine-editor.js',
	'build/templates.asset.php',
	'build/templates.js',
	'build/theme-builder.asset.php',
	'build/theme-builder.js',
	'build/viewport-shell.js',
	'build/visual-canvas.asset.php',
	'build/visual-canvas.js',
	'build/widget-control-enhancements.js',
	'build/widget-inspector-persistent.asset.php',
	'build/widget-inspector-persistent.js',
];

export const forbiddenPackageFragments = [
	'/.git/',
	'/.github/',
	'/node_modules/',
	'/runtime-src/',
	'/scripts/',
	'/src/',
	'/tests/',
	'/dist/',
	'/playwright-report/',
	'/test-results/',
	'/.env',
	'/.wp-env',
	'/phpunit',
	'/phpcs',
	'.map',
];

async function walkFiles( root, relativePath, predicate ) {
	const absolute = path.join( root, relativePath );
	const entries = await readdir( absolute, { withFileTypes: true } );
	const files = [];
	for ( const entry of entries.sort( ( left, right ) => left.name.localeCompare( right.name ) ) ) {
		const child = path.join( relativePath, entry.name );
		if ( entry.isDirectory() ) {
			files.push( ...( await walkFiles( root, child, predicate ) ) );
		} else if ( entry.isFile() ) {
			if ( predicate( child ) ) files.push( child );
		} else {
			throw new Error( `Release input must be a regular file: ${ child }` );
		}
	}
	return files;
}

export async function collectReleaseFiles( root = process.cwd() ) {
	const includes = await walkFiles( root, 'includes', ( file ) => file.endsWith( '.php' ) );
	const vendor = await walkFiles( root, 'vendor', () => true );
	const files = [
		...topLevelFiles,
		...releaseDocs,
		...blockFiles,
		...assetFiles,
		...buildFiles,
		...includes,
		...vendor,
	];
	const unique = [ ...new Set( files.map( ( file ) => file.replaceAll( path.sep, '/' ) ) ) ].sort();
	for ( const file of unique ) {
		const stat = await lstat( path.join( root, file ) );
		if ( ! stat.isFile() ) throw new Error( `Release allowlist entry is not a regular file: ${ file }` );
	}
	return unique;
}
