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

const source = 'runtime-src/build/website-builder-unset-styles.js';
const built = 'build/website-builder-unset-styles.js';

if ( ! exists( source ) ) errors.push( `Missing ${ source }` );
if ( ! exists( built ) ) errors.push( `Missing ${ built }` );
if ( exists( source ) && exists( built ) && hash( read( source ) ) !== hash( read( built ) ) ) {
	errors.push( 'Unset style runtime source/build parity failed.' );
}

for ( const token of [
	"mode:'unset-is-no-override'",
	"function ownBag(node,device,state)",
	"function fallbackValue(node,device,state,key)",
	"function ownValue(nodes,device,state,key)",
	"nativeSet(control,'')",
	"'Default / cascade'",
	"'Inherited: '",
	"'Empty = no CSS override.'",
	"data-cresco-style-source",
	"cresco:studio-session-change",
] ) expect( source, token );

// The canonical Studio mutation path must delete an override instead of writing
// an empty CSS value. The presentation module depends on this persistence rule.
for ( const token of [
	"if(value==='')delete b[key];else b[key]=value",
	"if(value==='')delete base[key];else base[key]=value",
	"if(value==='')delete rb[key];else rb[key]=value",
] ) expect( 'runtime-src/build/website-builder-studio.js', token );

// Empty values are not allowed to become frontend declarations.
expect( 'includes/Builder/WebsiteBuilderCssCompiler.php', "if ( '' === $value ) continue;" );

for ( const token of [
	"const UNSET_STYLE_SCRIPT = 'build/website-builder-unset-styles.js'",
	"'cresco-canvas-website-builder-unset-styles'",
] ) expect( 'includes/Builder/WebsiteBuilderStudio.php', token );

expect( 'includes/Builder/WebsiteBuilderModuleRegistry.php', "'file' => 'build/website-builder-unset-styles.js'" );
expect( 'runtime-src/manifest.json', 'website-builder-unset-styles.js' );
expect( 'scripts/release-files.mjs', 'build/website-builder-unset-styles.js' );

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( '[studio-unset-styles] Unset, inheritance, persistence, and packaging contracts verified.\n' );
