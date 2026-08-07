import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const runtimeFiles = [ 'build/standalone-visual-editor.js' ];
const errors = [];

for ( const file of runtimeFiles ) {
	const result = spawnSync( process.execPath, [ '--check', file ], {
		cwd: process.cwd(),
		encoding: 'utf8',
	} );
	if ( result.status !== 0 ) {
		errors.push(
			`${ file }: ${ result.stderr || result.stdout || 'syntax check failed' }`
		);
	}
}

const visualEditor = await readFile( 'includes/Admin/VisualEditor.php', 'utf8' );
const sessionManager = await readFile(
	'includes/Session/SessionManager.php',
	'utf8'
);
const runtime = await readFile( 'build/standalone-visual-editor.js', 'utf8' );
const asset = await readFile(
	'build/standalone-visual-editor.asset.php',
	'utf8'
);
const packaging = await readFile( 'scripts/build-release.mjs', 'utf8' );
const sessionSpec = await readFile( 'docs/CRESCO_SESSION_V1.md', 'utf8' );

const requiredVisualEditorTokens = [
	"'sessionPath'",
	"'validatePath'",
	"'aiContextPath'",
	'build/standalone-visual-editor.js',
	'assets/css/standalone-visual-editor.css',
	'GlobalStyles::css',
];
for ( const token of requiredVisualEditorTokens ) {
	if ( ! visualEditor.includes( token ) ) {
		errors.push( `VisualEditor is missing ${ token }` );
	}
}

const requiredSessionTokens = [
	"const SCHEMA = 'cresco-session/v1'",
	"const META_KEY = '_cresco_canvas_document'",
	"'/session/(?P<postId>\\d+)'",
	"'/session/validate'",
	"'/ai-context/(?P<postId>\\d+)'",
	'sanitize_custom_css',
	'compile_session_css',
	'data-cresco-part',
];
for ( const token of requiredSessionTokens ) {
	if ( ! sessionManager.includes( token ) ) {
		errors.push( `SessionManager is missing ${ token }` );
	}
}

const requiredRuntimeTokens = [
	'settings.aiContextPath',
	'settings.sessionPath',
	'settings.validatePath',
	'Copy AI Context',
	'Apply to Cresco Editor',
	'cc-session-canvas',
	'data-cresco-id',
	'customCSS',
];
for ( const token of requiredRuntimeTokens ) {
	if ( ! runtime.includes( token ) ) {
		errors.push( `Standalone editor runtime is missing ${ token }` );
	}
}

const forbiddenRuntimeTokens = [
	'BlockEditorProvider',
	'BlockInspector',
	'wp.blocks.parse',
	'wp.blocks.serialize',
	'core/block-editor',
];
for ( const token of forbiddenRuntimeTokens ) {
	if ( runtime.includes( token ) ) {
		errors.push(
			`Standalone editor must not depend on the retired Gutenberg document runtime: ${ token }`
		);
	}
}

const requiredDependencies = [
	"'wp-api-fetch'",
	"'wp-components'",
	"'wp-element'",
	"'wp-i18n'",
];
for ( const token of requiredDependencies ) {
	if ( ! asset.includes( token ) ) {
		errors.push( `Standalone asset manifest is missing ${ token }` );
	}
}
for ( const token of [ "'wp-block-editor'", "'wp-blocks'", "'wp-data'" ] ) {
	if ( asset.includes( token ) ) {
		errors.push( `Standalone asset manifest still depends on ${ token }` );
	}
}

for ( const file of [
	'docs/CRESCO_SESSION_V1.md',
	'assets/css/standalone-visual-editor.css',
	'build/standalone-visual-editor.js',
	'build/standalone-visual-editor.asset.php',
	'includes/Session/SessionManager.php',
] ) {
	if ( ! packaging.includes( `'${ file }'` ) ) {
		errors.push( `Release package does not require ${ file }` );
	}
}

for ( const token of [
	'Global Design + Widget Contract + Current Session',
	'Validate -> Apply -> Update',
	'Every node has a stable, unique `id`',
] ) {
	if ( ! sessionSpec.includes( token ) ) {
		errors.push( `Cresco Session specification is missing: ${ token }` );
	}
}

if ( visualEditor.includes( 'standalone-content-bootstrap.js' ) ) {
	errors.push(
		'VisualEditor still loads the retired standalone content bootstrap.'
	);
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write(
	'Checked the authoritative Cresco Session editor runtime, REST contract, AI interchange, dependencies, and package gates.\n'
);
