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
 * settings and token APIs. The Pro core owns the Global Design UI; workflow,
 * compact and shared-control layers are progressive enhancements.
 */
final class StudioGlobalDesignPro {
	const HANDLE                    = 'cresco-canvas-studio-global-design-pro';
	const SCRIPT                    = 'build/studio-global-design-pro.js';
	const STYLE                     = 'assets/css/studio-global-design-pro.css';
	const AUTHORITY_HANDLE          = 'cresco-canvas-studio-global-design-authority';
	const AUTHORITY_SCRIPT          = 'build/studio-global-design-authority.js';
	const AUTHORITY_STYLE           = 'assets/css/studio-global-design-authority.css';
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

		// Global Design Pro core is the only mandatory layer. A missing optional
		// enhancement must never send Studio back to the legacy raw-token UI.
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
		$optional_styles = array(
			array( self::AUTHORITY_HANDLE, self::AUTHORITY_STYLE ),
			array( self::WORKFLOW_HANDLE, self::WORKFLOW_STYLE ),
			array( self::COMPACT_HANDLE, self::COMPACT_STYLE ),
			array( self::FONT_SEARCH_FIX_HANDLE, self::FONT_SEARCH_FIX_STYLE ),
			array( self::SHARED_STYLE_HANDLE, self::SHARED_STYLE ),
		);
		foreach ( $optional_styles as $asset ) {
			if ( ! WebsiteBuilderAsset::readable( $asset[1] ) ) continue;
			wp_enqueue_style(
				$asset[0],
				WebsiteBuilderAsset::url( $asset[1] ),
				array( $last_style ),
				WebsiteBuilderAsset::version( $asset[1] )
			);
			$last_style = $asset[0];
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

		$authority_loaded = false;
		if ( WebsiteBuilderAsset::readable( self::AUTHORITY_SCRIPT ) ) {
			wp_enqueue_script(
				self::AUTHORITY_HANDLE,
				WebsiteBuilderAsset::url( self::AUTHORITY_SCRIPT ),
				array( WebsiteBuilderStudio::HANDLE ),
				WebsiteBuilderAsset::version( self::AUTHORITY_SCRIPT ),
				true
			);
			wp_add_inline_script( self::AUTHORITY_HANDLE, $config_script, 'before' );
			$authority_loaded = true;
		}

		$guard_loaded = false;
		if ( WebsiteBuilderAsset::readable( self::WORKFLOW_GUARD_SCRIPT ) ) {
			$guard_deps = array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch' );
			if ( $authority_loaded ) $guard_deps[] = self::AUTHORITY_HANDLE;
			wp_enqueue_script(
				self::WORKFLOW_GUARD_HANDLE,
				WebsiteBuilderAsset::url( self::WORKFLOW_GUARD_SCRIPT ),
				$guard_deps,
				WebsiteBuilderAsset::version( self::WORKFLOW_GUARD_SCRIPT ),
				true
			);
			$guard_loaded = true;
		}

		$workflow_loaded = false;
		if ( WebsiteBuilderAsset::readable( self::WORKFLOW_SCRIPT ) ) {
			$workflow_deps = array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch' );
			if ( $authority_loaded ) $workflow_deps[] = self::AUTHORITY_HANDLE;
			if ( $guard_loaded ) $workflow_deps[] = self::WORKFLOW_GUARD_HANDLE;
			if ( wp_script_is( StudioUxPro::HANDLE, 'enqueued' ) ) $workflow_deps[] = StudioUxPro::HANDLE;
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
		if ( $authority_loaded ) $script_deps[] = self::AUTHORITY_HANDLE;
		if ( $workflow_loaded ) $script_deps[] = self::WORKFLOW_HANDLE;
		if ( wp_script_is( StudioUxPro::HANDLE, 'enqueued' ) ) $script_deps[] = StudioUxPro::HANDLE;
		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			$script_deps,
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		// Attach config to the mandatory Pro handle as well. This guarantees the
		// core can boot even when workflow/authority enhancements are unavailable.
		wp_add_inline_script( self::HANDLE, $config_script, 'before' );

		$compact_loaded = false;
		if ( WebsiteBuilderAsset::readable( self::COMPACT_SCRIPT ) ) {
			wp_enqueue_script(
				self::COMPACT_HANDLE,
				WebsiteBuilderAsset::url( self::COMPACT_SCRIPT ),
				array( self::HANDLE ),
				WebsiteBuilderAsset::version( self::COMPACT_SCRIPT ),
				true
			);
			$compact_loaded = true;
		}

		if ( WebsiteBuilderAsset::readable( self::SHARED_SCRIPT ) ) {
			wp_enqueue_script(
				self::SHARED_SCRIPT_HANDLE,
				WebsiteBuilderAsset::url( self::SHARED_SCRIPT ),
				array( $compact_loaded ? self::COMPACT_HANDLE : self::HANDLE ),
				WebsiteBuilderAsset::version( self::SHARED_SCRIPT ),
				true
			);
		}
	}
}
