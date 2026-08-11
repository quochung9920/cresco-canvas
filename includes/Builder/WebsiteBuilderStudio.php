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
	const SCRIPT            = 'build/website-builder-studio.js';
	const RESPONSIVE_SCRIPT = 'build/website-builder-responsive-properties.js';
	const STYLE             = 'assets/css/website-builder-studio.css';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 121 );
	}

	/** Replace only the core editor implementation while preserving its public handle. */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$config = WebsiteBuilderEditorConfig::for_context( $context );
		if ( ! $config ) return;

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

		wp_dequeue_script( 'cresco-canvas-website-builder' );
		wp_deregister_script( 'cresco-canvas-website-builder' );
		wp_register_script(
			'cresco-canvas-website-builder',
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		wp_enqueue_script( 'cresco-canvas-website-builder' );
		wp_add_inline_script( 'cresco-canvas-website-builder', 'window.crescoWebsiteBuilderSettings=' . wp_json_encode( $config ) . ';', 'before' );
		wp_set_script_translations( 'cresco-canvas-website-builder', 'cresco-canvas' );

		if ( WebsiteBuilderAsset::readable( self::RESPONSIVE_SCRIPT ) ) {
			wp_enqueue_script(
				'cresco-canvas-website-builder-responsive-properties',
				WebsiteBuilderAsset::url( self::RESPONSIVE_SCRIPT ),
				array( 'cresco-canvas-website-builder' ),
				WebsiteBuilderAsset::version( self::RESPONSIVE_SCRIPT ),
				true
			);
		}

		wp_enqueue_style(
			'cresco-canvas-website-builder-studio',
			WebsiteBuilderAsset::url( self::STYLE ),
			array( 'cresco-canvas-website-builder', 'wp-components' ),
			WebsiteBuilderAsset::version( self::STYLE )
		);

		$this->install_structure_ownership();
	}

	/**
	 * Keep node-management controls in Structure instead of duplicating them in
	 * the widget Inspector. Visibility remains available as the persistent icon
	 * at the right edge of every Structure row; other actions appear on hover.
	 */
	private function install_structure_ownership() {
		$css = <<<'CSS'
.cc-studio-meta-grid{display:none!important}
.cc-studio-tree-label{cursor:text}
.cc-studio-tree-actions{display:flex!important;gap:1px;padding-right:4px;margin-left:auto}
.cc-studio-tree-actions>button{display:none!important}
.cc-studio-tree-actions>button:nth-child(2){display:inline-flex!important}
.cc-studio-tree-row:hover .cc-studio-tree-actions>button,.cc-studio-tree-row:focus-within .cc-studio-tree-actions>button{display:inline-flex!important}
CSS;
		wp_add_inline_style( 'cresco-canvas-website-builder-studio', $css );

		$js = <<<'JS'
(function(document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root||root.dataset.crescoStructureOwnership==='1')return;
root.dataset.crescoStructureOwnership='1';
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
})(document);
JS;
		wp_add_inline_script( 'cresco-canvas-website-builder', $js, 'after' );
	}
}
