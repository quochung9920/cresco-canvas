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
	const UI_SCRIPT         = 'build/website-builder-ui-correction.js';
	const UNSET_STYLE_SCRIPT = 'build/website-builder-unset-styles.js';
	const FOUNDATION_STYLE  = 'assets/css/cresco-foundation.css';
	const FOUNDATION_HANDLE = 'cresco-canvas-foundation';
	const INHERITANCE_SCRIPT = 'build/studio-responsive-inheritance.js';
	const INHERITANCE_STYLE  = 'assets/css/studio-responsive-inheritance.css';
	const INHERITANCE_HANDLE = 'cresco-canvas-studio-responsive-inheritance';
	const STYLE             = 'assets/css/website-builder-studio.css';
	const UI_STYLE          = 'assets/css/website-builder-ui-correction.css';
	const PREMIUM_STYLE     = 'assets/css/website-builder-premium-polish.css';
	const CONSISTENCY       = 'cresco-canvas-website-builder-consistency-guard';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 121 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enforce_runtime_ownership' ), 1390 );
	}

	/** Attach canonical Studio config and presentation without replacing its owner. */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;
		$config = $this->studio_config( $context );
		if ( ! $config ) return;

		$this->claim_runtime_handle();
		wp_add_inline_script( self::HANDLE, 'window.crescoWebsiteBuilderSettings=' . wp_json_encode( $config ) . ';window.crescoExpectedWebsiteBuilderRuntime="studio";', 'before' );
		wp_set_script_translations( self::HANDLE, 'cresco-canvas' );
		$this->enqueue_support_assets();
	}

	/** Reassert only the canonical registration after compatibility services run. */
	public function enforce_runtime_ownership() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;
		$config = $this->studio_config( $context );
		if ( ! $config ) return;

		$this->claim_runtime_handle();
		wp_add_inline_script(
			self::HANDLE,
			'window.crescoWebsiteBuilderSettings=Object.assign({},window.crescoWebsiteBuilderSettings||{},' . wp_json_encode( $config ) . ');window.crescoExpectedWebsiteBuilderRuntime="studio";',
			'before'
		);
		wp_set_script_translations( self::HANDLE, 'cresco-canvas' );
		$this->enqueue_support_assets();
		$this->install_canvas_visibility_recovery();
		$this->install_structure_ownership();
	}

	private function claim_runtime_handle() {
		$deps = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
		if ( wp_script_is( self::CONSISTENCY, 'registered' ) ) $deps[] = self::CONSISTENCY;
		$scripts = wp_scripts();
		if ( ! $scripts ) return;

		if ( ! isset( $scripts->registered[ self::HANDLE ] ) ) {
			wp_register_script( self::HANDLE, WebsiteBuilderAsset::url( self::SCRIPT ), $deps, WebsiteBuilderAsset::version( self::SCRIPT ), true );
		} else {
			$registered       = $scripts->registered[ self::HANDLE ];
			$registered->src  = WebsiteBuilderAsset::url( self::SCRIPT );
			$registered->deps = $deps;
			$registered->ver  = WebsiteBuilderAsset::version( self::SCRIPT );
		}
		wp_enqueue_script( self::HANDLE );
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
			'aiValidateResult'   => '/cresco-canvas/v1/ai-interchange/' . $context->post_id() . '/validate',
			'aiVisualExport'     => '/cresco-canvas/v1/ai-interchange/' . $context->post_id() . '/visual',
			'diagnosticsUrl'     => add_query_arg( array( 'page' => 'cresco-canvas-diagnostics', 'post' => $context->post_id() ), admin_url( 'tools.php' ) ),
		);
		return $config;
	}

	private function enqueue_support_assets() {
		$responsive_handle = self::HANDLE;
		if ( WebsiteBuilderAsset::readable( self::RESPONSIVE_SCRIPT ) ) {
			$responsive_handle = 'cresco-canvas-website-builder-responsive-properties';
			wp_enqueue_script(
				$responsive_handle,
				WebsiteBuilderAsset::url( self::RESPONSIVE_SCRIPT ),
				array( self::HANDLE ),
				WebsiteBuilderAsset::version( self::RESPONSIVE_SCRIPT ),
				true
			);
		}
		$style_semantics_handle = $responsive_handle;
		if ( WebsiteBuilderAsset::readable( self::UI_SCRIPT ) ) {
			$style_semantics_handle = 'cresco-canvas-website-builder-ui-correction';
			wp_enqueue_script(
				$style_semantics_handle,
				WebsiteBuilderAsset::url( self::UI_SCRIPT ),
				array( $responsive_handle ),
				WebsiteBuilderAsset::version( self::UI_SCRIPT ),
				true
			);
		}
		if ( WebsiteBuilderAsset::readable( self::UNSET_STYLE_SCRIPT ) ) {
			wp_enqueue_script(
				'cresco-canvas-website-builder-unset-styles',
				WebsiteBuilderAsset::url( self::UNSET_STYLE_SCRIPT ),
				array( $style_semantics_handle ),
				WebsiteBuilderAsset::version( self::UNSET_STYLE_SCRIPT ),
				true
			);
		}
		// The foundation declares @layer order, and layer order is fixed by first
		// appearance. It must resolve before any stylesheet that opens a layer.
		if ( WebsiteBuilderAsset::readable( self::FOUNDATION_STYLE ) ) {
			wp_enqueue_style(
				self::FOUNDATION_HANDLE,
				WebsiteBuilderAsset::url( self::FOUNDATION_STYLE ),
				array(),
				WebsiteBuilderAsset::version( self::FOUNDATION_STYLE )
			);
		}
		$studio_deps = array( self::HANDLE, 'wp-components' );
		if ( wp_style_is( self::FOUNDATION_HANDLE, 'enqueued' ) ) $studio_deps[] = self::FOUNDATION_HANDLE;
		wp_enqueue_style(
			'cresco-canvas-website-builder-studio',
			WebsiteBuilderAsset::url( self::STYLE ),
			$studio_deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);
		if ( WebsiteBuilderAsset::readable( self::UI_STYLE ) ) {
			wp_enqueue_style(
				'cresco-canvas-website-builder-ui-correction',
				WebsiteBuilderAsset::url( self::UI_STYLE ),
				array( 'cresco-canvas-website-builder-studio' ),
				WebsiteBuilderAsset::version( self::UI_STYLE )
			);
		}
		if ( WebsiteBuilderAsset::readable( self::PREMIUM_STYLE ) ) {
			$premium_deps = array( 'cresco-canvas-website-builder-studio' );
			if ( wp_style_is( 'cresco-canvas-website-builder-ui-correction', 'enqueued' ) ) $premium_deps[] = 'cresco-canvas-website-builder-ui-correction';
			wp_enqueue_style(
				'cresco-canvas-website-builder-premium-polish',
				WebsiteBuilderAsset::url( self::PREMIUM_STYLE ),
				$premium_deps,
				WebsiteBuilderAsset::version( self::PREMIUM_STYLE )
			);
		}
		$this->enqueue_responsive_inheritance();
	}

	/** Keep a populated Studio document editable when its local preview resolves fully invisible. */
	private function install_canvas_visibility_recovery() {
		$handle = wp_script_is( 'cresco-canvas-website-builder-unset-styles', 'enqueued' )
			? 'cresco-canvas-website-builder-unset-styles'
			: self::HANDLE;
		$guard = <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root||window.crescoStudioCanvasVisibilityRecovery)return;
