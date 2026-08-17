<?php
/**
 * Contract-aware state tabs for Cresco Studio widget controls.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes widget pseudo-state editing compact and consistent without changing
 * the saved document schema. Studio remains the owner of state persistence.
 */
final class StudioWidgetStateTabs {
	const HANDLE = 'cresco-canvas-studio-widget-state-tabs';
	const SCRIPT = 'build/studio-widget-state-tabs.js';
	const STYLE  = 'assets/css/studio-widget-state-tabs.css';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1435 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! wp_script_is( WebsiteBuilderStudio::HANDLE, 'enqueued' ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array( WebsiteBuilderStudio::HANDLE, 'wp-api-fetch' ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);

		$style_dependencies = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( StudioUxPro::HANDLE, 'enqueued' ) ) $style_dependencies[] = StudioUxPro::HANDLE;
		if ( wp_style_is( StudioColorHarmony::LIGHT_HANDLE, 'enqueued' ) ) $style_dependencies[] = StudioColorHarmony::LIGHT_HANDLE;

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$style_dependencies,
			WebsiteBuilderAsset::version( self::STYLE )
		);
	}
}
