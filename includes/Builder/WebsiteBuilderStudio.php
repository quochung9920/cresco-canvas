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
	 * the widget Inspector. The DOM guard intentionally removes both Studio and
	 * retired runtime metadata panels so a stale compatibility render cannot
	 * expose Navigator label / Lock / Hide again.
	 */
	private function install_structure_ownership() {
		$css = <<<'CSS'
.cc-studio-meta-grid,.cc-builder-meta-row{display:none!important}
.cc-studio-tree-label{cursor:text}
.cc-studio-tree-select>.dashicons-hidden{display:none!important}
.cc-studio-tree-actions{display:flex!important;gap:1px;padding-right:4px;margin-left:auto}
.cc-studio-tree-actions>button{display:none!important}
.cc-studio-tree-actions>button:nth-child(2){display:inline-flex!important}
.cc-studio-tree-row:hover .cc-studio-tree-actions>button,.cc-studio-tree-row:focus-within .cc-studio-tree-actions>button{display:inline-flex!important}
CSS;
		wp_add_inline_style( 'cresco-canvas-website-builder-studio', $css );

		$js = <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root||root.dataset.crescoStructureOwnership==='2')return;
root.dataset.crescoStructureOwnership='2';
var scheduled=false;
function purgeInspectorManagement(){
 root.querySelectorAll('.cc-studio-meta-grid,.cc-builder-meta-row').forEach(function(node){node.remove();});
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
function run(){scheduled=false;purgeInspectorManagement();}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(run);}
root.addEventListener('dblclick',function(event){
 if(renameFrom(event.target)){
  event.preventDefault();
  event.stopPropagation();
 }
},true);
root.addEventListener('keydown',function(event){
 if(event.key!=='F2')return;
 var selected=event.target&&event.target.closest?event.target.closest('.cc-studio-tree-select'):null;
 if(!selected)return;
 var label=selected.querySelector('.cc-studio-tree-label');
 if(label&&renameFrom(label)){
  event.preventDefault();
  event.stopPropagation();
 }
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
 window.crescoStudioRuntimeOwnership={expected:'studio',studioMounted:!!studio,legacyMounted:!!legacy,checkedAt:Date.now()};
 if(legacy&&!studio){
  legacy.setAttribute('data-cresco-retired-runtime','1');
  purgeInspectorManagement();
  if(window.console&&console.error)console.error('[Cresco] Retired Website Builder runtime mounted instead of Cresco Studio. The server runtime owner must be refreshed.');
 }
},1200);
})(window,document);
JS;
		wp_add_inline_script( self::HANDLE, $js, 'after' );
	}
}
