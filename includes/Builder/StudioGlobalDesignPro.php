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
 * settings and token APIs. The module is UI-only and does not introduce a
 * second persistence model.
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
	const TYPE_SIZE_STYLE_HANDLE    = 'cresco-canvas-studio-global-design-type-size-picker';
	const TYPE_SIZE_STYLE           = 'assets/css/studio-global-design-type-size-picker.css';
	const TYPE_SIZE_SCRIPT_HANDLE   = 'cresco-canvas-studio-global-design-type-size-picker-runtime';
	const TYPE_SIZE_SCRIPT          = 'build/studio-global-design-type-size-picker.js';
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
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::WORKFLOW_GUARD_SCRIPT ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::WORKFLOW_SCRIPT ) || ! WebsiteBuilderAsset::readable( self::WORKFLOW_STYLE ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::COMPACT_SCRIPT ) || ! WebsiteBuilderAsset::readable( self::COMPACT_STYLE ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::FONT_SEARCH_FIX_STYLE ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::TYPE_SIZE_STYLE ) || ! WebsiteBuilderAsset::readable( self::TYPE_SIZE_SCRIPT ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SHARED_STYLE ) || ! WebsiteBuilderAsset::readable( self::SHARED_SCRIPT ) ) return;

		$style_deps = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( StudioUxPro::HANDLE, 'enqueued' ) ) $style_deps[] = StudioUxPro::HANDLE;
		if ( wp_style_is( StudioColorHarmony::LIGHT_HANDLE, 'enqueued' ) ) $style_deps[] = StudioColorHarmony::LIGHT_HANDLE;

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$style_deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);
		wp_enqueue_style(
			self::WORKFLOW_HANDLE,
			WebsiteBuilderAsset::url( self::WORKFLOW_STYLE ),
			array( self::HANDLE ),
			WebsiteBuilderAsset::version( self::WORKFLOW_STYLE )
		);
		wp_enqueue_style(
			self::COMPACT_HANDLE,
			WebsiteBuilderAsset::url( self::COMPACT_STYLE ),
			array( self::WORKFLOW_HANDLE ),
			WebsiteBuilderAsset::version( self::COMPACT_STYLE )
		);
		wp_enqueue_style(
			self::FONT_SEARCH_FIX_HANDLE,
			WebsiteBuilderAsset::url( self::FONT_SEARCH_FIX_STYLE ),
			array( self::COMPACT_HANDLE ),
			WebsiteBuilderAsset::version( self::FONT_SEARCH_FIX_STYLE )
		);
		wp_enqueue_style(
			self::TYPE_SIZE_STYLE_HANDLE,
			WebsiteBuilderAsset::url( self::TYPE_SIZE_STYLE ),
			array( self::FONT_SEARCH_FIX_HANDLE ),
			WebsiteBuilderAsset::version( self::TYPE_SIZE_STYLE )
		);
		wp_enqueue_style(
			self::SHARED_STYLE_HANDLE,
			WebsiteBuilderAsset::url( self::SHARED_STYLE ),
			array( self::TYPE_SIZE_STYLE_HANDLE ),
			WebsiteBuilderAsset::version( self::SHARED_STYLE )
		);

		wp_enqueue_script(
			self::WORKFLOW_GUARD_HANDLE,
			WebsiteBuilderAsset::url( self::WORKFLOW_GUARD_SCRIPT ),
			array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch' ),
			WebsiteBuilderAsset::version( self::WORKFLOW_GUARD_SCRIPT ),
			true
		);

		$workflow_deps = array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch', self::WORKFLOW_GUARD_HANDLE );
		if ( wp_script_is( StudioUxPro::HANDLE, 'enqueued' ) ) $workflow_deps[] = StudioUxPro::HANDLE;
		wp_enqueue_script(
			self::WORKFLOW_HANDLE,
			WebsiteBuilderAsset::url( self::WORKFLOW_SCRIPT ),
			$workflow_deps,
			WebsiteBuilderAsset::version( self::WORKFLOW_SCRIPT ),
			true
		);

		$config = array(
			'schema'       => 'cresco-global-design-pro/v1',
			'settingsPath' => '/cresco-canvas/v1/settings',
			'tokensPath'   => '/cresco-canvas/v1/design-tokens',
			'resetPath'    => '/cresco-canvas/v1/settings/reset',
			'postId'       => $context->post_id(),
			'fonts'        => \CrescoCanvas\Styles\GlobalWebFonts::catalog(),
			'systemFonts'  => \CrescoCanvas\Styles\GlobalWebFonts::system_fonts(),
		);
		wp_add_inline_script(
			self::WORKFLOW_HANDLE,
			'window.crescoGlobalDesignProSettings=' . wp_json_encode( $config ) . ';',
			'before'
		);

		$script_deps = array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch', self::WORKFLOW_HANDLE );
		if ( wp_script_is( StudioUxPro::HANDLE, 'enqueued' ) ) $script_deps[] = StudioUxPro::HANDLE;
		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			$script_deps,
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		wp_enqueue_script(
			self::COMPACT_HANDLE,
			WebsiteBuilderAsset::url( self::COMPACT_SCRIPT ),
			array( self::HANDLE ),
			WebsiteBuilderAsset::version( self::COMPACT_SCRIPT ),
			true
		);
		wp_enqueue_script(
			self::TYPE_SIZE_SCRIPT_HANDLE,
			WebsiteBuilderAsset::url( self::TYPE_SIZE_SCRIPT ),
			array( self::COMPACT_HANDLE ),
			WebsiteBuilderAsset::version( self::TYPE_SIZE_SCRIPT ),
			true
		);
		wp_enqueue_script(
			self::SHARED_SCRIPT_HANDLE,
			WebsiteBuilderAsset::url( self::SHARED_SCRIPT ),
			array( self::TYPE_SIZE_SCRIPT_HANDLE ),
			WebsiteBuilderAsset::version( self::SHARED_SCRIPT ),
			true
		);
	}
}
