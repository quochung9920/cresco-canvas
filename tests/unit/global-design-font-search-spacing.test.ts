/** Font search spacing regression contracts. */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-font-search-fix.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );

describe( 'Global Design font search spacing', () => {
	it( 'keeps the search icon in its own grid column instead of overlaying placeholder text', () => {
		expect( CSS ).toContain( 'grid-template-columns:16px minmax(0,1fr)' );
		expect( CSS ).toContain( '.cc-gd-font-search>.dashicons' );
		expect( CSS ).toContain( 'position:static' );
		expect( CSS ).toContain( 'gap:7px' );
		expect( CSS ).toContain( 'input[type="search"]' );
		expect( CSS ).toContain( 'padding:4px 0!important' );
		expect( CSS ).not.toContain( 'position:absolute' );
	} );

	it( 'loads after the compact Global Design stylesheet and remains editor scoped', () => {
		expect( PHP ).toContain( "const FONT_SEARCH_FIX_STYLE  = 'assets/css/studio-global-design-font-search-fix.css'" );
		expect( PHP ).toContain( 'array( self::COMPACT_HANDLE )' );
		expect( CSS ).not.toContain( '.cresco-session-root' );
		expect( CSS ).not.toContain( '.cresco-website-builder-root' );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|textarea|a|p|h[1-6])\b/ );
	} );
});
