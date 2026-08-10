import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const errors = [];
const hash = ( value ) => createHash( 'sha256' ).update( value ).digest( 'hex' );
const read = ( file ) => readFile( file, 'utf8' );

const [ editorSource, editorBuild, bootstrapSource, bootstrapBuild, resilience ] = await Promise.all( [
	read( 'runtime-src/build/website-builder-editor.js' ),
	read( 'build/website-builder-editor.js' ),
	read( 'runtime-src/build/website-builder-bootstrap.js' ),
	read( 'build/website-builder-bootstrap.js' ),
	read( 'includes/Builder/WebsiteBuilderBootstrapResilience.php' ),
] );

for ( const file of [
	'runtime-src/build/website-builder-editor.js',
	'build/website-builder-editor.js',
	'runtime-src/build/website-builder-bootstrap.js',
	'build/website-builder-bootstrap.js',
] ) {
	const result = spawnSync( process.execPath, [ '--check', file ], { encoding: 'utf8' } );
	if ( result.status !== 0 ) errors.push( `${ file }: ${ result.stderr || result.stdout || 'syntax check failed' }` );
}

if ( hash( editorSource ) !== hash( editorBuild ) ) errors.push( 'Website Builder editor source/build drift detected.' );
if ( hash( bootstrapSource ) !== hash( bootstrapBuild ) ) errors.push( 'Website Builder bootstrap source/build drift detected.' );

for ( const token of [
	"apiFetch({path:settings.sessionPath}).then",
	"setContext(fallbackContext)",
	"setLoading(false)",
	"Promise.allSettled(requests)",
	"window.crescoWebsiteBuilderEditorBoot",
	"loading:'lazy'",
	"decoding:'async'",
	"name:fieldName",
	"htmlFor:fieldId",
] ) if ( ! editorSource.includes( token ) ) errors.push( `Editor startup hardening missing ${ token }` );

if ( editorSource.includes( 'Promise.all(requests)' ) ) errors.push( 'Editor must not block startup on monolithic Promise.all(requests).' );
if ( editorSource.includes( "var requests=[apiFetch({path:settings.sessionPath}),apiFetch({path:settings.contextPath})" ) ) {
	errors.push( 'Session must not be bundled with optional startup requests.' );
}

for ( const token of [
	'new AbortController()',
	'controller.abort()',
	'requestOptions.signal=controller.signal',
	"window.addEventListener('pagehide',abortActive",
	'state.middlewareInstalled=installApiFetchMiddleware()',
	'aborted:[]',
	'active:{}',
] ) if ( ! bootstrapSource.includes( token ) ) errors.push( `Abortable bootstrap missing ${ token }` );

for ( const token of [
	"ThemeSessionBridge::PAGE_SLUG",
	"'/cresco-canvas/v1/website-builder/theme-session/'",
	"'/cresco-canvas/v1/website-builder/theme-context/'",
	"'/cresco-canvas/v1/website-builder/theme-page-settings/'",
	'bootstrap.middlewareInstalled&&bootstrap.abortable',
	'attach_observer_guards',
] ) if ( ! resilience.includes( token ) ) errors.push( `Bootstrap resilience boundary missing ${ token }` );

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( 'Website Builder critical boot, abortable optional requests, source/build parity, form accessibility, lazy media, and observer stability contracts verified.\n' );
