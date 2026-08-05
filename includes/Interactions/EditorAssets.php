<?php
/**
 * Gutenberg assets for interaction and form blocks.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Interactions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorAssets {
	/** Register editor hooks. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/** Enqueue checked-in editor runtime. */
	public function enqueue() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/interactions-editor.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script(
			'cresco-canvas-interactions-editor',
			CRESCO_CANVAS_URL . 'build/interactions-editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'cresco-canvas-interactions-editor',
			CRESCO_CANVAS_URL . 'assets/css/interactions-editor.css',
			array(),
			CRESCO_CANVAS_VERSION
		);
	}
}
