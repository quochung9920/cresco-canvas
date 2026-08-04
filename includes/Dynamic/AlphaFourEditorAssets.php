<?php
/**
 * Dynamic Data alpha.4 Gutenberg assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AlphaFourEditorAssets {
	/** Register checked-in editor and shared block assets. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_styles' ) );
	}

	/** Enqueue the alpha.4 Gutenberg runtime. */
	public function enqueue() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/dynamic-alpha4.asset.php';
		$script     = CRESCO_CANVAS_PATH . 'build/dynamic-alpha4.js';
		if ( ! is_readable( $asset_file ) || ! is_readable( $script ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( 'cresco-canvas-dynamic-alpha4', CRESCO_CANVAS_URL . 'build/dynamic-alpha4.js', $asset['dependencies'], $asset['version'], true );
	}

	/** Enqueue frontend/editor styles for the new server-rendered blocks. */
	public function enqueue_styles() {
		$path = CRESCO_CANVAS_PATH . 'assets/css/dynamic-alpha4.css';
		if ( is_readable( $path ) ) {
			wp_enqueue_style( 'cresco-canvas-dynamic-alpha4', CRESCO_CANVAS_URL . 'assets/css/dynamic-alpha4.css', array(), CRESCO_CANVAS_VERSION );
		}
	}
}
