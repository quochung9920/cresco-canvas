/** Compact Global Design control-surface contracts. */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-compact.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-compact.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );

describe( 'compact Global Design UI', () => {
	it( 'loads after the canonical Global Design runtime without creating persistence', () => {
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

	it( 'removes decorative preview surfaces and keeps the Canvas as the preview', () => {
		expect( JS ).toContain( '.cc-gd-swatches,.cc-gd-type-preview,.cc-gd-bars,.cc-gd-contrast.is-compact' );
		expect( CSS ).toContain( '.cc-gd-font-preview' );
		expect( CSS ).toContain( '.cc-gd-type-preview' );
		expect( CSS ).toContain( 'display:none' );
	} );

	it( 'uses a widget-inspector-sized panel and never owns rendered website controls', () => {
		expect( CSS ).toContain( '--cc-ux-left-width:min(360px,calc(100vw - 66px))!important' );
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|textarea|a|p|h[1-6])\b/ );
		expect( CSS ).not.toMatch( /\.cc-studio-app\s+(button|input|textarea)\b/ );
	} );
});
