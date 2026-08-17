import { readFileSync } from 'fs';
import { join } from 'path';

const ROOT = join( __dirname, '..', '..' );
const JS = readFileSync( join( ROOT, 'build/studio-widget-state-tabs.js' ), 'utf8' );
const CSS = readFileSync( join( ROOT, 'assets/css/studio-widget-state-tabs.css' ), 'utf8' );
const PHP = readFileSync( join( ROOT, 'includes/Builder/StudioWidgetStateTabs.php' ), 'utf8' );
const BOOT = readFileSync( join( ROOT, 'cresco-canvas.php' ), 'utf8' );

describe( 'widget state tabs', () => {
	it( 'uses widget contracts to expose only supported pseudo states', () => {
		expect( JS ).toContain( "ORDER=['normal','hover','focus','active']" );
		expect( JS ).toContain( 'Array.isArray(def.states)' );
		expect( JS ).toContain( 'allowed={normal:true}' );
		expect( JS ).toContain( 'commonStates()' );
		expect( JS ).toContain( "activeTab!=='content'&&allowed.length>1" );
	} );

	it( 'presents state switching as accessible segmented tabs', () => {
		expect( JS ).toContain( "setAttribute('role','tablist')" );
		expect( JS ).toContain( "setAttribute('role','tab')" );
		expect( JS ).toContain( "setAttribute('aria-selected'" );
		expect( JS ).toContain( "['ArrowLeft','ArrowRight','Home','End']" );
		expect( CSS ).toContain( 'grid-template-columns:repeat(var(--cc-widget-state-count,3),minmax(0,1fr))' );
		expect( CSS ).toContain( 'button.is-active' );
	} );

	it( 'keeps state persistence owned by the canonical Studio runtime', () => {
		expect( JS ).not.toContain( "method:'POST'" );
		expect( JS ).not.toContain( 'localStorage' );
		expect( JS ).toContain( "window.addEventListener('cresco:studio-session-change'" );
		expect( JS ).toContain( 'normalButton.click()' );
	} );

	it( 'loads only in Studio and remains Canvas isolated', () => {
		expect( PHP ).toContain( 'WebsiteBuilderRuntimeContext::from_request()' );
		expect( PHP ).toContain( 'WebsiteBuilderStudio::HANDLE' );
		expect( BOOT ).toContain( 'new CrescoCanvas\\Builder\\StudioWidgetStateTabs()' );
		expect( CSS ).not.toContain( '.cresco-session-root' );
		expect( CSS ).not.toContain( '.cresco-website-builder-root' );
		expect( CSS ).not.toMatch( /\.cc-studio-canvas\s/ );
	} );
} );
