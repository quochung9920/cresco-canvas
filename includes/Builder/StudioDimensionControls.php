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
	const SCRIPT              = 'build/studio-dimension-controls.js';
	const SYNC_SCRIPT         = 'build/studio-dimension-controls-sync.js';
	const STYLE               = 'assets/css/studio-dimension-controls.css';
	const HANDLE              = 'cresco-canvas-studio-dimension-controls';
	const SYNC_HANDLE         = 'cresco-canvas-studio-dimension-controls-sync';
	const PRESET_SCRIPT       = 'build/studio-dimension-presets.js';
	const PRESET_STYLE        = 'assets/css/studio-dimension-presets.css';
	const PRESET_SCRIPT_HANDLE = 'cresco-canvas-studio-dimension-presets-runtime';
	const PRESET_STYLE_HANDLE  = 'cresco-canvas-studio-dimension-presets';

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

		if ( WebsiteBuilderAsset::readable( self::SYNC_SCRIPT ) ) {
			wp_enqueue_script(
				self::SYNC_HANDLE,
				WebsiteBuilderAsset::url( self::SYNC_SCRIPT ),
				array( self::HANDLE ),
				WebsiteBuilderAsset::version( self::SYNC_SCRIPT ),
				true
			);
		}

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

		if ( WebsiteBuilderAsset::readable( self::PRESET_SCRIPT ) ) {
			$preset_script_deps = array( wp_script_is( self::SYNC_HANDLE, 'enqueued' ) ? self::SYNC_HANDLE : self::HANDLE );
			wp_enqueue_script(
				self::PRESET_SCRIPT_HANDLE,
				WebsiteBuilderAsset::url( self::PRESET_SCRIPT ),
				$preset_script_deps,
				WebsiteBuilderAsset::version( self::PRESET_SCRIPT ),
				true
			);
		}

		if ( WebsiteBuilderAsset::readable( self::PRESET_STYLE ) ) {
			wp_enqueue_style(
				self::PRESET_STYLE_HANDLE,
				WebsiteBuilderAsset::url( self::PRESET_STYLE ),
				wp_style_is( self::HANDLE, 'enqueued' ) ? array( self::HANDLE ) : array(),
				WebsiteBuilderAsset::version( self::PRESET_STYLE )
			);
		}
	}
}
