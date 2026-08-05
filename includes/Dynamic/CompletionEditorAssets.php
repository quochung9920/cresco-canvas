<?php
/**
 * Final 0.8 Dynamic Data assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CompletionEditorAssets {
	/** Register editor and frontend assets. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_styles' ) );
	}

	/** Enqueue checked-in editor runtime. */
	public function enqueue_editor() {
		$this->enqueue_script( 'cresco-canvas-dynamic-completion-editor', 'build/dynamic-completion.js', 'build/dynamic-completion.asset.php' );
	}

	/** Enqueue progressive enhancement for counts/chips. */
	public function enqueue_frontend() {
		$this->enqueue_script( 'cresco-canvas-dynamic-completion-frontend', 'build/dynamic-completion-frontend.js', 'build/dynamic-completion-frontend.asset.php' );
	}

	/** Enqueue shared styles. */
	public function enqueue_styles() {
		$path = CRESCO_CANVAS_PATH . 'assets/css/dynamic-completion.css';
		if ( is_readable( $path ) ) {
			wp_enqueue_style( 'cresco-canvas-dynamic-completion', CRESCO_CANVAS_URL . 'assets/css/dynamic-completion.css', array(), CRESCO_CANVAS_VERSION );
		}
	}

	/** Enqueue one checked-in runtime when both files exist. */
	private function enqueue_script( $handle, $script, $manifest ) {
		if ( ! is_readable( CRESCO_CANVAS_PATH . $script ) || ! is_readable( CRESCO_CANVAS_PATH . $manifest ) ) {
			return;
		}
		$asset = require CRESCO_CANVAS_PATH . $manifest;
		wp_enqueue_script( $handle, CRESCO_CANVAS_URL . $script, $asset['dependencies'], $asset['version'], true );
	}
}
