<?php
/**
 * Filterable Loop editor assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InteractiveEditorAssets {
	/** Register Gutenberg editor assets. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_styles' ) );
	}

	/** Enqueue the checked-in alpha.5 editor runtime. */
	public function enqueue() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/dynamic-alpha5.asset.php';
		$script     = CRESCO_CANVAS_PATH . 'build/dynamic-alpha5.js';
		if ( ! is_readable( $asset_file ) || ! is_readable( $script ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script(
			'cresco-canvas-dynamic-alpha5',
			CRESCO_CANVAS_URL . 'build/dynamic-alpha5.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/** Enqueue shared editor/frontend styling. */
	public function enqueue_styles() {
		$path = CRESCO_CANVAS_PATH . 'assets/css/dynamic-alpha5.css';
		if ( is_readable( $path ) ) {
			wp_enqueue_style(
				'cresco-canvas-dynamic-alpha5-editor',
				CRESCO_CANVAS_URL . 'assets/css/dynamic-alpha5.css',
				array(),
				CRESCO_CANVAS_VERSION
			);
		}
	}
}
