/** Regression coverage for the Global Design loading guard. */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const GUARD = readFileSync( join( ROOT, 'build/studio-global-design-workflows-guard.js' ), 'utf8' );
const PRO = readFileSync( join( ROOT, 'build/studio-global-design-pro.js' ), 'utf8' );

function compatibilityHelpers() {
	const from = GUARD.indexOf( 'function isObject(' );
	const to = GUARD.indexOf( 'function globalDesignDirty(' );
	expect( from ).toBeGreaterThan( -1 );
	expect( to ).toBeGreaterThan( from );
	const scope: any = {};
	// eslint-disable-next-line no-new-func
	new Function( 'exports', `${ GUARD.slice( from, to ) };exports.decorateCustomColors=decorateCustomColors;` )( scope );
	return scope;
}

describe( 'Global Design loading guard', () => {
	it( 'bridges the canonical customColors object without changing persistence shape', () => {
		const { decorateCustomColors } = compatibilityHelpers();
		const settings: any = {
			primary: '#635bff',
			fluidTokens: {},
			breakpoints: {},
			customColors: { brand: '#123456', accent: '#abcdef' },
		};

		decorateCustomColors( settings );

		expect( settings.customColors.length ).toBe( 2 );
		expect( settings.customColors.slice( 0, 1 ) ).toEqual( [
			{ key: 'brand', label: 'brand', value: '#123456' },
		] );
		expect( Object.keys( settings.customColors ) ).toEqual( [ 'brand', 'accent' ] );
		expect( JSON.stringify( settings.customColors ) ).toBe( '{"brand":"#123456","accent":"#abcdef"}' );
	} );

	it( 'does not pollute Object.prototype to provide the compatibility bridge', () => {
		expect( GUARD ).not.toMatch( /Object\.defineProperty\(\s*Object\.prototype/ );
		expect( GUARD ).toContain( "Object.defineProperty(colors,'length'" );
		expect( GUARD ).toContain( "Object.defineProperty(colors,'slice'" );
	} );

	it( 'keeps the server contract object-shaped while shielding the v2 overview consumer', () => {
		expect( PRO ).toContain( 's.customColors=obj(s.customColors)' );
		expect( PRO ).toContain( '(S.settings.customColors||[]).slice(0,4)' );
		expect( GUARD ).toContain( 'decorateParsed(nativeJsonParse.call(window.JSON,text,reviver))' );
	} );

	it( 'turns indefinite loading into a recoverable error state', () => {
		expect( GUARD ).toContain( 'LOAD_TIMEOUT=12000' );
		expect( GUARD ).toContain( 'STALL_TIMEOUT=14000' );
		expect( GUARD ).toContain( 'Global Design could not load' );
		expect( GUARD ).toContain( 'Retry Global Design' );
		expect( GUARD ).toContain( "window.addEventListener('unhandledrejection'" );
	} );

	it( 'deduplicates workspace hosts and removes leaked widget-library filters', () => {
		expect( GUARD ).toContain( 'if(hosts.length>1)' );
		expect( GUARD ).toContain( "panel.querySelectorAll('.cc-studio-ux-library-filters')" );
		expect( GUARD ).toContain( 'host!==keep' );
	} );
} );
