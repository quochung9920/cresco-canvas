<?php
/**
 * Studio/frontend visual parity helpers for Website Builder documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Session\SessionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderVisualParity {
	const EDITOR_SCRIPT_HANDLE   = 'cresco-canvas-website-builder';
	const EDITOR_STYLE_HANDLE    = 'cresco-canvas-website-builder-ui-correction';
	const FRONTEND_STYLE_HANDLE  = 'cresco-canvas-website-builder-frontend';
	const STYLE_CONTRACT_VERSION = 'authoritative-v4';

	/** Apply one render/style contract at the last editor and frontend boundaries. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_parity' ), 1500 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_parity' ), 2000 );
		add_filter( 'the_content', array( $this, 'embed_frontend_parity' ), 110 );
	}

	/**
	 * Replace the Studio-only mock visual surface with the existing authoritative
	 * RenderEngine endpoint. The hidden React canvas remains the interaction/state
	 * owner, while the iframe is the visual owner.
	 */
	public function enqueue_editor_parity() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! wp_script_is( self::EDITOR_SCRIPT_HANDLE, 'enqueued' ) ) return;

		$style_handle = wp_style_is( self::EDITOR_STYLE_HANDLE, 'enqueued' )
			? self::EDITOR_STYLE_HANDLE
			: self::EDITOR_SCRIPT_HANDLE;
		wp_add_inline_style( $style_handle, self::editor_css() );
		wp_add_inline_script( self::EDITOR_SCRIPT_HANDLE, self::editor_script(), 'after' );
	}

	/** Append the authoritative CSS after every compatibility compiler. */
	public function enqueue_frontend_parity() {
		$document = $this->frontend_document();
		if ( ! $document || ! wp_style_is( self::FRONTEND_STYLE_HANDLE, 'enqueued' ) ) return;
		$css = self::frontend_css( $document['session'], $document['architecture'] );
		if ( '' === $css ) return;
		wp_add_inline_style(
			self::FRONTEND_STYLE_HANDLE,
			'/* cresco-style-contract:' . self::STYLE_CONTRACT_VERSION . ' */' . $css
		);
	}

	/**
	 * Bind final V2 markup to CSS compiled from the exact same persisted Session.
	 * A body-local style element intentionally wins over theme/head compatibility
	 * fragments, including historical container width rules.
	 */
	public function embed_frontend_parity( $content ) {
		if ( ! is_string( $content ) || '' === $content ) return $content;
		if ( false === strpos( $content, 'cresco-website-builder-root' ) ) return $content;
		if ( false !== strpos( $content, 'data-cresco-style-contract="' . self::STYLE_CONTRACT_VERSION . '"' ) ) return $content;

		$document = $this->frontend_document();
		if ( ! $document ) return $content;
		$css = self::frontend_css( $document['session'], $document['architecture'] );
		if ( '' === $css ) return $content;
		$hash = substr( hash( 'sha256', $css ), 0, 16 );
		return '<style data-cresco-style-contract="' . esc_attr( self::STYLE_CONTRACT_VERSION ) . '" data-cresco-style-hash="' . esc_attr( $hash ) . '">' . $css . '</style>' . $content;
	}

	/** Compile the final root and Widget Architecture v2 part-style contract. */
	public static function frontend_css( $session, $architecture = array() ) {
		if ( ! is_array( $session ) || empty( $session['nodes'] ) ) return '';
		return self::surface_css()
			. WebsiteBuilderCssCompiler::compile( $session )
			. WidgetPartStyleCompiler::compile( $session, (array) $architecture );
	}

	/**
	 * Theme-independent geometry boundary. Do not set widget widths here: those
	 * belong to the structured document compiler. This layer only normalizes box
	 * sizing and min-content behavior so the same node CSS wins everywhere.
	 */
	public static function surface_css() {
		return '.cresco-website-builder-root{width:100%;min-width:0;max-width:none;}'
			. '.cresco-website-builder-root,.cresco-website-builder-root *{box-sizing:border-box;}'
			. '.cresco-website-builder-root [data-cresco-id]{min-width:0;}'
			. '.cresco-website-builder-root img{max-width:100%;height:auto;}';
	}

	/** Load exactly the persisted frontend document used by the final renderer. */
	private function frontend_document() {
		if ( is_admin() || ! is_singular( 'page' ) ) return null;
		$post_id = absint( get_queried_object_id() );
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return null;
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		if ( '' === $raw ) return null;
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) return null;
		$session = WebsiteBuilder::sanitize_session( $decoded );
		if ( is_wp_error( $session ) || empty( $session['nodes'] ) ) return null;
		return array(
			'postId'       => $post_id,
			'session'      => $session,
			'architecture' => WebsiteBuilderArchitectureV2::load_document( $post_id, $session ),
		);
	}

	/** Studio frame styles for the authoritative same-origin renderer surface. */
	public static function editor_css() {
		return '.cc-studio-frame{position:relative;}'
			. '.cc-studio-canonical-preview{display:none;width:100%;min-height:720px;border:0;background:#fff;}'
			. '.cc-studio-frame.is-cresco-canonical-preview>.cc-studio-canonical-preview{display:block;}'
			. '.cc-studio-frame.is-cresco-canonical-preview>.cc-studio-canvas{display:none!important;}'
			. '.cc-studio-frame.is-cresco-canonical-drag>.cc-studio-canonical-preview{display:none!important;}'
			. '.cc-studio-frame.is-cresco-canonical-drag>.cc-studio-canvas{display:block!important;}'
			. '.cc-studio-canonical-preview-status{position:absolute;right:8px;top:8px;z-index:30;pointer-events:none;padding:3px 7px;border-radius:999px;background:rgba(17,24,39,.78);color:#fff;font:600 9px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;opacity:.72;}'
			. '.cc-studio-frame:not(.is-cresco-canonical-preview)>.cc-studio-canonical-preview-status{display:none;}';
	}

	/**
	 * Use /website-builder/render/{postId}, the same RenderEngine boundary already
	 * exposed by BuilderArchitecture, as the Studio visual surface. React's canvas
	 * remains mounted but hidden so selection, commands, DnD, undo and inspectors
	 * keep their existing ownership. Clicks in the iframe are relayed by stable id.
	 */
	public static function editor_script() {
		return <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
var wp=window.wp,settings=window.crescoWebsiteBuilderSettings||{};
if(!root||!wp||!wp.apiFetch||!settings.postId)return;
var api=wp.apiFetch,postId=Number(settings.postId||0),renderPath='/cresco-canvas/v1/website-builder/render/'+postId;
var latestSession=null,frame=null,legacy=null,preview=null,status=null,timer=null,lastSignature='',requestId=0,resizeObserver=null;
function arr(v){return Array.isArray(v)?v:[];}
function obj(v){return v&&typeof v==='object'&&!Array.isArray(v)?v:{};}
function stateSession(){
 if(latestSession&&Array.isArray(latestSession.nodes))return latestSession;
 var store=window.crescoDocumentStore;
 if(store&&typeof store.getState==='function'){
  var state=store.getState()||{},candidate=state.document||state.session||null;
  if(candidate&&Array.isArray(candidate.nodes))return candidate;
 }
 var runtime=window.crescoRuntimeState||{};
 return runtime.session&&Array.isArray(runtime.session.nodes)?runtime.session:null;
}
function architectureSettings(){return window.crescoBuilderArchitectureSettings||{};}
function architecturePath(){var cfg=architectureSettings();return cfg.renderPath||renderPath;}
function pluginBase(){
 var found=Array.prototype.slice.call(document.scripts).map(function(s){return String(s.src||'');}).find(function(src){return /\/build\/website-builder-(?:studio|editor)\.js(?:\?|$)/.test(src);});
 if(!found)return'';
 try{var u=new URL(found,window.location.href);u.pathname=u.pathname.replace(/\/build\/[^/]+$/,'/');u.search='';u.hash='';return u.href;}catch(error){return found.replace(/build\/[^/?]+(?:\?.*)?$/,'');}
}
function tokenCSS(){
 var source=root.querySelector('.cc-builder-canvas')||root.querySelector('.cc-studio-canvas')||root;
 var style=window.getComputedStyle(source),fallback=window.getComputedStyle(document.documentElement);
 var names=['--cc-primary','--cc-text','--cc-muted','--cc-background','--cc-font','--cc-font-base','--cc-h1','--cc-h2','--cc-h3','--cc-h4','--cc-h5','--cc-h6','--cc-container-gutter','--cc-container-max','--cc-grid-gap','--cc-space-xs','--cc-space-sm','--cc-space-md','--cc-space-lg','--cc-space-xl','--cc-radius-sm','--cc-radius-md','--cc-radius-lg','--cc-control-height','--cc-button-padding'];
 var defaults={'--cc-primary':'#635bff','--cc-text':'#1d2939','--cc-muted':'#667085','--cc-background':'#fff','--cc-font':'-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif','--cc-font-base':'16px','--cc-h1':'clamp(2.25rem,5vw,4.5rem)','--cc-h2':'clamp(1.9rem,4vw,3.5rem)','--cc-h3':'clamp(1.5rem,3vw,2.5rem)','--cc-h4':'1.5rem','--cc-h5':'1.25rem','--cc-h6':'1rem','--cc-container-gutter':'24px','--cc-container-max':'1320px','--cc-grid-gap':'24px','--cc-space-xs':'8px','--cc-space-sm':'12px','--cc-space-md':'16px','--cc-space-lg':'24px','--cc-space-xl':'32px','--cc-radius-sm':'4px','--cc-radius-md':'8px','--cc-radius-lg':'16px','--cc-control-height':'44px','--cc-button-padding':'18px'};
 return ':root{'+names.map(function(name){var value=String(style.getPropertyValue(name)||fallback.getPropertyValue(name)||defaults[name]||'').trim();return value?name+':'+value+';':'';}).join('')+'}';
}
function baseVisualCSS(){
 return 'html,body{margin:0!important;padding:0!important;min-width:0;background:var(--cc-background);color:var(--cc-text);}'
  +'body.cresco-canvas-page{font-family:var(--cc-font);font-size:var(--cc-font-base);line-height:1.65;}'
  +'body.cresco-canvas-page h1{font-size:var(--cc-h1);line-height:1.12;}body.cresco-canvas-page h2{font-size:var(--cc-h2);line-height:1.12;}body.cresco-canvas-page h3{font-size:var(--cc-h3);line-height:1.15;}'
  +'body.cresco-canvas-page h4{font-size:var(--cc-h4);}body.cresco-canvas-page h5{font-size:var(--cc-h5);}body.cresco-canvas-page h6{font-size:var(--cc-h6);}'
  +'.cresco-website-builder-root{width:100%;min-width:0;max-width:none}.cresco-website-builder-root,.cresco-website-builder-root *{box-sizing:border-box}.cresco-website-builder-root [data-cresco-id]{min-width:0}'
  +'[data-cresco-editor-selected="1"]{outline:2px solid #7c3aed!important;outline-offset:1px!important;}';
}
function escapeAttr(value){return String(value||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function srcdoc(render){
 var base=pluginBase(),links='';
 if(base){['assets/css/frontend.css','assets/css/website-builder-frontend.css','assets/css/forms.css'].forEach(function(path){links+='<link rel="stylesheet" href="'+escapeAttr(base+path)+'">';});}
 return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+links+'<style>'+tokenCSS()+baseVisualCSS()+String(render.css||'')+'</style></head><body class="cresco-canvas-page"><div data-cresco-canonical-surface="1">'+String(render.html||'')+'</div></body></html>';
}
function ensureSurface(){
 var nextFrame=root.querySelector('.cc-studio-frame');if(!nextFrame)return false;
 var nextLegacy=nextFrame.querySelector(':scope > .cc-studio-canvas')||nextFrame.querySelector('.cc-studio-canvas');if(!nextLegacy)return false;
 if(frame===nextFrame&&legacy===nextLegacy&&preview&&preview.isConnected)return true;
 frame=nextFrame;legacy=nextLegacy;
 preview=document.createElement('iframe');preview.className='cc-studio-canonical-preview';preview.title='Cresco canonical renderer';preview.setAttribute('sandbox','allow-same-origin');preview.setAttribute('data-cresco-canonical-preview','1');
 status=document.createElement('span');status.className='cc-studio-canonical-preview-status';status.textContent='Frontend renderer';
 frame.insertBefore(preview,legacy);frame.appendChild(status);
 preview.addEventListener('load',bindPreview);
 return true;
}
function fakeNode(id){if(!legacy)return null;var nodes=legacy.querySelectorAll('.cc-studio-canvas-node[data-cresco-id]');for(var i=0;i<nodes.length;i++)if(nodes[i].getAttribute('data-cresco-id')===id)return nodes[i];return null;}
function relay(event,type){
 var target=event.target&&event.target.closest?event.target.closest('[data-cresco-id]'):null;if(!target)return;
 event.preventDefault();event.stopPropagation();var id=target.getAttribute('data-cresco-id'),fake=fakeNode(id);if(!fake)return;
 var rect=preview.getBoundingClientRect(),x=rect.left+(event.clientX||0),y=rect.top+(event.clientY||0);
 fake.dispatchEvent(new MouseEvent(type,{bubbles:true,cancelable:true,view:window,clientX:x,clientY:y,ctrlKey:!!event.ctrlKey,metaKey:!!event.metaKey,shiftKey:!!event.shiftKey,altKey:!!event.altKey,button:event.button||0}));
 window.setTimeout(syncSelection,0);
}
function syncSelection(){
 if(!preview||!preview.contentDocument)return;var doc=preview.contentDocument;
 Array.prototype.slice.call(doc.querySelectorAll('[data-cresco-editor-selected]')).forEach(function(el){el.removeAttribute('data-cresco-editor-selected');});
 if(!legacy)return;Array.prototype.slice.call(legacy.querySelectorAll('.cc-studio-canvas-node.is-selected[data-cresco-id]')).forEach(function(el){var id=el.getAttribute('data-cresco-id'),node=doc.querySelector('[data-cresco-id="'+id+'"]');if(node)node.setAttribute('data-cresco-editor-selected','1');});
}
function resize(){
 if(!preview||!preview.contentDocument)return;var doc=preview.contentDocument,body=doc.body,html=doc.documentElement;if(!body||!html)return;
 var height=Math.max(720,body.scrollHeight,body.offsetHeight,html.scrollHeight,html.offsetHeight);preview.style.height=height+'px';
}
function bindPreview(){
 if(resizeObserver){try{resizeObserver.disconnect();}catch(error){}resizeObserver=null;}
 var doc=preview.contentDocument;if(!doc)return;
 doc.addEventListener('click',function(e){relay(e,'click');},true);doc.addEventListener('contextmenu',function(e){relay(e,'contextmenu');},true);doc.addEventListener('submit',function(e){e.preventDefault();},true);
 var win=preview.contentWindow;if(win&&win.ResizeObserver){resizeObserver=new win.ResizeObserver(resize);resizeObserver.observe(doc.documentElement);}resize();syncSelection();
}
function showLegacy(){if(frame)frame.classList.remove('is-cresco-canonical-preview');}
function activate(){if(frame){frame.classList.remove('is-cresco-canonical-drag');frame.classList.add('is-cresco-canonical-preview');}}
function signature(session){try{return JSON.stringify(session||null);}catch(error){return String(Date.now());}}
function refresh(force){
 timer=null;if(!ensureSurface())return window.setTimeout(function(){schedule(true);},120);
 var session=stateSession();if(session&&arr(session.nodes).length===0){showLegacy();return;}
 var sig=signature(session);if(!force&&sig===lastSignature)return;var current=++requestId,data={};if(session)data.currentSession=session;
 if(status)status.textContent='Rendering…';
 api({path:architecturePath(),method:'POST',data:data}).then(function(response){
  if(current!==requestId)return;var render=response&&response.render?response.render:response;if(!render||typeof render.html!=='string')throw new Error('Canonical render payload is unavailable.');
  lastSignature=sig;preview.srcdoc=srcdoc(render);activate();if(status)status.textContent='Frontend renderer';
 }).catch(function(){if(current!==requestId)return;showLegacy();if(status)status.textContent='Renderer unavailable';});
}
function schedule(force){window.clearTimeout(timer);timer=window.setTimeout(function(){refresh(!!force);},force?30:140);}
window.addEventListener('cresco:studio-session-change',function(event){var detail=event&&event.detail||{};if(detail.session&&Array.isArray(detail.session.nodes))latestSession=detail.session;schedule(false);});
window.addEventListener('cresco:document-store-change',function(){schedule(false);});
window.addEventListener('cresco:studio-ready',function(){schedule(true);});
window.addEventListener('cresco:architecture-ready',function(){schedule(true);});
root.addEventListener('input',function(){schedule(false);},true);root.addEventListener('change',function(){schedule(false);},true);
root.addEventListener('dragstart',function(){if(frame)frame.classList.add('is-cresco-canonical-drag');},true);
root.addEventListener('dragend',function(){if(frame){frame.classList.remove('is-cresco-canonical-drag');activate();}schedule(false);},true);
root.addEventListener('drop',function(){window.setTimeout(function(){if(frame){frame.classList.remove('is-cresco-canonical-drag');activate();}schedule(true);},0);},true);
if(window.MutationObserver)new MutationObserver(function(records){var selectionOnly=records.length&&records.every(function(r){return r.type==='attributes'&&r.attributeName==='class';});if(selectionOnly)syncSelection();else schedule(false);}).observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['class','style']});
window.crescoCanonicalEditorPreview={version:'canonical-v1',refresh:function(){schedule(true);},disable:showLegacy};
window.setTimeout(function(){schedule(true);},250);
})(window,document);
JS;
	}

	public function __construct() {}
}
