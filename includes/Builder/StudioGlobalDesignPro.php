<?php
/**
 * Professional Global Design workspace for Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a visual Design System workspace on top of the canonical Global Design
 * settings and token APIs. The Pro workspace is authoritative in Studio: the
 * legacy token-list UI is suppressed before the Pro runtime mounts.
 */
final class StudioGlobalDesignPro {
	const HANDLE                    = 'cresco-canvas-studio-global-design-pro';
	const SCRIPT                    = 'build/studio-global-design-pro.js';
	const STYLE                     = 'assets/css/studio-global-design-pro.css';
	const WORKFLOW_GUARD_HANDLE     = 'cresco-canvas-studio-global-design-workflows-guard';
	const WORKFLOW_GUARD_SCRIPT     = 'build/studio-global-design-workflows-guard.js';
	const WORKFLOW_HANDLE           = 'cresco-canvas-studio-global-design-workflows';
	const WORKFLOW_SCRIPT           = 'build/studio-global-design-workflows.js';
	const WORKFLOW_STYLE            = 'assets/css/studio-global-design-workflows.css';
	const COMPACT_HANDLE            = 'cresco-canvas-studio-global-design-compact';
	const COMPACT_SCRIPT            = 'build/studio-global-design-compact.js';
	const COMPACT_STYLE             = 'assets/css/studio-global-design-compact.css';
	const FONT_SEARCH_FIX_HANDLE    = 'cresco-canvas-studio-global-design-font-search-fix';
	const FONT_SEARCH_FIX_STYLE     = 'assets/css/studio-global-design-font-search-fix.css';
	const SHARED_STYLE_HANDLE       = 'cresco-canvas-studio-global-design-shared-controls';
	const SHARED_STYLE              = 'assets/css/studio-global-design-shared-controls.css';
	const SHARED_SCRIPT_HANDLE      = 'cresco-canvas-studio-global-design-shared-controls-runtime';
	const SHARED_SCRIPT             = 'build/studio-global-design-shared-controls.js';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1430 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! current_user_can( 'edit_theme_options' ) ) return;

		// Only the Pro core is mandatory. Optional enhancement assets must never
		// make Studio fall back to the legacy raw-token workspace.
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$style_deps = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( StudioUxPro::HANDLE, 'enqueued' ) ) $style_deps[] = StudioUxPro::HANDLE;
		if ( wp_style_is( StudioColorHarmony::LIGHT_HANDLE, 'enqueued' ) ) $style_deps[] = StudioColorHarmony::LIGHT_HANDLE;

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$style_deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);

		$last_style = self::HANDLE;
		if ( WebsiteBuilderAsset::readable( self::WORKFLOW_STYLE ) ) {
			wp_enqueue_style(
				self::WORKFLOW_HANDLE,
				WebsiteBuilderAsset::url( self::WORKFLOW_STYLE ),
				array( $last_style ),
				WebsiteBuilderAsset::version( self::WORKFLOW_STYLE )
			);
			$last_style = self::WORKFLOW_HANDLE;
		}
		if ( WebsiteBuilderAsset::readable( self::COMPACT_STYLE ) ) {
			wp_enqueue_style(
				self::COMPACT_HANDLE,
				WebsiteBuilderAsset::url( self::COMPACT_STYLE ),
				array( $last_style ),
				WebsiteBuilderAsset::version( self::COMPACT_STYLE )
			);
			$last_style = self::COMPACT_HANDLE;
		}
		if ( WebsiteBuilderAsset::readable( self::FONT_SEARCH_FIX_STYLE ) ) {
			wp_enqueue_style(
				self::FONT_SEARCH_FIX_HANDLE,
				WebsiteBuilderAsset::url( self::FONT_SEARCH_FIX_STYLE ),
				array( $last_style ),
				WebsiteBuilderAsset::version( self::FONT_SEARCH_FIX_STYLE )
			);
			$last_style = self::FONT_SEARCH_FIX_HANDLE;
		}
		if ( WebsiteBuilderAsset::readable( self::SHARED_STYLE ) ) {
			wp_enqueue_style(
				self::SHARED_STYLE_HANDLE,
				WebsiteBuilderAsset::url( self::SHARED_STYLE ),
				array( $last_style ),
				WebsiteBuilderAsset::version( self::SHARED_STYLE )
			);
		}

		$config = array(
			'schema'       => 'cresco-global-design-pro/v1',
			'settingsPath' => '/cresco-canvas/v1/settings',
			'tokensPath'   => '/cresco-canvas/v1/design-tokens',
			'resetPath'    => '/cresco-canvas/v1/settings/reset',
			'postId'       => $context->post_id(),
			'fonts'        => \CrescoCanvas\Styles\GlobalWebFonts::catalog(),
			'systemFonts'  => \CrescoCanvas\Styles\GlobalWebFonts::system_fonts(),
		);
		$config_script = 'window.crescoGlobalDesignProSettings=' . wp_json_encode( $config ) . ';';

		$workflow_deps = array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch' );
		if ( WebsiteBuilderAsset::readable( self::WORKFLOW_GUARD_SCRIPT ) ) {
			wp_enqueue_script(
				self::WORKFLOW_GUARD_HANDLE,
				WebsiteBuilderAsset::url( self::WORKFLOW_GUARD_SCRIPT ),
				$workflow_deps,
				WebsiteBuilderAsset::version( self::WORKFLOW_GUARD_SCRIPT ),
				true
			);
			$workflow_deps[] = self::WORKFLOW_GUARD_HANDLE;
		}
		if ( wp_script_is( StudioUxPro::HANDLE, 'enqueued' ) ) $workflow_deps[] = StudioUxPro::HANDLE;

		$workflow_loaded = false;
		if ( WebsiteBuilderAsset::readable( self::WORKFLOW_SCRIPT ) ) {
			wp_enqueue_script(
				self::WORKFLOW_HANDLE,
				WebsiteBuilderAsset::url( self::WORKFLOW_SCRIPT ),
				$workflow_deps,
				WebsiteBuilderAsset::version( self::WORKFLOW_SCRIPT ),
				true
			);
			wp_add_inline_script( self::WORKFLOW_HANDLE, $config_script, 'before' );
			$workflow_loaded = true;
		}

		$script_deps = array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch' );
		if ( $workflow_loaded ) $script_deps[] = self::WORKFLOW_HANDLE;
		if ( wp_script_is( StudioUxPro::HANDLE, 'enqueued' ) ) $script_deps[] = StudioUxPro::HANDLE;
		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			$script_deps,
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		wp_add_inline_script( self::HANDLE, $config_script, 'before' );
		wp_add_inline_script( self::HANDLE, self::legacy_fallback_guard(), 'before' );

		if ( WebsiteBuilderAsset::readable( self::COMPACT_SCRIPT ) ) {
			wp_enqueue_script(
				self::COMPACT_HANDLE,
				WebsiteBuilderAsset::url( self::COMPACT_SCRIPT ),
				array( self::HANDLE ),
				WebsiteBuilderAsset::version( self::COMPACT_SCRIPT ),
				true
			);
		}
		if ( WebsiteBuilderAsset::readable( self::SHARED_SCRIPT ) ) {
			$shared_deps = array( wp_script_is( self::COMPACT_HANDLE, 'enqueued' ) ? self::COMPACT_HANDLE : self::HANDLE );
			wp_enqueue_script(
				self::SHARED_SCRIPT_HANDLE,
				WebsiteBuilderAsset::url( self::SHARED_SCRIPT ),
				$shared_deps,
				WebsiteBuilderAsset::version( self::SHARED_SCRIPT ),
				true
			);
		}
	}

	private static function legacy_fallback_guard() {
		return <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
function guard(){
 var panels=root.querySelectorAll('.cc-studio-left .cc-studio-panel');
 for(var i=0;i<panels.length;i++){
  var panel=panels[i],heading=panel.querySelector('.cc-studio-panel-head strong');
  if(!heading||String(heading.textContent||'').trim()!=='Global Design')continue;
  panel.classList.add('cc-global-design-pro-host');
  panel.setAttribute('data-global-design-authority','pro');
 }
}
var queued=false;
function queue(){if(queued)return;queued=true;window.requestAnimationFrame(function(){queued=false;guard();});}
guard();
var observer=new MutationObserver(queue);
observer.observe(root,{childList:true,subtree:true});
window.crescoGlobalDesignLegacyFallback={disabled:true,authority:'pro'};
})(window,document);
JS;
	}
}
