/** Actionable Global Design workflow contracts. */
import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const GUARD = readFileSync( join( ROOT, 'build/studio-global-design-workflows-guard.js' ), 'utf8' );
const JS = readFileSync( join( ROOT, 'build/studio-global-design-workflows.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-global-design-workflows.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioGlobalDesignPro.php' ), 'utf8' );

function functionSource( name: string, nextName: string ) {
	const from = JS.indexOf( `function ${ name }(` );
	const to = JS.indexOf( `function ${ nextName }(` );
	expect( from ).toBeGreaterThan( -1 );
	expect( to ).toBeGreaterThan( from );
	return JS.slice( from, to );
}

describe( 'workflow loading boundary', () => {
	it( 'loads the workflow prelude before the main Global Design runtime', () => {
		expect( PHP ).toContain( "const WORKFLOW_GUARD_SCRIPT = 'build/studio-global-design-workflows-guard.js'" );
		expect( PHP ).toContain( "const WORKFLOW_SCRIPT       = 'build/studio-global-design-workflows.js'" );
		expect( PHP ).toContain( "'assets/css/studio-global-design-workflows.css'" );
		expect( PHP ).toContain( 'self::WORKFLOW_HANDLE' );
		const workflowEnqueue = PHP.indexOf( 'WebsiteBuilderAsset::url( self::WORKFLOW_SCRIPT )' );
		const mainEnqueue = PHP.lastIndexOf( 'WebsiteBuilderAsset::url( self::SCRIPT )' );
		expect( workflowEnqueue ).toBeGreaterThan( -1 );
		expect( mainEnqueue ).toBeGreaterThan( workflowEnqueue );
	} );

	it( 'keeps canonical settings and session paths rather than inventing persistence', () => {
		expect( GUARD ).toContain( 'Apply or discard Global Design changes before normalizing the page.' );
		expect( GUARD ).toContain( 'path===studio.sessionPath' );
		expect( JS ).toContain( 'cfg.settingsPath' );
		expect( JS ).toContain( 'cfg.resetPath' );
		expect( JS ).toContain( 'studio.sessionPath' );
		expect( JS ).not.toContain( 'localStorage.setItem' );
	} );
} );

describe( 'safe apply workflow', () => {
	it( 'intercepts Save globally for an impact review before allowing the existing save', () => {
		expect( JS ).toContain( 'impactDialog(save)' );
		expect( JS ).toContain( "confirm:'Apply globally'" );
		expect( JS ).toContain( 'W.allowSaveClick=true;saveButton.click()' );
		expect( JS ).toContain( 'impactReport(before,after,W.latestSession)' );
	} );

	it( 'validates strictly increasing breakpoints', () => {
		const objSource = functionSource( 'obj', 'arr' );
		const validateSource = functionSource( 'validateBreakpoints', 'healthReport' );
		const scope: any = {};
		// eslint-disable-next-line no-new-func
		new Function( 'exports', `${ objSource }${ validateSource };exports.validateBreakpoints=validateBreakpoints;` )( scope );
		expect( scope.validateBreakpoints( { breakpoints: { mobile: 0, tablet: 768, laptop: 1025, desktop: 1440, wide: 1920 } } ).valid ).toBe( true );
		expect( scope.validateBreakpoints( { breakpoints: { mobile: 0, tablet: 1025, laptop: 768, desktop: 1440, wide: 1920 } } ).valid ).toBe( false );
	} );

	it( 'keeps a reversible snapshot for global settings and session cleanup', () => {
		expect( JS ).toContain( 'SETTINGS_UNDO_KEY' );
		expect( JS ).toContain( 'SESSION_UNDO_KEY' );
		expect( JS ).toContain( 'data-gdw-undo-settings' );
		expect( JS ).toContain( 'data-gdw-undo-session' );
	} );
} );

describe( 'actionable usage and normalization', () => {
	it( 'locates an existing node by using Studio selection rather than a second selection model', () => {
		expect( JS ).toContain( '.cc-studio-canvas-node[data-cresco-id]' );
		expect( JS ).toContain( 'node.click()' );
		expect( JS ).toContain( 'node.scrollIntoView' );
		expect( JS ).toContain( '.cc-studio-tree-row[data-cresco-node-id]' );
	} );

	it( 'preserves node identity and only replaces existing compatible value paths', () => {
		expect( JS ).toContain( "setBySegments(n,r.segments,'{'+p.tokenPath+'}')" );
		expect( JS ).toContain( 'JSON.stringify(idList(current))!==JSON.stringify(idList(next))' );
		expect( JS ).toContain( 'Save the page before applying a design-system cleanup.' );
		expect( JS ).toContain( "api(studio.sessionPath,{method:'POST',data:{session:next}})" );
	} );

	it( 'distinguishes exact replacements from near matches that require review', () => {
		expect( JS ).toContain( "confidence:d===0?'Exact':d<=28?'Near':null" );
		expect( JS ).toContain( "confidence:d===0?'Exact':'Near'" );
		expect( JS ).toContain( "safeOnly||s.confidence==='Exact'" );
	} );

	it( 'does not silently remove an in-use custom color token', () => {
		expect( JS ).toContain( 'This color is still in use' );
		expect( JS ).toContain( 'Removing an in-use token without replacement' );
		expect( JS ).toContain( 'Replace usages' );
	} );
} );

describe( 'health and accessibility', () => {
	it( 'reports deterministic category scores and contrast checks', () => {
		expect( JS ).toContain( 'scores={colors:' );
		expect( JS ).toContain( "label:'Text on background'" );
		expect( JS ).toContain( "label:'Muted on background'" );
		expect( JS ).toContain( "label:'White on primary'" );
		expect( JS ).toContain( "label:'Black on primary'" );
	} );

	it( 'refuses contrast evaluation for unresolved or translucent values', () => {
		const rgbaSource = functionSource( 'rgbaOf', 'luminance' );
		const luminanceSource = functionSource( 'luminance', 'contrastRatio' );
		const contrastSource = functionSource( 'contrastRatio', 'contrastChecks' );
		const scope: any = {};
		// eslint-disable-next-line no-new-func
		new Function( 'exports', `${ rgbaSource }${ luminanceSource }${ contrastSource };exports.contrastRatio=contrastRatio;` )( scope );
		expect( scope.contrastRatio( '#ffffff', '#000000' ) ).toBeCloseTo( 21, 2 );
		expect( scope.contrastRatio( 'var(--brand)', '#ffffff' ) ).toBeNull();
		expect( scope.contrastRatio( 'rgba(0,0,0,.5)', '#ffffff' ) ).toBeNull();
	} );
} );

describe( 'canvas isolation', () => {
	it( 'never themes the rendered document from the workflow stylesheet', () => {
		expect( CSS ).not.toMatch( /\.cresco-session-root/ );
		expect( CSS ).not.toMatch( /\.cresco-website-builder-root/ );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s+(button|input|textarea|a|p|h[1-6])\b/ );
		expect( CSS ).not.toMatch( /\.cc-studio-app\s+(button|input|textarea)\b/ );
	} );

	it( 'limits permanent CSS ownership to Global Design and its modal UI', () => {
		expect( CSS ).toContain( '.cc-global-design-pro .cc-gdw-action-center' );
		expect( CSS ).toContain( '.cc-gdw-modal' );
	} );
} );
