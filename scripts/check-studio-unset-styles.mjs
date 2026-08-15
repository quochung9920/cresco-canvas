import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const exists = ( relative ) => fs.existsSync( path.join( root, relative ) );
const read = ( relative ) => fs.readFileSync( path.join( root, relative ), 'utf8' );
const hash = ( value ) => crypto.createHash( 'sha256' ).update( value ).digest( 'hex' );
const expect = ( relative, token ) => {
	if ( ! exists( relative ) ) {
		errors.push( `Missing ${ relative }` );
		return;
	}
	if ( ! read( relative ).includes( token ) ) errors.push( `${ relative } missing ${ token }` );
};
const reject = ( relative, token ) => {
	if ( exists( relative ) && read( relative ).includes( token ) ) errors.push( `${ relative } must not contain ${ token }` );
};

const source = 'runtime-src/build/website-builder-unset-styles.js';
const built = 'build/website-builder-unset-styles.js';

if ( ! exists( source ) ) errors.push( `Missing ${ source }` );
if ( ! exists( built ) ) errors.push( `Missing ${ built }` );
if ( exists( source ) && exists( built ) && hash( read( source ) ) !== hash( read( built ) ) ) {
	errors.push( 'Unset style runtime source/build parity failed.' );
}

for ( const token of [
	"mode:'unset-is-no-override'",
	"resetMode:'css-initial'",
	"function ownBag(node,device,state)",
	"function fallbackValue(node,device,state,key)",
	"function ownValue(nodes,device,state,key)",
	"function dispatchValue(control,value)",
	"function resetToCssInitial(event)",
	"dispatchValue(control,'initial')",
	"'CSS default'",
	"'Default / cascade'",
	"'Inherited: '",
	"'Empty = no CSS override.'",
	"'Reset ↶ = CSS default (initial).'",
	"data-cresco-style-source",
	"cresco:studio-session-change",
] ) expect( source, token );

// Empty means no override. The canonical mutation deletes an empty value rather
// than serializing a fake CSS declaration.
for ( const token of [
	"if(value==='')delete b[key];else b[key]=value",
	"if(value==='')delete base[key];else base[key]=value",
	"if(value==='')delete rb[key];else rb[key]=value",
] ) expect( 'runtime-src/build/website-builder-studio.js', token );

// Reset is intentionally different from empty: CSS `initial` is persisted and
// compiled so it blocks wider breakpoint/state overrides without hard-coded
// values such as flex, 176px, or 44px.
expect( 'includes/Builder/WebsiteBuilder.php', "return preg_match( \"/^[#a-zA-Z0-9.,:%+\\-*\\/() _\\\"']+$/\", $value ) ? $value : '';" );
expect( 'includes/Builder/WebsiteBuilderCssCompiler.php', "if ( '' === $value ) continue;" );
expect( 'docs/STYLE_UNSET_SEMANTICS.md', 'Reset writes the CSS-wide keyword `initial`' );

for ( const token of [
	"const UNSET_STYLE_SCRIPT = 'build/website-builder-unset-styles.js'",
	"'cresco-canvas-website-builder-unset-styles'",
] ) expect( 'includes/Builder/WebsiteBuilderStudio.php', token );
expect( 'includes/Builder/WebsiteBuilderModuleRegistry.php', "'file' => 'build/website-builder-unset-styles.js'" );
expect( 'runtime-src/manifest.json', 'website-builder-unset-styles.js' );
expect( 'scripts/release-files.mjs', 'build/website-builder-unset-styles.js' );

// One editor shell only: the WordPress screen renders the Studio loader and no
// longer registers the historical standalone React application. RuntimeOwner
// deregisters retired handles again at late ownership gates so dependencies or
// compatibility callbacks cannot resurrect the old UI before Studio mounts.
for ( const token of [
	'class="cc-studio-loading"',
	'Loading Cresco Studio…',
	'canonical browser application is owned by WebsiteBuilderRuntimeOwner',
] ) expect( 'includes/Admin/VisualEditor.php', token );
for ( const token of [
	"add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) )",
	"wp_enqueue_script( 'cresco-canvas-standalone-visual-editor'",
	"class=\"cc-standalone-loading\"",
] ) reject( 'includes/Admin/VisualEditor.php', token );
for ( const token of [
	'wp_deregister_script( $handle )',
	'wp_deregister_style( $handle )',
	"#cresco-canvas-standalone-editor>.cc-studio-loading>.spinner{display:none}",
	"'legacyHandlesRegistered' => false",
	'Last gate before WordPress prints footer assets',
] ) expect( 'includes/Builder/WebsiteBuilderRuntimeOwner.php', token );

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( '[studio-unset-styles] Empty/inherit, CSS-initial reset, single-loader, single-Studio-runtime, persistence, and packaging contracts verified.\n' );
