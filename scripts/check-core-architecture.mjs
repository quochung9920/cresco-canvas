import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const errors = [];
const read = ( file ) => readFile( file, 'utf8' );
const hash = ( value ) => createHash( 'sha256' ).update( value ).digest( 'hex' );
const phpBinary = process.env.CRESCO_PHP_BINARY || ( process.platform === 'win32' ? 'C:\\xampp\\php\\php.exe' : 'php' );
const contractFiles = [
	'contracts/document/v1.schema.json',
	'contracts/scope/v1.schema.json',
	'contracts/command/v1.schema.json',
	'contracts/transaction/v1.schema.json',
	'contracts/ai-context/v2.schema.json',
];
for ( const file of contractFiles ) {
	try { JSON.parse( await read( file ) ); } catch ( error ) { errors.push( `${ file }: ${ error.message }` ); }
}
const phpFiles = [
	'includes/Core/Document/Document.php',
	'includes/Core/Scope/ScopeEngine.php',
	'includes/Core/Context/ContextEngine.php',
	'includes/Core/Command/CommandBus.php',
	'includes/Core/Command/TransactionManager.php',
	'includes/Core/Responsive/ResponsiveResolver.php',
	'includes/Core/Design/DesignSystemAnalyzer.php',
	'includes/Core/UI/UiRegistry.php',
	'includes/Core/UI/InspectorSchema.php',
	'includes/Core/Widget/WidgetRegistry.php',
	'includes/Core/Storage/DocumentRepository.php',
	'includes/Core/Module/ModuleRegistry.php',
	'includes/Infrastructure/WordPress/Storage/WordPressDocumentRepository.php',
	'includes/Styles/StyleCascade.php',
	'includes/Builder/ComponentStyleCompiler.php',
	'includes/Builder/WebsiteBuilderCorePlatform.php',
	'includes/Builder/WebsiteBuilderCssCompiler.php',
	'includes/Builder/WidgetPartStyleCompiler.php',
	'includes/Rendering/RenderEngine.php',
	'includes/Application/BuilderArchitecture.php',
	'includes/AI/ContractRegistry.php',
	'includes/AI/ContextBuilder.php',
	'includes/AI/PatchValidator.php',
	'includes/Builder/WebsiteBuilderRendererParity.php',
	'includes/Builder/WebsiteBuilderStabilization.php',
	'includes/Session/HistoryManager.php',
];
for ( const file of phpFiles ) {
	const result = spawnSync( phpBinary, [ '-l', file ], { encoding: 'utf8' } );
	if ( result.error ) {
		errors.push( `${ file }: unable to execute PHP syntax checker at ${ phpBinary }: ${ result.error.message }` );
		continue;
	}
	if ( result.status !== 0 ) errors.push( `${ file }: ${ result.stderr || result.stdout || 'PHP syntax check failed' }` );
}
const source = await read( 'runtime-src/build/website-builder-architecture.js' );
const build = await read( 'build/website-builder-architecture.js' );
for ( const file of [ 'runtime-src/build/website-builder-architecture.js', 'build/website-builder-architecture.js', 'scripts/check-core-architecture.mjs' ] ) {
	const result = spawnSync( process.execPath, [ '--check', file ], { encoding: 'utf8' } );
	if ( result.status !== 0 ) errors.push( `${ file }: ${ result.stderr || result.stdout || 'JavaScript syntax check failed' }` );
}
if ( hash( source ) !== hash( build ) ) errors.push( 'Architecture runtime source/build mismatch.' );
const phpCorpus = ( await Promise.all( phpFiles.map( read ) ) ).join( '\n' );
for ( const token of [ 'cresco-ai-context/v2', 'ScopeEngine', 'CommandBus', 'TransactionManager', 'ResponsiveResolver', 'DesignSystemAnalyzer', 'InspectorSchema', 'StyleCascade', 'RenderEngine', 'UiRegistry', 'WidgetRegistry', 'DocumentRepository', 'ModuleRegistry', 'cresco-command/v1', 'cresco-transaction/v1', 'cresco_command_scope_mismatch' ] ) {
	if ( ! phpCorpus.includes( token ) ) errors.push( `Architecture PHP missing ${ token }` );
}
for ( const token of [ 'metaKey||e.ctrlKey', 'crescoBuilderArchitecture', 'addCommand', 'data-cresco-zone', 'Scoped AI', 'Authoritative Renderer Preview', 'Cresco Documents', 'selection' ] ) {
	if ( ! source.includes( token ) ) errors.push( `Architecture runtime missing ${ token }` );
}

const contractRegistry = await read( 'includes/AI/ContractRegistry.php' );
if ( ! contractRegistry.includes( 'WidgetCatalog::all()' ) ) errors.push( 'AI ContractRegistry must use the canonical Website Builder widget catalog.' );
if ( contractRegistry.includes( 'SessionManager::widget_catalog()' ) ) errors.push( 'AI ContractRegistry regressed to the legacy Session widget catalog.' );
for ( const token of [ 'validate_states_map', 'WebsiteBuilder::sanitize_custom_css', "'json' === $kind", "'bool' === $kind" ] ) {
	if ( ! contractRegistry.includes( token ) ) errors.push( `AI ContractRegistry missing extended contract support ${ token }` );
}

