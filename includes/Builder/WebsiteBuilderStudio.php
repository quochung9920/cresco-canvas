<?php
/**
 * Cresco Studio next-generation Website Builder runtime.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderStudio {
	const HANDLE            = 'cresco-canvas-website-builder';
	const SCRIPT            = 'build/website-builder-studio.js';
	const RESPONSIVE_SCRIPT = 'build/website-builder-responsive-properties.js';
	const STYLE             = 'assets/css/website-builder-studio.css';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 121 );
		// Compatibility still runs later in the request. Reassert the canonical
		// Studio source immediately before the final module policy so the retired
		// website-builder-editor.js runtime can never become the rendered owner.
		add_action( 'admin_enqueue_scripts', array( $this, 'enforce_runtime_ownership' ), 1390 );
	}

	/** Replace only the core editor implementation while preserving its public handle. */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$config = $this->studio_config( $context );
		if ( ! $config ) return;

		wp_dequeue_script( self::HANDLE );
		wp_deregister_script( self::HANDLE );
		wp_register_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, 'window.crescoWebsiteBuilderSettings=' . wp_json_encode( $config ) . ';window.crescoExpectedWebsiteBuilderRuntime="studio";', 'before' );
		wp_set_script_translations( self::HANDLE, 'cresco-canvas' );

		$this->enqueue_support_assets();
	}

	/**
	 * Reassert the canonical source after legacy/compatibility services run.
	 *
	 * We mutate the registered dependency object instead of deregistering it so
	 * RuntimeGuard inline diagnostics/config already attached to the public
	 * handle are preserved.
	 */
	public function enforce_runtime_ownership() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$config = $this->studio_config( $context );
		if ( ! $config ) return;

		$scripts = wp_scripts();
		if ( ! $scripts ) return;

		if ( ! isset( $scripts->registered[ self::HANDLE ] ) ) {
			wp_register_script(
				self::HANDLE,
				WebsiteBuilderAsset::url( self::SCRIPT ),
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				WebsiteBuilderAsset::version( self::SCRIPT ),
				true
			);
		} else {
			$registered       = $scripts->registered[ self::HANDLE ];
			$registered->src  = WebsiteBuilderAsset::url( self::SCRIPT );
			$registered->deps = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
			$registered->ver  = WebsiteBuilderAsset::version( self::SCRIPT );
		}

		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script(
			self::HANDLE,
			'window.crescoWebsiteBuilderSettings=Object.assign({},window.crescoWebsiteBuilderSettings||{},' . wp_json_encode( $config ) . ');window.crescoExpectedWebsiteBuilderRuntime="studio";',
			'before'
		);
		wp_set_script_translations( self::HANDLE, 'cresco-canvas' );

		$this->enqueue_support_assets();
		$this->install_structure_ownership();
	}

	private function studio_config( WebsiteBuilderRuntimeContext $context ) {
		$config = WebsiteBuilderEditorConfig::for_context( $context );
		if ( ! $config ) return array();

		$config['studio'] = array(
			'version'            => '2.0.0',
			'platformPath'       => '/cresco-canvas/v1/website-builder/platform/' . $context->post_id(),
			'presencePath'       => '/cresco-canvas/v1/website-builder/platform/' . $context->post_id() . '/presence',
			'commentsPath'       => '/cresco-canvas/v1/website-builder/platform/' . $context->post_id() . '/comments',
			'interchangeExport'  => '/cresco-canvas/v1/website-builder/interchange/' . $context->post_id() . '/export',
			'interchangePreview' => '/cresco-canvas/v1/website-builder/interchange/' . $context->post_id() . '/preview',
			'diagnosticsUrl'     => add_query_arg(
				array( 'page' => 'cresco-canvas-diagnostics', 'post' => $context->post_id() ),
				admin_url( 'tools.php' )
			),
		);

		return $config;
	}

	private function enqueue_support_assets() {
		if ( WebsiteBuilderAsset::readable( self::RESPONSIVE_SCRIPT ) ) {
			wp_enqueue_script(
				'cresco-canvas-website-builder-responsive-properties',
				WebsiteBuilderAsset::url( self::RESPONSIVE_SCRIPT ),
				array( self::HANDLE ),
				WebsiteBuilderAsset::version( self::RESPONSIVE_SCRIPT ),
				true
			);
		}

		wp_enqueue_style(
			'cresco-canvas-website-builder-studio',
			WebsiteBuilderAsset::url( self::STYLE ),
			array( self::HANDLE, 'wp-components' ),
			WebsiteBuilderAsset::version( self::STYLE )
		);
	}

	/**
	 * Keep node-management controls in Structure instead of duplicating them in
	 * the widget Inspector. Studio gets native hover actions. A small legacy DOM
	 * adapter mirrors Rename / Visibility / Lock through the hidden Inspector
	 * controls only when an older runtime is still mounted by a stale local copy.
	 */
	private function install_structure_ownership() {
		$css = <<<'CSS'
.cc-studio-meta-grid,.cc-builder-meta-row{display:none!important}
.cc-builder-inspector .cc-builder-mini-actions{display:none!important}
.cc-studio-panel.is-cresco-structure-managed .cc-studio-panel-head .cc-studio-panel-actions{display:none!important}
.cc-studio-tree-label{cursor:text}
.cc-studio-tree-select>.dashicons-hidden{display:none!important}
.cc-studio-tree-row{padding-right:31px}
.cc-studio-tree-select{min-width:0!important;overflow:hidden}
.cc-studio-tree-actions{display:flex!important;align-items:center;gap:1px;position:absolute;right:3px;top:4px;z-index:8;margin-left:0!important;padding-right:0!important;border-radius:6px}
.cc-studio-tree-actions>button{display:none!important}
.cc-studio-tree-actions>button:nth-child(2){display:inline-flex!important}
.cc-studio-tree-row:hover .cc-studio-tree-actions,.cc-studio-tree-row:focus-within .cc-studio-tree-actions{background:var(--cc-panel-2);box-shadow:-10px 0 14px rgba(17,20,27,.92)}
.cc-studio-tree-row:hover .cc-studio-tree-actions>button,.cc-studio-tree-row:focus-within .cc-studio-tree-actions>button{display:inline-flex!important}
.cc-builder-structure-item{position:relative!important;padding-right:34px!important}
.cc-cresco-legacy-tree-actions{position:absolute;right:4px;top:50%;transform:translateY(-50%);z-index:9;display:flex;align-items:center;gap:1px;border-radius:6px}
.cc-cresco-legacy-tree-action{display:none;align-items:center;justify-content:center;width:24px;height:24px;border-radius:5px;color:inherit;cursor:pointer}
.cc-cresco-legacy-tree-action:hover,.cc-cresco-legacy-tree-action:focus-visible{background:rgba(127,86,217,.12);outline:1px solid rgba(127,86,217,.35)}
.cc-cresco-legacy-tree-action.is-visibility{display:inline-flex}
.cc-builder-structure-item:hover .cc-cresco-legacy-tree-actions,.cc-builder-structure-item:focus-within .cc-cresco-legacy-tree-actions{background:#fff;box-shadow:-10px 0 14px rgba(255,255,255,.96)}
.cc-builder-structure-item:hover .cc-cresco-legacy-tree-action,.cc-builder-structure-item:focus-within .cc-cresco-legacy-tree-action{display:inline-flex}
.cc-cresco-legacy-tree-action .dashicons{font-size:16px;width:16px;height:16px;line-height:16px}
.cc-builder-structure-item.is-cresco-inline-renaming .cc-builder-structure-copy strong{outline:2px solid #7f56d9;outline-offset:2px;border-radius:3px;cursor:text;min-width:64px}
CSS;
		wp_add_inline_style( 'cresco-canvas-website-builder-studio', $css );

		$js = <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root||root.dataset.crescoStructureOwnership==='4')return;
root.dataset.crescoStructureOwnership='4';
var scheduled=false;
function purgeInspectorManagement(){
 root.querySelectorAll('.cc-studio-meta-grid,.cc-builder-inspector .cc-builder-mini-actions').forEach(function(node){node.remove();});
 root.querySelectorAll('.cc-studio-inspector-tabs').forEach(function(tabs){
  var panel=tabs.closest('.cc-studio-panel');
  if(!panel)return;
  panel.classList.add('is-cresco-structure-managed');
  var head=panel.querySelector('.cc-studio-panel-head');
  if(!head)return;
  var group=head.querySelector('.cc-studio-panel-actions');
  if(group)group.remove();
  head.querySelectorAll('button').forEach(function(button){
   var name=String(button.getAttribute('title')||button.getAttribute('aria-label')||button.textContent||'').trim().toLowerCase();
   if(/^(rename|hide|show|lock|unlock|duplicate|delete|copy styles?|paste styles?)$/.test(name))button.remove();
  });
 });
}
function renameFrom(target){
 var label=target&&target.closest?target.closest('.cc-studio-tree-label'):null;
 if(!label||!root.contains(label))return false;
 var row=label.closest('.cc-studio-tree-row');
 var buttons=row?row.querySelectorAll('.cc-studio-tree-actions button'):[];
 for(var i=0;i<buttons.length;i++){
  if(String(buttons[i].getAttribute('title')||buttons[i].getAttribute('aria-label')||'').toLowerCase()==='rename'){
   buttons[i].click();
   return true;
  }
 }
 return false;
}
function nativeInputValue(input,value){
 if(!input)return;
 var descriptor=Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value');
 if(descriptor&&descriptor.set)descriptor.set.call(input,value);else input.value=value;
 input.dispatchEvent(new window.Event('input',{bubbles:true}));
 input.dispatchEvent(new window.Event('change',{bubbles:true}));
}
function legacyRows(){return Array.prototype.slice.call(root.querySelectorAll('.cc-builder-structure-item'));}
function legacyRowAt(index){var rows=legacyRows();return rows[index]||null;}
function selectLegacy(row,callback){
 if(!row)return;
 var index=parseInt(row.dataset.crescoLegacyIndex||'-1',10);
 if(!row.classList.contains('is-selected'))row.click();
 window.requestAnimationFrame(function(){window.requestAnimationFrame(function(){callback(index>=0?legacyRowAt(index):row);});});
}
function legacyMetaInput(label){
 var fields=root.querySelectorAll('.cc-builder-meta-row .cc-builder-field');
 for(var i=0;i<fields.length;i++){
  var text=String(fields[i].querySelector('label')&&fields[i].querySelector('label').textContent||'').trim().toLowerCase();
  if(text===label.toLowerCase())return fields[i].querySelector('input');
 }
 return null;
}
function legacyToggle(label){
 var rows=root.querySelectorAll('.cc-builder-meta-row .cc-builder-toggle-field');
 for(var i=0;i<rows.length;i++){
  var text=String(rows[i].querySelector('span')&&rows[i].querySelector('span').textContent||'').trim().toLowerCase();
  if(text===label.toLowerCase())return rows[i].querySelector('input');
 }
 return null;
}
function toggleLegacy(row,label){
 selectLegacy(row,function(){var input=legacyToggle(label);if(input)input.click();});
}
function finishLegacyRename(row,label,original,commit){
 if(!row||!label)return;
 label.removeAttribute('contenteditable');
 row.classList.remove('is-cresco-inline-renaming');
 var value=commit?String(label.textContent||'').trim():original;
 if(!commit)label.textContent=original;
 if(!commit)return;
 selectLegacy(row,function(){
  var input=legacyMetaInput('Navigator label');
  if(input)nativeInputValue(input,value);
 });
}
function startLegacyRename(row){
 if(!row)return;
 if(!row.classList.contains('is-selected')){
  selectLegacy(row,function(selected){startLegacyRename(selected);});
  return;
 }
 var label=row.querySelector('.cc-builder-structure-copy strong');
 if(!label||label.isContentEditable)return;
 var original=String(label.textContent||'').trim();
 row.classList.add('is-cresco-inline-renaming');
 label.setAttribute('contenteditable','true');
 label.setAttribute('spellcheck','false');
 label.focus();
 var range=document.createRange(),selection=window.getSelection();
 range.selectNodeContents(label);selection.removeAllRanges();selection.addRange(range);
 function blur(){cleanup();finishLegacyRename(row,label,original,true);}
 function keydown(event){
  if(event.key==='Enter'){event.preventDefault();cleanup();finishLegacyRename(row,label,original,true);}
  else if(event.key==='Escape'){event.preventDefault();cleanup();finishLegacyRename(row,label,original,false);}
 }
 function cleanup(){label.removeEventListener('blur',blur);label.removeEventListener('keydown',keydown);}
 label.addEventListener('blur',blur);
 label.addEventListener('keydown',keydown);
}
function action(iconName,label,className,handler){
 var item=document.createElement('span');
 item.className='cc-cresco-legacy-tree-action '+className;
 item.setAttribute('role','button');item.setAttribute('tabindex','0');item.setAttribute('title',label);item.setAttribute('aria-label',label);
 var glyph=document.createElement('span');glyph.className='dashicons dashicons-'+iconName;glyph.setAttribute('aria-hidden','true');item.appendChild(glyph);
 function run(event){event.preventDefault();event.stopPropagation();handler();}
 item.addEventListener('click',run);
 item.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){run(event);}});
 return item;
}
function refreshLegacyActionState(row,actions){
 var hidden=!!row.querySelector('.dashicons-hidden'),locked=!!row.querySelector('.dashicons-lock');
 var visibility=actions.querySelector('.is-visibility .dashicons'),lock=actions.querySelector('.is-lock .dashicons');
 if(visibility)visibility.className='dashicons dashicons-'+(hidden?'visibility':'hidden');
 var visibilityAction=actions.querySelector('.is-visibility');
 if(visibilityAction){visibilityAction.setAttribute('title',hidden?'Show':'Hide');visibilityAction.setAttribute('aria-label',hidden?'Show':'Hide');}
 if(lock)lock.className='dashicons dashicons-'+(locked?'unlock':'lock');
 var lockAction=actions.querySelector('.is-lock');
 if(lockAction){lockAction.setAttribute('title',locked?'Unlock':'Lock');lockAction.setAttribute('aria-label',locked?'Unlock':'Lock');}
}
function decorateLegacy(){
 legacyRows().forEach(function(row,index){
  row.dataset.crescoLegacyIndex=String(index);
  var actions=row.querySelector('.cc-cresco-legacy-tree-actions');
  if(!actions){
   actions=document.createElement('span');actions.className='cc-cresco-legacy-tree-actions';
   actions.appendChild(action('edit','Rename','is-rename',function(){startLegacyRename(legacyRowAt(index));}));
   actions.appendChild(action('hidden','Hide','is-visibility',function(){toggleLegacy(legacyRowAt(index),'Hide');}));
   actions.appendChild(action('lock','Lock','is-lock',function(){toggleLegacy(legacyRowAt(index),'Lock');}));
   row.appendChild(actions);
  }
  refreshLegacyActionState(row,actions);
 });
}
function run(){scheduled=false;purgeInspectorManagement();decorateLegacy();}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(run);}
root.addEventListener('dblclick',function(event){
 if(renameFrom(event.target)){
  event.preventDefault();event.stopPropagation();return;
 }
 var legacy=event.target&&event.target.closest?event.target.closest('.cc-builder-structure-copy strong'):null;
 if(legacy){event.preventDefault();event.stopPropagation();startLegacyRename(legacy.closest('.cc-builder-structure-item'));}
},true);
root.addEventListener('keydown',function(event){
 if(event.key!=='F2')return;
 var selected=event.target&&event.target.closest?event.target.closest('.cc-studio-tree-select'):null;
 if(selected){
  var label=selected.querySelector('.cc-studio-tree-label');
  if(label&&renameFrom(label)){event.preventDefault();event.stopPropagation();return;}
 }
 var legacy=root.querySelector('.cc-builder-structure-item.is-selected');
 if(legacy){event.preventDefault();event.stopPropagation();startLegacyRename(legacy);}
},true);
var observer=new MutationObserver(function(records){
 for(var i=0;i<records.length;i++){
  if(records[i].addedNodes&&records[i].addedNodes.length){schedule();return;}
 }
});
observer.observe(root,{childList:true,subtree:true});
schedule();
window.setTimeout(function(){
 var studio=root.querySelector('.cc-studio-app');
 var legacy=root.querySelector('.cc-builder-app:not(.cc-studio-app)');
 var inspectorManagementRemoved=!root.querySelector('.cc-studio-meta-grid,.cc-builder-inspector .cc-builder-mini-actions');
 window.crescoStudioRuntimeOwnership={expected:'studio',studioMounted:!!studio,legacyMounted:!!legacy,legacyStructureAdapter:!!root.querySelector('.cc-cresco-legacy-tree-actions'),inspectorManagementRemoved:inspectorManagementRemoved,checkedAt:Date.now()};
 if(legacy&&!studio){
  legacy.setAttribute('data-cresco-retired-runtime','1');
  schedule();
  if(window.console&&console.warn)console.warn('[Cresco] Retired Website Builder runtime mounted; Structure management fallback is active while Studio ownership is being recovered.');
 }
},1200);
})(window,document);
JS;
		wp_add_inline_script( self::HANDLE, $js, 'after' );
	}
}
