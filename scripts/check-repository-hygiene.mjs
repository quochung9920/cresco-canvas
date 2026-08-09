import { access, readFile, readdir } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const errors = [];

const activeStandaloneAssets = [
	'assets/css/container-width.css',
	'assets/css/standalone-visual-editor.css',
	'assets/css/standalone-inspector-v2.css',
	'assets/css/standalone-ui-v3.css',
	'assets/css/standalone-page-settings.css',
	'assets/css/global-config-import.css',
	'assets/css/viewport-shell.css',
	'build/standalone-visual-editor.js',
	'build/standalone-visual-editor.asset.php',
	'build/standalone-inspector-v2.js',
	'build/standalone-ui-v3.js',
	'build/standalone-page-settings.js',
	'build/global-config-import.js',
	'build/viewport-shell.js',
];

const retiredEditorAssets = [
	'assets/css/editor-hub.css',
	'assets/css/elements-usage-sort.css',
	'assets/css/workspace-layout.css',
	'assets/css/widget-inspector.css',
	'build/editor-hub.js',
	'build/editor-hub.asset.php',
	'build/elements-usage-sort.js',
	'build/elements-usage-sort.asset.php',
	'build/workspace-layout.js',
	'build/workspace-layout.asset.php',
	'build/widget-inspector.js',
	'build/widget-inspector.asset.php',
	'build/widget-inspector-compat.js',
	'build/widget-inspector-compat.asset.php',
	'build/standalone-content-bootstrap.js',
	'build/standalone-content-bootstrap.asset.php',
];

async function exists( relativePath ) {
	try {
		await access( path.join( root, relativePath ) );
		return true;
	} catch {
		return false;
	}
}

for ( const file of activeStandaloneAssets ) {
	if ( !( await exists( file ) ) ) errors.push( `Missing active standalone asset: ${ file }` );
}

for ( const file of retiredEditorAssets ) {
	if ( await exists( file ) ) errors.push( `Retired editor asset is still checked in: ${ file }` );
}

const visualEditor = await readFile( 'includes/Admin/VisualEditor.php', 'utf8' );
const packaging = await readFile( 'scripts/build-release.mjs', 'utf8' );

for ( const file of activeStandaloneAssets ) {
	if ( file === 'assets/css/container-width.css' ) continue;
	if ( ! packaging.includes( `'${ file }'` ) ) {
		errors.push( `Release packaging does not explicitly require ${ file }` );
	}
}

for ( const file of activeStandaloneAssets.filter( ( file ) => file.startsWith( 'build/' ) || file.startsWith( 'assets/css/standalone' ) || file.includes( 'global-config-import' ) || file.includes( 'viewport-shell' ) ) ) {
	if ( file.endsWith( '.asset.php' ) ) continue;
	if ( ! visualEditor.includes( file ) ) {
		errors.push( `VisualEditor does not own active asset ${ file }` );
	}
}

for ( const file of retiredEditorAssets ) {
	if ( ! packaging.includes( `'${ file }'` ) ) {
		errors.push( `Release packaging is missing a retired-asset guard for ${ file }` );
	}
}

for ( const directory of [ 'build', 'assets/css' ] ) {
	const entries = await readdir( path.join( root, directory ) );
	for ( const entry of entries ) {
		if ( entry.endsWith( '.map' ) ) errors.push( `Source map must not be checked in: ${ directory }/${ entry }` );
	}
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( 'Repository hygiene verified: active standalone assets are owned and retired editor artifacts are absent.\n' );
