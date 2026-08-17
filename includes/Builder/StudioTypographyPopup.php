<?php
/**
 * Canonical light Typography popup for Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StudioTypographyPopup {
	const SCRIPT = 'build/studio-typography-popup.js';
	const STYLE  = 'assets/css/studio-typography-popup.css';
	const HANDLE = 'cresco-canvas-studio-typography-popup';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1430 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! wp_script_is( WebsiteBuilderStudio::HANDLE, 'enqueued' ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) ) return;

		$script_dependencies = array( WebsiteBuilderStudio::HANDLE );
		if ( wp_script_is( 'cresco-canvas-website-builder-responsive-properties', 'enqueued' ) ) {
			$script_dependencies[] = 'cresco-canvas-website-builder-responsive-properties';
		}
		if ( wp_script_is( StudioDimensionControls::SYNC_HANDLE, 'enqueued' ) ) {
			$script_dependencies[] = StudioDimensionControls::SYNC_HANDLE;
		} elseif ( wp_script_is( StudioDimensionControls::HANDLE, 'enqueued' ) ) {
			$script_dependencies[] = StudioDimensionControls::HANDLE;
		}

		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array_values( array_unique( $script_dependencies ) ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);

		if ( WebsiteBuilderAsset::readable( self::STYLE ) ) {
			$style_dependencies = array();
			if ( wp_style_is( 'cresco-canvas-website-builder-studio', 'enqueued' ) ) {
				$style_dependencies[] = 'cresco-canvas-website-builder-studio';
			}
			if ( wp_style_is( StudioDimensionControls::HANDLE, 'enqueued' ) ) {
				$style_dependencies[] = StudioDimensionControls::HANDLE;
			}
			wp_enqueue_style(
				self::HANDLE,
				WebsiteBuilderAsset::url( self::STYLE ),
				array_values( array_unique( $style_dependencies ) ),
				WebsiteBuilderAsset::version( self::STYLE )
			);
		}
	}
}
