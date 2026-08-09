import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const errors = [];
const hash = ( value ) => createHash( 'sha256' ).update( value ).digest( 'hex' );
const read = ( file ) => readFile( file, 'utf8' );

const [
	catalog,
	builder,
	compatibility,
	cssCompiler,
	renderer,
	history,
	plugin,
	editorSource,
	editorBuild,
	frontendSource,
	frontendBuild,
	manifestText,
	releaseFiles,
	docs,
] = await Promise.all( [
	read( 'includes/Builder/WidgetCatalog.php' ),
	read( 'includes/Builder/WebsiteBuilder.php' ),
	read( 'includes/Builder/WebsiteBuilderCompatibility.php' ),
	read( 'includes/Builder/WebsiteBuilderCssCompiler.php' ),
	read( 'includes/Builder/WebsiteRenderer.php' ),
	read( 'includes/Session/HistoryManager.php' ),
	read( 'includes/Plugin.php' ),
	read( 'runtime-src/build/website-builder-editor.js' ),
	read( 'build/website-builder-editor.js' ),
	read( 'runtime-src/build/website-builder-frontend.js' ),
	read( 'build/website-builder-frontend.js' ),
	read( 'runtime-src/manifest.json' ),
	read( 'scripts/release-files.mjs' ),
	read( 'docs/WEBSITE_BUILDER_CORE.md' ),
] );

for ( const file of [
	'runtime-src/build/website-builder-editor.js',
	'build/website-builder-editor.js',
	'runtime-src/build/website-builder-frontend.js',
	'build/website-builder-frontend.js',
	'scripts/build-runtime.mjs',
	'scripts/release-files.mjs',
] ) {
	const result = spawnSync( process.execPath, [ '--check', file ], { encoding: 'utf8' } );
	if ( result.status !== 0 ) errors.push( `${ file }: ${ result.stderr || result.stdout || 'syntax check failed' }` );
}

const catalogTokens = [
	"'container'", "'accordion'", "'tabs'", "'gallery'", "'nav-menu'", "'breadcrumbs'",
	"'dynamic-field'", "'loop-grid'", "'form'", "'woo-products'", "'woo-add-to-cart'",
	"'flexGrow'", "'gridTemplateRows'", "'transform'", "'filter'",
];
for ( const token of catalogTokens ) if ( ! catalog.includes( token ) ) errors.push( `Widget catalog missing ${ token }` );

for ( const token of [
	"const BUILDER_VERSION = 'website-core/v1'",
	"const MAX_NODES       = 1000",
	"const MAX_DEPTH       = 16",
	"'/website-builder/session/(?P<postId>\\d+)'",
	"'/website-builder/components'",
	"'states'     => self::sanitize_states",
	"'meta'       => self::sanitize_meta",
	"wp_dequeue_script",
	"build/website-builder-editor.js",
	"build/website-builder-frontend.js",
] ) if ( ! builder.includes( token ) ) errors.push( `WebsiteBuilder missing ${ token }` );

for ( const token of [
	"'cresco-canvas-standalone-ai-bridge'",
	"'cresco-canvas-standalone-visual-editor'",
	"add_action( 'admin_enqueue_scripts', array( $this, 'remove_legacy_editor_assets' ), 999 )",
	"add_action( 'wp_enqueue_scripts', array( $this, 'replace_frontend_compiled_styles' ), 999 )",
	'replace_frontend_compiled_styles',
	'customCss',
	'customCSS',
] ) if ( ! compatibility.includes( token ) ) errors.push( `Website Builder compatibility boundary missing ${ token }` );

for ( const token of [ 'wrap_range', "'mobile'  => array( 0, $tablet - 1 )", "'tablet'  => array( $tablet, $laptop - 1 )", "'desktop' => array( $desktop, $wide - 1 )" ] ) {
	if ( ! cssCompiler.includes( token ) ) errors.push( `Website Builder responsive compiler missing ${ token }` );
}

for ( const token of [
	'render_loop_grid', 'render_dynamic_field', 'render_form', 'render_woo_products',
	'render_accordion', 'render_tabs', 'compile_css', 'resolve_token', 'scope_custom_css',
] ) if ( ! renderer.includes( token ) ) errors.push( `WebsiteRenderer missing ${ token }` );

for ( const token of [
	'use CrescoCanvas\\Builder\\WebsiteBuilder;',
	'WebsiteBuilder::sanitize_session',
	'uses_builder_contract',
	'WebsiteBuilder::BUILDER_META',
] ) if ( ! history.includes( token ) ) errors.push( `History is not Website Builder aware: ${ token }` );

for ( const token of [
	'use CrescoCanvas\\Builder\\WebsiteBuilder;',
	'use CrescoCanvas\\Builder\\WebsiteBuilderCompatibility;',
	'( new WebsiteBuilder() )->register();',
	'( new WebsiteBuilderCompatibility() )->register();',
] ) if ( ! plugin.includes( token ) ) errors.push( `Plugin registration missing ${ token }` );

for ( const token of [
	'cc-builder-widget-grid', 'Reusable Components', 'Theme Builder',
	'AI Website Workflow', 'Global Design', 'Page Settings', 'data-cresco-id',
	'activeState', 'selectedIds', 'startResize', 'Ctrl/Cmd+S',
] ) if ( ! editorSource.includes( token ) ) errors.push( `Website Builder editor missing ${ token }` );

for ( const token of [ 'data-cresco-accordion', 'role="tab"', 'bootNavigation', 'aria-expanded', 'IntersectionObserver', 'cresco-builder-lightbox' ] ) {
	if ( ! frontendSource.includes( token ) ) errors.push( `Website Builder frontend runtime missing ${ token }` );
}

if ( hash( frontendSource ) !== hash( frontendBuild ) ) errors.push( 'Website Builder frontend build differs from authoritative source.' );
if ( hash( editorSource ) !== hash( editorBuild ) ) {
	errors.push( 'Website Builder editor build differs from authoritative source; checked-in admin runtime must be self-contained.' );
}
if ( editorBuild.includes( '../runtime-src/' ) || editorBuild.includes( 'crescoBuilderSource' ) ) {
	errors.push( 'Website Builder editor build must not load runtime-src at browser runtime.' );
}

const manifest = JSON.parse( manifestText );
if ( ! manifest.reviewed.includes( 'website-builder-frontend.js' ) ) errors.push( 'Runtime manifest does not review website-builder-frontend.js.' );
if ( manifest.generated?.[ 'website-builder-editor.js' ] !== 'runtime-src/build/website-builder-editor.js' ) errors.push( 'Runtime manifest does not own website-builder-editor.js.' );

for ( const token of [
	"'assets/css/website-builder.css'",
	"'assets/css/website-builder-frontend.css'",
	"'build/website-builder-editor.js'",
	"'build/website-builder-frontend.js'",
	"'docs/WEBSITE_BUILDER_CORE.md'",
] ) if ( ! releaseFiles.includes( token ) ) errors.push( `Release allowlist missing ${ token }` );

for ( const token of [ 'cresco-session/v1', '1,000 nodes', 'Reusable components', 'Theme Builder integration', 'Security boundaries' ] ) {
	if ( ! docs.includes( token ) ) errors.push( `Website Builder documentation missing ${ token }` );
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}
process.stdout.write( 'Website Builder Core contract, responsive compiler, History compatibility, runtime ownership, package inventory, and integration tokens verified.\n' );
