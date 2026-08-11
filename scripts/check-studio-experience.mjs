import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const root = process.cwd();
const errors = [];
const read = ( relative ) => fs.readFileSync( path.join( root, relative ), 'utf8' );
const exists = ( relative ) => fs.existsSync( path.join( root, relative ) );
const expect = ( relative, token ) => {
	if ( ! exists( relative ) ) return errors.push( `Missing ${ relative }` );
	if ( ! read( relative ).includes( token ) ) errors.push( `${ relative } missing ${ token }` );
};
const reject = ( relative, token ) => {
	if ( ! exists( relative ) ) return;
	if ( read( relative ).includes( token ) ) errors.push( `${ relative } must not contain ${ token }` );
};
const hash = ( value ) => crypto.createHash( 'sha256' ).update( value ).digest( 'hex' );

const required = [
	'includes/Builder/WebsiteBuilderStudio.php',
	'includes/Builder/WebsiteBuilderRuntimeOwner.php',
	'includes/Builder/WebsiteBuilderConcurrencyGuard.php',
	'includes/Builder/WebsiteBuilderSessionIsolation.php',
	'includes/Security/PublicRequestAtomicity.php',
	'includes/Theme/ThemePageSettingsBridge.php',
	'build/website-builder-studio.js',
	'runtime-src/build/website-builder-studio.js',
	'build/website-builder-bootstrap.js',
	'runtime-src/build/website-builder-bootstrap.js',
	'build/website-builder-responsive-properties.js',
	'runtime-src/build/website-builder-responsive-properties.js',
	'build/website-builder-pointer-drag.js',
	'runtime-src/build/website-builder-pointer-drag.js',
	'build/website-builder-consistency-guard.js',
	'runtime-src/build/website-builder-consistency-guard.js',
	'assets/css/website-builder-studio.css',
	'tests/e2e/studio-hardening.spec.ts',
	'tests/php/StudioHardeningTest.php',
];
for ( const relative of required ) if ( ! exists( relative ) ) errors.push( `Missing Studio file: ${ relative }` );
for ( const retired of [ 'build/website-builder-structure-row-drag.js', 'runtime-src/build/website-builder-structure-row-drag.js' ] ) {
	if ( exists( retired ) ) errors.push( `Retired duplicate drag runtime must be deleted: ${ retired }` );
}

for ( const [ built, source, message ] of [
	[ 'build/website-builder-studio.js', 'runtime-src/build/website-builder-studio.js', 'Studio runtime source/build parity failed.' ],
	[ 'build/website-builder-bootstrap.js', 'runtime-src/build/website-builder-bootstrap.js', 'Bootstrap runtime source/build parity failed.' ],
	[ 'build/website-builder-responsive-properties.js', 'runtime-src/build/website-builder-responsive-properties.js', 'Responsive runtime source/build parity failed.' ],
	[ 'build/website-builder-pointer-drag.js', 'runtime-src/build/website-builder-pointer-drag.js', 'Pointer drag source/build parity failed.' ],
	[ 'build/website-builder-consistency-guard.js', 'runtime-src/build/website-builder-consistency-guard.js', 'Consistency guard source/build parity failed.' ],
] ) {
	if ( exists( built ) && exists( source ) && hash( read( built ) ) !== hash( read( source ) ) ) errors.push( message );
}

for ( const token of [
	'cc-studio-app cc-builder-app',
	'cresco:studio-ready',
	'window.crescoStudioDiagnostics',
	'window.CrescoStudioSDK',
	'function moveNode(nodes,sourceId,targetId,zone,catalog)',
	"updateNodes(moveNode(nodes,drag.sourceId,desc.targetId,desc.zone,catalog),'structure-move')",
	"'data-cresco-node-id':node.id",
	'dragExpandTimer.current = window.setTimeout',
	'BroadcastChannel',
	'AUTO_SAVE_KEY',
	'selection-subtrees',
] ) expect( 'runtime-src/build/website-builder-studio.js', token );
reject( 'runtime-src/build/website-builder-studio.js', "setPageSettings(n)}})})),h('select'" );
reject( 'runtime-src/build/website-builder-studio.js', "x.version||''))))))}" );

for ( const token of [
	'window.crescoStudioDragMove',
	"engine:'canvas-pointer-core-dispatch'",
	"transport:'core-only'",
	"root.addEventListener('pointerdown'",
	"window.addEventListener('pointermove'",
	"window.addEventListener('pointerup'",
	'dispatchCore',
	'crescoForwarded',
	'application/x-cresco-studio-node',
	'function canvasManaged(event)',
] ) expect( 'runtime-src/build/website-builder-pointer-drag.js', token );
for ( const token of [ 'fallbackMove', 'moveTree', "method:'POST'", 'refreshSession' ] ) reject( 'runtime-src/build/website-builder-pointer-drag.js', token );

for ( const token of [
	'window.crescoStudioConsistencyGuard',
	'baseChecksum',
	'cresco_save_superseded',
	'cresco:studio-session-change',
	'state.revision++',
] ) expect( 'runtime-src/build/website-builder-consistency-guard.js', token );

