/** Searchable Global Design font-library contracts. */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const PHP = readFileSync( join( ROOT, 'includes/Styles/FontLibrary.php' ), 'utf8' );
const STUDIO = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );
const ENTRY = readFileSync( join( ROOT, 'cresco-canvas.php' ), 'utf8' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-fonts.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-fonts.css' ), 'utf8' );

describe( 'Global Design font library', () => {
	it( 'uses the complete Google metadata catalog with a persistent cache and a large offline fallback', () => {
		expect( PHP ).toContain( "https://fonts.google.com/metadata/fonts" );
		expect( PHP ).toContain( "familyMetadataList" );
		expect( PHP ).toContain( 'wp_safe_remote_get' );
		expect( PHP ).toContain( 'set_transient' );
		expect( PHP ).toContain( 'update_option( self::CACHE_OPTION' );
		expect( PHP ).not.toContain( 'AIza' );
		const from = PHP.indexOf( '$groups = array(' );
		const to = PHP.indexOf( '$out = array();', from );
		const fallback = PHP.slice( from, to );
		const names = fallback.match( /'[^']+'/g ) || [];
		expect( names.length ).toBeGreaterThan( 100 );
		[ 'Inter', 'Roboto', 'Open Sans', 'Poppins', 'Manrope', 'DM Sans', 'Playfair Display', 'Source Sans 3', 'Space Grotesk' ].forEach( ( family ) => expect( fallback ).toContain( `'${ family }'` ) );
	} );

	it( 'keeps font discovery metadata outside Global Design persistence', () => {
		expect( PHP ).toContain( "const CACHE_OPTION   = 'cresco_google_font_catalog_cache_v2'" );
		expect( PHP ).toContain( "GlobalStyles::get_settings()" );
		expect( PHP ).not.toContain( "update_option( 'cresco_canvas_settings'" );
		expect( JS ).toContain( "input[data-bind=\"fontFamily\"]" );
		expect( JS ).toContain( "state.input.dispatchEvent(new Event('input',{bubbles:true}))" );
		expect( JS ).not.toContain( "method:'POST'" );
	} );

	it( 'offers search, categories, recent fonts and favorites without loading every font file', () => {
		expect( JS ).toContain( 'Search fonts…' );
		expect( JS ).toContain( "['recent','Recent']" );
		expect( JS ).toContain( "['favorites','Favorites']" );
		expect( JS ).toContain( "['sans-serif','Sans']" );
		expect( JS ).toContain( "['serif','Serif']" );
		expect( JS ).toContain( "['display','Display']" );
		expect( JS ).toContain( "['handwriting','Handwriting']" );
		expect( JS ).toContain( "['monospace','Mono']" );
		expect( JS ).toContain( "slice(0,120)" );
		expect( JS ).toContain( "slice(0,12)" );
		expect( JS ).toContain( 'RECENT_KEY' );
		expect( JS ).toContain( 'FAVORITES_KEY' );
	} );

	it( 'loads only selected Google Fonts on Studio and frontend, including the canonical iframe', () => {
		expect( PHP ).toContain( "https://fonts.googleapis.com/css2?family=" );
		expect( PHP ).toContain( 'wp_enqueue_style( self::STYLE_HANDLE' );
		expect( PHP ).toContain( 'self::find_google_font( $family, false )' );
		expect( JS ).toContain( "https://fonts.googleapis.com/css2?family=" );
		expect( JS ).toContain( ".cc-studio-canonical-preview" );
		expect( JS ).toContain( 'setLink(canonicalDocument(),SELECTED_LINK_ID,href)' );
		expect( JS ).toContain( 'previewCssUrl' );
	} );

	it( 'enqueues after the compact runtime and exposes one protected font-library route', () => {
		expect( ENTRY ).toContain( '( new CrescoCanvas\\Styles\\FontLibrary() )->register();' );
		expect( STUDIO ).toContain( "const FONT_SCRIPT           = 'build/studio-global-design-fonts.js'" );
		expect( STUDIO ).toContain( "const FONT_STYLE            = 'assets/css/studio-global-design-fonts.css'" );
		expect( STUDIO ).toContain( "'fontLibraryPath' => '/cresco-canvas/v1/font-library'" );
		expect( STUDIO ).toContain( "array( self::COMPACT_HANDLE, 'wp-api-fetch' )" );
		expect( PHP ).toContain( "current_user_can( 'edit_theme_options' )" );
	} );

	it( 'keeps all font-picker styling in Studio chrome', () => {
		expect( CSS ).toContain( '.cc-global-design-pro .cc-gd-font-picker' );
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|textarea|a|p|h[1-6])\b/ );
		expect( CSS ).not.toMatch( /\.cc-studio-app\s+(button|input|textarea)\b/ );
	} );
} );
