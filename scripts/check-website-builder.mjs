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
	professionalUxPhp,
	cssCompiler,
	responsiveResolver,
	renderer,
	history,
	plugin,
	editorSource,
	editorBuild,
	controlsSource,
	controlsBuild,
	controlsCss,
	professionalUxSource,
	professionalUxBuild,
	professionalUxCss,
	previewFitSource,
	previewFitBuild,
	frontendSource,
	frontendBuild,
	manifestText,
	releaseFiles,
	docs,
] = await Promise.all( [
	read( 'includes/Builder/WidgetCatalog.php' ),
	read( 'includes/Builder/WebsiteBuilder.php' ),
	read( 'includes/Builder/WebsiteBuilderCompatibility.php' ),
	read( 'includes/Builder/WebsiteBuilderProfessionalUx.php' ),
	read( 'includes/Builder/WebsiteBuilderCssCompiler.php' ),
	read( 'includes/Core/Responsive/ResponsiveResolver.php' ),
	read( 'includes/Builder/WebsiteRenderer.php' ),
	read( 'includes/Session/HistoryManager.php' ),
	read( 'includes/Plugin.php' ),
	read( 'runtime-src/build/website-builder-editor.js' ),
	read( 'build/website-builder-editor.js' ),
	read( 'runtime-src/build/website-builder-controls.js' ),
	read( 'build/website-builder-controls.js' ),
	read( 'assets/css/website-builder-controls.css' ),
	read( 'runtime-src/build/website-builder-professional-ux.js' ),
	read( 'build/website-builder-professional-ux.js' ),
	read( 'assets/css/website-builder-professional-ux.css' ),
	read( 'runtime-src/build/website-builder-preview-fit.js' ),
	read( 'build/website-builder-preview-fit.js' ),
	read( 'runtime-src/build/website-builder-frontend.js' ),
	read( 'build/website-builder-frontend.js' ),
	read( 'runtime-src/manifest.json' ),
	read( 'scripts/release-files.mjs' ),
	read( 'docs/WEBSITE_BUILDER_CORE.md' ),
] );

for ( const file of [
	'runtime-src/build/website-builder-editor.js',
	'build/website-builder-editor.js',
	'runtime-src/build/website-builder-controls.js',
	'build/website-builder-controls.js',
	'runtime-src/build/website-builder-professional-ux.js',
	'build/website-builder-professional-ux.js',
	'runtime-src/build/website-builder-preview-fit.js',
	'build/website-builder-preview-fit.js',
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
	"add_action( 'admin_footer', array( $this, 'render_editor_bootstrap_watchdog' ), 9999 )",
	"add_action( 'wp_enqueue_scripts', array( $this, 'replace_frontend_compiled_styles' ), 999 )",
	'replace_frontend_compiled_styles',
	'harden_editor_bootstrap',
	"hash_file( 'sha256'",
	"wp_localize_script( self::BUILDER_HANDLE, 'crescoWebsiteBuilderSettings'",
	'crescoBuilderRetry',
	'Website Builder request timed out:',
	'The Website Builder runtime loaded but did not mount.',
	'customCss',
	'customCSS',
	'website-builder-controls.js',
	'website-builder-controls.css',
] ) if ( ! compatibility.includes( token ) ) errors.push( `Website Builder compatibility boundary missing ${ token }` );

for ( const token of [
	"const SCRIPT_HANDLE",
	"const PREVIEW_SCRIPT_HANDLE",
	"add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ), 1001 )",
	"'build/website-builder-professional-ux.js'",
	"'assets/css/website-builder-professional-ux.css'",
	"'build/website-builder-preview-fit.js'",
	"array( 'cresco-canvas-website-builder-controls' )",
	"array( self::SCRIPT_HANDLE )",
	"VisualEditor::PAGE_SLUG",
	"current_user_can( 'edit_post', $post_id )",
	"hash_file( 'sha256'",
] ) if ( ! professionalUxPhp.includes( token ) ) errors.push( `Website Builder Professional UX loader missing ${ token }` );

