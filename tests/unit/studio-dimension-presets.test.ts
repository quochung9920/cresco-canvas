import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-dimension-presets.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-dimension-presets.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioDimensionControls.php' ), 'utf8' );

describe( 'Studio dimension presets', () => {
	it( 'enhances existing canonical dimension controls rather than writing widget state directly', () => {
		expect( JS ).toContain( '.cc-studio-dimension-control' );
		expect( JS ).toContain( '.cc-studio-dimension-proxy' );
		expect( JS ).toContain( '.cc-studio-dimension-unit' );
		expect( JS ).toContain( "dispatchEvent(new Event('input',{bubbles:true}))" );
		expect( JS ).not.toContain( 'apiFetch' );
		expect( JS ).not.toContain( 'sessionStorage' );
	} );

	it( 'offers context-sensitive presets plus custom values', () => {
		expect( JS ).toContain( 'fontsize' );
		expect( JS ).toContain( 'lineheight' );
		expect( JS ).toContain( 'letterspacing' );
		expect( JS ).toContain( 'width|height|basis' );
		expect( JS ).toContain( 'radius' );
		expect( JS ).toContain( "Custom…" );
		expect( JS ).toContain( '100%' );
	} );

	it( 'covers margin, padding, border width and radius controls', () => {
		expect( JS ).toContain( '.cc-studio-spacing__grid>label' );
		expect( JS ).toContain( '.cc-studio-spacing-proxy' );
		expect( JS ).toContain( '.cc-studio-border-box-grid>label' );
		expect( JS ).toContain( 'cc-studio-border-preset' );
		expect( PHP ).toContain( "const PRESET_SCRIPT       = 'build/studio-dimension-presets.js'" );
		expect( PHP ).toContain( "const PRESET_STYLE        = 'assets/css/studio-dimension-presets.css'" );
	} );

	it( 'scopes styles to Studio left chrome and never owns website Canvas controls', () => {
		expect( CSS ).toContain( '#cresco-canvas-standalone-editor .cc-studio-left' );
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|select|textarea)/ );
	} );
});
