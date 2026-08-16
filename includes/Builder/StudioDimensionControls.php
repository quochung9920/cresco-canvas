<?php
/**
 * Canonical Cresco Studio dimension and border controls.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StudioDimensionControls {
	const SCRIPT = 'build/studio-dimension-controls.js';
	const STYLE  = 'assets/css/studio-dimension-controls.css';
	const HANDLE = 'cresco-canvas-studio-dimension-controls';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1420 );
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

		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			$script_dependencies,
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);

		if ( WebsiteBuilderAsset::readable( self::STYLE ) ) {
			$style_dependencies = array();
			if ( wp_style_is( 'cresco-canvas-website-builder-studio', 'enqueued' ) ) {
				$style_dependencies[] = 'cresco-canvas-website-builder-studio';
			}
			wp_enqueue_style(
				self::HANDLE,
				WebsiteBuilderAsset::url( self::STYLE ),
				$style_dependencies,
				WebsiteBuilderAsset::version( self::STYLE )
			);
		}
	}
}
