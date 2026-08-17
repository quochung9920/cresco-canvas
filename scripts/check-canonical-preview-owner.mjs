import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root = process.cwd();
const errors = [];
// The repository has no `.gitattributes` and Git for Windows checks out with
// `core.autocrlf=true`, so every file here is CRLF on a Windows clone. The
// registration token below is matched across a line break and the runtime
// heredoc is matched with a `\n`-anchored regex, both of which fail on CRLF for
// reasons that have nothing to do with the code being checked. Normalise on
// read so this gate reports the same result on every platform.
const read = ( relative ) =>
	fs.readFileSync( path.join( root, relative ), 'utf8' ).replace( /\r\n/g, '\n' );
const expect = ( source, token, label ) => {
	if ( ! source.includes( token ) ) errors.push( `${ label } missing ${ token }` );
};
const reject = ( source, token, label ) => {
	if ( source.includes( token ) ) errors.push( `${ label } must not contain ${ token }` );
};

const ownerPath = 'includes/Builder/WebsiteBuilderCanonicalPreviewOwner.php';
const renderPath = 'includes/Rendering/RenderEngine.php';
const pluginPath = 'includes/Plugin.php';
for ( const file of [ ownerPath, renderPath, pluginPath ] ) {
	if ( ! fs.existsSync( path.join( root, file ) ) ) errors.push( `Missing ${ file }` );
}

const owner = errors.length ? '' : read( ownerPath );
const render = errors.length ? '' : read( renderPath );
const plugin = errors.length ? '' : read( pluginPath );

for ( const token of [
	"add_action( 'admin_enqueue_scripts', array( $this, 'claim_visual_ownership' ), 1490 )",
	"WebsiteBuilderVisualParity::class, 'enqueue_editor_parity'",
	'window.crescoCanonicalBootstrap=',
	'RenderEngine::render( $session, $context->post_id(), $context->document_type() )',
	"'session'         => is_array( $session ) ? $session : null",
	"'responsive'      => ResponsiveResolver::manifest()",
	"'tokens'          => DesignTokens::catalog( GlobalStyles::get_settings() )",
	'.cc-studio-frame>.cc-studio-canvas{display:none!important;visibility:hidden!important;pointer-events:none!important;}',
	'.cc-studio-frame>.cc-studio-canonical-preview{display:block!important;width:100%;min-height:720px;border:0;background:#fff;opacity:1;pointer-events:auto;}',
	'function applyLiveSession(session)',
	'function compileLiveCSS(session)',
	'function applyServerRender(render,session)',
	'function scheduleReconcile(session,force)',
	"WIDGET_MIME='application/x-cresco-studio-widget'",
	'function canonicalDropDescriptor(event)',
	'function showDropIndicator(desc)',
	'function createWidgetInParent(type,parentId)',
	'function moveStructureNode(sourceId,targetId,zone)',
	'function insertWidgetAtDescriptor(type,desc)',
	'function handleCanonicalDrag(event,type)',
	"mode:'canonical-realtime'",
	'legacyVisualFallback:false',
	'realtime:true',
	'iframeReloadOnEdit:false',
	'canonicalWidgetDrop:true',
	"window.crescoCanonicalEditorPreview=window.crescoCanonicalVisualOwner",
] ) expect( owner, token, ownerPath );

for ( const token of [
	'function showLegacy',
	"classList.add('is-cresco-canonical-drag')",
	'legacyVisualFallback:true',
	"setState('loading','Rendering preview')",
] ) reject( owner, token, ownerPath );

for ( const token of [
	"'rootCss'      => $css_parts['rootCss']",
	"'stableCss'    => $css_parts['stableCss']",
	'private static function compile_css_parts',
] ) expect( render, token, renderPath );

expect( plugin, 'use CrescoCanvas\\Builder\\WebsiteBuilderCanonicalPreviewOwner;', pluginPath );
expect( plugin, '( new WebsiteBuilderVisualParity() )->register();\n\t\t\t( new WebsiteBuilderCanonicalPreviewOwner() )->register();', pluginPath );

const scriptMatch = owner.match( /return <<<'JS'\n([\s\S]*?)\nJS;/ );
if ( ! scriptMatch ) errors.push( `${ ownerPath } canonical runtime heredoc is missing.` );
else {
	try {
		new vm.Script( scriptMatch[ 1 ], { filename: 'canonical-preview-owner.js' } );
	} catch ( error ) {
		errors.push( `${ ownerPath } canonical runtime JS syntax failed: ${ error.message }` );
	}
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( '[canonical-preview-owner] Studio prehydrates one canonical iframe, applies Session edits locally, accepts positioned widget drops with a visible indicator, reconciles RenderEngine in the background, and never exposes the legacy visual.\n' );
