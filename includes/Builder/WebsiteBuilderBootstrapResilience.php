<?php
/**
 * Startup middleware and observer stability for Website Builder editors.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderBootstrapResilience {
	const HANDLE = 'cresco-canvas-website-builder-bootstrap';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 119 );
		add_action( 'admin_enqueue_scripts', array( $this, 'attach_request_guard' ), 121 );
		add_action( 'admin_enqueue_scripts', array( $this, 'attach_runtime_state' ), 122 );
		add_action( 'admin_enqueue_scripts', array( $this, 'attach_observer_guards' ), 1200 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderAsset::readable( 'build/website-builder-bootstrap.js' ) ) return;

		$config = WebsiteBuilderEditorConfig::for_context( $context );
		if ( ! $config ) return;

		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( 'build/website-builder-bootstrap.js' ),
			array( 'wp-api-fetch' ),
			WebsiteBuilderAsset::version( 'build/website-builder-bootstrap.js' ),
			true
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.crescoWebsiteBuilderBootstrapSettings=' . wp_json_encode(
				array(
					'postId'            => $context->post_id(),
					'postTitle'         => (string) ( $config['postTitle'] ?? '' ),
					'documentType'      => $context->document_type(),
					'builderVersion'    => WebsiteBuilder::BUILDER_VERSION,
					'optionalTimeoutMs' => 2500,
					'criticalTimeoutMs' => 8000,
					'watchdogMs'        => 10000,
					'paths'             => WebsiteBuilderEditorConfig::bootstrap_paths( $context ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Emergency request guard used only when the dedicated bootstrap middleware
	 * could not install. Optional requests degrade; only the Session is critical.
	 */
	public function attach_request_guard() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) return;

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

	/** Publish one lightweight startup state object; RuntimeGuard owns recovery UI. */
	public function attach_runtime_state() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) return;
		$post_id = wp_json_encode( $context->post_id() );
		$state   = <<<JS
(function(window,document){
'use strict';
var started=Date.now(),state=window.crescoRuntimeState=window.crescoRuntimeState||{phase:'CORE_LOADED',postId:{$post_id},startedAt:Date.now(),events:[]};
function emit(phase,detail){state.phase=phase;state.updatedAt=Date.now();state.events.push({at:state.updatedAt-started,phase:phase,detail:detail||null});if(state.events.length>80)state.events.shift();}
emit('CORE_LOADED');
function inspect(){var root=document.getElementById('cresco-canvas-standalone-editor'),app=root&&root.querySelector('.cc-builder-app');if(app){emit('READY');return true}var editor=window.crescoWebsiteBuilderEditorBoot||{},bootstrap=window.crescoWebsiteBuilderBootstrap||window.crescoWebsiteBuilderRequestGuard||{};if(editor.phase==='session')emit('SESSION_PENDING');if(bootstrap.fatal)emit('FAILED',bootstrap.fatal);return false;}
var timer=window.setInterval(function(){if(inspect())window.clearInterval(timer)},250);window.setTimeout(function(){window.clearInterval(timer);if(!inspect()&&state.phase!=='FAILED')emit('FAILED',{reason:'startup-timeout'});},10500);
})(window,document);
JS;
		wp_add_inline_script( 'cresco-canvas-website-builder', $state, 'after' );
	}

	/**
	 * Guard enhancement observers against mutating the DOM they are currently
	 * observing. The wrapper is active only while each optional runtime boots.
	 */
	public function attach_observer_guards() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context ) return;

		$before = <<<'JS'
(function(window){
'use strict';
var Native=window.MutationObserver;
if(!Native||Native.__crescoObserverStabilityGuard)return;
var stack=window.__crescoObserverGuardStack=window.__crescoObserverGuardStack||[];stack.push(Native);
function GuardedMutationObserver(callback){var self=this,target=null,options=null,active=false,generation=0;var observer=new Native(function(records){if(!active)return;var runGeneration=generation;observer.disconnect();window.requestAnimationFrame(function(){if(!active||runGeneration!==generation)return;var thrown=null;try{callback.call(self,records,self)}catch(error){thrown=error}window.requestAnimationFrame(function(){if(active&&target&&runGeneration===generation)observer.observe(target,options)});if(thrown)window.setTimeout(function(){throw thrown},0);});});self.observe=function(nextTarget,nextOptions){generation+=1;target=nextTarget;options=nextOptions||{};active=true;observer.disconnect();observer.observe(target,options)};self.disconnect=function(){generation+=1;active=false;target=null;options=null;observer.disconnect()};self.takeRecords=function(){return observer.takeRecords()};}
GuardedMutationObserver.__crescoObserverStabilityGuard=true;GuardedMutationObserver.__crescoNative=Native;window.MutationObserver=GuardedMutationObserver;window.crescoObserverStability={version:'observer-stability-v1',active:true};
})(window);
JS;
		$after = <<<'JS'
(function(window){'use strict';var stack=window.__crescoObserverGuardStack;if(stack&&stack.length)window.MutationObserver=stack.pop();if(window.crescoObserverStability)window.crescoObserverStability.active=false;})(window);
JS;

		foreach ( WebsiteBuilderModuleRegistry::all() as $key => $module ) {
			if ( in_array( $key, array( 'bootstrap', 'core' ), true ) ) continue;
			foreach ( $module['scripts'] as $asset ) {
				$handle = $asset['handle'];
				if ( ! wp_script_is( $handle, 'registered' ) ) continue;
				wp_add_inline_script( $handle, $before, 'before' );
				wp_add_inline_script( $handle, $after, 'after' );
			}
		}
	}
}
