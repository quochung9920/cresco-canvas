<?php
/**
 * React-native Cresco Studio dimension controls.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the React SDK sizing inspector. Historical DOM proxy/sync behavior
 * stays retired; the canonical React fields remain mounted as the state bridge.
 */
final class StudioDimensionControls {
	const SCRIPT      = 'build/studio-dimension-controls.js';
	const SYNC_SCRIPT = 'build/studio-dimension-controls-sync.js';
	const STYLE       = 'assets/css/studio-dimension-controls.css';
	const HANDLE      = 'cresco-canvas-studio-dimension-controls';
	const SYNC_HANDLE = 'cresco-canvas-studio-dimension-controls-sync';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1420 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! wp_script_is( WebsiteBuilderStudio::HANDLE, 'enqueued' ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) ) return;

		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array( WebsiteBuilderStudio::HANDLE, 'wp-element' ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);

		if ( WebsiteBuilderAsset::readable( self::STYLE ) ) {
			$style_dependencies = array( 'cresco-canvas-website-builder-studio' );
			if ( wp_style_is( 'cresco-canvas-website-builder-premium-polish', 'enqueued' ) ) {
				$style_dependencies[] = 'cresco-canvas-website-builder-premium-polish';
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
