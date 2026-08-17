import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-shared-controls.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-shared-controls.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );

describe( 'Global Design shared controls', () => {
	it( 'adds a visual picker for custom colors without changing persistence', () => {
		expect( JS ).toContain( "input[data-color-value]" );
		expect( JS ).toContain( "picker.type='color'" );
		expect( JS ).toContain( "value.value=picker.value" );
		expect( JS ).not.toContain( 'apiFetch' );
		expect( JS ).not.toContain( 'localStorage' );
	} );

	it( 'provides preset and Custom flows for spacing, radius, widths and breakpoints', () => {
		expect( JS ).toContain( "cc-gd-space-list input[data-fluid]" );
		expect( JS ).toContain( "input[data-number=\"radius\"]" );
		expect( JS ).toContain( "input[data-breakpoint]" );
		expect( JS ).toContain( "Custom…" );
		expect( JS ).toContain( 'clamp(1rem, 0.82rem + 0.7vw, 1.5rem)' );
	} );

	it( 'loads after the type-size picker and remains Canvas-isolated', () => {
		expect( PHP ).toContain( "const SHARED_SCRIPT             = 'build/studio-global-design-shared-controls.js'" );
		expect( PHP ).toContain( "array( self::TYPE_SIZE_SCRIPT_HANDLE )" );
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|select|textarea)/ );
	} );
});
