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
	const HANDLE = 'cresco-canvas-studio-global-design-pro';
	const SCRIPT = 'build/studio-global-design-pro.js';
	const STYLE  = 'assets/css/studio-global-design-pro.css';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1430 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! current_user_can( 'edit_theme_options' ) ) return;
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

		$script_deps = array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch' );
		if ( wp_script_is( StudioUxPro::HANDLE, 'enqueued' ) ) $script_deps[] = StudioUxPro::HANDLE;
		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			$script_deps,
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.crescoGlobalDesignProSettings=' . wp_json_encode(
				array(
					'schema'       => 'cresco-global-design-pro/v1',
					'settingsPath' => '/cresco-canvas/v1/settings',
					'tokensPath'   => '/cresco-canvas/v1/design-tokens',
					'resetPath'    => '/cresco-canvas/v1/settings/reset',
					'postId'       => $context->post_id(),
				)
			) . ';',
			'before'
		);
	}
}
