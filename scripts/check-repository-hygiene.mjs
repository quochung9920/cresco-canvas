import { access, readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { assetFiles, blockFiles, buildFiles } from './release-files.mjs';

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
	'build/native-gutenberg-bridge.js',
	'build/native-gutenberg-bridge.asset.php',
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

const packaging = await readFile( 'scripts/build-release.mjs', 'utf8' );

// Every shipped runtime path named by PHP, mapped back to the file that names
// it. `VisualEditor` used to enqueue the standalone runtime and this gate
// asserted that class by name; the runtime moved to WebsiteBuilderRuntimeOwner
// and the hard-coded owner turned into a permanent false failure. Ownership is
// what matters, not which class holds it, so resolve the owner from source.
async function collectPhpFiles( directory, files = [] ) {
	for ( const entry of await readdir( path.join( root, directory ), { withFileTypes: true } ) ) {
		const relative = `${ directory }/${ entry.name }`;
		if ( entry.isDirectory() ) await collectPhpFiles( relative, files );
		else if ( entry.name.endsWith( '.php' ) ) files.push( relative );
	}
	return files;
}

const referencedAssets = new Map();
for ( const file of await collectPhpFiles( 'includes' ) ) {
	const source = await readFile( path.join( root, file ), 'utf8' );
	for ( const match of source.matchAll( /'((?:build|assets)\/[A-Za-z0-9._/-]+\.(?:js|css|php))'/g ) ) {
		if ( ! referencedAssets.has( match[ 1 ] ) ) referencedAssets.set( match[ 1 ], [] );
		referencedAssets.get( match[ 1 ] ).push( file );
	}
}

for ( const file of activeStandaloneAssets ) {
	if ( file === 'assets/css/container-width.css' ) continue;
	if ( ! packaging.includes( `'${ file }'` ) ) {
		errors.push( `Release packaging does not explicitly require ${ file }` );
	}
}

// This gate used to require `VisualEditor` to enqueue each standalone asset by
// path. That class no longer mounts a runtime, and no class replaced it:
// WebsiteBuilder, WebsiteBuilderCompatibility and WebsiteBuilderRuntimeOwner
// all *dequeue and deregister* these handles so Studio is the only editor
// presentation. The files are therefore retired artifacts that are still
// checked in and still packaged. Removing them is a separate, verified change
// -- they stay listed here so packaging stays deliberate rather than silent,
// but no ownership is asserted, because asserting it is what kept this gate
// permanently red.

// A strict allowlist only protects the release if everything the plugin
// actually enqueues is on it. Studio's Global Design Pro, UX Pro, Color
// Harmony, Dimension Controls and Widget State Tabs runtimes were all enqueued
// unconditionally while absent from the ZIP, so they worked in a source
// checkout and silently vanished in a packaged install.
const packagedAssets = new Set( [ ...assetFiles, ...blockFiles, ...buildFiles ] );
for ( const [ file, owners ] of referencedAssets ) {
	if ( packagedAssets.has( file ) ) continue;
	if ( ! ( await exists( file ) ) ) continue; // Guarded reference to a retired runtime.
	errors.push( `Enqueued asset is missing from the release allowlist: ${ file } (enqueued by ${ [ ...new Set( owners ) ].join( ', ' ) })` );
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

process.stdout.write( `Repository hygiene verified: every enqueued asset (${ referencedAssets.size } referenced from includes/) is on the release allowlist, retired editor artifacts are absent, and no source maps are checked in.\n` );
