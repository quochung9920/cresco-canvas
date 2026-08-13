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
	const STYLE_CONTRACT_VERSION = 'authoritative-v3';

	/** Apply the same visual contract at the last editor and frontend boundaries. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_parity' ), 1500 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_parity' ), 2000 );
		add_filter( 'the_content', array( $this, 'embed_frontend_parity' ), 110 );
	}

	public function enqueue_editor_parity() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! wp_script_is( self::EDITOR_SCRIPT_HANDLE, 'enqueued' ) ) return;

		$style_handle = wp_style_is( self::EDITOR_STYLE_HANDLE, 'enqueued' )
			? self::EDITOR_STYLE_HANDLE
			: self::EDITOR_SCRIPT_HANDLE;
		wp_add_inline_style( $style_handle, self::editor_css() );
		wp_add_inline_script( self::EDITOR_SCRIPT_HANDLE, self::editor_script(), 'after' );
	}

	/**
	 * Append an authoritative root + part-style bundle after every compatibility
	 * compiler. This deliberately does not delete earlier fragments because v2
	 * component CSS may share the same inline fragment; the final bundle wins by
	 * source order and neutralizes historical container width:100% defaults.
	 */
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
	 *
	 * WordPress themes, preview revisions, cache layers, and late style handles can
	 * otherwise leave the rendered document paired with an older compiler bundle.
	 * A body-local style element is intentionally emitted after head styles, giving
	 * the builder document one deterministic last visual boundary.
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
		return WebsiteBuilderCssCompiler::compile( $session ) . WidgetPartStyleCompiler::compile( $session, (array) $architecture );
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

	/**
	 * Normalize the simplified Studio preview DOM so leaf-widget presentation is
	 * inherited from the node wrapper exactly like the frontend widget element.
	 */
	public static function editor_css() {
		return '.cc-builder-canvas .cc-studio-canvas-node{box-sizing:border-box;min-width:0;}'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="heading"]>h1,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="heading"]>h2,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="heading"]>h3,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="heading"]>h4,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="heading"]>h5,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="heading"]>h6{margin:0;color:inherit;font-family:inherit;font-size:inherit;font-style:inherit;font-weight:inherit;line-height:inherit;letter-spacing:inherit;text-align:inherit;text-decoration:inherit;text-transform:inherit;}'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="button"]>a{display:inline-flex;align-items:center;justify-content:center;max-width:100%;min-height:inherit;color:inherit;font:inherit;letter-spacing:inherit;text-align:inherit;text-decoration:inherit;text-transform:inherit;}'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="image"]>img,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="featured-image"]>img,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="site-logo"]>img{display:block;max-width:100%;height:auto;}'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="form"]>form{width:100%;margin:0;color:inherit;font:inherit;}'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="form"]>form>label{display:grid;gap:.35rem;min-width:0;margin:0 0 .9rem;color:inherit;font:inherit;}'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="form"]>form input,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="form"]>form textarea,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="form"]>form select,'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="form"]>form button{box-sizing:border-box;font:inherit;}'
			. '.cc-builder-canvas .cc-studio-canvas-node[data-cresco-widget="form"]>form label[data-cresco-field-type="textarea"] input{min-height:6rem;}'
			. '.cc-builder-canvas .cc-studio-canvas-node.is-cresco-decoration>.cc-studio-container-empty{display:none!important;}';
	}

	/**
	 * Annotate Studio's lightweight preview DOM with the saved node contract and
	 * fill only semantic layout properties that React did not explicitly render.
	 * This preserves live responsive inline styles while matching frontend props
	 * defaults for new/unstyled Container, Columns, and Spacer widgets.
	 */
	public static function editor_script() {
		return <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var scheduled=false,latestSession=null;
function arr(v){return Array.isArray(v)?v:[];}
function obj(v){return v&&typeof v==='object'&&!Array.isArray(v)?v:{};}
function find(nodes,id){
 var found=null;
 (function walk(items){arr(items).some(function(node){if(!node||typeof node!=='object')return false;if(String(node.id||'')===id){found=node;return true;}return walk(node.children||[]);});})(nodes);
 return found;
}
function documentState(){
 if(latestSession&&Array.isArray(latestSession.nodes))return latestSession;
 var store=window.crescoDocumentStore;
 if(store&&typeof store.getState==='function'){
  var state=store.getState()||{};
  if(state.document&&Array.isArray(state.document.nodes))return state.document;
 }
 var runtime=window.crescoRuntimeState||{};
 return runtime.session&&Array.isArray(runtime.session.nodes)?runtime.session:null;
}
function isDecoration(node){
 if(!node||node.type!=='container'||arr(node.children).length)return false;
 var buckets=[obj(node.style)].concat(Object.keys(obj(node.responsive)).map(function(key){return obj(node.responsive[key]);}));
 if(buckets.some(function(style){var p=String(style.position||'').toLowerCase();return p==='absolute'||p==='fixed';}))return true;
 return Object.keys(obj(node.customCSS)).some(function(key){return /position\s*:\s*(?:absolute|fixed)\b|pointer-events\s*:\s*none\b/i.test(String(node.customCSS[key]||''));});
}
function applySemanticLayout(el,node){
 var props=obj(node.props),type=String(node.type||'');
 function fallback(property,value){if(value!==undefined&&value!==null&&String(value)!==''&&!el.style.getPropertyValue(property))el.style.setProperty(property,String(value));}
 if(type==='container'){
  var layout=/^(?:block|flex|grid)$/.test(String(props.layout||''))?String(props.layout):'flex';
  fallback('display',layout);
  if(layout==='flex'){
   fallback('flex-direction',props.direction||'column');
   fallback('flex-wrap',props.wrap||'nowrap');
   fallback('align-items',props.align||'stretch');
   fallback('justify-content',props.justify||'flex-start');
  }else if(layout==='grid'){
   var columns=Math.max(1,Math.min(12,parseInt(props.columns||2,10)||2));
   fallback('grid-template-columns',props.gridTemplate||('repeat('+columns+', minmax(0, 1fr))'));
  }
 }else if(type==='columns'){
  var count=Math.max(1,Math.min(12,parseInt(props.columns||2,10)||2));
  fallback('display','grid');
  fallback('grid-template-columns','repeat('+count+', minmax(0, 1fr))');
 }else if(type==='spacer'){
  fallback('min-height',props.height||'48px');
 }
}
function patch(){
 scheduled=false;
 var doc=documentState();
 if(!doc)return;
 Array.prototype.slice.call(root.querySelectorAll('.cc-studio-canvas-node[data-cresco-id]')).forEach(function(el){
  var id=String(el.getAttribute('data-cresco-id')||''),node=find(doc.nodes,id);
  if(!node)return;
  el.setAttribute('data-cresco-widget',String(node.type||'widget'));
  el.classList.toggle('is-cresco-decoration',isDecoration(node));
  applySemanticLayout(el,node);
  if(node.type!=='form')return;
  var form=el.querySelector(':scope > form');
  if(!form)return;
  form.classList.add('cresco-form');
  var fields=arr(obj(node.props).fields),labels=Array.prototype.slice.call(form.children).filter(function(child){return child.tagName==='LABEL';});
  labels.forEach(function(label,index){
   label.classList.add('cresco-form-field');
   var field=obj(fields[index]);
   label.setAttribute('data-cresco-field-type',String(field.type||'text'));
   var control=label.querySelector('input,textarea,select');
   if(control){control.disabled=true;if(control.tagName==='INPUT'&&/^(?:text|email|tel|number|url|date)$/.test(String(field.type||'text')))try{control.type=field.type||'text';}catch(error){}}
  });
  var button=form.querySelector(':scope > button');
  if(button&&button.getAttribute('type')!=='submit')button.setAttribute('type','submit');
 });
}
function schedule(){if(scheduled)return;scheduled=true;if(window.requestAnimationFrame)window.requestAnimationFrame(patch);else window.setTimeout(patch,0);}
window.addEventListener('cresco:studio-session-change',function(event){var detail=event&&event.detail||{};if(detail.session&&Array.isArray(detail.session.nodes))latestSession=detail.session;schedule();});
schedule();
if(window.MutationObserver)new MutationObserver(schedule).observe(root,{childList:true,subtree:true});
window.addEventListener('cresco:document-store-change',schedule);
window.addEventListener('cresco:studio-consistency-change',schedule);
window.addEventListener('cresco:studio-ready',schedule);
})(window,document);
JS;
	}

	public function __construct() {}
}
