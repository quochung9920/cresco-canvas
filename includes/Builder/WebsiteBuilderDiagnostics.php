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
		return new WP_REST_Response( $this->collect_server_diagnostics( absint( $request['postId'] ) ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_pages' ) ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostics.
		$data = $post_id ? $this->collect_server_diagnostics( $post_id ) : array();
		echo '<div class="wrap"><h1>' . esc_html__( 'Cresco Diagnostics', 'cresco-canvas' ) . '</h1>';
		echo '<p>' . esc_html__( 'Use this page for server-side Website Builder checks. For browser/runtime errors open a Cresco editor URL with &cresco-debug=1.', 'cresco-canvas' ) . '</p>';
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '"><label for="cresco-diagnostic-post"><strong>' . esc_html__( 'Page ID', 'cresco-canvas' ) . '</strong></label> <input id="cresco-diagnostic-post" name="post" type="number" min="1" value="' . esc_attr( $post_id ) . '"> <button class="button button-primary" type="submit">' . esc_html__( 'Run checks', 'cresco-canvas' ) . '</button></form>';
		if ( $data ) {
			echo '<table class="widefat striped" style="max-width:1100px;margin-top:20px"><tbody>';
			foreach ( $data as $key => $value ) {
				echo '<tr><th style="width:240px">' . esc_html( $key ) . '</th><td><code style="white-space:pre-wrap">' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</code></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	public function print_editor_probe() {
		if ( ! $this->is_editor_request() ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostics.
		$debug = isset( $_GET['cresco-debug'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cresco-debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostics.
		$endpoint = rest_url( 'cresco-canvas/v1/website-builder/diagnostics/' . $post_id );
		$nonce = wp_create_nonce( 'wp_rest' );
		$config = wp_json_encode( array( 'postId' => $post_id, 'debug' => $debug, 'endpoint' => $endpoint, 'nonce' => $nonce, 'pluginVersion' => CRESCO_CANVAS_VERSION ) );
		echo '<script>window.crescoDiagnosticConfig=' . $config . ';</script>';
		?>
<script id="cresco-diagnostics-probe">
(function(window,document){'use strict';
var cfg=window.crescoDiagnosticConfig||{},started=Date.now(),events=[],requests={},lastError=null,open=!!cfg.debug;
function now(){return Date.now()-started}function add(type,data){events.push({at:now(),type:type,data:data||null});if(events.length>200)events.shift();render()}
function safe(v){try{return JSON.parse(JSON.stringify(v))}catch(e){return String(v)}}
function snapshot(){var root=document.getElementById('cresco-canvas-standalone-editor');return{postId:cfg.postId,pluginVersion:cfg.pluginVersion,elapsedMs:now(),ready:!!(root&&root.querySelector('.cc-builder-app')),loading:!!(root&&root.querySelector('.cc-builder-loading')),settingsPresent:!!window.crescoWebsiteBuilderSettings,bootstrap:safe(window.crescoWebsiteBuilderBootstrap||null),editorBoot:safe(window.crescoWebsiteBuilderEditorBoot||null),wpElement:!!(window.wp&&window.wp.element),wpApiFetch:!!(window.wp&&window.wp.apiFetch),lastError:lastError,requests:safe(requests),events:safe(events)}}
window.crescoDiagnostics={snapshot:snapshot,events:events,requests:requests,open:function(){open=true;render()},close:function(){open=false;render()}};
window.addEventListener('error',function(e){lastError={type:'error',message:String(e.message||'JavaScript error'),source:String(e.filename||''),line:Number(e.lineno||0),column:Number(e.colno||0)};add('window.error',lastError)},true);
window.addEventListener('unhandledrejection',function(e){var r=e.reason;lastError={type:'unhandledrejection',message:r&&r.message?String(r.message):String(r||'Unhandled promise rejection')};add('promise.rejection',lastError)});
document.addEventListener('error',function(e){var t=e.target;if(t&&t.tagName&&(t.tagName==='SCRIPT'||t.tagName==='LINK'))add('resource.error',{tag:t.tagName,url:t.src||t.href||''})},true);
function wrapApiFetch(){var wp=window.wp;if(!wp||!wp.apiFetch||wp.apiFetch.__crescoDiagnosticsWrapped)return false;var original=wp.apiFetch;function wrapped(options){var path=String(options&&options.path||''),id=path+'#'+Date.now()+'-'+Math.random().toString(36).slice(2,6),began=Date.now();requests[id]={path:path,method:String(options&&options.method||'GET').toUpperCase(),status:'pending',startedAt:now()};add('request.start',{id:id,path:path});return Promise.resolve(original(options)).then(function(value){requests[id].status='fulfilled';requests[id].durationMs=Date.now()-began;add('request.success',{id:id,path:path,durationMs:requests[id].durationMs});return value},function(error){requests[id].status='rejected';requests[id].durationMs=Date.now()-began;requests[id].error=error&&error.message?String(error.message):String(error||'Request failed');add('request.error',{id:id,path:path,durationMs:requests[id].durationMs,error:requests[id].error});throw error})}try{Object.keys(original).forEach(function(k){wrapped[k]=original[k]})}catch(e){}if(typeof original.use==='function')wrapped.use=original.use.bind(original);wrapped.__crescoDiagnosticsWrapped=true;wp.apiFetch=wrapped;add('apiFetch.wrapped');return true}
var poll=window.setInterval(function(){if(wrapApiFetch())window.clearInterval(poll)},50);window.setTimeout(function(){window.clearInterval(poll)},10000);
function copy(){var text=JSON.stringify(snapshot(),null,2);if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(text);else{var a=document.createElement('textarea');a.value=text;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove()}}
function render(){var existing=document.getElementById('cresco-diagnostics-console');if(existing)existing.remove();if(!open)return;var s=snapshot(),box=document.createElement('div');box.id='cresco-diagnostics-console';box.style.cssText='position:fixed;right:16px;bottom:16px;z-index:999999;width:min(560px,calc(100vw - 32px));max-height:70vh;overflow:auto;background:#111827;color:#e5e7eb;border:1px solid #374151;border-radius:10px;box-shadow:0 18px 50px rgba(0,0,0,.35);font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;padding:12px';var status=s.ready?'READY':s.lastError?'ERROR':'LOADING';box.innerHTML='<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><strong style="font-size:13px">Cresco Diagnostics</strong><span style="margin-left:auto">'+status+' · '+s.elapsedMs+'ms</span><button id="cresco-diag-copy" type="button">Copy</button><button id="cresco-diag-close" type="button">×</button></div><pre style="white-space:pre-wrap;margin:0">'+String(JSON.stringify(s,null,2)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</pre>';document.body.appendChild(box);document.getElementById('cresco-diag-copy').onclick=copy;document.getElementById('cresco-diag-close').onclick=function(){open=false;render()}}
function ensureLauncher(){if(document.getElementById('cresco-diagnostics-launcher'))return;var b=document.createElement('button');b.id='cresco-diagnostics-launcher';b.type='button';b.textContent='Cresco Debug';b.style.cssText='position:fixed;right:16px;bottom:16px;z-index:999998;background:#111827;color:white;border:0;border-radius:999px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,.25);cursor:pointer';b.onclick=function(){open=!open;render()};document.body.appendChild(b)}
add('probe.loaded',{postId:cfg.postId});document.addEventListener('DOMContentLoaded',function(){ensureLauncher();add('dom.ready')},{once:true});if(document.readyState!=='loading')ensureLauncher();
window.setTimeout(function(){var s=snapshot();if(!s.ready){open=true;add('startup.stalled',{elapsedMs:s.elapsedMs});render()}},10000);
if(cfg.endpoint&&window.fetch){window.fetch(cfg.endpoint,{credentials:'same-origin',headers:{'X-WP-Nonce':cfg.nonce}}).then(function(r){return r.json()}).then(function(v){add('server.diagnostics',v)}).catch(function(e){add('server.diagnostics.error',{message:String(e&&e.message||e)})})}
window.setInterval(function(){if(open)render()},1000);
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
			'postId'              => $post_id,
			'postExists'          => $post instanceof \WP_Post,
			'postType'            => $post instanceof \WP_Post ? $post->post_type : '',
			'canEdit'             => current_user_can( 'edit_post', $post_id ),
			'pluginVersion'       => CRESCO_CANVAS_VERSION,
			'phpVersion'          => PHP_VERSION,
			'wordpressVersion'    => get_bloginfo( 'version' ),
			'sessionMetaPresent'  => '' !== $raw,
			'sessionBytes'        => strlen( $raw ),
			'sessionJsonValid'    => '' === $raw || is_array( $decoded ),
			'sessionSanitizeValid'=> null === $sanitized || ! is_wp_error( $sanitized ),
			'sessionSanitizeError'=> is_wp_error( $sanitized ) ? $sanitized->get_error_message() : '',
			'assets'              => $asset_status,
		);
	}

	private function is_editor_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return VisualEditor::PAGE_SLUG === $page;
	}
}
