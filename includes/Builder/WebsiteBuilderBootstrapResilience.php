<?php
namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Theme\ThemeBuilder;
use CrescoCanvas\Theme\ThemeSessionBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WebsiteBuilderBootstrapResilience {
	const HANDLE = 'cresco-canvas-website-builder-bootstrap';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 119 );
		add_action( 'admin_enqueue_scripts', array( $this, 'attach_request_guard' ), 121 );
		add_action( 'admin_enqueue_scripts', array( $this, 'attach_editor_watchdog' ), 122 );
		add_action( 'admin_enqueue_scripts', array( $this, 'attach_observer_guards' ), 1200 );
	}

	public function enqueue() {
		if ( ! $this->is_editor_request() ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = get_post( $post_id );
		$script = CRESCO_CANVAS_PATH . 'build/website-builder-bootstrap.js';
		if ( ! $post instanceof \WP_Post || ! is_readable( $script ) ) return;

		$is_theme = ThemeSessionBridge::PAGE_SLUG === $page;
		$paths = array(
			'session' => $is_theme ? '/cresco-canvas/v1/website-builder/theme-session/' . $post_id : '/cresco-canvas/v1/website-builder/session/' . $post_id,
			'context' => $is_theme ? '/cresco-canvas/v1/website-builder/theme-context/' . $post_id : '/cresco-canvas/v1/website-builder/context/' . $post_id,
			'options' => '/cresco-canvas/v1/website-builder/options',
			'components' => '/cresco-canvas/v1/website-builder/components',
			'pageSettings' => $is_theme ? '/cresco-canvas/v1/website-builder/theme-page-settings/' . $post_id : '/cresco-canvas/v1/page-settings/' . $post_id,
		
'themeTemplates' => '/cresco-canvas/v1/theme-templates',
			'globalSettings' => '/cresco-canvas/v1/settings',
		);

		wp_enqueue_script( self::HANDLE, CRESCO_CANVAS_URL . 'build/website-builder-bootstrap.js', array( 'wp-api-fetch' ), $this->asset_version( $script ), true );
		wp_add_inline_script( self::HANDLE, 'window.crescoWebsiteBuilderBootstrapSettings=' . wp_json_encode( array(
			'postId' => $post_id,
			'postTitle' => (string) $post->post_title,
			'builderVersion' => WebsiteBuilder::BUILDER_VERSION,
			'optionalTimeoutMs' => 2500,
			'criticalTimeoutMs' => 8000,
			'watchdogMs' => 10000,
			'paths' => $paths,
		) ) . ';', 'before' );
	}

	/**
	 * Emergency inline guard used only when the bootstrap middleware could not be
	 * installed. Normal requests are handled by website-builder-bootstrap.js,
	 * which now aborts the underlying fetch when a timeout expires.
	 */
	public function attach_request_guard() {
		if ( ! $this->is_editor_request() || ! wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) return;
		$guard = <<<'JS'
(function(window){
'use strict';
var wp=window.wp,settings=window.crescoWebsiteBuilderSettings||{},boot=window.crescoWebsiteBuilderBootstrapSettings||{},bootstrap=window.crescoWebsiteBuilderBootstrap||{},original=wp&&wp.apiFetch;
if(bootstrap.middlewareInstalled&&bootstrap.abortable){window.crescoWebsiteBuilderRequestGuard=bootstrap;return;}
if(!original||original.__crescoStartupGuard)return;
var bp=boot.paths||{},paths={session:settings.sessionPath||bp.session||'',context:settings.contextPath||bp.context||'',options:settings.optionsPath||bp.options||'',components:settings.componentsPath||bp.components||'',pageSettings:settings.pageSettingsPath||bp.pageSettings||'',themeTemplates:settings.themeTemplatesPath||bp.themeTemplates||'',globalSettings:settings.settingsPath||bp.globalSettings||''};
var state={startedAt:Date.now(),fallbacks:[],timeouts:[],aborted:[],fatal:null,active:{},abortable:!!window.AbortController};window.crescoWebsiteBuilderRequestGuard=state;
function method(o){return String(o&&o.method||'GET').toUpperCase()}function reqPath(o){return String(o&&o.path||'')}
function clone(v){if(v===null||v===undefined)return v;try{return JSON.parse(JSON.stringify(v))}catch(e){return v}}
function record(list,p,e){list.push({path:p,message:e&&e.message?String(e.message):String(e||'Request failed'),at:Date.now()})}
function fallback(p){if(p===paths.context)return{matched:true,value:{format:'cresco-website-builder-context/v1',builder:settings.builderVersion||boot.builderVersion||'website-core/v1',global:{},widgets:settings.widgetCatalog||{},session:null,postTitle:settings.postTitle||'',capabilities:{degraded:true},instructions:[]}};if(p===paths.options)return{matched:true,value:{menus:[],postTypes:[],taxonomies:[],woocommerce:false,acf:false,siteName:'',themeTypes:[]}};if(p===paths.components)return{matched:true,value:[]};if(p===paths.pageSettings)return{matched:true,value:{settings:{}}};if(p===paths.themeTemplates)return{matched:true,value:[]};if(p===paths.globalSettings)return{matched:true,value:null};return{matched:false,value:null}}
function guarded(options){var p=reqPath(options),fb=fallback(p),critical=p===paths.session&&!!p;if(method(options)!=='GET'||(!critical&&!fb.matched))return original(options);var ms=critical?Number(boot.criticalTimeoutMs||8000):Number(boot.optionalTimeoutMs||2500);return new Promise(function(resolve,reject){var settled=false,controller=window.AbortController?new AbortController():null,requestOptions=Object.assign({},options||{}),id=p+'#'+Date.now()+'-'+Math.random().toString(36).slice(2,7);if(controller)requestOptions.signal=controller.signal;state.active[id]={path:p,startedAt:Date.now(),timeoutMs:ms};function finish(){delete state.active[id]}var timer=window.setTimeout(function(){if(settled)return;settled=true;var e=new Error('Website Builder request timed out after '+ms+'ms: '+p);e.code='cresco_builder_startup_timeout';if(controller&&!controller.signal.aborted){controller.abort();record(state.aborted,p,e)}record(state.timeouts,p,e);finish();if(critical){state.fatal={path:p,message:e.message,at:Date.now()};reject(e)}else{record(state.fallbacks,p,e);resolve(clone(fb.value))}},ms);Promise.resolve(original(requestOptions)).then(function(v){if(settled)return;settled=true;window.clearTimeout(timer);finish();resolve(v)},function(e){if(settled)return;settled=true;window.clearTimeout(timer);finish();if(critical){state.fatal={path:p,message:e&&e.message?String(e.message):String(e||'Session request failed'),at:Date.now()};reject(e)}else{record(state.fallbacks,p,e);resolve(clone(fb.value))}})})}
try{Object.keys(original).forEach(function(k){guarded[k]=original[k]})}catch(e){}if(typeof original.use==='function')guarded.use=original.use.bind(original);guarded.__crescoStartupGuard=true;guarded.__crescoOriginal=original;wp.apiFetch=guarded;
})(window);
JS;
		wp_add_inline_script( 'cresco-canvas-website-builder', $guard, 'before' );
	}

	public function attach_editor_watchdog() {
		if ( ! $this->is_editor_request() || ! wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id_json = wp_json_encode( $post_id );
		$watchdog = <<<JS
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');if(!root)return;var startedAt=Date.now();
function ready(){return !!root.querySelector('.cc-builder-app')}
function diagnostics(){var b=window.crescoWebsiteBuilderBootstrap||{},g=window.crescoWebsiteBuilderRequestGuard||{},e=window.crescoWebsiteBuilderEditorBoot||{};return{postId:{$post_id_json},elapsedMs:Date.now()-startedAt,ready:ready(),documentReadyState:document.readyState,loading:!!root.querySelector('.cc-builder-loading'),settingsPresent:!!(window.crescoWebsiteBuilderSettings&&window.crescoWebsiteBuilderSettings.postId),wpElement:!!(window.wp&&window.wp.element),wpApiFetch:!!(window.wp&&window.wp.apiFetch),editorBoot:e,bootstrap:b,requestGuard:g,lastError:b.lastError||null}}
function copyText(v){if(navigator.clipboard&&navigator.clipboard.writeText)return navigator.clipboard.writeText(v);var a=document.createElement('textarea');a.value=v;a.style.position='fixed';a.style.opacity='0';document.body.appendChild(a);a.select();try{document.execCommand('copy')}catch(e){}a.remove();return Promise.resolve()}
function recover(){if(ready()||root.querySelector('[data-cresco-stall-recovery],[data-cresco-bootstrap-recovery]'))return;while(root.firstChild)root.removeChild(root.firstChild);var p=document.createElement('div');p.className='cc-builder-loading cc-builder-bootstrap-recovery';p.setAttribute('data-cresco-stall-recovery','1');p.setAttribute('role','alert');var s=document.createElement('strong');s.textContent='Cresco Website Builder could not finish loading.';p.appendChild(s);var m=document.createElement('p');m.textContent='A critical startup request did not finish. Optional modules are isolated from editor boot and timed-out requests are aborted. Your saved document has not been changed.';p.appendChild(m);var actions=document.createElement('div');actions.className='cc-builder-ai-actions';var retry=document.createElement('button');retry.type='button';retry.className='cc-builder-primary';retry.textContent='Retry';retry.onclick=function(){var u=new URL(window.location.href);u.searchParams.set('cresco-retry',String(Date.now()));window.location.href=u.toString()};actions.appendChild(retry);var copy=document.createElement('button');copy.type='button';copy.className='cc-builder-secondary';copy.textContent='Copy diagnostics';copy.onclick=function(){copyText(JSON.stringify(diagnostics(),null,2))};actions.appendChild(copy);p.appendChild(actions);var d=document.createElement('details'),sum=document.createElement('summary'),pre=document.createElement('pre');sum.textContent='Diagnostics';pre.textContent=JSON.stringify(diagnostics(),null,2);d.appendChild(sum);d.appendChild(pre);p.appendChild(d);root.appendChild(p)}
window.setTimeout(recover,10500);
})(window,document);
JS;
		wp_add_inline_script( 'cresco-canvas-website-builder', $watchdog, 'after' );
	}

	/**
	 * Prevent enhancement runtimes from observing DOM writes caused by their own
	 * MutationObserver callbacks. Each guarded observer is disconnected for the
	 * callback frame and the following animation frame, then reconnected. This
	 * breaks microtask/RAF feedback loops without disabling live editor updates.
	 */
	public function attach_observer_guards() {
		if ( ! $this->is_editor_request() ) return;

		$handles = array(
			'cresco-canvas-website-builder-controls',
			'cresco-canvas-website-builder-professional-ux',
			'cresco-canvas-builder-architecture',
		);

		$before = <<<'JS'
(function(window){
'use strict';
var Native=window.MutationObserver;
if(!Native||Native.__crescoObserverStabilityGuard)return;
var stack=window.__crescoObserverGuardStack=window.__crescoObserverGuardStack||[];
stack.push(Native);
function GuardedMutationObserver(callback){
 var self=this,target=null,options=null,active=false,generation=0;
 var observer=new Native(function(records){
  if(!active)return;
  var runGeneration=generation;
  observer.disconnect();
  window.requestAnimationFrame(function(){
   if(!active||runGeneration!==generation)return;
   var thrown=null;
   try{callback.call(self,records,self)}catch(error){thrown=error}
   window.requestAnimationFrame(function(){if(active&&target&&runGeneration===generation)observer.observe(target,options)});
   if(thrown)window.setTimeout(function(){throw thrown},0);
  });
 });
 self.observe=function(nextTarget,nextOptions){generation+=1;target=nextTarget;options=nextOptions||{};active=true;observer.disconnect();observer.observe(target,options)};
 self.disconnect=function(){generation+=1;active=false;target=null;options=null;observer.disconnect()};
 self.takeRecords=function(){return observer.takeRecords()};
}
GuardedMutationObserver.__crescoObserverStabilityGuard=true;
GuardedMutationObserver.__crescoNative=Native;
window.MutationObserver=GuardedMutationObserver;
window.crescoObserverStability={version:'observer-stability-v1',active:true};
})(window);
JS;

		$after = <<<'JS'
(function(window){
'use strict';
var stack=window.__crescoObserverGuardStack;
if(stack&&stack.length)window.MutationObserver=stack.pop();
if(window.crescoObserverStability)window.crescoObserverStability.active=false;
})(window);
JS;

		foreach ( $handles as $handle ) {
			if ( ! wp_script_is( $handle, 'registered' ) ) continue;
			wp_add_inline_script( $handle, $before, 'before' );
			wp_add_inline_script( $handle, $after, 'after' );
		}
	}

	private function is_editor_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( VisualEditor::PAGE_SLUG, ThemeSessionBridge::PAGE_SLUG ), true ) ) return false;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post_id ) ) return false;
		$expected_type = VisualEditor::PAGE_SLUG === $page ? 'page' : ThemeBuilder::POST_TYPE;
		return $expected_type === $post->post_type;
	}

	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}
}
