/**
 * Contracts for the Global Design workspace.
 *
 * The workspace ships as a hand-authored runtime under `build/`, so these tests
 * read the shipped artifact rather than a TypeScript source. That is deliberate:
 * the artifact is what loads in Studio, and a test against a source that is not
 * built would prove nothing about what users get.
 */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-pro.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-pro.css' ), 'utf8' );

/** Evaluate the contrast helpers in isolation, as the runtime defines them. */
function contrastHelpers() {
	const from = JS.indexOf( 'function rgbOf(' );
	const to = JS.indexOf( 'function contrastPairs(' );
	expect( from ).toBeGreaterThan( -1 );
	expect( to ).toBeGreaterThan( from );
	const scope: any = {};
	// eslint-disable-next-line no-new-func
	new Function( 'exports', `${ JS.slice( from, to ) };
		exports.contrastRatio=contrastRatio;exports.wcagLevel=wcagLevel;exports.rgbOf=rgbOf;` )( scope );
	return scope;
}

describe( 'navigation', () => {
	it( 'presents five top-level destinations, not seven peers', () => {
		const tabs = JS.match( /var TABS=\[(.*?)\];/ )?.[ 1 ] ?? '';
		expect( tabs.match( /\['/g ) ).toHaveLength( 5 );
		for ( const id of [ 'overview', 'colors', 'typography', 'layout', 'more' ] ) {
			expect( tabs ).toContain( `'${ id }'` );
		}
	} );

	it( 'redirects the retired tab ids so old entry points still land somewhere', () => {
		// Spacing, Usage and Advanced were top-level; they are now inside Layout
		// and More. Anything still asking for them must not render an empty panel.
		expect( JS ).toMatch( /LEGACY_TABS=\{spacing:'layout',usage:'more',advanced:'more'\}/ );
		expect( JS ).toContain( 'if(LEGACY_TABS[S.tab])S.tab=LEGACY_TABS[S.tab];' );
	} );

	it( 'keeps Spacing reachable by folding it into Layout rather than deleting it', () => {
		expect( JS ).toMatch( /function layoutAll\(\)\{return spacing\(\)\+layout\(\)\}/ );
	} );
} );

describe( 'contrast', () => {
	const { contrastRatio, wcagLevel, rgbOf } = contrastHelpers();

	it( 'computes the WCAG ratio symmetrically', () => {
		expect( contrastRatio( '#ffffff', '#000000' ) ).toBeCloseTo( 21, 2 );
		expect( contrastRatio( '#000000', '#ffffff' ) ).toBeCloseTo( 21, 2 );
	} );

	it( 'resolves the colour forms the runtime claims to support', () => {
		expect( rgbOf( '#fff' ) ).toEqual( [ 255, 255, 255 ] );
		expect( rgbOf( '#ffffffff' ) ).toEqual( [ 255, 255, 255 ] );
		expect( rgbOf( 'rgb(11, 79, 147)' ) ).toEqual( [ 11, 79, 147 ] );
		expect( rgbOf( 'rgba(11,79,147,0.5)' ) ).toEqual( [ 11, 79, 147 ] );
	} );

	it( 'reports inability rather than guessing an unresolvable colour', () => {
		// A wrong ratio is worse than no ratio: it would silently pass an audit.
		expect( contrastRatio( 'var(--brand)', '#ffffff' ) ).toBeNull();
		expect( contrastRatio( 'hsl(0 0% 0%)', '#ffffff' ) ).toBeNull();
		expect( wcagLevel( null, false ).label ).toBe( 'Unable to evaluate' );
	} );

	it( 'maps ratios to WCAG levels at the published thresholds', () => {
		expect( wcagLevel( 7, false ).label ).toBe( 'AAA' );
		expect( wcagLevel( 4.5, false ).label ).toBe( 'AA' );
		expect( wcagLevel( 3, false ).label ).toBe( 'AA Large only' );
		expect( wcagLevel( 2.9, false ).label ).toBe( 'Fail' );
	} );

	it( 'is deterministic for a real brand pair', () => {
		const ratio = contrastRatio( '#0b4f93', '#ffffff' );
		expect( ratio ).not.toBeNull();
		expect( ratio as number ).toBeCloseTo( 8.22, 2 );
	} );
} );

describe( 'canvas isolation', () => {
	it( 'never styles the rendered website roots', () => {
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
	} );

	it( 'never styles bare elements inside the canvas', () => {
		// `.cc-studio-canvas button` would repaint the user's own buttons.
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|textarea|a|p|h[1-6])\b/ );
		expect( CSS ).not.toMatch( /\.cc-studio-app\s+(button|input|textarea)\b/ );
	} );

	it( 'scopes every rule it adds to the workspace host', () => {
		const selectors = CSS.split( '}' )
			.map( ( chunk ) => chunk.split( '{' )[ 0 ]?.trim() ?? '' )
			.filter( ( sel ) => sel && ! sel.startsWith( '@' ) && ! sel.startsWith( '/*' ) );
		const unscoped = selectors.filter(
			( sel ) => ! /\.cc-global-design-pro|\.cc-gd-|\.cc-global-design-toast|^:root/.test( sel )
		);
		expect( unscoped ).toEqual( [] );
	} );
} );

describe( 'persistence boundary', () => {
	it( 'does not invent settings the server schema has no field for', () => {
		// GlobalStyles::defaults() persists no shadow or motion values; they are
		// derived in DesignTokens::catalog(). Editing them here would look saved
		// and be gone on reload, so the workspace must not offer the control.
		expect( JS ).not.toMatch( /data-(shadow|motion)=/ );
	} );
} );
