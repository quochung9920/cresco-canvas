<?php
/**
 * Studio/frontend visual parity helpers for Website Builder documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderVisualParity {
	const EDITOR_SCRIPT_HANDLE = 'cresco-canvas-website-builder';
	const EDITOR_STYLE_HANDLE  = 'cresco-canvas-website-builder-ui-correction';

	/** Apply editor-only markup normalization after the canonical Studio runtime owns the screen. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_parity' ), 1500 );
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
	 * Annotate Studio's intentionally lightweight preview DOM with the saved node
	 * contract. The annotations let scoped Custom CSS and editor parity CSS see
	 * the same widget/field semantics as the frontend without replacing React DOM.
	 */
	public static function editor_script() {
		return <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var scheduled=false;
function arr(v){return Array.isArray(v)?v:[];}
function obj(v){return v&&typeof v==='object'&&!Array.isArray(v)?v:{};}
function find(nodes,id){
 var found=null;
 (function walk(items){arr(items).some(function(node){if(!node||typeof node!=='object')return false;if(String(node.id||'')===id){found=node;return true;}return walk(node.children||[]);});})(nodes);
 return found;
}
function documentState(){
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
function patch(){
 scheduled=false;
 var doc=documentState();
 if(!doc)return;
 Array.prototype.slice.call(root.querySelectorAll('.cc-studio-canvas-node[data-cresco-id]')).forEach(function(el){
  var id=String(el.getAttribute('data-cresco-id')||''),node=find(doc.nodes,id);
  if(!node)return;
  el.setAttribute('data-cresco-widget',String(node.type||'widget'));
  el.classList.toggle('is-cresco-decoration',isDecoration(node));
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
schedule();
if(window.MutationObserver)new MutationObserver(schedule).observe(root,{childList:true,subtree:true});
window.addEventListener('cresco:studio-consistency-change',schedule);
window.addEventListener('cresco:studio-ready',schedule);
})(window,document);
JS;
	}

	public function __construct() {}
}