// Breakpoint ownership was consolidated into ResponsiveResolver. The compiler
// must consume that shared contract rather than duplicate hard-coded ranges.
for ( const token of [
	'use CrescoCanvas\\Core\\Responsive\\ResponsiveResolver;',
	'ResponsiveResolver::OVERRIDE_DEVICES',
	'ResponsiveResolver::wrap(',
] ) if ( ! cssCompiler.includes( token ) ) errors.push( `Website Builder responsive compiler missing shared resolver contract ${ token }` );
for ( const token of [
	"const OVERRIDE_DEVICES = array( 'desktop', 'laptop', 'tablet', 'mobile' )",
	"'wide'",
	"'desktop'",
	"'laptop'",
	"'tablet'",
	"'mobile'",
	'public static function wrap',
] ) if ( ! responsiveResolver.includes( token ) ) errors.push( `ResponsiveResolver missing ${ token }` );

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
	'use CrescoCanvas\\Builder\\WebsiteBuilderProfessionalUx;',
	'( new WebsiteBuilder() )->register();',
	'( new WebsiteBuilderCompatibility() )->register();',
	'( new WebsiteBuilderProfessionalUx() )->register();',
] ) if ( ! plugin.includes( token ) ) errors.push( `Plugin registration missing ${ token }` );

for ( const token of [
	'cc-builder-widget-grid', 'Reusable Components', 'Theme Builder',
	'AI Website Workflow', 'Global Design', 'Page Settings', 'data-cresco-id',
	'activeState', 'selectedIds', 'startResize', 'Ctrl/Cmd+S',
] ) if ( ! editorSource.includes( token ) ) errors.push( `Website Builder editor missing ${ token }` );

for ( const token of [
	'cc-builder-unit-picker',
	'cc-builder-spacing-visual',
	'cc-builder-responsive-hint',
	'cc-builder-quick-toolbar',
	'cc-builder-inline-edit-popover',
	'cc-builder-smart-add-trigger',
	'cc-builder-dependency-badge',
	'WooCommerce required',
	'checkbox_group',
] ) if ( ! controlsSource.includes( token ) ) errors.push( `Website Builder professional controls missing ${ token }` );

for ( const token of [
	'.cc-builder-spacing-visual',
	'.cc-builder-quick-toolbar',
	'.cc-builder-inline-edit-popover',
	'.cc-builder-smart-add-trigger',
	'.cc-builder-dependency-badge',
	'prefers-reduced-motion',
	'focus-visible',
] ) if ( ! controlsCss.includes( token ) ) errors.push( `Website Builder controls CSS missing ${ token }` );

for ( const token of [
	'cc-builder-pro-segmented',
	'cc-builder-pro-inspector-search',
	'cc-builder-pro-responsive-tools',
	'Copy overrides ↓',
	'cc-builder-pro-quality',
	'cc-builder-pro-breadcrumb',
	'cc-builder-pro-context-menu',
	'cresco-builder-favorite-widgets-v1',
	'cc-builder-pro-library-filters',
	'cc-builder-pro-empty-start',
	'Editor diagnostics',
	'Autosave: On',
	'Layout presets',
	'Loop presets',
	'Focus mode enabled.',
] ) if ( ! professionalUxSource.includes( token ) ) errors.push( `Website Builder Professional UX runtime missing ${ token }` );

for ( const token of [
	'.cc-builder-pro-segmented',
	'.cc-builder-pro-inspector-search',
	'.cc-builder-pro-responsive-tools',
	'.cc-builder-pro-quality',
	'.cc-builder-pro-context-menu',
	'.cc-builder-pro-library-filters',
	'.cc-builder-pro-empty-start',
	'.cc-builder-pro-dialog',
	'.is-cresco-focus-mode',
	'forced-colors',
	'prefers-reduced-motion',
	'focus-visible',
] ) if ( ! professionalUxCss.includes( token ) ) errors.push( `Website Builder Professional UX CSS missing ${ token }` );

