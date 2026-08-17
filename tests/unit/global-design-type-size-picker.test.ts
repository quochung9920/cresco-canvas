/** Global Design typography size picker contracts. */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-type-size-picker.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-type-size-picker.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );

describe( 'Global Design type size picker', () => {
	it( 'loads after compact Global Design without creating a new persistence path', () => {
		expect( PHP ).toContain( "const TYPE_SIZE_SCRIPT         = 'build/studio-global-design-type-size-picker.js'" );
		expect( PHP ).toContain( "const TYPE_SIZE_STYLE          = 'assets/css/studio-global-design-type-size-picker.css'" );
		expect( PHP ).toContain( 'array( self::COMPACT_HANDLE )' );
		expect( JS ).not.toContain( 'apiFetch' );
		expect( JS ).not.toContain( 'localStorage' );
	} );

	it( 'offers broad responsive and fixed-size choices plus a custom mode', () => {
		expect( JS ).toContain( "responsive.label='Responsive'" );
		expect( JS ).toContain( "fixed.label='Fixed pixels'" );
		expect( JS ).toContain( "['Fluid 6XL - 52-104px'" );
		expect( JS ).toContain( '10,11,12,13,14,15,16,18,20,22,24,26,28,30,32,36,40,44,48,52,56,60,64,72,80,88,96,104,112,120,128' );
		expect( JS ).toContain( "CUSTOM='__custom__'" );
		expect( JS ).toContain( "placeholder','e.g. 48px, 3rem or clamp(...)" );
	} );

	it( 'keeps canonical data-fluid inputs and forwards changes to the existing Global Design runtime', () => {
		expect( JS ).toContain( "row.querySelector('input[data-fluid]')" );
		expect( JS ).toContain( "input.dispatchEvent(new Event('input',{bubbles:true}))" );
		expect( JS ).toContain( "input.dispatchEvent(new Event('change',{bubbles:true}))" );
		expect( JS ).toContain( "event.target.closest('.cc-gd-presets')" );
	} );

	it( 'uses compact inspector controls and remains isolated from rendered website content', () => {
		expect( CSS ).toContain( '.cc-gd-size-select{height:30px!important' );
		expect( CSS ).toContain( '.cc-gd-size-control.is-custom .cc-gd-size-custom{display:block}' );
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|select|textarea)/ );
	} );
});