const patchValidator = await read( 'includes/AI/PatchValidator.php' );
for ( const token of [ 'WebsiteBuilder::sanitize_session', 'Document::checksum', 'WebsiteBuilder::sanitize_custom_css' ] ) {
	if ( ! patchValidator.includes( token ) ) errors.push( `PatchValidator missing canonical boundary ${ token }` );
}
if ( patchValidator.includes( 'SessionManager::sanitize_session' ) ) errors.push( 'PatchValidator regressed to legacy Session sanitization.' );

const commandBus = await read( 'includes/Core/Command/CommandBus.php' );
for ( const token of [ 'Document::checksum', "'node.group'", "'node.duplicate'", "'responsive.reset'", "'state.update'", "'ai.applyPatch'" ] ) {
	if ( ! commandBus.includes( token ) ) errors.push( `CommandBus missing canonical command support ${ token }` );
}
if ( commandBus.includes( 'use CrescoCanvas\\AI\\ContextBuilder;' ) ) errors.push( 'CommandBus must not depend on AI ContextBuilder for checksums.' );
const transactions = await read( 'includes/Core/Command/TransactionManager.php' );
for ( const token of [ 'cresco-transaction/v1', 'CommandBus::preview', 'DiffEngine::compare', 'beforeChecksum', 'afterChecksum' ] ) {
	if ( ! transactions.includes( token ) ) errors.push( `TransactionManager missing ${ token }` );
}
const styleCascade = await read( 'includes/Styles/StyleCascade.php' );
for ( const token of [ "const BREAKPOINTS = array( 'wide', 'desktop', 'laptop', 'tablet', 'mobile' )", "const SOURCES     = array( 'token', 'global', 'component', 'local' )", 'previousBreakpoint', 'explicitAtCurrent', "return 'clamp('" ] ) {
	if ( ! styleCascade.includes( token ) ) errors.push( `StyleCascade missing ${ token }` );
}

const responsive = await read( 'includes/Core/Responsive/ResponsiveResolver.php' );
for ( const token of [ "const DEVICES = array( 'wide', 'desktop', 'laptop', 'tablet', 'mobile' )", "'desktop' => $bp['wide'] - 1", "'mobile'  => $bp['tablet'] - 1", 'effective_style', "'schema'      => 'cresco-responsive/v2'" ] ) {
	if ( ! responsive.includes( token ) ) errors.push( `ResponsiveResolver missing ${ token }` );
}
const rootCompiler = await read( 'includes/Builder/WebsiteBuilderCssCompiler.php' );
const partCompiler = await read( 'includes/Builder/WidgetPartStyleCompiler.php' );
for ( const compiler of [ rootCompiler, partCompiler ] ) {
	if ( ! compiler.includes( 'ResponsiveResolver::wrap' ) ) errors.push( 'All style compilers must use ResponsiveResolver::wrap.' );
}
if ( partCompiler.includes( 'max_width_for_device' ) ) errors.push( 'Part-style compiler still owns a duplicate breakpoint resolver.' );

const renderEngine = await read( 'includes/Rendering/RenderEngine.php' );
for ( const token of [ 'WebsiteBuilderArchitectureV2::load_document', 'WebsiteRendererV2::render_document', 'WebsiteBuilderRendererParity::repair_document_html', 'WidgetPartStyleCompiler::compile', 'ComponentStyleCompiler::compile', "'renderOwner'  => 'RenderEngine/v2'" ] ) {
	if ( ! renderEngine.includes( token ) ) errors.push( `RenderEngine missing canonical v2 boundary ${ token }` );
}
if ( renderEngine.includes( 'WebsiteRenderer::render_document' ) ) errors.push( 'RenderEngine regressed to the legacy renderer.' );

const rendererParity = await read( 'includes/Builder/WebsiteBuilderRendererParity.php' );
for ( const token of [ 'public static function repair_document_html', '<!-- wp:cresco/form-field ' ] ) {
	if ( ! rendererParity.includes( token ) ) errors.push( `Renderer parity missing ${ token }` );
}