for ( const token of [
	"const SCRIPT             = 'build/website-builder-studio.js'",
	"const STYLE              = 'assets/css/website-builder.css'",
	"const CONSISTENCY_SCRIPT = 'build/website-builder-consistency-guard.js'",
	'retire_historical_editor_enqueue_callbacks',
	'dequeue_retired_presentation',
	"WebsiteBuilder::class, 'enqueue_editor'",
	"ThemeSessionBridge::class, 'enqueue_editor'",
	"'cresco-canvas-standalone-visual-editor'",
	"'cresco-canvas-editor-experience-v2'",
	'retire_legacy_admin_runtime',
	'retire_observer_monkeypatch',
	'wp_enqueue_media',
	'GlobalStyles::css',
	"WebsiteBuilderAsset::url( self::SCRIPT )",
	"runtimeTransport' => 'direct-content-addressed-asset'",
	"legacyEditorEnqueue' => false",
	"retiredPresentation' => true",
	'window.crescoCanonicalRuntimeOwner=',
] ) expect( 'includes/Builder/WebsiteBuilderRuntimeOwner.php', token );
for ( const token of [ 'wp_ajax_', 'serve_runtime', 'syntax-repair', "CRESCO_CANVAS_URL . 'build/website-builder-editor.js'" ] ) reject( 'includes/Builder/WebsiteBuilderRuntimeOwner.php', token );

for ( const token of [
	'.cc-studio-meta-grid{display:none!important}',
	'.cc-studio-tree-actions{display:none!important',
	"legacyStructureAdapter:false",
	"inspectorManagementRemoved:false",
] ) expect( 'includes/Builder/WebsiteBuilderStudio.php', token );
for ( const token of [ 'cc-cresco-legacy-tree-actions', 'startLegacyRename', 'toggleLegacy', 'node.remove()', 'new MutationObserver' ] ) reject( 'includes/Builder/WebsiteBuilderStudio.php', token );

for ( const token of [
	"'cresco_builder_precondition_required'",
	"'cresco_builder_conflict'",
	"'status' => 428",
	"'status' => 409",
	'hash_equals',
] ) expect( 'includes/Builder/WebsiteBuilderConcurrencyGuard.php', token );
for ( const token of [
	"'/cresco-canvas/v1/session/(\\d+)'",
	"'cresco_legacy_session_write_blocked'",
	'WebsiteBuilder::BUILDER_META',
] ) expect( 'includes/Builder/WebsiteBuilderSessionIsolation.php', token );

for ( const token of [
	"add_filter( 'rest_pre_dispatch', array( $this, 'acquire' ), 4, 3 )",
	"add_filter( 'rest_pre_dispatch', array( $this, 'release' ), 6, 3 )",
	'add_option( $key, $value',
	'cresco_request_busy',
	'idempotency_key_for_request',
	'request_identity',
] ) expect( 'includes/Security/PublicRequestAtomicity.php', token );
expect( 'includes/Plugin.php', 'new PublicRequestAtomicity()' );
expect( 'includes/Plugin.php', 'new WebsiteBuilderSessionIsolation()' );
expect( 'includes/Plugin.php', 'new WebsiteBuilderConcurrencyGuard()' );
expect( 'includes/Plugin.php', 'new ThemePageSettingsBridge()' );

for ( const token of [
	"'/website-builder/theme-page-settings/(?P<postId>\\d+)'",
	'PageSettings::sanitize_page_custom_css',
	'update_post_meta( $post_id, PageSettings::META_KEY, $json )',
] ) expect( 'includes/Theme/ThemePageSettingsBridge.php', token );

expect( 'includes/Builder/WebsiteBuilderRuntimeContext.php', "current_user_can( 'manage_options' )" );
expect( 'includes/Builder/WebsiteBuilderModuleRegistry.php', 'build/website-builder-pointer-drag.js' );
reject( 'includes/Builder/WebsiteBuilderModuleRegistry.php', 'website-builder-structure-row-drag.js' );
expect( 'runtime-src/build/website-builder-bootstrap.js', 'if(path===paths.pageSettings)return{critical:true' );
reject( 'runtime-src/build/website-builder-bootstrap.js', "if(path===paths.pageSettings)return{matched:true,value:{settings:{}}}" );
expect( 'scripts/release-files.mjs', 'build/website-builder-consistency-guard.js' );
reject( 'scripts/release-files.mjs', 'build/website-builder-structure-row-drag.js' );
expect( 'runtime-src/manifest.json', 'website-builder-consistency-guard.js' );
reject( 'runtime-src/manifest.json', 'website-builder-structure-row-drag.js' );

for ( const testFile of [ 'tests/e2e/release-critical.spec.ts', 'tests/e2e/editor-shell.spec.ts', 'tests/e2e/page-settings.spec.ts', 'tests/e2e/accessibility-release.spec.ts', 'tests/e2e/theme-shell.spec.ts' ] ) {
	expect( testFile, '.cc-studio-app' );
	reject( testFile, '.cc-standalone-app' );
}
expect( 'tests/e2e/theme-shell.spec.ts', 'cresco-canvas-theme-editor' );
expect( 'tests/e2e/global-setup.ts', 'cresco-e2e-theme-header' );

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}
process.stdout.write( '[studio] Hardened Studio verified: one direct canonical bootstrap, retired standalone presentation, repaired source/build parity, optimistic concurrency, legacy Session isolation, atomic public abuse controls, fail-closed Page Settings, durable Theme settings, one Core Structure move path, core-only Canvas pointer bridge, no duplicate Structure drag runtime, no legacy DOM adapter, and privileged quarantine controls.\n' );
