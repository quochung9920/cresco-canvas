<?php
/**
 * Independent diagnostics center for Website Builder runtime and persistence.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Session\SessionManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderDiagnostics {
	const PAGE_SLUG = 'cresco-canvas-diagnostics';

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 40 );
		add_action( 'admin_head', array( $this, 'print_editor_probe' ), 1 );
	}

	public function register_page() {
		add_management_page( __( 'Cresco Diagnostics', 'cresco-canvas' ), __( 'Cresco Diagnostics', 'cresco-canvas' ), 'edit_pages', self::PAGE_SLUG, array( $this, 'render_page' ) );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/diagnostics/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_diagnostics' ),
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$post_id = absint( $request['postId'] ?? 0 );
					return $post_id > 0 && current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	public function rest_diagnostics( WP_REST_Request $request ) {
		$data = $this->server_report( absint( $request['postId'] ) );
		$data['moduleRisks'] = $this->module_risks();
		return new WP_REST_Response( $data );
	}

	/** Stable Tools page; intentionally independent from the editor runtime. */
	public function render_page() {
		if ( ! current_user_can( 'edit_pages' ) ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selector.
		$context = $post_id ? WebsiteBuilderRuntimeContext::for_document( $post_id ) : null;
		$report  = $context ? $this->server_report( $post_id ) : array();
		$tests   = $context ? $this->runtime_tests( $context ) : array();
		$links   = $context ? $this->isolation_links( $context ) : array();
		$risks   = $this->module_risks();

		echo '<div class="wrap"><h1>' . esc_html__( 'Cresco Diagnostics', 'cresco-canvas' ) . '</h1>';
		echo '<p>' . esc_html__( 'Diagnose assets, REST endpoints, persistence and browser startup without depending on the Website Builder tab.', 'cresco-canvas' ) . '</p>';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '"><label for="cresco-diag-post"><strong>' . esc_html__( 'Document ID', 'cresco-canvas' ) . '</strong></label> <input id="cresco-diag-post" name="post" type="number" min="1" value="' . esc_attr( $post_id ) . '"> <button class="button button-primary">' . esc_html__( 'Run server checks', 'cresco-canvas' ) . '</button></form>';
		if ( $post_id && ! $context ) echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Unsupported document, missing document, or insufficient permission.', 'cresco-canvas' ) . '</p></div>';

		if ( $context ) {
			echo '<h2>' . esc_html__( 'Server report', 'cresco-canvas' ) . '</h2><pre style="max-height:360px;overflow:auto;background:#111827;color:#e5e7eb;padding:12px;border-radius:6px">' . esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
			echo '<h2>' . esc_html__( 'Runtime modules', 'cresco-canvas' ) . '</h2><table class="widefat striped"><thead><tr><th>Module</th><th>Required</th><th>Default</th><th>Observer risk</th></tr></thead><tbody>';
			foreach ( $risks as $risk ) {
				echo '<tr><td><strong>' . esc_html( $risk['label'] ) . '</strong><br><code>' . esc_html( $risk['key'] ) . '</code></td><td>' . esc_html( $risk['required'] ? 'yes' : 'no' ) . '</td><td>' . esc_html( $risk['quarantinedDefault'] ? 'quarantined' : 'enabled' ) . '</td><td>' . esc_html( strtoupper( $risk['risk'] ) . ' · observers ' . $risk['observerCount'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<h2 style="margin-top:24px">' . esc_html__( 'Module isolation', 'cresco-canvas' ) . '</h2><p>' . esc_html__( 'Start with Core only, then add one layer. These links use the same central module policy as the editor.', 'cresco-canvas' ) . '</p><p>';
			foreach ( $links as $link ) echo '<a class="button" style="margin:0 8px 8px 0" target="_blank" rel="noopener" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a>';
			echo '</p>';
			echo '<h2>' . esc_html__( 'REST runtime tests', 'cresco-canvas' ) . '</h2><p><button class="button button-primary" id="cresco-run-tests" type="button">' . esc_html__( 'Run REST tests', 'cresco-canvas' ) . '</button> <button class="button" id="cresco-copy-report" type="button">' . esc_html__( 'Copy full report', 'cresco-canvas' ) . '</button></p>';
			echo '<table class="widefat striped"><thead><tr><th>Test</th><th>Status</th><th>Duration</th><th>Detail</th></tr></thead><tbody>';
			foreach ( $tests as $test ) echo '<tr data-test="' . esc_attr( $test['key'] ) . '"><td>' . esc_html( $test['label'] ) . '</td><td data-status>Not run</td><td data-duration>—</td><td data-detail><code>' . esc_html( $test['path'] ) . '</code></td></tr>';
			echo '</tbody></table><h2>' . esc_html__( 'Last editor heartbeat', 'cresco-canvas' ) . '</h2><pre id="cresco-heartbeat" style="max-height:360px;overflow:auto;background:#111827;color:#e5e7eb;padding:12px;border-radius:6px">No trace yet.</pre>';
		}
		echo '</div>';
		if ( ! $context ) return;
		$config = wp_json_encode( array( 'postId' => $post_id, 'nonce' => wp_create_nonce( 'wp_rest' ), 'tests' => $tests, 'server' => $report, 'risks' => $risks, 'storageKey' => 'cresco-diagnostics-last-' . $post_id ) );
		if ( ! is_string( $config ) ) return;
		$this->print_tools_runtime( $config );
	}

	private function print_tools_runtime( $config ) {
		echo '<script>window.crescoDiagnosticsPageConfig=' . $config . ';</script>';
		?>
<script id="cresco-diagnostics-page-runtime">
(function(w,d){'use strict';var c=w.crescoDiagnosticsPageConfig||{},results={};
function getRow(k){return d.querySelector('[data-test="'+String(k).replace(/"/g,'')+'"]')}
function setRow(k,s,ms,detail){var r=getRow(k);if(!r)return;r.querySelector('[data-status]').textContent=s;r.querySelector('[data-duration]').textContent=ms==null?'—':ms+' ms';r.querySelector('[data-detail]').textContent=detail||''}
function heartbeat(){try{var x=localStorage.getItem(c.storageKey),v=x?JSON.parse(x):null;d.getElementById('cresco-heartbeat').textContent=v?JSON.stringify(v,null,2):'No trace yet.';return v}catch(e){return{error:String(e)}}}
function one(t){setRow(t.key,'RUNNING',null,t.path);var ctl=w.AbortController?new AbortController():null,start=performance.now(),timer=setTimeout(function(){if(ctl)ctl.abort()},6000),o={credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce}};if(ctl)o.signal=ctl.signal;return fetch(t.url,o).then(function(r){return r.text().then(function(body){clearTimeout(timer);var ms=Math.round(performance.now()-start),detail='HTTP '+r.status+' · '+body.slice(0,180);results[t.key]={status:r.ok?'OK':'ERROR',durationMs:ms,detail:detail};setRow(t.key,results[t.key].status,ms,detail)})}).catch(function(e){clearTimeout(timer);var ms=Math.round(performance.now()-start),detail=e&&e.name==='AbortError'?'TIMEOUT after 6000 ms':String(e&&e.message||e);results[t.key]={status:'ERROR',durationMs:ms,detail:detail};setRow(t.key,'ERROR',ms,detail)})}
function run(){results={};var chain=Promise.resolve();(c.tests||[]).forEach(function(t){chain=chain.then(function(){return one(t)})});return chain}
function report(){return{generatedAt:new Date().toISOString(),postId:c.postId,userAgent:navigator.userAgent,server:c.server,moduleRisks:c.risks,runtimeTests:results,lastEditorHeartbeat:heartbeat()}}
function copy(){var text=JSON.stringify(report(),null,2);if(navigator.clipboard&&navigator.clipboard.writeText)return navigator.clipboard.writeText(text);var a=d.createElement('textarea');a.value=text;d.body.appendChild(a);a.select();d.execCommand('copy');a.remove();return Promise.resolve()}
var runButton=d.getElementById('cresco-run-tests'),copyButton=d.getElementById('cresco-copy-report');if(runButton)runButton.onclick=run;if(copyButton)copyButton.onclick=copy;heartbeat();
})(window,document);
</script>
		<?php
	}

	/** Early browser probe so failures before the main bundle mounts are visible. */
	public function print_editor_probe() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context ) return;
		$config = wp_json_encode( array(
			'postId' => $context->post_id(), 'debug' => $context->debug_enabled(), 'context' => $context->to_array(),
			'endpoint' => rest_url( 'cresco-canvas/v1/website-builder/diagnostics/' . $context->post_id() ), 'nonce' => wp_create_nonce( 'wp_rest' ),
			'storageKey' => 'cresco-diagnostics-last-' . $context->post_id(),
		) );
		if ( ! is_string( $config ) ) return;
		echo '<script>window.crescoDiagnosticConfig=' . $config . ';</script>';
		?>
<script id="cresco-diagnostics-probe">
(function(w,d){'use strict';var c=w.crescoDiagnosticConfig||{},started=Date.now(),events=[],requests={},lastError=null,lastTick=Date.now(),open=!!c.debug;
function safe(v){try{return JSON.parse(JSON.stringify(v))}catch(e){return String(v)}}function now(){return Date.now()-started}
function modules(){return{core:!!d.getElementById('cresco-canvas-website-builder-js'),controls:!!d.getElementById('cresco-canvas-website-builder-controls-js'),professionalUx:!!d.getElementById('cresco-canvas-website-builder-professional-ux-js'),architecture:!!d.getElementById('cresco-canvas-builder-architecture-js'),comprehensiveV3:!!d.getElementById('cresco-canvas-website-builder-comprehensive-v3-js'),workflow:!!d.getElementById('cresco-canvas-website-builder-workflow-extensions-js')}}
function snapshot(){var root=d.getElementById('cresco-canvas-standalone-editor');return{postId:c.postId,elapsedMs:now(),ready:!!(root&&root.querySelector('.cc-builder-app')),loading:!!(root&&root.querySelector('.cc-builder-loading,.cc-standalone-loading')),context:c.context,runtimePolicy:safe(w.crescoRuntimePolicy||null),runtimeState:safe(w.crescoRuntimeState||null),editorBoot:safe(w.crescoWebsiteBuilderEditorBoot||null),bootstrap:safe(w.crescoWebsiteBuilderBootstrap||w.crescoWebsiteBuilderRequestGuard||null),settingsPresent:!!w.crescoWebsiteBuilderSettings,wpElement:!!(w.wp&&w.wp.element),wpApiFetch:!!(w.wp&&w.wp.apiFetch),modules:modules(),architecture:safe(w.crescoBuilderArchitectureDiagnostics||null),lastError:lastError,requests:safe(requests),events:safe(events)}}
function persist(){try{var s=snapshot();s.events=s.events.slice(-60);localStorage.setItem(c.storageKey,JSON.stringify({savedAt:Date.now(),snapshot:s}))}catch(e){}}
function add(type,data){events.push({at:now(),type:type,data:data||null});if(events.length>160)events.shift();persist()}
w.crescoDiagnostics={snapshot:snapshot,persist:persist,events:events,requests:requests};
w.addEventListener('error',function(e){lastError={type:'error',message:String(e.message||'Resource/JavaScript error'),source:String(e.filename||(e.target&&e.target.src)||(e.target&&e.target.href)||''),line:Number(e.lineno||0)};add('window.error',lastError)},true);w.addEventListener('unhandledrejection',function(e){var r=e.reason;lastError={type:'unhandledrejection',message:r&&r.message?String(r.message):String(r||'Unhandled rejection')};add('promise.rejection',lastError)});w.addEventListener('cresco:architecture-observer-guard',function(e){add('architecture.observer.guard',safe(e.detail||null))});
function wrap(){var wp=w.wp;if(!wp||!wp.apiFetch||wp.apiFetch.__crescoDiagnosticsWrapped)return false;var original=wp.apiFetch;function f(o){var path=String(o&&o.path||''),id=path+'#'+Date.now(),begin=Date.now();requests[id]={path:path,status:'pending',startedAt:now()};add('request.start',{id:id,path:path});return Promise.resolve(original(o)).then(function(v){requests[id].status='fulfilled';requests[id].durationMs=Date.now()-begin;add('request.success',requests[id]);return v},function(e){requests[id].status='rejected';requests[id].durationMs=Date.now()-begin;requests[id].error=String(e&&e.message||e);add('request.error',requests[id]);throw e})}try{Object.keys(original).forEach(function(k){f[k]=original[k]})}catch(e){}if(typeof original.use==='function')f.use=original.use.bind(original);f.__crescoDiagnosticsWrapped=true;f.__crescoOriginal=original;wp.apiFetch=f;add('apiFetch.wrapped');return true}var poll=setInterval(function(){if(wrap())clearInterval(poll)},50);setTimeout(function(){clearInterval(poll)},10000);
function render(){if(!open||!d.body)return;var old=d.getElementById('cresco-debug-console');if(old)old.remove();var s=snapshot(),box=d.createElement('div');box.id='cresco-debug-console';box.style.cssText='position:fixed;right:16px;bottom:16px;z-index:999999;width:min(560px,calc(100vw - 32px));max-height:65vh;overflow:auto;background:#111827;color:#e5e7eb;padding:12px;border-radius:10px;font:12px/1.45 monospace';box.innerHTML='<strong>Cresco Debug · '+(s.ready?'READY':(s.runtimeState&&s.runtimeState.phase)||'LOADING')+'</strong><pre style="white-space:pre-wrap">'+JSON.stringify(s,null,2).replace(/&/g,'&amp;').replace(/</g,'&lt;')+'</pre>';d.body.appendChild(box)}
if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',render,{once:true});else render();setTimeout(function(){var s=snapshot();if(!s.ready){add('startup.stalled',s.runtimeState);open=true;render()}},10000);setInterval(function(){var t=Date.now(),lag=t-lastTick-1000;lastTick=t;if(lag>1200)add('eventloop.stall',{lagMs:lag});persist();if(open)render()},1000);
})(window,document);
</script>
		<?php
	}

	private function server_report( $post_id ) {
		$post = get_post( $post_id );
		$context = WebsiteBuilderRuntimeContext::for_document( $post_id );
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : null;
		$sanitized = is_array( $decoded ) ? WebsiteBuilder::sanitize_session( $decoded ) : null;
		return array(
			'generatedAt' => gmdate( 'c' ), 'pluginVersion' => CRESCO_CANVAS_VERSION, 'wordpressVersion' => get_bloginfo( 'version' ), 'phpVersion' => PHP_VERSION,
			'context' => $context ? $context->to_array() : null,
			'post' => array( 'id' => $post_id, 'exists' => $post instanceof \WP_Post, 'type' => $post instanceof \WP_Post ? $post->post_type : '', 'canEdit' => current_user_can( 'edit_post', $post_id ) ),
			'session' => array( 'metaPresent' => '' !== $raw, 'bytes' => strlen( $raw ), 'jsonValid' => '' === $raw || is_array( $decoded ), 'jsonError' => '' === $raw || is_array( $decoded ) ? '' : json_last_error_msg(), 'sanitizeValid' => null === $sanitized || ! is_wp_error( $sanitized ), 'sanitizeError' => is_wp_error( $sanitized ) ? $sanitized->get_error_message() : '' ),
			'modules' => WebsiteBuilderModuleRegistry::asset_reports(),
		);
	}

	private function runtime_tests( WebsiteBuilderRuntimeContext $context ) {
		$id = $context->post_id(); $cfg = WebsiteBuilderEditorConfig::for_context( $context );
		$items = array(
			array( 'key' => 'diagnostics', 'label' => 'Diagnostics REST', 'path' => '/cresco-canvas/v1/website-builder/diagnostics/' . $id ),
			array( 'key' => 'session', 'label' => 'Session REST', 'path' => $cfg['sessionPath'] ?? '' ), array( 'key' => 'context', 'label' => 'Context REST', 'path' => $cfg['contextPath'] ?? '' ),
			array( 'key' => 'pageSettings', 'label' => 'Page Settings REST', 'path' => $cfg['pageSettingsPath'] ?? '' ), array( 'key' => 'history', 'label' => 'History REST', 'path' => $cfg['historyPath'] ?? '' ),
			array( 'key' => 'options', 'label' => 'Builder Options REST', 'path' => $cfg['optionsPath'] ?? '' ), array( 'key' => 'components', 'label' => 'Components REST', 'path' => $cfg['componentsPath'] ?? '' ),
			array( 'key' => 'architecture', 'label' => 'Architecture REST', 'path' => '/cresco-canvas/v1/website-builder/architecture/' . $id ), array( 'key' => 'documentDiagnostics', 'label' => 'Document Diagnostics REST', 'path' => '/cresco-canvas/v1/website-builder/document-diagnostics/' . $id ),
		);
		foreach ( $items as &$item ) $item['url'] = rest_url( ltrim( (string) $item['path'], '/' ) ); unset( $item ); return $items;
	}

	private function isolation_links( WebsiteBuilderRuntimeContext $context ) {
		$base = add_query_arg( array( 'page' => $context->screen(), 'post' => $context->post_id(), 'cresco-debug' => 1 ), admin_url( 'admin.php' ) );
		return array(
			array( 'label' => 'Normal policy', 'url' => $base ), array( 'label' => 'Core only', 'url' => add_query_arg( 'cresco-safe-mode', 1, $base ) ),
			array( 'label' => 'Core + Controls', 'url' => add_query_arg( 'cresco-module', 'controls', $base ) ), array( 'label' => 'Core + Professional UX', 'url' => add_query_arg( 'cresco-module', 'professional-ux', $base ) ),
			array( 'label' => 'Core + Architecture', 'url' => add_query_arg( array( 'cresco-module' => 'architecture', 'cresco-architecture-debug' => 1 ), $base ) ), array( 'label' => 'All modules', 'url' => add_query_arg( array( 'cresco-module' => 'all', 'cresco-architecture-debug' => 1 ), $base ) ),
		);
	}

	private function module_risks() {
		$output = array();
		foreach ( WebsiteBuilderModuleRegistry::all() as $key => $module ) {
			$code = ''; foreach ( $module['scripts'] as $asset ) { $path = WebsiteBuilderAsset::absolute( $asset['file'] ); if ( is_readable( $path ) ) { $part = @file_get_contents( $path ); if ( is_string( $part ) ) $code .= $part; } } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local immutable assets.
			$count = substr_count( $code, 'MutationObserver' ); $scheduler = false !== strpos( $code, 'requestAnimationFrame' ); $feedback = false !== strpos( $code, 'new MutationObserver(function(){shell();contextMenu();}).observe(root' );
			$output[] = array( 'key' => $key, 'label' => $module['label'], 'required' => ! empty( $module['required'] ), 'quarantinedDefault' => ! empty( $module['quarantinedDefault'] ), 'risk' => $feedback ? 'high' : ( $count && ! $scheduler ? 'medium' : 'low' ), 'observerCount' => $count, 'scheduler' => $scheduler );
		}
		return $output;
	}
}
