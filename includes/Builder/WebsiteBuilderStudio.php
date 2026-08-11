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
			'diagnosticsUrl'     => add_query_arg( array( 'page' => 'cresco-canvas-diagnostics', 'post' => $context->post_id() ), admin_url( 'tools.php' ) ),
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
	 * Structure owns node-management controls. Keep React DOM intact and express
	 * the ownership boundary through presentation only; no mutation adapter or
	 * legacy runtime bridge is allowed to rewrite mounted Studio nodes.
	 */
	private function install_structure_ownership() {
		$css = <<<'CSS'
.cc-studio-meta-grid{display:none!important}
.cc-studio-left .cc-studio-panel-head .cc-studio-panel-actions{display:none!important}
.cc-studio-tree-label{cursor:text}
.cc-studio-tree-row{padding-right:4px}
.cc-studio-tree-select{min-width:0!important;overflow:hidden}
.cc-studio-tree-select>.dashicons-lock,.cc-studio-tree-select>.dashicons-hidden{display:inline-flex!important;flex:0 0 17px;opacity:.72}
.cc-studio-tree-actions{display:none!important;align-items:center;gap:1px;position:absolute;right:3px;top:4px;z-index:8;margin-left:0!important;padding-right:0!important;border-radius:6px;background:var(--cc-panel-2);box-shadow:-10px 0 14px rgba(17,20,27,.92)}
.cc-studio-tree-actions>button{display:inline-flex!important}
.cc-studio-tree-row:hover .cc-studio-tree-actions,.cc-studio-tree-row:focus-within .cc-studio-tree-actions{display:flex!important}
.cc-studio-tree-row:hover .cc-studio-tree-select>.dashicons-lock,.cc-studio-tree-row:hover .cc-studio-tree-select>.dashicons-hidden,.cc-studio-tree-row:focus-within .cc-studio-tree-select>.dashicons-lock,.cc-studio-tree-row:focus-within .cc-studio-tree-select>.dashicons-hidden{opacity:0}
CSS;
		wp_add_inline_style( 'cresco-canvas-website-builder-studio', $css );

		$diagnostic = <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
window.crescoStudioRuntimeOwnership={expected:'studio',studioMounted:!!root.querySelector('.cc-studio-app'),legacyMounted:!!root.querySelector('.cc-builder-app:not(.cc-studio-app)'),legacyStructureAdapter:false,inspectorManagementRemoved:false,structureActionMode:'hover-with-status-icons',checkedAt:Date.now()};
})(window,document);
JS;
		wp_add_inline_script( self::HANDLE, $diagnostic, 'after' );
	}
}
