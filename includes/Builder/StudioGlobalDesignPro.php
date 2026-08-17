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
	const HANDLE                = 'cresco-canvas-studio-global-design-pro';
	const SCRIPT                = 'build/studio-global-design-pro.js';
	const STYLE                 = 'assets/css/studio-global-design-pro.css';
	const WORKFLOW_GUARD_HANDLE = 'cresco-canvas-studio-global-design-workflows-guard';
	const WORKFLOW_GUARD_SCRIPT = 'build/studio-global-design-workflows-guard.js';
	const WORKFLOW_HANDLE       = 'cresco-canvas-studio-global-design-workflows';
	const WORKFLOW_SCRIPT       = 'build/studio-global-design-workflows.js';
	const WORKFLOW_STYLE        = 'assets/css/studio-global-design-workflows.css';

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
	}
}
