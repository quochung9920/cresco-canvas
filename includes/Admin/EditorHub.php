<?php
/**
 * Unified Cresco Canvas editor hub.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorHub {
	/** Register editor assets. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ), 100 );
	}

	/** Load the single Cresco Canvas entry point after feature modules. */
	public function enqueue() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/editor-hub.asset.php';
		if ( ! is_readable( $asset_file ) || ! is_readable( CRESCO_CANVAS_PATH . 'build/editor-hub.js' ) ) {
			return;
		}

		$asset = require $asset_file;
		wp_enqueue_script(
			'cresco-canvas-editor-hub',
			CRESCO_CANVAS_URL . 'build/editor-hub.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? CRESCO_CANVAS_VERSION ),
			true
		);
		wp_enqueue_style(
			'cresco-canvas-editor-hub',
			CRESCO_CANVAS_URL . 'assets/css/editor-hub.css',
			array(),
			CRESCO_CANVAS_VERSION
		);
	}
}
