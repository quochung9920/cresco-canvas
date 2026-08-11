import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const exists = ( relative ) => fs.existsSync( path.join( root, relative ) );
const read = ( relative ) => fs.readFileSync( path.join( root, relative ), 'utf8' );
const hash = ( value ) => crypto.createHash( 'sha256' ).update( value ).digest( 'hex' );
const expect = ( relative, token ) => {
	if ( ! exists( relative ) ) return errors.push( `Missing ${ relative }` );
	if ( ! read( relative ).includes( token ) ) errors.push( `${ relative } missing ${ token }` );
};

const source = 'runtime-src/build/website-builder-ui-correction.js';
const built = 'build/website-builder-ui-correction.js';
const css = 'assets/css/website-builder-ui-correction.css';
for ( const file of [ source, built, css ] ) if ( ! exists( file ) ) errors.push( `Missing Studio UI correction asset: ${ file }` );
if ( exists( source ) && exists( built ) && hash( read( source ) ) !== hash( read( built ) ) ) errors.push( 'Studio UI correction source/build parity failed.' );

for ( const token of [
	"var order=['wide','desktop','laptop','tablet','mobile']",
	"id==='wide'",
	"id==='desktop'",
	"id==='laptop'",
	"id==='tablet'",
	"data-cresco-device-icon",
	"meta[active].label+' · '+String(widths[active]",
	"structure:'tree-grid-v2'",
	"responsiveIcons:'cresco-device-v1'",
] ) expect( source, token );

for ( const token of [
	'grid-template-columns: 24px 18px minmax(0, 1fr) 30px',
	'.cc-studio-tree-actions > button:last-child',
	'.cc-studio-tree ul',
	'.cc-cresco-device-icon',
	'[data-cresco-breakpoint-label="1"]',
	'box-shadow: inset 3px 0 0 var(--cc-accent)',
] ) expect( css, token );

for ( const token of [
	"const UI_SCRIPT         = 'build/website-builder-ui-correction.js'",
	"const UI_STYLE          = 'assets/css/website-builder-ui-correction.css'",
	"'cresco-canvas-website-builder-ui-correction'",
] ) expect( 'includes/Builder/WebsiteBuilderStudio.php', token );

expect( 'includes/Builder/WebsiteBuilderModuleRegistry.php', "'file' => 'build/website-builder-ui-correction.js'" );
expect( 'includes/Builder/WebsiteBuilderModuleRegistry.php', "'file' => 'assets/css/website-builder-ui-correction.css'" );
expect( 'runtime-src/manifest.json', 'website-builder-ui-correction.js' );
expect( 'scripts/release-files.mjs', 'assets/css/website-builder-ui-correction.css' );
expect( 'scripts/release-files.mjs', 'build/website-builder-ui-correction.js' );

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}
process.stdout.write( '[studio-ui] Structure tree and responsive device correction contracts verified.\n' );
