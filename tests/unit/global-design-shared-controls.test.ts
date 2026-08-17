import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-shared-controls.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-shared-controls.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );
const DIMENSION_PHP = readFileSync( join( ROOT, 'includes/Builder/StudioDimensionControls.php' ), 'utf8' );
const DIMENSION_JS = readFileSync( join( ROOT, 'build/studio-dimension-controls.js' ), 'utf8' );

describe( 'shared dimension controls', () => {
	it( 'adds a visual picker for custom colors without changing persistence', () => {
		expect( JS ).toContain( "input[data-color-value]" );
		expect( JS ).toContain( "picker.type='color'" );
		expect( JS ).toContain( "value.value=picker.value" );
		expect( JS ).not.toContain( 'apiFetch' );
		expect( JS ).not.toContain( 'localStorage' );
	} );

	it( 'uses value plus unit selectors for every editable Global Design dimension', () => {
		expect( JS ).toContain( "ALL_UNITS=['px','%','em','rem','vw','vh','vmin','vmax','ch']" );
		expect( JS ).toContain( "input[data-fluid],input[data-number],input[data-breakpoint]" );
		expect( JS ).toContain( "unit.appendChild(option('custom','Custom'))" );
		expect( JS ).toContain( "proxy.type=custom?'text':'number'" );
		expect( JS ).toContain( "placeholder=custom?'clamp(), calc(), min(), max()':'0'" );
		expect( CSS ).toContain( 'grid-template-columns:minmax(0,1fr) 62px' );
	} );

	it( 'keeps spacing value and unit controls on the same row', () => {
		expect( CSS ).toContain( '.cc-gd-space-list label:has(.cc-gd-unit-control)>.cc-gd-unit-control{grid-column:2;grid-row:1}' );
		expect( CSS ).toContain( '>.cc-gd-unit-control>.cc-gd-unit-value{grid-column:1!important;grid-row:1!important' );
		expect( CSS ).toContain( '>.cc-gd-unit-control>.cc-gd-unit-select{grid-column:2!important;grid-row:1!important' );
	} );

	it( 'keeps numeric-only settings honest by exposing px only', () => {
		expect( JS ).toContain( "input.hasAttribute('data-number')||input.hasAttribute('data-breakpoint')" );
		expect( JS ).toContain( "return['px']" );
	} );

	it( 'removes preset overlays and keeps the canonical widget dimension unit UI', () => {
		expect( PHP ).not.toContain( 'TYPE_SIZE_SCRIPT' );
		expect( PHP ).toContain( "array( self::COMPACT_HANDLE )" );
		expect( DIMENSION_PHP ).not.toContain( 'PRESET_SCRIPT' );
		expect( DIMENSION_PHP ).not.toContain( 'PRESET_STYLE' );
		expect( DIMENSION_JS ).toContain( "ALL_UNITS=['px','%','em','rem','vw','vh','vmin','vmax','ch']" );
		expect( DIMENSION_JS ).toContain( "unitsFor(key).concat(['custom'])" );
		expect( DIMENSION_JS ).toContain( "link.textContent=linked?'Linked':'Individual'" );
	} );

	it( 'remains Canvas-isolated', () => {
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|select|textarea)/ );
	} );
});