for ( const token of [
	'Fit Area',
	'data-cresco-fit-area',
	'data-cresco-preview-fit',
	'canvasMinHeight',
	'transformOrigin',
	'ResizeObserver',
] ) if ( ! previewFitSource.includes( token ) ) errors.push( `Website Builder preview fit runtime missing ${ token }` );

for ( const token of [ 'data-cresco-accordion', 'role="tab"', 'bootNavigation', 'aria-expanded', 'IntersectionObserver', 'cresco-builder-lightbox' ] ) {
	if ( ! frontendSource.includes( token ) ) errors.push( `Website Builder frontend runtime missing ${ token }` );
}

if ( hash( frontendSource ) !== hash( frontendBuild ) ) errors.push( 'Website Builder frontend build differs from authoritative source.' );
if ( hash( editorSource ) !== hash( editorBuild ) ) {
	errors.push( 'Website Builder editor build differs from authoritative source; checked-in admin runtime must be self-contained.' );
}
if ( hash( controlsSource ) !== hash( controlsBuild ) ) {
	errors.push( 'Website Builder professional controls build differs from authoritative source.' );
}
if ( hash( professionalUxSource ) !== hash( professionalUxBuild ) ) {
	errors.push( 'Website Builder Professional UX build differs from authoritative source.' );
}
if ( hash( previewFitSource ) !== hash( previewFitBuild ) ) {
	errors.push( 'Website Builder preview fit build differs from authoritative source.' );
}
if ( editorBuild.includes( '../runtime-src/' ) || editorBuild.includes( 'crescoBuilderSource' ) ) {
	errors.push( 'Website Builder editor build must not load runtime-src at browser runtime.' );
}

const manifest = JSON.parse( manifestText );
if ( ! manifest.reviewed.includes( 'website-builder-controls.js' ) ) errors.push( 'Runtime manifest does not review website-builder-controls.js.' );
if ( ! manifest.reviewed.includes( 'website-builder-frontend.js' ) ) errors.push( 'Runtime manifest does not review website-builder-frontend.js.' );
if ( ! manifest.reviewed.includes( 'website-builder-professional-ux.js' ) ) errors.push( 'Runtime manifest does not review website-builder-professional-ux.js.' );
if ( ! manifest.reviewed.includes( 'website-builder-preview-fit.js' ) ) errors.push( 'Runtime manifest does not review website-builder-preview-fit.js.' );
if ( manifest.generated?.[ 'website-builder-editor.js' ] !== 'runtime-src/build/website-builder-editor.js' ) errors.push( 'Runtime manifest does not own website-builder-editor.js.' );

for ( const token of [
	"'assets/css/website-builder.css'",
	"'assets/css/website-builder-controls.css'",
	"'assets/css/website-builder-frontend.css'",
	"'assets/css/website-builder-professional-ux.css'",
	"'build/website-builder-controls.js'",
	"'build/website-builder-editor.js'",
	"'build/website-builder-frontend.js'",
	"'build/website-builder-preview-fit.js'",
	"'build/website-builder-professional-ux.js'",
	"'docs/WEBSITE_BUILDER_CORE.md'",
] ) if ( ! releaseFiles.includes( token ) ) errors.push( `Release allowlist missing ${ token }` );

for ( const token of [ 'cresco-session/v1', '1,000 nodes', 'Reusable components', 'Theme Builder integration', 'Security boundaries' ] ) {
	if ( ! docs.includes( token ) ) errors.push( `Website Builder documentation missing ${ token }` );
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}
process.stdout.write( 'Website Builder Core, Professional UX V2, Preview Fit Area, visual controls, bootstrap recovery, shared responsive resolver, History compatibility, runtime ownership, package inventory, and integration tokens verified.\n' );
