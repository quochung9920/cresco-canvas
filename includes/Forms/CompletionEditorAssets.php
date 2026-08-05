<?php
/**
 * Gutenberg assets for final 0.9 form blocks.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CompletionEditorAssets {
	/** Register editor hook. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/** Enqueue checked-in editor runtime. */
	public function enqueue() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/forms-completion-editor.asset.php';
		if ( ! is_readable( $asset_file ) || ! is_readable( CRESCO_CANVAS_PATH . 'build/forms-completion-editor.js' ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( 'cresco-canvas-forms-completion-editor', CRESCO_CANVAS_URL . 'build/forms-completion-editor.js', $asset['dependencies'], $asset['version'], true );
	}
}
