<?php
/**
 * Independent diagnostics for the Website Builder startup lifecycle.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;
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
		add_action( 'admin_enqueue_scripts', array( $this, 'apply_editor_isolation' ), 1400 );
	}

	public function register_page() {
		add_management_page(
			__( 'Cresco Diagnostics', 'cresco-canvas' ),
			__( 'Cresco Diagnostics', 'cresco-canvas' ),
			'edit_pages',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/diagnostics/(?P<postId>\\d+)',
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
		$post_id = absint( $request['postId'] );
		$data = $this->collect_server_diagnostics( $post_id );
		$data['moduleRisks'] = $this->scan_module_risks();
		return new WP_REST_Response( $data );
	}

	/** Render a diagnostics center that remains usable even when the editor tab freezes. */
	public function render_page() {
		if ( ! current_user_can( 'edit_pages' ) ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostics.
		$data = $post_id ? $this->collect_server_diagnostics( $post_id ) : array();
		$modules = $this->scan_module_risks();
		$tests = $post_id ? $this->runtime_tests( $post_id ) : array();
		$links = $post_id ? $this->isolation_links( $post_id ) : array();
		$storage_key = $post_id ? 'cresco-diagnostics-last-' . $post_id : '';
		$config = wp_json_encode(
			array(
				'postId'     => $post_id,
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'tests'      => $tests,
				'server'     => $data,
				'modules'    => $modules,
				'storageKey' => $storage_key,
			)
		);

		echo '<div class="wrap cresco-diagnostics-center"><h1>' . esc_html__( 'Cresco Diagnostics', 'cresco-canvas' ) . '</h1>';
		echo '<p>' . esc_html__( 'This page runs outside the Website Builder, so it remains available when an editor tab is frozen or unresponsive.', 'cresco-canvas' ) . '</p>';
		echo '<style>
		.cresco-diagnostics-center{max-width:1240px}.cresco-diag-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:20px 0}.cresco-diag-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.cresco-diag-card h2{margin:0 0 10px;font-size:16px}.cresco-diag-actions{display:flex;flex-wrap:wrap;gap:8px}.cresco-diag-risk-high{color:#b32d2e;font-weight:700}.cresco-diag-risk-medium{color:#996800;font-weight:700}.cresco-diag-risk-low{color:#008a20;font-weight:700}.cresco-diag-status-pending{color:#996800}.cresco-diag-status-ok{color:#008a20;font-weight:700}.cresco-diag-status-error{color:#b32d2e;font-weight:700}.cresco-diag-pre{max-height:360px;overflow:auto;background:#111827;color:#e5e7eb;padding:12px;border-radius:6px;white-space:pre-wrap}.cresco-diag-note{color:#646970}.cresco-diag-danger{border-color:#d63638!important;color:#b32d2e!important}
		</style>';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '"><label for="cresco-diagnostic-post"><strong>' . esc_html__( 'Page ID', 'cresco-canvas' ) . '</strong></label> <input id="cresco-diagnostic-post" name="post" type="number" min="1" value="' . esc_attr( $post_id ) . '"> <button class="button button-primary" type="submit">' . esc_html__( 'Run server checks', 'cresco-canvas' ) . '</button></form>';

		if ( $data ) {
			echo '<div class="cresco-diag-grid">';
			echo '<section class="cresco-diag-card"><h2>' . esc_html__( 'Server summary', 'cresco-canvas' ) . '</h2><p><strong>Page:</strong> ' . esc_html( (string) $post_id ) . '</p><p><strong>Session:</strong> ' . esc_html( ! empty( $data['sessionJsonValid'] ) && ! empty( $data['sessionSanitizeValid'] ) ? 'VALID' : 'CHECK REQUIRED' ) . '</p><p><strong>Plugin:</strong> ' . esc_html( (string) CRESCO_CANVAS_VERSION ) . '</p></section>';
			$high_risk = array_values( array_filter( $modules, static function ( $module ) { return 'high' === ( $module['risk'] ?? '' ); } ) );
			echo '<section class="cresco-diag-card"><h2>' . esc_html__( 'Module risk summary', 'cresco-canvas' ) . '</h2><p><strong>' . esc_html( (string) count( $high_risk ) ) . '</strong> high-risk module(s) detected by static runtime heuristics.</p>';
			if ( $high_risk ) {
				echo '<p class="cresco-diag-risk-high">' . esc_html( implode( ', ', array_map( static function ( $item ) { return (string) ( $item['label'] ?? '' ); }, $high_risk ) ) ) . '</p>';
			}
			echo '<p class="cresco-diag-note">' . esc_html__( 'A high-risk flag is a diagnostic signal, not proof by itself. Isolation mode confirms the runtime culprit.', 'cresco-canvas' ) . '</p></section>';
			echo '<section class="cresco-diag-card"><h2>' . esc_html__( 'Last editor heartbeat', 'cresco-canvas' ) . '</h2><p id="cresco-heartbeat-summary">' . esc_html__( 'No browser heartbeat loaded yet.', 'cresco-canvas' ) . '</p><button class="button" id="cresco-refresh-heartbeat" type="button">' . esc_html__( 'Refresh heartbeat', 'cresco-canvas' ) . '</button></section>';
			echo '</div>';

			echo '<h2>' . esc_html__( 'Server diagnostics', 'cresco-canvas' ) . '</h2><table class="widefat striped"><tbody>';
			foreach ( $data as $key => $value ) {
				echo '<tr><th style="width:250px">' . esc_html( $key ) . '</th><td><code style="white-space:pre-wrap">' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</code></td></tr>';
			}
			echo '</tbody></table>';

			echo '<h2 style="margin-top:28px">' . esc_html__( 'Module risk scan', 'cresco-canvas' ) . '</h2><table class="widefat striped"><thead><tr><th>Module</th><th>Asset</th><th>Risk</th><th>Observer</th><th>Scheduler</th><th>Probable feedback loop</th></tr></thead><tbody>';
			foreach ( $modules as $module ) {
				$risk = (string) ( $module['risk'] ?? 'low' );
				echo '<tr><td><strong>' . esc_html( (string) $module['label'] ) . '</strong></td><td><code>' . esc_html( (string) $module['file'] ) . '</code></td><td class="cresco-diag-risk-' . esc_attr( $risk ) . '">' . esc_html( strtoupper( $risk ) ) . '</td><td>' . esc_html( ! empty( $module['mutationObserver'] ) ? 'yes (' . (int) $module['observerCount'] . ')' : 'no' ) . '</td><td>' . esc_html( ! empty( $module['animationFrameScheduler'] ) ? 'requestAnimationFrame' : 'none detected' ) . '</td><td>' . esc_html( ! empty( $module['probableFeedbackLoop'] ) ? 'YES' : 'no' ) . '</td></tr>';
			}
			echo '</tbody></table>';

			echo '<h2 style="margin-top:28px">' . esc_html__( 'Module isolation', 'cresco-canvas' ) . '</h2><p>' . esc_html__( 'Open the same Page with controlled module combinations. Core-only is the safest first test. Architecture and All are intentionally marked dangerous because they can reproduce the freeze.', 'cresco-canvas' ) . '</p><div class="cresco-diag-actions">';
			foreach ( $links as $link ) {
				$class = ! empty( $link['danger'] ) ? 'button cresco-diag-danger' : 'button';
				echo '<a class="' . esc_attr( $class ) . '" target="_blank" rel="noopener" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a>';
			}
			echo '</div>';

			echo '<h2 style="margin-top:28px">' . esc_html__( 'REST runtime tests', 'cresco-canvas' ) . '</h2><p>' . esc_html__( 'These requests run from this stable Tools page with a 6 second timeout, independent from the editor tab.', 'cresco-canvas' ) . '</p><div class="cresco-diag-actions"><button class="button button-primary" id="cresco-run-rest-tests" type="button">' . esc_html__( 'Run REST tests', 'cresco-canvas' ) . '</button><button class="button" id="cresco-copy-full-report" type="button">' . esc_html__( 'Copy full report', 'cresco-canvas' ) . '</button></div>';
			echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>Test</th><th>Status</th><th>Duration</th><th>Detail</th></tr></thead><tbody>';
			foreach ( $tests as $test ) {
				echo '<tr data-test-key="' . esc_attr( $test['key'] ) . '"><td><strong>' . esc_html( $test['label'] ) . '</strong></td><td data-status>Not run</td><td data-duration>—</td><td data-detail><code>' . esc_html( $test['path'] ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
			echo '<h2 style="margin-top:28px">' . esc_html__( 'Last persisted editor trace', 'cresco-canvas' ) . '</h2><pre id="cresco-last-heartbeat" class="cresco-diag-pre">' . esc_html__( 'No persisted trace found for this Page yet. Open the editor once with cresco-debug=1, then return here if it freezes.', 'cresco-canvas' ) . '</pre>';
		}
		echo '</div>';

		if ( ! $post_id || ! is_string( $config ) ) return;
		echo '<script>window.crescoDiagnosticsPageConfig=' . $config . ';</script>';
		?>
<script id="cresco-diagnostics-page-runtime">
(function(window,document){'use strict';
var cfg=window.crescoDiagnosticsPageConfig||{},results={};
function row(key){return document.querySelector('[data-test-key="'+String(key).replace(/"/g,'')+'"]')}
function setRow(key,status,duration,detail){var r=row(key);if(!r)return;var s=r.querySelector('[data-status]'),d=r.querySelector('[data-duration]'),x=r.querySelector('[data-detail]');s.textContent=status;s.className=status==='OK'?'cresco-diag-status-ok':status==='RUNNING'?'cresco-diag-status-pending':'cresco-diag-status-error';d.textContent=duration==null?'—':String(duration)+' ms';x.textContent=detail||''}
function readHeartbeat(){if(!cfg.storageKey)return null;try{var raw=window.localStorage.getItem(cfg.storageKey);return raw?JSON.parse(raw):null}catch(e){return{readError:String(e&&e.message||e)}}}
function renderHeartbeat(){var value=readHeartbeat(),pre=document.getElementById('cresco-last-heartbeat'),summary=document.getElementById('cresco-heartbeat-summary');if(!value){if(summary)summary.textContent='No persisted editor heartbeat found.';if(pre)pre.textContent='No persisted trace found for this Page yet.';return null}var age=value.savedAt?Date.now()-Number(value.savedAt):null;if(summary)summary.textContent=age==null?'Heartbeat found.':'Last heartbeat '+Math.round(age/1000)+' second(s) ago'+(age>3000?' — the editor may have stalled after this point.':'.');if(pre)pre.textContent=JSON.stringify(value,null,2);return value}
function timeoutFetch(test){var controller=window.AbortController?new AbortController():null,start=performance.now(),timer=window.setTimeout(function(){if(controller)controller.abort()},6000);var options={method:'GET',credentials:'same-origin',headers:{'X-WP-Nonce':cfg.nonce}};if(controller)options.signal=controller.signal;return window.fetch(test.url,options).then(function(response){return response.text().then(function(body){var ms=Math.round(performance.now()-start);window.clearTimeout(timer);if(!response.ok)throw{status:response.status,message:'HTTP '+response.status+' '+body.slice(0,240),duration:ms};return{status:response.status,duration:ms,detail:'HTTP '+response.status+' · '+body.slice(0,240)}})},function(error){window.clearTimeout(timer);throw{status:0,message:error&&error.name==='AbortError'?'TIMEOUT after 6000 ms':String(error&&error.message||error),duration:Math.round(performance.now()-start)}})}
function runOne(test){setRow(test.key,'RUNNING',null,test.path);return timeoutFetch(test).then(function(value){results[test.key]={status:'OK',durationMs:value.duration,detail:value.detail};setRow(test.key,'OK',value.duration,value.detail)},function(error){results[test.key]={status:error.message&&error.message.indexOf('TIMEOUT')===0?'TIMEOUT':'ERROR',durationMs:error.duration||null,detail:error.message||String(error)};setRow(test.key,results[test.key].status,error.duration||null,results[test.key].detail)})}
function runAll(){results={};var button=document.getElementById('cresco-run-rest-tests');if(button){button.disabled=true;button.textContent='Running…'}var chain=Promise.resolve();(cfg.tests||[]).forEach(function(test){chain=chain.then(function(){return runOne(test)})});return chain.then(function(){if(button){button.disabled=false;button.textContent='Run REST tests'}})}
function report(){return{generatedAt:new Date().toISOString(),postId:cfg.postId,userAgent:navigator.userAgent,server:cfg.server,modules:cfg.modules,runtimeTests:results,lastEditorHeartbeat:readHeartbeat()}}
function copyReport(){var text=JSON.stringify(report(),null,2);if(navigator.clipboard&&navigator.clipboard.writeText)return navigator.clipboard.writeText(text);var a=document.createElement('textarea');a.value=text;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove();return Promise.resolve()}
var run=document.getElementById('cresco-run-rest-tests'),copy=document.getElementById('cresco-copy-full-report'),refresh=document.getElementById('cresco-refresh-heartbeat');if(run)run.addEventListener('click',runAll);if(copy)copy.addEventListener('click',function(){copyReport().then(function(){copy.textContent='Copied';window.setTimeout(function(){copy.textContent='Copy full report'},1500)})});if(refresh)refresh.addEventListener('click',renderHeartbeat);renderHeartbeat();
})(window,document);
</script>
		<?php
	}

	/** Apply late, deterministic module isolation for editor startup tests. */
	public function apply_editor_isolation() {
		if ( ! $this->is_editor_request() ) return;
		$mode = $this->current_isolation_mode();
		if ( 'normal' === $mode ) return;

		$modules = array(
			'controls'        => array( 'scripts' => array( 'cresco-canvas-website-builder-controls' ), 'styles' => array( 'cresco-canvas-website-builder-controls' ) ),
			'professional-ux' => array( 'scripts' => array( 'cresco-canvas-website-builder-professional-ux', 'cresco-canvas-website-builder-preview-fit' ), 'styles' => array( 'cresco-canvas-website-builder-professional-ux' ) ),
			'architecture'    => array( 'scripts' => array( 'cresco-canvas-builder-architecture' ), 'styles' => array( 'cresco-canvas-builder-architecture' ) ),
			'comprehensive-v3'=> array( 'scripts' => array( 'cresco-canvas-website-builder-comprehensive-v3' ), 'styles' => array( 'cresco-canvas-website-builder-comprehensive-v3' ) ),
			'workflow'        => array( 'scripts' => array( 'cresco-canvas-website-builder-workflow-extensions' ), 'styles' => array() ),
		);
		$allowed = array(
			'core'            => array(),
			'controls'        => array( 'controls' ),
			'professional-ux' => array( 'controls', 'professional-ux' ),
			'architecture'    => array( 'architecture' ),
			'all'             => array_keys( $modules ),
		);
		$enabled = $allowed[ $mode ] ?? array();
		foreach ( $modules as $key => $handles ) {
			$keep = in_array( $key, $enabled, true );
			foreach ( $handles['scripts'] as $handle ) {
				if ( $keep && wp_script_is( $handle, 'registered' ) ) wp_enqueue_script( $handle );
				if ( ! $keep ) wp_dequeue_script( $handle );
			}
			foreach ( $handles['styles'] as $handle ) {
				if ( $keep && wp_style_is( $handle, 'registered' ) ) wp_enqueue_style( $handle );
				if ( ! $keep ) wp_dequeue_style( $handle );
			}
		}
		if ( wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) {
			$architecture_enabled = in_array( 'architecture', $enabled, true );
			wp_add_inline_script(
				'cresco-canvas-website-builder',
				'window.crescoDiagnosticsIsolationMode=' . wp_json_encode( $mode ) . ';window.crescoArchitectureQuarantined=' . ( $architecture_enabled ? 'false' : 'true' ) . ';',
				'before'
			);
		}
	}

	public function print_editor_probe() {
		if ( ! $this->is_editor_request() ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostics.
		$debug = isset( $_GET['cresco-debug'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cresco-debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostics.
		$endpoint = rest_url( 'cresco-canvas/v1/website-builder/diagnostics/' . $post_id );
		$nonce = wp_create_nonce( 'wp_rest' );
		$config = wp_json_encode( array( 'postId' => $post_id, 'debug' => $debug, 'endpoint' => $endpoint, 'nonce' => $nonce, 'pluginVersion' => CRESCO_CANVAS_VERSION, 'isolationMode' => $this->current_isolation_mode(), 'storageKey' => 'cresco-diagnostics-last-' . $post_id ) );
		echo '<script>window.crescoDiagnosticConfig=' . $config . ';</script>';
		?>
<script id="cresco-diagnostics-probe">
(function(window,document){'use strict';
var cfg=window.crescoDiagnosticConfig||{},started=Date.now(),events=[],requests={},lastError=null,open=!!cfg.debug,renderQueued=false,lastTick=Date.now();
function now(){return Date.now()-started}
function bodyReady(){return !!document.body}
function queueRender(){if(renderQueued||bodyReady())return;renderQueued=true;document.addEventListener('DOMContentLoaded',function(){renderQueued=false;render()},{once:true})}
function safe(v){try{return JSON.parse(JSON.stringify(v))}catch(e){return String(v)}}
function moduleScripts(){return{core:!!document.getElementById('cresco-canvas-website-builder-js'),controls:!!document.getElementById('cresco-canvas-website-builder-controls-js'),professionalUx:!!document.getElementById('cresco-canvas-website-builder-professional-ux-js'),architecture:!!document.getElementById('cresco-canvas-builder-architecture-js'),comprehensiveV3:!!document.getElementById('cresco-canvas-website-builder-comprehensive-v3-js'),workflow:!!document.getElementById('cresco-canvas-website-builder-workflow-extensions-js')}}
function snapshot(){var root=document.getElementById('cresco-canvas-standalone-editor');return{postId:cfg.postId,pluginVersion:cfg.pluginVersion,isolationMode:window.crescoDiagnosticsIsolationMode||cfg.isolationMode||'normal',architectureQuarantined:!!window.crescoArchitectureQuarantined,elapsedMs:now(),ready:!!(root&&root.querySelector('.cc-builder-app')),loading:!!(root&&root.querySelector('.cc-builder-loading')),settingsPresent:!!window.crescoWebsiteBuilderSettings,bootstrap:safe(window.crescoWebsiteBuilderBootstrap||null),editorBoot:safe(window.crescoWebsiteBuilderEditorBoot||null),wpElement:!!(window.wp&&window.wp.element),wpApiFetch:!!(window.wp&&window.wp.apiFetch),moduleScripts:moduleScripts(),moduleGlobals:{architecture:!!window.crescoBuilderArchitecture,stability:!!window.crescoWebsiteBuilderStability},lastError:lastError,requests:safe(requests),events:safe(events)}}
function persist(){if(!cfg.storageKey)return;try{var s=snapshot();s.events=(s.events||[]).slice(-60);window.localStorage.setItem(cfg.storageKey,JSON.stringify({savedAt:Date.now(),snapshot:s}))}catch(e){}}
function add(type,data){events.push({at:now(),type:type,data:data||null});if(events.length>200)events.shift();persist();render()}
window.crescoDiagnostics={snapshot:snapshot,events:events,requests:requests,open:function(){open=true;render()},close:function(){open=false;render()},persist:persist};
window.addEventListener('error',function(e){lastError={type:'error',message:String(e.message||'JavaScript error'),source:String(e.filename||''),line:Number(e.lineno||0),column:Number(e.colno||0),stack:e.error&&e.error.stack?String(e.error.stack):''};add('window.error',lastError)},true);
window.addEventListener('unhandledrejection',function(e){var r=e.reason;lastError={type:'unhandledrejection',message:r&&r.message?String(r.message):String(r||'Unhandled promise rejection'),stack:r&&r.stack?String(r.stack):''};add('promise.rejection',lastError)});
document.addEventListener('error',function(e){var t=e.target;if(t&&t.tagName&&(t.tagName==='SCRIPT'||t.tagName==='LINK'))add('resource.error',{tag:t.tagName,url:t.src||t.href||'',id:t.id||''})},true);
document.addEventListener('load',function(e){var t=e.target;if(t&&t.tagName&&(t.tagName==='SCRIPT'||t.tagName==='LINK')&&String(t.id||'').indexOf('cresco')!==-1)add('resource.load',{tag:t.tagName,url:t.src||t.href||'',id:t.id||''})},true);
function wrapApiFetch(){var wp=window.wp;if(!wp||!wp.apiFetch||wp.apiFetch.__crescoDiagnosticsWrapped)return false;var original=wp.apiFetch;function wrapped(options){var path=String(options&&options.path||''),id=path+'#'+Date.now()+'-'+Math.random().toString(36).slice(2,6),began=Date.now();requests[id]={path:path,method:String(options&&options.method||'GET').toUpperCase(),status:'pending',startedAt:now()};add('request.start',{id:id,path:path});return Promise.resolve(original(options)).then(function(value){requests[id].status='fulfilled';requests[id].durationMs=Date.now()-began;add('request.success',{id:id,path:path,durationMs:requests[id].durationMs});return value},function(error){requests[id].status='rejected';requests[id].durationMs=Date.now()-began;requests[id].error=error&&error.message?String(error.message):String(error||'Request failed');add('request.error',{id:id,path:path,durationMs:requests[id].durationMs,error:requests[id].error});throw error})}try{Object.keys(original).forEach(function(k){wrapped[k]=original[k]})}catch(e){}if(typeof original.use==='function')wrapped.use=original.use.bind(original);wrapped.__crescoDiagnosticsWrapped=true;wp.apiFetch=wrapped;add('apiFetch.wrapped');return true}
var poll=window.setInterval(function(){if(wrapApiFetch())window.clearInterval(poll)},50);window.setTimeout(function(){window.clearInterval(poll)},10000);
function copy(){var text=JSON.stringify(snapshot(),null,2);if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(text);else if(bodyReady()){var a=document.createElement('textarea');a.value=text;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove()}}
function render(){if(!bodyReady()){queueRender();return}var existing=document.getElementById('cresco-diagnostics-console');if(existing)existing.remove();if(!open)return;var s=snapshot(),box=document.createElement('div');box.id='cresco-diagnostics-console';box.style.cssText='position:fixed;right:16px;bottom:16px;z-index:999999;width:min(560px,calc(100vw - 32px));max-height:70vh;overflow:auto;background:#111827;color:#e5e7eb;border:1px solid #374151;border-radius:10px;box-shadow:0 18px 50px rgba(0,0,0,.35);font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;padding:12px';var status=s.ready?'READY':s.lastError?'ERROR':'LOADING';box.innerHTML='<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><strong style="font-size:13px">Cresco Diagnostics</strong><span style="margin-left:auto">'+status+' · '+s.elapsedMs+'ms</span><button id="cresco-diag-copy" type="button">Copy</button><button id="cresco-diag-close" type="button">×</button></div><pre style="white-space:pre-wrap;margin:0">'+String(JSON.stringify(s,null,2)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</pre>';document.body.appendChild(box);var copyButton=document.getElementById('cresco-diag-copy'),closeButton=document.getElementById('cresco-diag-close');if(copyButton)copyButton.onclick=copy;if(closeButton)closeButton.onclick=function(){open=false;render()}}
function ensureLauncher(){if(!bodyReady()){queueRender();return}if(document.getElementById('cresco-diagnostics-launcher'))return;var b=document.createElement('button');b.id='cresco-diagnostics-launcher';b.type='button';b.textContent='Cresco Debug';b.style.cssText='position:fixed;right:16px;bottom:16px;z-index:999998;background:#111827;color:white;border:0;border-radius:999px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,.25);cursor:pointer';b.onclick=function(){open=!open;render()};document.body.appendChild(b)}
add('probe.loaded',{postId:cfg.postId,isolationMode:cfg.isolationMode});document.addEventListener('DOMContentLoaded',function(){ensureLauncher();add('dom.ready')},{once:true});if(document.readyState!=='loading'){ensureLauncher();render()}
window.setTimeout(function(){var s=snapshot();if(!s.ready){open=true;add('startup.stalled',{elapsedMs:s.elapsedMs});render()}},10000);
if(cfg.endpoint&&window.fetch){window.fetch(cfg.endpoint,{credentials:'same-origin',headers:{'X-WP-Nonce':cfg.nonce}}).then(function(r){return r.json()}).then(function(v){add('server.diagnostics',v)}).catch(function(e){add('server.diagnostics.error',{message:String(e&&e.message||e)})})}
window.setInterval(function(){var current=Date.now(),lag=current-lastTick-1000;lastTick=current;if(lag>1200)add('eventloop.stall',{lagMs:lag});persist();if(open)render()},1000);
})(window,document);
</script>
		<?php
	}

	private function collect_server_diagnostics( $post_id ) {
		$post = get_post( $post_id );
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : null;
		$sanitized = is_array( $decoded ) ? WebsiteBuilder::sanitize_session( $decoded ) : null;
		$assets = array(
			'build/website-builder-editor.js',
			'build/website-builder-bootstrap.js',
			'build/website-builder-controls.js',
			'build/website-builder-professional-ux.js',
			'build/website-builder-architecture.js',
			'build/website-builder-comprehensive-v3.js',
			'build/website-builder-workflow-extensions.js',
			'assets/css/website-builder.css',
		);
		$asset_status = array();
		foreach ( $assets as $relative ) {
			$absolute = CRESCO_CANVAS_PATH . $relative;
			$asset_status[ $relative ] = array(
				'readable' => is_readable( $absolute ),
				'size'     => is_readable( $absolute ) ? filesize( $absolute ) : 0,
				'hash'     => is_readable( $absolute ) ? substr( (string) hash_file( 'sha256', $absolute ), 0, 12 ) : '',
			);
		}
		return array(
			'postId'               => $post_id,
			'postExists'           => $post instanceof \WP_Post,
			'postType'             => $post instanceof \WP_Post ? $post->post_type : '',
			'canEdit'              => current_user_can( 'edit_post', $post_id ),
			'pluginVersion'        => CRESCO_CANVAS_VERSION,
			'phpVersion'           => PHP_VERSION,
			'wordpressVersion'     => get_bloginfo( 'version' ),
			'sessionMetaPresent'   => '' !== $raw,
			'sessionBytes'         => strlen( $raw ),
			'sessionJsonValid'     => '' === $raw || is_array( $decoded ),
			'sessionSanitizeValid' => null === $sanitized || ! is_wp_error( $sanitized ),
			'sessionSanitizeError' => is_wp_error( $sanitized ) ? $sanitized->get_error_message() : '',
			'architectureDefaultQuarantine' => true,
			'assets'               => $asset_status,
		);
	}

	private function runtime_tests( $post_id ) {
		$items = array(
			array( 'key' => 'diagnostics', 'label' => 'Diagnostics REST', 'path' => '/cresco-canvas/v1/website-builder/diagnostics/' . $post_id ),
			array( 'key' => 'session', 'label' => 'Session REST', 'path' => '/cresco-canvas/v1/website-builder/session/' . $post_id ),
			array( 'key' => 'architecture', 'label' => 'Architecture REST', 'path' => '/cresco-canvas/v1/website-builder/architecture/' . $post_id ),
			array( 'key' => 'pageSettings', 'label' => 'Page Settings REST', 'path' => '/cresco-canvas/v1/page-settings/' . $post_id ),
			array( 'key' => 'history', 'label' => 'History REST', 'path' => '/cresco-canvas/v1/history/' . $post_id ),
			array( 'key' => 'options', 'label' => 'Builder Options REST', 'path' => '/cresco-canvas/v1/website-builder/options' ),
			array( 'key' => 'components', 'label' => 'Components REST', 'path' => '/cresco-canvas/v1/website-builder/components' ),
			array( 'key' => 'v3Diagnostics', 'label' => 'V3 Diagnostics REST', 'path' => '/cresco-canvas/v1/website-builder/v3/diagnostics/' . $post_id ),
		);
		foreach ( $items as &$item ) $item['url'] = rest_url( ltrim( $item['path'], '/' ) );
		unset( $item );
		return $items;
	}

	private function isolation_links( $post_id ) {
		$base = add_query_arg( array( 'page' => VisualEditor::PAGE_SLUG, 'post' => $post_id, 'cresco-debug' => 1 ), admin_url( 'admin.php' ) );
		return array(
			array( 'label' => 'Open normal (Architecture quarantined)', 'url' => $base, 'danger' => false ),
			array( 'label' => 'Open Core only', 'url' => add_query_arg( array( 'cresco-safe-mode' => 1 ), $base ), 'danger' => false ),
			array( 'label' => 'Open Core + Controls', 'url' => add_query_arg( array( 'cresco-module' => 'controls' ), $base ), 'danger' => false ),
			array( 'label' => 'Open Core + Professional UX', 'url' => add_query_arg( array( 'cresco-module' => 'professional-ux' ), $base ), 'danger' => false ),
			array( 'label' => 'Open Core + Architecture', 'url' => add_query_arg( array( 'cresco-module' => 'architecture', 'cresco-architecture-debug' => 1 ), $base ), 'danger' => true ),
			array( 'label' => 'Open ALL modules', 'url' => add_query_arg( array( 'cresco-module' => 'all', 'cresco-architecture-debug' => 1 ), $base ), 'danger' => true ),
		);
	}

	private function scan_module_risks() {
		$catalog = array(
			'core' => array( 'label' => 'Core editor', 'file' => 'build/website-builder-editor.js' ),
			'bootstrap' => array( 'label' => 'Bootstrap resilience', 'file' => 'build/website-builder-bootstrap.js' ),
			'controls' => array( 'label' => 'Controls', 'file' => 'build/website-builder-controls.js' ),
			'professional-ux' => array( 'label' => 'Professional UX', 'file' => 'build/website-builder-professional-ux.js' ),
			'architecture' => array( 'label' => 'Architecture', 'file' => 'build/website-builder-architecture.js' ),
			'comprehensive-v3' => array( 'label' => 'Comprehensive V3', 'file' => 'build/website-builder-comprehensive-v3.js' ),
			'workflow' => array( 'label' => 'Workflow extensions', 'file' => 'build/website-builder-workflow-extensions.js' ),
		);
		$output = array();
		foreach ( $catalog as $key => $module ) {
			$absolute = CRESCO_CANVAS_PATH . $module['file'];
			$contents = is_readable( $absolute ) ? @file_get_contents( $absolute ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local immutable plugin asset.
			$contents = is_string( $contents ) ? $contents : '';
			$observer_count = substr_count( $contents, 'MutationObserver' );
			$observes_attributes = false !== strpos( $contents, 'attributes:true' ) || false !== strpos( $contents, 'attributes: true' );
			$observes_subtree = false !== strpos( $contents, 'subtree:true' ) || false !== strpos( $contents, 'subtree: true' );
			$scheduler = false !== strpos( $contents, 'requestAnimationFrame' ) && false !== strpos( $contents, 'scheduled' );
			$mutates_root = false !== strpos( $contents, 'root.appendChild' ) || false !== strpos( $contents, 'root.setAttribute' ) || false !== strpos( $contents, 'root.innerHTML' );
			$feedback = 'architecture' === $key && false !== strpos( $contents, 'new MutationObserver(function(){shell();contextMenu();}).observe(root' ) && false !== strpos( $contents, 'root.appendChild(bar)' );
			$risk = 'low';
			if ( $feedback || ( $observer_count && $observes_attributes && $mutates_root && ! $scheduler ) ) $risk = 'high';
			elseif ( $observer_count && $observes_subtree ) $risk = 'medium';
			$output[] = array(
				'key'                    => $key,
				'label'                  => $module['label'],
				'file'                   => $module['file'],
				'readable'               => is_readable( $absolute ),
				'hash'                   => is_readable( $absolute ) ? substr( (string) hash_file( 'sha256', $absolute ), 0, 12 ) : '',
				'risk'                   => $risk,
				'mutationObserver'       => $observer_count > 0,
				'observerCount'          => $observer_count,
				'observesAttributes'     => $observes_attributes,
				'observesSubtree'        => $observes_subtree,
				'animationFrameScheduler'=> $scheduler,
				'mutatesObservedRoot'    => $mutates_root,
				'probableFeedbackLoop'   => $feedback,
			);
		}
		return $output;
	}

	private function current_isolation_mode() {
		$safe = isset( $_GET['cresco-safe-mode'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cresco-safe-mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- diagnostic routing only.
		if ( $safe ) return 'core';
		$mode = isset( $_GET['cresco-module'] ) ? sanitize_key( wp_unslash( $_GET['cresco-module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- diagnostic routing only.
		return in_array( $mode, array( 'core', 'controls', 'professional-ux', 'architecture', 'all' ), true ) ? $mode : 'normal';
	}

	private function is_editor_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return VisualEditor::PAGE_SLUG === $page;
	}
}
