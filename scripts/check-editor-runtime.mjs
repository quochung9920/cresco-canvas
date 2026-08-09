import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const runtimeFiles = [
	'build/standalone-visual-editor.js',
	'build/standalone-inspector-v2.js',
	'build/standalone-ui-v3.js',
	'build/standalone-page-settings.js',
	'build/standalone-history.js',
	'build/global-config-import.js',
	'build/viewport-shell.js',
];
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
const plugin = await readFile( 'includes/Plugin.php', 'utf8' );
const pageSettingsService = await readFile( 'includes/Page/PageSettings.php', 'utf8' );
const historyService = await readFile( 'includes/Session/HistoryManager.php', 'utf8' );
const sessionManager = await readFile(
	'includes/Session/SessionManager.php',
	'utf8'
);
const runtime = await readFile( 'build/standalone-visual-editor.js', 'utf8' );
const inspectorV2 = await readFile( 'build/standalone-inspector-v2.js', 'utf8' );
const uiV3 = await readFile( 'build/standalone-ui-v3.js', 'utf8' );
const pageSettingsRuntime = await readFile( 'build/standalone-page-settings.js', 'utf8' );
const historyRuntime = await readFile( 'build/standalone-history.js', 'utf8' );
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
	"'historyPath'",
	"'pageSettingsPath'",
	'build/standalone-visual-editor.js',
	'build/standalone-inspector-v2.js',
	'build/standalone-ui-v3.js',
	'build/standalone-page-settings.js',
	'build/standalone-history.js',
	'build/global-config-import.js',
	'build/viewport-shell.js',
	'assets/css/standalone-visual-editor.css',
	'assets/css/standalone-inspector-v2.css',
	'assets/css/standalone-ui-v3.css',
	'assets/css/standalone-page-settings.css',
	'assets/css/standalone-history.css',
	'assets/css/global-config-import.css',
	'assets/css/viewport-shell.css',
	'GlobalStyles::css',
];
for ( const token of requiredVisualEditorTokens ) {
	if ( ! visualEditor.includes( token ) ) {
		errors.push( `VisualEditor is missing ${ token }` );
	}
}

for ( const token of [
	'use CrescoCanvas\\Page\\PageSettings;',
	'( new PageSettings() )->register();',
] ) {
	if ( ! plugin.includes( token ) ) errors.push( `Plugin is missing Page Settings registration: ${ token }` );
}

for ( const token of [
	'use CrescoCanvas\\Session\\HistoryManager;',
	'( new HistoryManager() )->register();',
] ) {
	if ( ! plugin.includes( token ) ) errors.push( `Plugin is missing History registration: ${ token }` );
}

for ( const token of [
	"const META_KEY = '_cresco_canvas_page_settings'",
	"'/page-settings/(?P<postId>\\d+)'",
	"'layout'      => 'full-width'",
	"'contentRoot' => 'viewport'",
	'template_include',
	'pre_render_block',
	'render_block_core/template-part',
	'get_block_template',
	'template_part_area',
	'cresco-page-root-viewport',
	'pageSettingsEffective',
	'not part of cresco-session/v1',
] ) {
	if ( ! pageSettingsService.includes( token ) ) {
		errors.push( `Page Settings service is missing ${ token }` );
	}
}

for ( const token of [
	"const POST_TYPE = 'cresco_revision'",
	"'/history/(?P<postId>\\d+)'",
	"/(?P<revisionId>\\d+)/restore",
	'capture_session_update',
	'rest_restore_revision',
	'MAX_REVISIONS',
] ) {
	if ( ! historyService.includes( token ) ) {
		errors.push( `History service is missing ${ token }` );
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

for ( const token of [
	'cc-inspector-v2-tabs',
	'Full Width uses 100% of the parent container.',
	'Boxed is constrained by the Global container maximum width.',
	'cc-inspector-v2-section-toggle',
	'sessionStorage',
] ) {
	if ( ! inspectorV2.includes( token ) ) {
		errors.push( `Standalone Inspector v2 is missing ${ token }` );
	}
}

for ( const token of [
	'cc-ui-v3-panel-controls',
	'cc-ui-v3-left-drawer-open',
	'cc-ui-v3-right-drawer-open',
	'aria-expanded',
	'sessionStorage',
	'Escape',
] ) {
	if ( ! uiV3.includes( token ) ) {
		errors.push( `Standalone UI v3 is missing ${ token }` );
	}
}

for ( const token of [
	'settings.pageSettingsPath',
	'Page Settings',
	'Theme Default',
	'Full Width',
	'Canvas',
	'Full Viewport',
	'Save Page Settings',
	'cresco:page-settings-saved',
] ) {
	if ( ! pageSettingsRuntime.includes( token ) ) {
		errors.push( `Standalone Page Settings is missing ${ token }` );
	}
}

for ( const token of [
	'settings.historyPath',
	'History',
	'Actions',
	'Revisions',
	'No History Yet',
	'Apply',
	'/restore',
] ) {
	if ( ! historyRuntime.includes( token ) ) {
		errors.push( `Standalone History is missing ${ token }` );
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
	'assets/css/container-width.css',
	'assets/css/standalone-visual-editor.css',
	'assets/css/standalone-inspector-v2.css',
	'assets/css/standalone-ui-v3.css',
	'assets/css/standalone-page-settings.css',
	'assets/css/standalone-history.css',
	'assets/css/global-config-import.css',
	'assets/css/viewport-shell.css',
	'build/standalone-visual-editor.js',
	'build/standalone-visual-editor.asset.php',
	'build/standalone-inspector-v2.js',
	'build/standalone-ui-v3.js',
	'build/standalone-page-settings.js',
	'build/standalone-history.js',
	'build/global-config-import.js',
	'build/viewport-shell.js',
	'includes/Page/PageSettings.php',
	'includes/Page/canvas-template.php',
	'includes/Session/HistoryManager.php',
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
	'Checked the authoritative Cresco Session editor runtime, Inspector v2, UI v3, History, Page Settings, Global/viewport helpers, REST contract, AI interchange, dependencies, and package gates.\n'
);
