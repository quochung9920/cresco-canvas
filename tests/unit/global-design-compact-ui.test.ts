/** Compact Global Design control-surface contracts. */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-compact.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-compact.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );
const WEB_FONTS = readFileSync( join( ROOT, 'includes/Styles/GlobalWebFonts.php' ), 'utf8' );
const BOOT = readFileSync( join( ROOT, 'cresco-canvas.php' ), 'utf8' );

describe( 'compact Global Design UI', () => {
	it( 'loads after the canonical Global Design runtime without creating design persistence', () => {
		expect( PHP ).toContain( "const COMPACT_SCRIPT        = 'build/studio-global-design-compact.js'" );
		expect( PHP ).toContain( "const COMPACT_STYLE         = 'assets/css/studio-global-design-compact.css'" );
		const main = PHP.lastIndexOf( 'WebsiteBuilderAsset::url( self::SCRIPT )' );
		const compact = PHP.lastIndexOf( 'WebsiteBuilderAsset::url( self::COMPACT_SCRIPT )' );
		expect( main ).toBeGreaterThan( -1 );
		expect( compact ).toBeGreaterThan( main );
		expect( JS ).not.toContain( 'apiFetch' );
		expect( JS ).not.toContain( 'localStorage' );
	} );

	it( 'turns typography previews into compact controls while preserving existing data-fluid inputs', () => {
		expect( JS ).toContain( "host.querySelectorAll('.cc-gd-font-preview')" );
		expect( JS ).toContain( "article.querySelector('input[data-fluid]')" );
		expect( JS ).toContain( "row.className='cc-gd-control-row cc-gd-control-row--type'" );
		expect( JS ).toContain( "input.setAttribute('aria-label',label+' global size')" );
		expect( JS ).not.toContain( 'fontWeight' );
		expect( JS ).not.toContain( 'lineHeight' );
	} );

	it( 'provides a searchable categorized font picker and preserves the canonical fontFamily input', () => {
		expect( PHP ).toContain( "'fonts'        => \\CrescoCanvas\\Styles\\GlobalWebFonts::catalog()" );
		expect( PHP ).toContain( "'systemFonts'  => \\CrescoCanvas\\Styles\\GlobalWebFonts::system_fonts()" );
		expect( JS ).toContain( "input[data-bind=\"fontFamily\"]" );
		expect( JS ).toContain( 'Search '+"'"+'+fonts.length+'+"'"+' fonts...' );
		expect( JS ).toContain( "data-gd-font-category=\"sans\"" );
		expect( JS ).toContain( "data-gd-font-category=\"serif\"" );
		expect( JS ).toContain( "data-gd-font-category=\"display\"" );
		expect( JS ).toContain( "data-gd-font-category=\"mono\"" );
		expect( JS ).toContain( "original.dispatchEvent(new Event('input',{bubbles:true}))" );
		expect( JS ).toContain( 'fonts.googleapis.com/css2?family=' );
	} );

	it( 'ships a broad web-font catalog and loads the selected family on the frontend', () => {
		expect( WEB_FONTS ).toContain( "'Inter'" );
		expect( WEB_FONTS ).toContain( "'Plus Jakarta Sans'" );
		expect( WEB_FONTS ).toContain( "'Playfair Display'" );
		expect( WEB_FONTS ).toContain( "'JetBrains Mono'" );
		expect( WEB_FONTS ).toContain( "'Dancing Script'" );
		expect( WEB_FONTS ).toContain( 'fonts.googleapis.com/css2?family=' );
		expect( WEB_FONTS ).toContain( "wp_enqueue_style( self::HANDLE, $url, array(), null )" );
		expect( BOOT ).toContain( '( new CrescoCanvas\\Styles\\GlobalWebFonts() )->register();' );
	} );

	it( 'removes decorative preview surfaces and keeps the Canvas as the preview', () => {
		expect( JS ).toContain( '.cc-gd-swatches,.cc-gd-type-preview,.cc-gd-bars,.cc-gd-contrast.is-compact' );
		expect( CSS ).toContain( '.cc-gd-font-preview' );
		expect( CSS ).toContain( '.cc-gd-type-preview' );
		expect( CSS ).toContain( 'display:none' );
	} );

	it( 'keeps the tab strip compact instead of stretching into unused vertical space', () => {
		expect( CSS ).toContain( 'grid-template-rows:auto minmax(0,1fr) auto' );
		expect( CSS ).toContain( 'grid-template-columns:repeat(5,minmax(0,1fr))' );
		expect( CSS ).toContain( 'height:34px;min-height:34px' );
		expect( CSS ).toContain( 'height:28px!important;min-height:28px!important' );
	} );

	it( 'normalizes field padding, footer width, and avoids horizontal panel overflow', () => {
		expect( CSS ).toContain( 'overflow-x:hidden' );
		expect( CSS ).toContain( 'border-radius:7px!important' );
		expect( CSS ).toContain( '.cc-gd-footer{width:100%;min-width:0;margin:0' );
		expect( CSS ).not.toContain( '.cc-gd-footer{margin:0 -10px' );
	} );

	it( 'fits usage actions into the color row instead of creating a narrow wrapped column', () => {
		expect( JS ).toContain( 'function compactColorActions(host)' );
		expect( JS ).toContain( "button.textContent=count+' use'+(count===1?'':'s')" );
		expect( JS ).toContain( 'button.disabled=count===0' );
		expect( CSS ).toContain( '.cc-gdw-color-actions{grid-column:3;grid-row:1' );
		expect( CSS ).toContain( '.cc-gd-color-card>input:not([type=color]){grid-column:1/4;grid-row:2' );
	} );

	it( 'uses a widget-inspector-sized panel and never owns rendered website controls', () => {
		expect( CSS ).toContain( '--cc-ux-left-width:min(360px,calc(100vw - 66px))!important' );
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|textarea|a|p|h[1-6])\b/ );
		expect( CSS ).not.toMatch( /\.cc-studio-app\s+(button|input|textarea)\b/ );
	} );
});
