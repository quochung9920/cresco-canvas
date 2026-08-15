<?php
/**
 * Canonical-only visual owner for Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Core\Responsive\ResponsiveResolver;
use CrescoCanvas\Infrastructure\WordPress\Storage\WordPressDocumentRepository;
use CrescoCanvas\Rendering\RenderEngine;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;

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
	 * Remove the previous dual-surface parity callback, preload the persisted
	 * canonical render, and install one persistent realtime iframe surface.
	 */
	public function claim_visual_ownership() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! wp_script_is( self::EDITOR_SCRIPT_HANDLE, 'enqueued' ) ) return;

		$this->remove_object_method( 'admin_enqueue_scripts', WebsiteBuilderVisualParity::class, 'enqueue_editor_parity' );

		$style_handle = wp_style_is( self::EDITOR_STYLE_HANDLE, 'enqueued' )
			? self::EDITOR_STYLE_HANDLE
			: self::EDITOR_SCRIPT_HANDLE;
		wp_add_inline_style( $style_handle, self::editor_css() );
		wp_add_inline_script(
			self::EDITOR_SCRIPT_HANDLE,
			'window.crescoCanonicalBootstrap=' . wp_json_encode( $this->bootstrap_payload( $context ) ) . ';',
			'after'
		);
		wp_add_inline_script( self::EDITOR_SCRIPT_HANDLE, self::editor_script(), 'after' );
	}

	private function bootstrap_payload( WebsiteBuilderRuntimeContext $context ) {
		$session = ( new WordPressDocumentRepository() )->load( $context->post_id() );
		if ( is_wp_error( $session ) ) $session = null;
		$render = null;
		if ( is_array( $session ) ) {
			$candidate = RenderEngine::render( $session, $context->post_id(), $context->document_type() );
			if ( ! is_wp_error( $candidate ) ) {
				$render = array(
					'html'      => (string) ( $candidate['html'] ?? '' ),
					'css'       => (string) ( $candidate['css'] ?? '' ),
					'rootCss'   => (string) ( $candidate['rootCss'] ?? '' ),
					'stableCss' => (string) ( $candidate['stableCss'] ?? '' ),
				);
			}
		}
		return array(
			'version'         => 'realtime-v2',
			'render'          => $render,
			'session'         => is_array( $session ) ? $session : null,
			'responsive'      => ResponsiveResolver::manifest(),
			'tokens'          => DesignTokens::catalog( GlobalStyles::get_settings() ),
			'styleProperties' => WidgetCatalog::style_properties(),
		);
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

	/** Canonical preview is the only visual surface and never blanks during edits. */
	public static function editor_css() {
		return '.cc-studio-frame{position:relative;min-height:720px;isolation:isolate;}'
			. '.cc-studio-frame>.cc-studio-canvas{display:none!important;visibility:hidden!important;pointer-events:none!important;}'
			. '.cc-studio-frame>.cc-studio-canonical-preview{display:block!important;width:100%;min-height:720px;border:0;background:#fff;opacity:1;pointer-events:auto;}'
			. '.cc-studio-canonical-overlay{position:absolute;inset:0;z-index:40;display:none;place-content:center;justify-items:center;gap:10px;padding:28px;background:rgba(248,250,252,.96);color:#344054;text-align:center;font:500 12px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
			. '.cc-studio-frame.is-cresco-canonical-blocking>.cc-studio-canonical-overlay,.cc-studio-frame.is-cresco-canonical-error>.cc-studio-canonical-overlay{display:grid;}'
			. '.cc-studio-canonical-overlay__spinner{width:30px;height:30px;border:3px solid rgba(99,91,255,.15);border-top-color:#635bff;border-radius:999px;animation:cresco-canonical-spin .8s linear infinite;}'
			. '.cc-studio-frame.is-cresco-canonical-error .cc-studio-canonical-overlay__spinner{display:none;}'
			. '.cc-studio-canonical-overlay__retry{display:none;min-height:34px;padding:0 13px;border:1px solid #635bff;border-radius:7px;background:#635bff;color:#fff;font:600 12px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;cursor:pointer;}'
			. '.cc-studio-frame.is-cresco-canonical-error .cc-studio-canonical-overlay__retry{display:inline-flex;align-items:center;justify-content:center;}'
			. '.cc-studio-canonical-preview-status{position:absolute;right:8px;top:8px;z-index:30;pointer-events:none;padding:3px 7px;border-radius:999px;background:rgba(17,24,39,.78);color:#fff;font:600 9px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;opacity:.72;}'
			. '.cc-studio-frame.is-cresco-canonical-syncing>.cc-studio-canonical-preview-status{background:rgba(99,91,255,.82);}'
			. '.cc-studio-frame.is-cresco-canonical-sync-error>.cc-studio-canonical-preview-status{background:rgba(180,83,9,.88);}'
			. '@keyframes cresco-canonical-spin{to{transform:rotate(360deg)}}'
			. '@media (prefers-reduced-motion:reduce){.cc-studio-canonical-overlay__spinner{animation:none;border-top-color:rgba(99,91,255,.15);background:radial-gradient(circle,#635bff 0 30%,transparent 33%)}}';
	}

	/**
	 * Hydrate once, patch local Session changes immediately, reconcile with
	 * RenderEngine in the background, and make the canonical iframe the actual
	 * drop target for new widgets.
	 */
	public static function editor_script() {
		return <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
var wp=window.wp,settings=window.crescoWebsiteBuilderSettings||{},bootstrap=window.crescoCanonicalBootstrap||{};
if(!root||!wp||!wp.apiFetch||!settings.postId)return;
var api=wp.apiFetch,postId=Number(settings.postId||0),renderPath='/cresco-canvas/v1/website-builder/render/'+postId;
var latestSession=bootstrap.session&&Array.isArray(bootstrap.session.nodes)?bootstrap.session:null,frame=null,legacy=null,preview=null,overlay=null,overlayText=null,retryButton=null,status=null;
var serverRootStyle=null,liveRootStyle=null,stableStyle=null,surface=null,resizeObserver=null;
var reconcileTimer=null,requestId=0,lastReconciledSignature='',visualState='booting',didHydrate=false,boundDocument=null;
var allowedStyles=new Set(Array.isArray(bootstrap.styleProperties)?bootstrap.styleProperties:[]);
var WIDGET_MIME='application/x-cresco-studio-widget',activeWidgetType='',dropIndicator=null,dropDescriptorCache=null;
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
  +'.cresco-website-builder-root{width:100%;min-width:0;max-width:none}.cresco-website-builder-root,.cresco-website-builder-root *{box-sizing:border-box}.cresco-website-builder-root [data-cresco-id]{min-width:0}.cresco-website-builder-root img{max-width:100%;height:auto;}'
  +'[data-cresco-editor-selected="1"]{outline:2px solid #7c3aed!important;outline-offset:1px!important;}';
}
function escapeAttr(value){return String(value||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function initialDoc(render){
 var base=pluginBase(),links='',rootCss=String(render&&render.rootCss||''),stableCss=String(render&&render.stableCss||'');
 if(!rootCss&&!stableCss)rootCss=String(render&&render.css||'');
 if(base){['assets/css/frontend.css','assets/css/website-builder-frontend.css','assets/css/forms.css'].forEach(function(path){links+='<link rel="stylesheet" href="'+escapeAttr(base+path)+'">';});}
 return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+links
  +'<style id="cresco-canonical-foundation-style">'+tokenCSS()+baseVisualCSS()+'</style>'
  +'<style id="cresco-canonical-stable-style">'+stableCss+'</style>'
  +'<style id="cresco-canonical-server-root-style">'+rootCss+'</style>'
  +'<style id="cresco-canonical-live-root-style"></style>'
  +'</head><body class="cresco-canvas-page"><div data-cresco-canonical-surface="1">'+String(render&&render.html||'')+'</div></body></html>';
}
function setState(next,message){
 visualState=next;if(!frame)return;
 frame.classList.add('is-cresco-canonical-owner');
 frame.classList.remove('is-cresco-canonical-blocking','is-cresco-canonical-ready','is-cresco-canonical-error','is-cresco-canonical-syncing','is-cresco-canonical-sync-error','is-cresco-canonical-drag');
 frame.classList.add('is-cresco-canonical-'+next);
 if(overlayText&&message)overlayText.textContent=message;
 if(status){
  if(next==='syncing')status.textContent='Syncing...';
  else if(next==='sync-error')status.textContent='Live - sync delayed';
  else status.textContent='Frontend renderer';
 }
}
function ensureSurface(){
 var nextFrame=root.querySelector('.cc-studio-frame');if(!nextFrame)return false;
 var nextLegacy=nextFrame.querySelector(':scope > .cc-studio-canvas')||nextFrame.querySelector('.cc-studio-canvas')||null;
 if(frame===nextFrame&&preview&&preview.isConnected){legacy=nextLegacy;return true;}
 if(resizeObserver){try{resizeObserver.disconnect();}catch(error){}resizeObserver=null;}
 frame=nextFrame;legacy=nextLegacy;
 preview=frame.querySelector(':scope > .cc-studio-canonical-preview[data-cresco-canonical-owner="realtime-v2"]');
 if(!preview){
  preview=document.createElement('iframe');preview.className='cc-studio-canonical-preview';preview.title='Cresco canonical renderer';preview.setAttribute('sandbox','allow-same-origin');preview.setAttribute('data-cresco-canonical-preview','1');preview.setAttribute('data-cresco-canonical-owner','realtime-v2');
  if(legacy)frame.insertBefore(preview,legacy);else frame.appendChild(preview);
  preview.addEventListener('load',bindPreview);
 }
 overlay=frame.querySelector(':scope > .cc-studio-canonical-overlay');
 if(!overlay){
  overlay=document.createElement('div');overlay.className='cc-studio-canonical-overlay';overlay.setAttribute('role','status');overlay.setAttribute('aria-live','polite');
  var spinner=document.createElement('span');spinner.className='cc-studio-canonical-overlay__spinner';spinner.setAttribute('aria-hidden','true');
  overlayText=document.createElement('strong');overlayText.className='cc-studio-canonical-overlay__text';overlayText.textContent='Rendering preview...';
  retryButton=document.createElement('button');retryButton.type='button';retryButton.className='cc-studio-canonical-overlay__retry';retryButton.textContent='Retry renderer';retryButton.addEventListener('click',function(){requestRender(stateSession(),true);});
  overlay.appendChild(spinner);overlay.appendChild(overlayText);overlay.appendChild(retryButton);frame.appendChild(overlay);
 }else{overlayText=overlay.querySelector('.cc-studio-canonical-overlay__text');retryButton=overlay.querySelector('.cc-studio-canonical-overlay__retry');}
 status=frame.querySelector(':scope > .cc-studio-canonical-preview-status');
 if(!status){status=document.createElement('span');status.className='cc-studio-canonical-preview-status';status.textContent='Frontend renderer';frame.appendChild(status);}
 frame.classList.add('is-cresco-canonical-owner');
 return true;
}
function hydrate(render){
 if(!preview||!render||typeof render.html!=='string')return false;
 didHydrate=true;preview.dataset.crescoHasRender='1';preview.srcdoc=initialDoc(render);return true;
}
function refreshDocRefs(){
 if(!preview||!preview.contentDocument)return false;var doc=preview.contentDocument;
 surface=doc.querySelector('[data-cresco-canonical-surface="1"]');
 stableStyle=doc.getElementById('cresco-canonical-stable-style');
 serverRootStyle=doc.getElementById('cresco-canonical-server-root-style');
 liveRootStyle=doc.getElementById('cresco-canonical-live-root-style');
 return !!surface;
}
function walk(nodes,fn){arr(nodes).forEach(function(node){fn(node);walk(node&&node.children,fn);});}
function sessionInfo(id){
 var result=null,session=stateSession();
 function scan(nodes,parentId){
  for(var i=0;i<arr(nodes).length;i++){
   var node=nodes[i];if(String(node.id)===String(id)){result={node:node,parentId:parentId||null,index:i};return true;}
   if(scan(node.children||[],node.id))return true;
  }
  return false;
 }
 if(session)scan(session.nodes||[],null);return result;
}
function sessionIds(){var out={};var session=stateSession();if(session)walk(session.nodes,function(node){out[String(node.id)]=true;});return out;}
function allowsChildren(node){return !!node&&(node.type==='container'||node.type==='columns');}
function safeId(id){return String(id||'').replace(/[^a-zA-Z0-9_-]/g,'-');}
function cssName(key){return String(key||'').replace(/([a-z])([A-Z])/g,'$1-$2').toLowerCase();}
function readToken(path){
 var cur=bootstrap.tokens,parts=String(path||'').split('.');for(var i=0;i<parts.length;i++){if(!cur||typeof cur!=='object'||!Object.prototype.hasOwnProperty.call(cur,parts[i]))return'';cur=cur[parts[i]];}return(cur===null||typeof cur==='object')?'':String(cur);
}
function cleanValue(key,value){
 if(value===null||value===undefined)return'';var out=String(value).trim(),match=out.match(/^\{([a-zA-Z0-9._-]+)\}$/);if(match)out=readToken(match[1]);
 if(!out||/[{};]/.test(out)||/[<>]/.test(out))return'';var prop=cssName(key);
 try{if(window.CSS&&typeof window.CSS.supports==='function'&&!window.CSS.supports(prop,out))return'';}catch(error){}
 return out;
}
function propsStyle(node){
 var type=String(node&&node.type||''),p=obj(node&&node.props),style={};
 if(type==='container'){
  var layout=['block','flex','grid'].indexOf(p.layout)!==-1?p.layout:'flex';style.display=layout;style.width='auto';
  if(p.contentWidth==='boxed'){style.width='100%';style.maxWidth='{layout.containerMax}';style.marginLeft='auto';style.marginRight='auto';}
  if(layout==='flex'){style.flexDirection=String(p.direction||'column');style.flexWrap=String(p.wrap||'nowrap');style.alignItems=String(p.align||'stretch');style.justifyContent=String(p.justify||'flex-start');}
  if(layout==='grid'){style.gridTemplateColumns=String(p.gridTemplate||('repeat('+Math.min(12,Math.max(1,Number(p.columns)||2))+',minmax(0,1fr))'));}
 }else if(type==='columns'){
  style.display='grid';style.gridTemplateColumns='repeat('+Math.min(12,Math.max(1,Number(p.columns)||2))+',minmax(0,1fr))';style.gap='{layout.gridGap}';
 }else if(type==='spacer')style.minHeight=String(p.height||'48px');
 return style;
}
function declarations(styles){
 var out='';Object.keys(obj(styles)).forEach(function(key){if(allowedStyles.size&&!allowedStyles.has(key))return;var value=cleanValue(key,styles[key]);if(value)out+=cssName(key)+':'+value+';';});return out;
}
function scopedCustom(selector,css){
 if(typeof css!=='string'||!css.trim()||/<\/?(?:style|script)/i.test(css))return'';var clean=css.replace(/\u0000/g,''),out='',cursor=0,open,close;
 while((open=clean.indexOf('{',cursor))!==-1){var raw=clean.slice(cursor,open).trim();close=clean.indexOf('}',open+1);if(close===-1)break;var body=clean.slice(open+1,close);if(raw&&body&&body.indexOf('<')===-1){var scoped=raw.split(',').map(function(part){part=part.trim();return part.indexOf('&')!==-1?part.replace(/&/g,selector):selector+' '+part;});out+=scoped.join(',')+'{'+body+'}';}cursor=close+1;}return out;
}
function compileNode(node){
 if(!node||typeof node!=='object')return'';var selector='.cresco-website-builder-root [data-cresco-id="'+safeId(node.id)+'"]',css='';
 var base=Object.assign({},propsStyle(node),obj(node.style)),decl=declarations(base);if(decl)css+=selector+'{'+decl+'}';
 ['hover','focus','active'].forEach(function(state){var d=declarations(obj(obj(node.states)[state]));if(d)css+=selector+':'+state+'{'+d+'}';});
 var max=obj(obj(bootstrap.responsive).maxWidths);['desktop','laptop','tablet','mobile'].forEach(function(device){var d=declarations(obj(obj(node.responsive)[device]));if(d&&Number(max[device])>=0)css+='@media (max-width:'+Number(max[device])+'px){'+selector+'{'+d+'}}';});
 var custom=obj(node.customCSS);if(custom.base)css+=scopedCustom(selector,String(custom.base));['desktop','laptop','tablet','mobile'].forEach(function(device){if(custom[device]&&Number(max[device])>=0){var scoped=scopedCustom(selector,String(custom[device]));if(scoped)css+='@media (max-width:'+Number(max[device])+'px){'+scoped+'}';}});
 arr(node.children).forEach(function(child){css+=compileNode(child);});return css;
}
function compileLiveCSS(session){var css='';arr(session&&session.nodes).forEach(function(node){css+=compileNode(node);});return css;}
function nodeElement(doc,id){
 if(!doc)return null;var all=doc.querySelectorAll('[data-cresco-id]'),needle=String(id||'');for(var i=0;i<all.length;i++)if(all[i].getAttribute('data-cresco-id')===needle)return all[i];return null;
}
function patchNodeProps(doc,node){
 var el=nodeElement(doc,node&&node.id);if(!el)return;var p=obj(node.props),type=String(node.type||'');
 if(type==='container'){el.setAttribute('data-cresco-content-width',String(p.contentWidth||'full'));if(p.ariaLabel)el.setAttribute('aria-label',String(p.ariaLabel));else el.removeAttribute('aria-label');}
 else if(type==='columns'){el.setAttribute('data-columns',String(p.columns||2));el.setAttribute('data-collapse-at',String(p.collapseAt||'tablet'));}
 else if(type==='heading'){
  var wanted='H'+Math.min(6,Math.max(1,Number(p.level)||2));if(el.tagName===wanted){var text=String(p.text||''),link=el.querySelector(':scope > a');if(p.url){if(!link){el.textContent='';link=doc.createElement('a');el.appendChild(link);}link.textContent=text;link.setAttribute('href',String(p.url));}else{el.textContent=text;}}
 }
 else if(type==='text'){var text=String(p.text||'');if(text.indexOf('<')===-1)el.textContent=text;}
 else if(type==='button'){
  var label=el.querySelector('[data-cresco-part="text"]');if(label)label.textContent=String(p.text||'Button');if(p.url!==undefined)el.setAttribute('href',String(p.url||'#'));el.setAttribute('target',p.target==='_blank'?'_blank':'_self');if(p.rel)el.setAttribute('rel',String(p.rel));else el.removeAttribute('rel');
 }
 else if(type==='image'){
  var media=el.querySelector('[data-cresco-part="media"]');if(media&&media.tagName==='IMG'&&p.url){media.setAttribute('src',String(p.url));media.setAttribute('alt',String(p.alt||''));}var caption=el.querySelector('[data-cresco-part="caption"]');if(caption&&p.caption!==undefined)caption.textContent=String(p.caption||'');
 }
 else if(type==='list'){
  var items=arr(p.items),lis=el.querySelectorAll('[data-cresco-part="item"]');if(items.length===lis.length)items.forEach(function(item,index){lis[index].textContent=String(item);});
 }
 else if(type==='icon-box'){
  var title=el.querySelector('.cresco-icon-box__body h3'),body=el.querySelector('.cresco-icon-box__body p');if(title)title.textContent=String(p.title||'');if(body)body.textContent=String(p.text||'');el.setAttribute('data-icon-position',String(p.position||'start'));el.setAttribute('data-content-align',String(p.contentAlign||'start'));
 }
}
function patchLiveProps(session){if(!preview||!preview.contentDocument)return;var doc=preview.contentDocument;walk(session&&session.nodes,function(node){patchNodeProps(doc,node);});}
function applyLiveSession(session){
 if(!session||!Array.isArray(session.nodes))return;latestSession=session;if(!refreshDocRefs())return;
 if(serverRootStyle)serverRootStyle.textContent='';if(liveRootStyle)liveRootStyle.textContent=compileLiveCSS(session);patchLiveProps(session);syncSelection();resize();
 if(visualState!=='blocking'&&visualState!=='error')setState('ready');
}
function legacyNode(id){if(!legacy)return null;var nodes=legacy.querySelectorAll('.cc-studio-canvas-node[data-cresco-id]');for(var i=0;i<nodes.length;i++)if(nodes[i].getAttribute('data-cresco-id')===id)return nodes[i];return null;}
function structureRow(id){var rows=root.querySelectorAll('.cc-studio-tree-row[data-cresco-node-id]');for(var i=0;i<rows.length;i++)if(rows[i].getAttribute('data-cresco-node-id')===id)return rows[i];return null;}
function relaySelection(event,type){
 var target=event.target&&event.target.closest?event.target.closest('[data-cresco-id]'):null;if(!target)return;event.preventDefault();event.stopPropagation();var id=target.getAttribute('data-cresco-id'),row=structureRow(id),bridge=row&&(row.querySelector('.cc-studio-tree-select')||row);if(!bridge)bridge=legacyNode(id);if(!bridge)return;
 var rect=preview.getBoundingClientRect(),x=rect.left+(event.clientX||0),y=rect.top+(event.clientY||0);bridge.dispatchEvent(new MouseEvent(type,{bubbles:true,cancelable:true,view:window,clientX:x,clientY:y,ctrlKey:!!event.ctrlKey,metaKey:!!event.metaKey,shiftKey:!!event.shiftKey,altKey:!!event.altKey,button:event.button||0}));window.setTimeout(syncSelection,0);
}
function relayLegacyDrag(event,type){
 var target=event.target&&event.target.closest?event.target.closest('[data-cresco-id]'):null;if(!target)return;var id=target.getAttribute('data-cresco-id'),bridge=legacyNode(id);if(!bridge)return;var dropTarget=bridge.querySelector('.cc-builder-dropzone')||bridge;event.preventDefault();event.stopPropagation();var init={bubbles:true,cancelable:true,dataTransfer:event.dataTransfer,clientX:event.clientX||0,clientY:event.clientY||0};
 try{dropTarget.dispatchEvent(new DragEvent(type,init));}catch(error){var forwarded=new Event(type,{bubbles:true,cancelable:true});try{Object.defineProperty(forwarded,'dataTransfer',{value:event.dataTransfer});}catch(ignore){}dropTarget.dispatchEvent(forwarded);}if(type==='drop')window.setTimeout(function(){scheduleReconcile(stateSession(),true);},0);
}
function selectedIds(){
 var out=[],rows=root.querySelectorAll('.cc-studio-tree-row.is-selected[data-cresco-node-id]');for(var i=0;i<rows.length;i++){var id=rows[i].getAttribute('data-cresco-node-id');if(id)out.push(id);}if(out.length||!legacy)return out;var nodes=legacy.querySelectorAll('.cc-studio-canvas-node.is-selected[data-cresco-id]');for(var j=0;j<nodes.length;j++){var legacyId=nodes[j].getAttribute('data-cresco-id');if(legacyId)out.push(legacyId);}return out;
}
function syncSelection(){
 if(!preview||!preview.contentDocument)return;var doc=preview.contentDocument,ids=selectedIds();Array.prototype.slice.call(doc.querySelectorAll('[data-cresco-editor-selected]')).forEach(function(el){el.removeAttribute('data-cresco-editor-selected');});ids.forEach(function(id){var node=nodeElement(doc,id);if(node)node.setAttribute('data-cresco-editor-selected','1');});
}
function resize(){if(!preview||!preview.contentDocument)return;var doc=preview.contentDocument,body=doc.body,html=doc.documentElement;if(!body||!html)return;var height=Math.max(720,body.scrollHeight,body.offsetHeight,html.scrollHeight,html.offsetHeight);preview.style.height=height+'px';}
function layoutAxis(el){
 if(!el||!preview||!preview.contentWindow)return'y';var style=preview.contentWindow.getComputedStyle(el);if(style&&style.display==='flex'&&String(style.flexDirection||'').indexOf('row')===0)return'x';return'y';
}
function canonicalDropDescriptor(event){
 if(!preview||!preview.contentDocument||!surface)return null;var doc=preview.contentDocument,target=event.target&&event.target.closest?event.target.closest('[data-cresco-id]'):null;
 if(!target||!surface.contains(target))return{targetId:'',zone:'root',axis:'y',el:surface,clientX:event.clientX||0,clientY:event.clientY||0};
 var id=target.getAttribute('data-cresco-id'),info=sessionInfo(id);if(!info)return null;var rect=target.getBoundingClientRect(),parentEl=info.parentId?nodeElement(doc,info.parentId):null,parentAxis=layoutAxis(parentEl),point=parentAxis==='x'?(event.clientX||0):(event.clientY||0),start=parentAxis==='x'?rect.left:rect.top,size=Math.max(1,parentAxis==='x'?rect.width:rect.height),ratio=(point-start)/size;
 if(allowsChildren(info.node)){
  if(info.parentId&&(ratio<.16||ratio>.84))return{targetId:id,zone:ratio<.5?'before':'after',axis:parentAxis,el:target,clientX:event.clientX||0,clientY:event.clientY||0};
  var childAxis=layoutAxis(target),children=arr(info.node.children).map(function(child){var el=nodeElement(doc,child.id);return el?{node:child,el:el,rect:el.getBoundingClientRect()}:null;}).filter(Boolean);
  if(children.length){
   var coordinate=childAxis==='x'?(event.clientX||0):(event.clientY||0),best=null,bestDistance=Infinity;children.forEach(function(child){var center=childAxis==='x'?child.rect.left+child.rect.width/2:child.rect.top+child.rect.height/2,distance=Math.abs(coordinate-center);if(distance<bestDistance){best=child;bestDistance=distance;}});
   if(best){var center=childAxis==='x'?best.rect.left+best.rect.width/2:best.rect.top+best.rect.height/2;return{targetId:best.node.id,zone:coordinate<center?'before':'after',axis:childAxis,el:best.el,clientX:event.clientX||0,clientY:event.clientY||0};}
  }
  return{targetId:id,zone:'inside',axis:layoutAxis(target),el:target,clientX:event.clientX||0,clientY:event.clientY||0};
 }
 var center=parentAxis==='x'?rect.left+rect.width/2:rect.top+rect.height/2;return{targetId:id,zone:point<center?'before':'after',axis:parentAxis,el:target,clientX:event.clientX||0,clientY:event.clientY||0};
}
function ensureDropIndicator(){
 if(!preview||!preview.contentDocument)return null;var doc=preview.contentDocument;if(dropIndicator&&dropIndicator.ownerDocument===doc&&dropIndicator.isConnected)return dropIndicator;dropIndicator=doc.createElement('div');dropIndicator.setAttribute('data-cresco-canonical-drop-indicator','1');dropIndicator.style.position='absolute';dropIndicator.style.zIndex='2147483646';dropIndicator.style.pointerEvents='none';dropIndicator.style.boxSizing='border-box';doc.body.appendChild(dropIndicator);return dropIndicator;
}
function showDropIndicator(desc){
 if(!desc||!desc.el||!preview||!preview.contentDocument)return;var doc=preview.contentDocument,win=preview.contentWindow,indicator=ensureDropIndicator(),rect=desc.el.getBoundingClientRect(),scrollX=win&&win.scrollX||0,scrollY=win&&win.scrollY||0;if(!indicator)return;
 indicator.style.border='0';indicator.style.background='#635bff';indicator.style.boxShadow='0 0 0 1px rgba(255,255,255,.8)';indicator.style.borderRadius='2px';
 if(desc.zone==='inside'){indicator.style.left=(rect.left+scrollX)+'px';indicator.style.top=(rect.top+scrollY)+'px';indicator.style.width=Math.max(2,rect.width)+'px';indicator.style.height=Math.max(2,rect.height)+'px';indicator.style.background='rgba(99,91,255,.10)';indicator.style.border='2px solid #635bff';indicator.style.borderRadius='4px';return;}
 if(desc.zone==='root'){indicator.style.left=(rect.left+scrollX)+'px';indicator.style.top=(Math.max(rect.top,desc.clientY)+scrollY-2)+'px';indicator.style.width=Math.max(2,rect.width)+'px';indicator.style.height='4px';return;}
 if(desc.axis==='x'){var x=(desc.zone==='before'?rect.left:rect.right)+scrollX-2;indicator.style.left=x+'px';indicator.style.top=(rect.top+scrollY)+'px';indicator.style.width='4px';indicator.style.height=Math.max(8,rect.height)+'px';}
 else{var y=(desc.zone==='before'?rect.top:rect.bottom)+scrollY-2;indicator.style.left=(rect.left+scrollX)+'px';indicator.style.top=y+'px';indicator.style.width=Math.max(8,rect.width)+'px';indicator.style.height='4px';}
}
function clearDropIndicator(){if(dropIndicator&&dropIndicator.parentNode)dropIndicator.parentNode.removeChild(dropIndicator);dropIndicator=null;dropDescriptorCache=null;}
function widgetDragType(event){
 var value='';try{value=String(event.dataTransfer&&event.dataTransfer.getData(WIDGET_MIME)||'');}catch(error){}return value||activeWidgetType;
}
function isWidgetDrag(event){var types=event&&event.dataTransfer&&event.dataTransfer.types||[];return !!activeWidgetType||Array.prototype.indexOf.call(types,WIDGET_MIME)!==-1;}
function nextPaint(){return new Promise(function(resolve){window.requestAnimationFrame(function(){window.setTimeout(resolve,0);});});}
function waitForStudio(fn,timeout){return new Promise(function(resolve,reject){var started=Date.now();(function poll(){var value;try{value=fn();}catch(error){}if(value)return resolve(value);if(Date.now()-started>(timeout||1400))return reject(new Error('Studio interaction bridge timed out.'));window.setTimeout(poll,24);})();});}
function selectStudioNode(id){var row=structureRow(id),button=row&&(row.querySelector('.cc-studio-tree-select')||row),fallback=legacyNode(id);if(button){button.click();return true;}if(fallback){fallback.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true,view:window}));return true;}return false;}
function clearStudioSelection(){var stage=root.querySelector('.cc-studio-stage');if(stage){stage.click();return true;}return false;}
function openWidgetsPanel(){var buttons=root.querySelectorAll('.cc-studio-rail button');for(var i=0;i<buttons.length;i++){var title=String(buttons[i].getAttribute('title')||''),label=String(buttons[i].textContent||'').trim();if(title==='Add'||label==='Add'){buttons[i].click();return true;}}return false;}
function widgetButton(type){var buttons=root.querySelectorAll('.cc-studio-widget-grid button');for(var i=0;i<buttons.length;i++){var small=buttons[i].querySelector('small');if(small&&String(small.textContent||'').trim()===String(type))return buttons[i];}return null;}
function expandStructure(){var button=root.querySelector('.cc-studio-structure button[title="Expand all"]');if(button)button.click();}
function fireDrag(el,name,x,y,dataTransfer){
 var event;try{event=new DragEvent(name,{bubbles:true,cancelable:true,view:window,clientX:x,clientY:y,dataTransfer:dataTransfer});}catch(error){event=new Event(name,{bubbles:true,cancelable:true});try{Object.defineProperty(event,'clientX',{value:x});Object.defineProperty(event,'clientY',{value:y});Object.defineProperty(event,'dataTransfer',{value:dataTransfer});}catch(ignore){}}
 el.dispatchEvent(event);return event;
}
function dragDataTransfer(){
 try{return new DataTransfer();}catch(error){var data={};return{types:[],setData:function(type,value){data[type]=String(value);if(this.types.indexOf(type)===-1)this.types.push(type);},getData:function(type){return data[type]||'';},effectAllowed:'move',dropEffect:'move'};}
}
function moveStructureNode(sourceId,targetId,zone){
 return (async function(){expandStructure();await nextPaint();var source=await waitForStudio(function(){return structureRow(sourceId);},1200),target=await waitForStudio(function(){return structureRow(targetId);},1200),dt=dragDataTransfer();try{dt.setData('application/x-cresco-studio-node',String(sourceId));}catch(error){}var sr=source.getBoundingClientRect();fireDrag(source,'dragstart',sr.left+sr.width/2,sr.top+sr.height/2,dt);await nextPaint();target=structureRow(targetId)||target;var tr=target.getBoundingClientRect(),x=tr.left+tr.width/2,y=zone==='before'?tr.top+1:tr.bottom-1;fireDrag(target,'dragover',x,y,dt);fireDrag(target,'drop',x,y,dt);await nextPaint();fireDrag(source,'dragend',x,y,dt);return true;})();
}
function createWidgetInParent(type,parentId){
 return (async function(){var before=sessionIds();if(parentId){if(!selectStudioNode(parentId))throw new Error('Drop parent is not selectable.');}else clearStudioSelection();await nextPaint();if(!openWidgetsPanel())throw new Error('Widget library is unavailable.');var button=await waitForStudio(function(){return widgetButton(type);},1400);button.click();var newId=await waitForStudio(function(){var after=sessionIds(),ids=Object.keys(after);for(var i=0;i<ids.length;i++)if(!before[ids[i]])return ids[i];return'';},1800);return newId;})();
}
function insertWidgetAtDescriptor(type,desc){
 return (async function(){if(!type||!desc)return false;var targetInfo=desc.targetId?sessionInfo(desc.targetId):null,parentId=null,move=false;if(desc.zone==='inside'){if(!targetInfo||!allowsChildren(targetInfo.node))throw new Error('This target cannot contain widgets.');parentId=desc.targetId;}else if(desc.zone==='before'||desc.zone==='after'){if(!targetInfo)throw new Error('Drop target is unavailable.');parentId=targetInfo.parentId||null;move=true;}var newId=await createWidgetInParent(type,parentId);if(move)await moveStructureNode(newId,desc.targetId,desc.zone);scheduleReconcile(stateSession(),true);return true;})();
}
function handleCanonicalDrag(event,type){
 if(!isWidgetDrag(event)){relayLegacyDrag(event,type);return;}var desc=canonicalDropDescriptor(event);if(!desc)return;event.preventDefault();event.stopPropagation();if(event.dataTransfer)try{event.dataTransfer.dropEffect='copy';}catch(error){}dropDescriptorCache=desc;showDropIndicator(desc);if(type!=='drop')return;var widgetType=widgetDragType(event);clearDropIndicator();activeWidgetType='';insertWidgetAtDescriptor(widgetType,desc).catch(function(error){window.crescoCanonicalVisualOwner.lastError=String(error&&error.message||error);setState('sync-error');});
}
function bindPreview(){
 if(!preview||preview.dataset.crescoHasRender!=='1')return;var doc=preview.contentDocument;if(!doc)return;refreshDocRefs();
 if(boundDocument!==doc){boundDocument=doc;doc.addEventListener('click',function(e){relaySelection(e,'click');},true);doc.addEventListener('contextmenu',function(e){relaySelection(e,'contextmenu');},true);doc.addEventListener('submit',function(e){e.preventDefault();},true);doc.addEventListener('dragenter',function(e){handleCanonicalDrag(e,'dragenter');},true);doc.addEventListener('dragover',function(e){handleCanonicalDrag(e,'dragover');},true);doc.addEventListener('drop',function(e){handleCanonicalDrag(e,'drop');},true);doc.addEventListener('dragleave',function(e){if(!e.relatedTarget)clearDropIndicator();},true);}
 if(resizeObserver){try{resizeObserver.disconnect();}catch(error){}resizeObserver=null;}var win=preview.contentWindow;if(win&&win.ResizeObserver){resizeObserver=new win.ResizeObserver(resize);resizeObserver.observe(doc.documentElement);}setState('ready');var session=stateSession();if(session)applyLiveSession(session);resize();syncSelection();
}
function signature(session){try{return JSON.stringify(session||{persisted:true});}catch(error){return String(Date.now());}}
function applyServerRender(render,session){
 if(!render||typeof render.html!=='string'||!refreshDocRefs())return false;if(stableStyle)stableStyle.textContent=String(render.stableCss||'');if(serverRootStyle)serverRootStyle.textContent=String(render.rootCss||render.css||'');if(liveRootStyle)liveRootStyle.textContent='';surface.innerHTML=String(render.html||'');lastReconciledSignature=signature(session);patchLiveProps(session||{});syncSelection();resize();setState('ready');return true;
}
function requestRender(session,blocking){
 if(!ensureSurface())return window.setTimeout(function(){requestRender(session,blocking);},40);var current=++requestId,sig=signature(session),data={};if(session)data.currentSession=session;if(blocking)setState('blocking','Rendering preview...');else setState('syncing');
 api({path:architecturePath(),method:'POST',data:data}).then(function(response){
  if(current!==requestId)return;var render=response&&response.render?response.render:response;if(!render||typeof render.html!=='string')throw new Error('Canonical render payload is unavailable.');var now=stateSession();if(!blocking&&signature(now)!==sig)return;
  if(!didHydrate){hydrate(render);return;}applyServerRender(render,now||session);
 }).catch(function(error){if(current!==requestId)return;if(blocking)setState('error','Renderer unavailable. Retry to continue editing.');else setState('sync-error');window.crescoCanonicalVisualOwner.lastError=String(error&&error.message||error);});
}
function scheduleReconcile(session,force){
 window.clearTimeout(reconcileTimer);if(!session||!Array.isArray(session.nodes))session=stateSession();if(!session)return;var sig=signature(session);if(!force&&sig===lastReconciledSignature)return;reconcileTimer=window.setTimeout(function(){requestRender(session,false);},force?40:420);
}
function onSession(session){if(!session||!Array.isArray(session.nodes))return;latestSession=session;applyLiveSession(session);scheduleReconcile(session,false);}
root.addEventListener('dragstart',function(event){var button=event.target&&event.target.closest?event.target.closest('.cc-studio-widget-grid button[draggable="true"]'):null;if(!button)return;var small=button.querySelector('small');activeWidgetType=small?String(small.textContent||'').trim():'';if(activeWidgetType&&event.dataTransfer)try{event.dataTransfer.setData(WIDGET_MIME,activeWidgetType);event.dataTransfer.effectAllowed='copy';}catch(error){}},true);
root.addEventListener('dragend',function(){activeWidgetType='';clearDropIndicator();},true);
window.addEventListener('cresco:studio-session-change',function(event){var detail=event&&event.detail||{};if(detail.session)onSession(detail.session);});
window.addEventListener('cresco:document-store-change',function(){var session=stateSession();if(session)onSession(session);});
window.addEventListener('cresco:studio-ready',function(){var session=stateSession();if(session){applyLiveSession(session);scheduleReconcile(session,false);}});
window.addEventListener('cresco:architecture-ready',function(){var session=stateSession();if(session)scheduleReconcile(session,false);});
if(window.MutationObserver)new MutationObserver(function(){if(!frame||!frame.isConnected){frame=null;preview=null;didHydrate=false;clearDropIndicator();boot();return;}syncSelection();}).observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['class']});
window.crescoCanonicalVisualOwner={version:'3.1.0',mode:'canonical-realtime',legacyVisualFallback:false,realtime:true,iframeReloadOnEdit:false,canonicalWidgetDrop:true,refresh:function(){scheduleReconcile(stateSession(),true);},state:function(){return visualState;},lastError:''};
window.crescoCanonicalEditorPreview=window.crescoCanonicalVisualOwner;
function boot(){
 if(!ensureSurface())return window.setTimeout(boot,30);if(didHydrate)return;var initial=bootstrap.render;if(initial&&typeof initial.html==='string'){hydrate(initial);return;}requestRender(stateSession(),true);
}
boot();
})(window,document);
JS;
	}
}
