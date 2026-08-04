<?php
/**
 * Advanced Dynamic Data editor assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdvancedEditorAssets {
	/** Register Gutenberg assets. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_styles' ) );
	}

	/** Enqueue checked-in editor runtime. */
	public function enqueue() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/dynamic-advanced.asset.php';
		$script     = CRESCO_CANVAS_PATH . 'build/dynamic-advanced.js';
		if ( ! is_readable( $asset_file ) || ! is_readable( $script ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( 'cresco-canvas-dynamic-advanced', CRESCO_CANVAS_URL . 'build/dynamic-advanced.js', $asset['dependencies'], $asset['version'], true );
	}

	/** Enqueue shared editor/frontend styles. */
	public function enqueue_styles() {
		$path = CRESCO_CANVAS_PATH . 'assets/css/dynamic-advanced.css';
		if ( is_readable( $path ) ) {
			wp_enqueue_style( 'cresco-canvas-dynamic-advanced', CRESCO_CANVAS_URL . 'assets/css/dynamic-advanced.css', array(), CRESCO_CANVAS_VERSION );
		}
	}
}
