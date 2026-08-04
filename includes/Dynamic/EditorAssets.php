<?php
/**
 * Dynamic Data editor runtime.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorAssets {
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ) );
	}

	public function enqueue() {
		$asset_path = CRESCO_CANVAS_PATH . 'build/dynamic.asset.php';
		$script     = CRESCO_CANVAS_PATH . 'build/dynamic.js';
		$style      = CRESCO_CANVAS_PATH . 'assets/css/dynamic.css';
		if ( ! is_readable( $asset_path ) || ! is_readable( $script ) || ! is_readable( $style ) ) {
			return;
		}
		$asset = require $asset_path;
		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return;
		}
		wp_enqueue_style( 'cresco-canvas-dynamic', CRESCO_CANVAS_URL . 'assets/css/dynamic.css', array( 'wp-components' ), (string) $asset['version'] );
		wp_style_add_data( 'cresco-canvas-dynamic', 'rtl', 'replace' );
		wp_enqueue_script( 'cresco-canvas-dynamic', CRESCO_CANVAS_URL . 'build/dynamic.js', (array) $asset['dependencies'], (string) $asset['version'], true );
		wp_set_script_translations( 'cresco-canvas-dynamic', 'cresco-canvas' );
	}

	public function frontend_styles() {
		if ( wp_style_is( 'cresco-canvas-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'cresco-canvas-dynamic', CRESCO_CANVAS_URL . 'assets/css/dynamic.css', array( 'cresco-canvas-frontend' ), CRESCO_CANVAS_VERSION );
		}
	}
}