const corePlatform = await read( 'includes/Builder/WebsiteBuilderCorePlatform.php' );
for ( const token of [ "const SCHEMA = 'cresco-builder-core/v2'", "const STYLE_CONTRACT = 'authoritative-v5'", 'prune_legacy_frontend_hooks', '/website-builder/session/', '/website-builder/theme-session/', '/website-builder/components', '/website-builder/architecture-v2/', '/page-settings/', '/website-builder/theme-page-settings/', '/website-builder/transactions/', '/website-builder/system-status/', 'TransactionManager::preview', 'WordPressDocumentRepository', 'wp_slash( $json )', 'ResponsiveResolver::manifest', 'DesignSystemAnalyzer::manifest', 'InspectorSchema::manifest', 'index=new Map()', 'ThemeSessionBridge::block_markup', 'render_theme_preview', "'privacySafe'   => true" ] ) {
	if ( ! corePlatform.includes( token ) ) errors.push( `Core Platform v2 missing ${ token }` );
}
const inspector = await read( 'includes/Core/UI/InspectorSchema.php' );
if ( ! inspector.includes( 'WidgetCatalog::all()' ) || ! inspector.includes( 'cresco-inspector/v2' ) ) errors.push( 'Inspector v2 is not catalog-driven.' );
const design = await read( 'includes/Core/Design/DesignSystemAnalyzer.php' );
if ( ! design.includes( 'DesignTokens::catalog' ) || ! design.includes( 'cresco-design-system/v2' ) ) errors.push( 'Design System v2 contract is incomplete.' );

const stabilization = await read( 'includes/Builder/WebsiteBuilderStabilization.php' );
for ( const token of [ "'wp-components'", '/website-builder/theme-page-settings/', "document.querySelector('.cc-builder-pro-context-menu')", "version:'stability-v1'", 'buffer_theme_preview', 'enqueue_theme_form_assets' ] ) {
	if ( ! stabilization.includes( token ) ) errors.push( `Website Builder stabilization missing ${ token }` );
}
const professionalUx = await read( 'includes/Builder/WebsiteBuilderProfessionalUx.php' );
if ( ! professionalUx.includes( '( new WebsiteBuilderStabilization() )->register();' ) ) errors.push( 'Professional UX does not register the stabilization boundary.' );

const history = await read( 'includes/Session/HistoryManager.php' );
for ( const token of [ '/website-builder/theme-history/', 'ThemeBuilder::POST_TYPE', "register_rest_route( 'cresco-canvas/v1', '/website-builder/theme-history/" ] ) {
	if ( ! history.includes( token ) ) errors.push( `History service missing Theme document support ${ token }` );
}
const repository = await read( 'includes/Infrastructure/WordPress/Storage/WordPressDocumentRepository.php' );
if ( ! repository.includes( "'header', 'footer', 'single', 'page', 'archive', 'search', '404'" ) ) errors.push( 'WordPress document type mapping must preserve Theme page templates.' );
for ( const token of [ 'public function checksum( $document_id )', 'public function verify( $document_id, $expected_checksum )', "'cresco_document_storage_verify'", 'wp_slash( $json )' ] ) if ( ! repository.includes( token ) ) errors.push( `Document repository missing ${ token }` );

const plugin = await read( 'includes/Plugin.php' );
if ( ! plugin.includes( 'BuilderArchitecture' ) || ! plugin.includes( '( new BuilderArchitecture() )->register();' ) ) errors.push( 'Plugin does not register BuilderArchitecture.' );
if ( ! plugin.includes( 'WebsiteBuilderCorePlatform' ) || ! plugin.includes( '( new WebsiteBuilderCorePlatform() )->register();' ) ) errors.push( 'Plugin does not register Core Platform v2.' );
const release = await read( 'scripts/release-files.mjs' );
for ( const token of [ "'docs/CORE_ARCHITECTURE.md'", "'assets/css/website-builder-architecture.css'", "'build/website-builder-architecture.js'", "walkFiles( root, 'contracts'" ] ) if ( ! release.includes( token ) ) errors.push( `Release inventory missing ${ token }` );
const manifest = JSON.parse( await read( 'runtime-src/manifest.json' ) );
if ( ! manifest.reviewed.includes( 'website-builder-architecture.js' ) ) errors.push( 'Runtime manifest does not review website-builder-architecture.js.' );
const packageJson = JSON.parse( await read( 'package.json' ) );
if ( packageJson.scripts?.[ 'check:architecture' ] !== 'node scripts/check-core-architecture.mjs' ) errors.push( 'package.json is missing check:architecture.' );
if ( ! String( packageJson.scripts?.[ 'check:quality' ] || '' ).includes( 'check:architecture' ) ) errors.push( 'check:quality does not include check:architecture.' );
const docs = await read( 'docs/CORE_ARCHITECTURE.md' );
for ( const token of [ 'One document model', 'One mutation path', 'Scoped AI', 'Editor UX shell', 'Compatibility policy' ] ) if ( ! docs.includes( token ) ) errors.push( `Architecture documentation missing ${ token }` );
if ( /ComprehensiveV4|ProfessionalUxV4|WebsiteBuilderV4/.test( source + docs + corePlatform ) ) errors.push( 'Architecture consolidation must not introduce a V4 builder layer.' );
if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}
process.stdout.write( `Cresco Core Platform v2 verified with PHP binary ${ phpBinary }: canonical transactions, shared responsive resolver, schema Inspector, Design System usage, unified V2 rendering, persistence verification, AI validation, Theme history/settings, release ownership, and architecture runtime.\n` );