var scheduled=false,recovered=new Map(),props=['display','visibility','opacity','position','top','right','bottom','left','inset','transform','width','min-width','height','min-height'];
var stats=window.crescoStudioCanvasVisibilityRecovery={version:'1.0.0',active:false,recoveries:0,reason:'',recoveredNodes:0,lastRun:0};
function all(selector,scope){return Array.prototype.slice.call((scope||root).querySelectorAll(selector));}
function one(selector,scope){return(scope||root).querySelector(selector);}
function visible(node,viewport){if(!node||!viewport)return false;var style=window.getComputedStyle(node),opacity=parseFloat(style.opacity||'1'),rect=node.getBoundingClientRect();if(style.display==='none'||style.visibility==='hidden'||style.visibility==='collapse'||(!isNaN(opacity)&&opacity<=0.01))return false;if(rect.width<=1||rect.height<=1)return false;return rect.bottom>viewport.top&&rect.top<viewport.bottom&&rect.right>viewport.left&&rect.left<viewport.right;}
function leaves(canvas){var nodes=all('.cc-studio-canvas-node',canvas),leaf=nodes.filter(function(node){return!one('.cc-studio-canvas-node',node);});return leaf.length?leaf:nodes;}
function hasVisibleLeaf(canvas){var viewport=canvas&&canvas.getBoundingClientRect();return!!viewport&&leaves(canvas).some(function(node){return visible(node,viewport);});}
function remember(node){if(recovered.has(node))return;var saved={};props.forEach(function(property){saved[property]={value:node.style.getPropertyValue(property),priority:node.style.getPropertyPriority(property)};});recovered.set(node,saved);}
function restore(){recovered.forEach(function(saved,node){if(!node||!node.style)return;props.forEach(function(property){var previous=saved[property]||{value:'',priority:''};node.style.removeProperty(property);if(previous.value)node.style.setProperty(property,previous.value,previous.priority||'');});if(node.dataset)delete node.dataset.crescoCanvasRecovered;});recovered.clear();stats.active=false;stats.reason='';stats.recoveredNodes=0;}
function recoverNode(node,viewport){if(!node||visible(node,viewport))return false;var style=window.getComputedStyle(node),rect=node.getBoundingClientRect(),opacity=parseFloat(style.opacity||'1'),outside=rect.bottom<=viewport.top||rect.top>=viewport.bottom||rect.right<=viewport.left||rect.left>=viewport.right,changed=false;remember(node);if(style.display==='none'){node.style.setProperty('display','block','important');changed=true;}if(style.visibility==='hidden'||style.visibility==='collapse'){node.style.setProperty('visibility','visible','important');changed=true;}if(!isNaN(opacity)&&opacity<=0.01){node.style.setProperty('opacity','1','important');changed=true;}if(rect.width<=1){node.style.setProperty('width','100%','important');node.style.setProperty('min-width','4px','important');changed=true;}if(rect.height<=1){node.style.setProperty('min-height','24px','important');changed=true;}if(outside||style.position==='fixed'||(style.position==='absolute'&&(rect.width<=1||rect.height<=1))){node.style.setProperty('position','relative','important');node.style.setProperty('inset','auto','important');node.style.setProperty('top','auto','important');node.style.setProperty('right','auto','important');node.style.setProperty('bottom','auto','important');node.style.setProperty('left','auto','important');node.style.setProperty('transform','none','important');changed=true;}if(changed&&node.dataset)node.dataset.crescoCanvasRecovered='1';return changed;}
function run(){scheduled=false;stats.lastRun=Date.now();var canvas=one('.cc-studio-canvas',root),nodes=canvas?all('.cc-studio-canvas-node',canvas):[];if(!canvas||!nodes.length){if(recovered.size)restore();return;}if(stats.active&&hasVisibleLeaf(canvas))return;if(stats.active)restore();if(hasVisibleLeaf(canvas)){stats.reason='';return;}var viewport=canvas.getBoundingClientRect(),changed=0;nodes.forEach(function(node){if(recoverNode(node,viewport))changed++;});if(changed&&hasVisibleLeaf(canvas)){stats.active=true;stats.recoveries++;stats.reason='session-nodes-rendered-invisible';stats.recoveredNodes=changed;stats.recoveredAt=Date.now();return;}if(recovered.size)restore();stats.reason='blank-canvas-unresolved';}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(function(){window.setTimeout(run,0);});}
function resetAndSchedule(){if(recovered.size)restore();schedule();}
root.addEventListener('input',resetAndSchedule,true);root.addEventListener('change',resetAndSchedule,true);root.addEventListener('click',function(event){var target=event.target&&event.target.closest?event.target.closest('.cc-studio-device-toolbar button,.cc-studio-state-tabs button'):null;if(target)resetAndSchedule();else schedule();},true);
window.addEventListener('cresco:studio-session-change',resetAndSchedule);window.addEventListener('cresco:studio-ready',schedule);window.addEventListener('resize',schedule);
schedule();
})(window,document);
JS;
		wp_add_inline_script( $handle, $guard, 'after' );
	}

	/**
	 * Responsive inheritance section.
	 *
	 * Registers itself through window.CrescoStudioSDK, so it depends on the
	 * Studio handle for load order only and never touches Studio's DOM. Absent
	 * assets are skipped silently; the section is additive and the editor stays
	 * usable without it.
	 */
	private function enqueue_responsive_inheritance() {
		if ( ! WebsiteBuilderAsset::readable( self::INHERITANCE_SCRIPT ) ) return;

		$asset_file = CRESCO_CANVAS_PATH . 'build/studio-responsive-inheritance.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array();
		$deps       = isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : array( 'wp-element', 'wp-i18n' );
		$deps[]     = self::HANDLE;

		wp_enqueue_script(
			self::INHERITANCE_HANDLE,
			WebsiteBuilderAsset::url( self::INHERITANCE_SCRIPT ),
			$deps,
			WebsiteBuilderAsset::version( self::INHERITANCE_SCRIPT ),
			true
		);
		wp_set_script_translations( self::INHERITANCE_HANDLE, 'cresco-canvas' );

		if ( WebsiteBuilderAsset::readable( self::INHERITANCE_STYLE ) ) {
			wp_enqueue_style(
				self::INHERITANCE_HANDLE,
				WebsiteBuilderAsset::url( self::INHERITANCE_STYLE ),
				array( 'cresco-canvas-website-builder-studio' ),
				WebsiteBuilderAsset::version( self::INHERITANCE_STYLE )
			);
		}
	}

	/**
	 * Structure owns node-management controls. Keep React DOM intact and express
	 * the ownership boundary through presentation only; no mutation adapter or
	 * legacy runtime bridge is allowed to rewrite mounted Studio nodes.
	 */
	private function install_structure_ownership() {
		// Inline styles are unlayered, and unlayered rules outrank every cascade
		// layer declared in cresco-foundation.css. Ownership is expressed by that
		// position alone, so these rules carry no !important.
		$css = <<<'CSS'
.cc-studio-meta-grid{display:none}
.cc-studio-left .cc-studio-panel-head .cc-studio-panel-actions{display:none}
.cc-studio-tree-label{cursor:text}
.cc-studio-tree-row{padding-right:4px}
.cc-studio-tree-select{min-width:0;overflow:hidden}
.cc-studio-tree-select>.dashicons-lock,.cc-studio-tree-select>.dashicons-hidden{display:inline-flex;flex:0 0 17px;opacity:.72}
.cc-studio-tree-actions{display:none;align-items:center;gap:1px;position:absolute;right:3px;top:4px;z-index:8;margin-left:0;padding-right:0;border-radius:6px;background:var(--cc-color-surface-raised);box-shadow:var(--cc-shadow-popover)}
.cc-studio-tree-actions>button{display:inline-flex}
.cc-studio-tree-row:hover .cc-studio-tree-actions,.cc-studio-tree-row:focus-within .cc-studio-tree-actions{display:flex}
.cc-studio-tree-row:hover .cc-studio-tree-select>.dashicons-lock,.cc-studio-tree-row:hover .cc-studio-tree-select>.dashicons-hidden,.cc-studio-tree-row:focus-within .cc-studio-tree-select>.dashicons-lock,.cc-studio-tree-row:focus-within .cc-studio-tree-select>.dashicons-hidden{opacity:0}
CSS;
		wp_add_inline_style( 'cresco-canvas-website-builder-studio', $css );

		$diagnostic = <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
window.crescoStudioRuntimeOwnership={expected:'studio',studioMounted:!!root.querySelector('.cc-studio-app'),legacyMounted:!!root.querySelector('.cc-builder-app:not(.cc-studio-app)'),legacyStructureAdapter:false,inspectorManagementRemoved:false,structureActionMode:'single-more-action',checkedAt:Date.now()};
})(window,document);
JS;
		wp_add_inline_script( self::HANDLE, $diagnostic, 'after' );
	}
}
