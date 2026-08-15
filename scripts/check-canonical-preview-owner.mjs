import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root = process.cwd();
const errors = [];
const read = ( relative ) => fs.readFileSync( path.join( root, relative ), 'utf8' );
const expect = ( source, token, label ) => {
	if ( ! source.includes( token ) ) errors.push( `${ label } missing ${ token }` );
};
const reject = ( source, token, label ) => {
	if ( source.includes( token ) ) errors.push( `${ label } must not contain ${ token }` );
};

const ownerPath = 'includes/Builder/WebsiteBuilderCanonicalPreviewOwner.php';
const pluginPath = 'includes/Plugin.php';
if ( ! fs.existsSync( path.join( root, ownerPath ) ) ) errors.push( `Missing ${ ownerPath }` );
if ( ! fs.existsSync( path.join( root, pluginPath ) ) ) errors.push( `Missing ${ pluginPath }` );

const owner = errors.length ? '' : read( ownerPath );
const plugin = errors.length ? '' : read( pluginPath );

for ( const token of [
	"add_action( 'admin_enqueue_scripts', array( $this, 'claim_visual_ownership' ), 1490 )",
	"WebsiteBuilderVisualParity::class, 'enqueue_editor_parity'",
	'.cc-studio-frame>.cc-studio-canvas{display:none!important;visibility:hidden!important;pointer-events:none!important;}',
	'.cc-studio-frame.is-cresco-canonical-ready>.cc-studio-canonical-preview{opacity:1;pointer-events:auto;}',
	"setState('loading','Rendering preview…')",
	"setState('error','Renderer unavailable. Retry to continue editing.')",
	"mode:'canonical-only'",
	'legacyVisualFallback:false',
	"window.crescoCanonicalEditorPreview=window.crescoCanonicalVisualOwner",
] ) expect( owner, token, ownerPath );

for ( const token of [
	'function showLegacy',
	"classList.add('is-cresco-canonical-drag')",
	'legacyVisualFallback:true',
] ) reject( owner, token, ownerPath );

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

process.stdout.write( '[canonical-preview-owner] Studio has one canonical visual renderer, no legacy visual fallback, loading/error stay on the canonical surface, and the runtime JS parses successfully.\n' );
