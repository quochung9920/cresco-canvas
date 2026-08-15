<?php
/**
 * Canonical-only visual owner for Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderCanonicalPreviewOwner {
	const EDITOR_SCRIPT_HANDLE = 'cresco-canvas-website-builder';
	const EDITOR_STYLE_HANDLE  = 'cresco-canvas-website-builder-ui-correction';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'claim_visual_ownership' ), 1490 );
	}

	/**
	 * Remove the previous dual-surface editor parity callback before it runs,
	 * then install one canonical renderer surface. The hidden React canvas may
	 * remain temporarily as an interaction bridge, but it can never be visible.
	 */
	public function claim_visual_ownership() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! wp_script_is( self::EDITOR_SCRIPT_HANDLE, 'enqueued' ) ) return;

		$this->remove_object_method( 'admin_enqueue_scripts', WebsiteBuilderVisualParity::class, 'enqueue_editor_parity' );

		$style_handle = wp_style_is( self::EDITOR_STYLE_HANDLE, 'enqueued' )
			? self::EDITOR_STYLE_HANDLE
			: self::EDITOR_SCRIPT_HANDLE;
		wp_add_inline_style( $style_handle, self::editor_css() );
		wp_add_inline_script( self::EDITOR_SCRIPT_HANDLE, self::editor_script(), 'after' );
	}

	private function remove_object_method( $hook_name, $class_name, $method_name ) {
		global $wp_filter;
		$hook = $wp_filter[ $hook_name ] ?? null;
		if ( ! $hook instanceof \WP_Hook || empty( $hook->callbacks ) ) return;
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'] ?? null;
				if ( ! is_array( $function ) || ! isset( $function[0], $function[1] ) ) continue;
				if ( ! is_object( $function[0] ) || ! is_a( $function[0], $class_name ) || $method_name !== $function[1] ) continue;
				remove_action( $hook_name, $function, (int) $priority );
			}
		}
	}

	/** Canonical preview is always the visual surface; the legacy canvas is never shown. */
	public static function editor_css() {
		return '.cc-studio-frame{position:relative;min-height:720px;isolation:isolate;}'
			. '.cc-studio-frame>.cc-studio-canvas{display:none!important;visibility:hidden!important;pointer-events:none!important;}'
			. '.cc-studio-frame>.cc-studio-canonical-preview{display:block!important;width:100%;min-height:720px;border:0;background:#fff;opacity:0;pointer-events:none;transition:opacity .16s ease;}'
			. '.cc-studio-frame.is-cresco-canonical-ready>.cc-studio-canonical-preview{opacity:1;pointer-events:auto;}'
			. '.cc-studio-frame.is-cresco-canonical-error>.cc-studio-canonical-preview{opacity:.16;pointer-events:none;}'
			. '.cc-studio-frame:not(.is-cresco-canonical-owner)::before{content:"Loading preview…";position:absolute;inset:0;z-index:35;display:grid;place-items:center;background:#f8fafc;color:#667085;font:600 12px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
			. '.cc-studio-frame.is-cresco-canonical-owner::before{display:none;}'
			. '.cc-studio-canonical-overlay{position:absolute;inset:0;z-index:40;display:none;place-content:center;justify-items:center;gap:10px;padding:28px;background:rgba(248,250,252,.96);color:#344054;text-align:center;font:500 12px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
			. '.cc-studio-frame.is-cresco-canonical-loading>.cc-studio-canonical-overlay,.cc-studio-frame.is-cresco-canonical-error>.cc-studio-canonical-overlay{display:grid;}'
			. '.cc-studio-canonical-overlay__spinner{width:30px;height:30px;border:3px solid rgba(99,91,255,.15);border-top-color:#635bff;border-radius:999px;animation:cresco-canonical-spin .8s linear infinite;}'
			. '.cc-studio-frame.is-cresco-canonical-error .cc-studio-canonical-overlay__spinner{display:none;}'
			. '.cc-studio-canonical-overlay__retry{display:none;min-height:34px;padding:0 13px;border:1px solid #635bff;border-radius:7px;background:#635bff;color:#fff;font:600 12px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;cursor:pointer;}'
			. '.cc-studio-frame.is-cresco-canonical-error .cc-studio-canonical-overlay__retry{display:inline-flex;align-items:center;justify-content:center;}'
			. '.cc-studio-canonical-preview-status{position:absolute;right:8px;top:8px;z-index:30;pointer-events:none;padding:3px 7px;border-radius:999px;background:rgba(17,24,39,.78);color:#fff;font:600 9px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;opacity:.72;}'
			. '.cc-studio-frame:not(.is-cresco-canonical-ready)>.cc-studio-canonical-preview-status{display:none;}'
			. '@keyframes cresco-canonical-spin{to{transform:rotate(360deg)}}'
			. '@media (prefers-reduced-motion:reduce){.cc-studio-frame>.cc-studio-canonical-preview{transition:none}.cc-studio-canonical-overlay__spinner{animation:none;border-top-color:rgba(99,91,255,.15);background:radial-gradient(circle,#635bff 0 30%,transparent 33%)}}';
	}

	/**
	 * Drive Studio from one same-origin RenderEngine surface. The hidden React
	 * canvas is optional and used only as a temporary event bridge while direct
	 * canonical interactions are completed.
	 */
	public static function editor_script() {
		return <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
var wp=window.wp,settings=window.crescoWebsiteBuilderSettings||{};
if(!root||!wp||!wp.apiFetch||!settings.postId)return;
var api=wp.apiFetch,postId=Number(settings.postId||0),renderPath='/cresco-canvas/v1/website-builder/render/'+postId;
var latestSession=null,frame=null,legacy=null,preview=null,overlay=null,overlayText=null,retryButton=null,status=null,timer=null,lastSignature='',requestId=0,resizeObserver=null,visualState='booting';
function arr(v){return Array.isArray(v)?v:[];}
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
function setState(next,message){
 visualState=next;
 if(!frame)return;
 frame.classList.add('is-cresco-canonical-owner');
 frame.classList.remove('is-cresco-canonical-loading','is-cresco-canonical-ready','is-cresco-canonical-error','is-cresco-canonical-drag');
 frame.classList.add('is-cresco-canonical-'+next);
 if(overlayText&&message)overlayText.textContent=message;
 if(status)status.textContent='Frontend renderer';
}
function ensureSurface(){
 var nextFrame=root.querySelector('.cc-studio-frame');if(!nextFrame)return false;
 var nextLegacy=nextFrame.querySelector(':scope > .cc-studio-canvas')||nextFrame.querySelector('.cc-studio-canvas')||null;
 if(frame===nextFrame&&preview&&preview.isConnected){legacy=nextLegacy;return true;}
 if(resizeObserver){try{resizeObserver.disconnect();}catch(error){}resizeObserver=null;}
 frame=nextFrame;legacy=nextLegacy;
 preview=frame.querySelector(':scope > .cc-studio-canonical-preview[data-cresco-canonical-owner="v2"]');
 if(!preview){
  preview=document.createElement('iframe');preview.className='cc-studio-canonical-preview';preview.title='Cresco canonical renderer';preview.setAttribute('sandbox','allow-same-origin');preview.setAttribute('data-cresco-canonical-preview','1');preview.setAttribute('data-cresco-canonical-owner','v2');
  if(legacy)frame.insertBefore(preview,legacy);else frame.appendChild(preview);
  preview.addEventListener('load',bindPreview);
 }
 overlay=frame.querySelector(':scope > .cc-studio-canonical-overlay');
 if(!overlay){
  overlay=document.createElement('div');overlay.className='cc-studio-canonical-overlay';overlay.setAttribute('role','status');overlay.setAttribute('aria-live','polite');
  var spinner=document.createElement('span');spinner.className='cc-studio-canonical-overlay__spinner';spinner.setAttribute('aria-hidden','true');
  overlayText=document.createElement('strong');overlayText.className='cc-studio-canonical-overlay__text';overlayText.textContent='Rendering preview…';
  retryButton=document.createElement('button');retryButton.type='button';retryButton.className='cc-studio-canonical-overlay__retry';retryButton.textContent='Retry renderer';retryButton.addEventListener('click',function(){schedule(true);});
  overlay.appendChild(spinner);overlay.appendChild(overlayText);overlay.appendChild(retryButton);frame.appendChild(overlay);
 }else{
  overlayText=overlay.querySelector('.cc-studio-canonical-overlay__text');retryButton=overlay.querySelector('.cc-studio-canonical-overlay__retry');
 }
 status=frame.querySelector(':scope > .cc-studio-canonical-preview-status');
 if(!status){status=document.createElement('span');status.className='cc-studio-canonical-preview-status';status.textContent='Frontend renderer';frame.appendChild(status);}
 setState('loading','Rendering preview…');
 return true;
}
function legacyNode(id){
 if(!legacy)return null;var nodes=legacy.querySelectorAll('.cc-studio-canvas-node[data-cresco-id]');for(var i=0;i<nodes.length;i++)if(nodes[i].getAttribute('data-cresco-id')===id)return nodes[i];return null;
}
function structureRow(id){
 var rows=root.querySelectorAll('.cc-studio-tree-row[data-cresco-node-id]');for(var i=0;i<rows.length;i++)if(rows[i].getAttribute('data-cresco-node-id')===id)return rows[i];return null;
}
function relaySelection(event,type){
 var target=event.target&&event.target.closest?event.target.closest('[data-cresco-id]'):null;if(!target)return;
 event.preventDefault();event.stopPropagation();var id=target.getAttribute('data-cresco-id'),row=structureRow(id),bridge=row&&(row.querySelector('.cc-studio-tree-select')||row);
 if(!bridge)bridge=legacyNode(id);if(!bridge)return;
 var rect=preview.getBoundingClientRect(),x=rect.left+(event.clientX||0),y=rect.top+(event.clientY||0);
 bridge.dispatchEvent(new MouseEvent(type,{bubbles:true,cancelable:true,view:window,clientX:x,clientY:y,ctrlKey:!!event.ctrlKey,metaKey:!!event.metaKey,shiftKey:!!event.shiftKey,altKey:!!event.altKey,button:event.button||0}));
 window.setTimeout(syncSelection,0);
}
function relayDrag(event,type){
 var target=event.target&&event.target.closest?event.target.closest('[data-cresco-id]'):null;if(!target)return;
 var id=target.getAttribute('data-cresco-id'),bridge=legacyNode(id);if(!bridge)return;
 var dropTarget=bridge.querySelector('.cc-builder-dropzone')||bridge;event.preventDefault();event.stopPropagation();
 var init={bubbles:true,cancelable:true,dataTransfer:event.dataTransfer,clientX:event.clientX||0,clientY:event.clientY||0,ctrlKey:!!event.ctrlKey,metaKey:!!event.metaKey,shiftKey:!!event.shiftKey,altKey:!!event.altKey};
 try{dropTarget.dispatchEvent(new DragEvent(type,init));}catch(error){var forwarded=new Event(type,{bubbles:true,cancelable:true});try{Object.defineProperty(forwarded,'dataTransfer',{value:event.dataTransfer});}catch(ignore){}dropTarget.dispatchEvent(forwarded);}
 if(type==='drop')window.setTimeout(function(){schedule(true);},0);
}
function selectedIds(){
 var out=[],rows=root.querySelectorAll('.cc-studio-tree-row.is-selected[data-cresco-node-id]');for(var i=0;i<rows.length;i++){var id=rows[i].getAttribute('data-cresco-node-id');if(id)out.push(id);}if(out.length||!legacy)return out;
 var nodes=legacy.querySelectorAll('.cc-studio-canvas-node.is-selected[data-cresco-id]');for(var j=0;j<nodes.length;j++){var legacyId=nodes[j].getAttribute('data-cresco-id');if(legacyId)out.push(legacyId);}return out;
}
function syncSelection(){
 if(!preview||!preview.contentDocument)return;var doc=preview.contentDocument,ids=selectedIds();
 Array.prototype.slice.call(doc.querySelectorAll('[data-cresco-editor-selected]')).forEach(function(el){el.removeAttribute('data-cresco-editor-selected');});
 ids.forEach(function(id){var nodes=doc.querySelectorAll('[data-cresco-id]');for(var i=0;i<nodes.length;i++)if(nodes[i].getAttribute('data-cresco-id')===id){nodes[i].setAttribute('data-cresco-editor-selected','1');break;}});
}
function resize(){
 if(!preview||!preview.contentDocument)return;var doc=preview.contentDocument,body=doc.body,html=doc.documentElement;if(!body||!html)return;
 var height=Math.max(720,body.scrollHeight,body.offsetHeight,html.scrollHeight,html.offsetHeight);preview.style.height=height+'px';
}
function bindPreview(){
 if(!preview||preview.dataset.crescoHasRender!=='1')return;
 if(resizeObserver){try{resizeObserver.disconnect();}catch(error){}resizeObserver=null;}
 var doc=preview.contentDocument;if(!doc)return;
 doc.addEventListener('click',function(e){relaySelection(e,'click');},true);doc.addEventListener('contextmenu',function(e){relaySelection(e,'contextmenu');},true);doc.addEventListener('submit',function(e){e.preventDefault();},true);
 doc.addEventListener('dragenter',function(e){relayDrag(e,'dragenter');},true);doc.addEventListener('dragover',function(e){relayDrag(e,'dragover');},true);doc.addEventListener('drop',function(e){relayDrag(e,'drop');},true);
 var win=preview.contentWindow;if(win&&win.ResizeObserver){resizeObserver=new win.ResizeObserver(resize);resizeObserver.observe(doc.documentElement);}resize();syncSelection();setState('ready');
}
function signature(session){try{return JSON.stringify(session||{persisted:true});}catch(error){return String(Date.now());}}
function refresh(force){
 timer=null;if(!ensureSurface())return window.setTimeout(function(){schedule(true);},60);
 var session=stateSession(),sig=signature(session);if(!force&&sig===lastSignature&&visualState==='ready'){syncSelection();return;}
 var current=++requestId,data={};if(session)data.currentSession=session;setState('loading','Rendering preview…');
 api({path:architecturePath(),method:'POST',data:data}).then(function(response){
  if(current!==requestId)return;var render=response&&response.render?response.render:response;if(!render||typeof render.html!=='string')throw new Error('Canonical render payload is unavailable.');
  lastSignature=sig;preview.dataset.crescoHasRender='1';preview.srcdoc=srcdoc(render);
 }).catch(function(error){if(current!==requestId)return;setState('error','Renderer unavailable. Retry to continue editing.');window.crescoCanonicalVisualOwner.lastError=String(error&&error.message||error);});
}
function schedule(force){window.clearTimeout(timer);timer=window.setTimeout(function(){refresh(!!force);},force?0:90);}
window.addEventListener('cresco:studio-session-change',function(event){var detail=event&&event.detail||{};if(detail.session&&Array.isArray(detail.session.nodes))latestSession=detail.session;schedule(false);});
window.addEventListener('cresco:document-store-change',function(){schedule(false);});
window.addEventListener('cresco:studio-ready',function(){schedule(true);});
window.addEventListener('cresco:architecture-ready',function(){schedule(true);});
root.addEventListener('input',function(){schedule(false);},true);root.addEventListener('change',function(){schedule(false);},true);
if(window.MutationObserver)new MutationObserver(function(records){var structural=false;records.forEach(function(record){if(record.type==='childList')structural=true;});if(structural)schedule(false);else syncSelection();}).observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['class']});
window.crescoCanonicalVisualOwner={version:'2.0.0',mode:'canonical-only',legacyVisualFallback:false,refresh:function(){schedule(true);},state:function(){return visualState;},lastError:''};
window.crescoCanonicalEditorPreview=window.crescoCanonicalVisualOwner;
schedule(true);
})(window,document);
JS;
	}
}
