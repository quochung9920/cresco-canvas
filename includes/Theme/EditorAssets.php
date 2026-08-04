<?php
/**
 * Theme Builder Gutenberg editor assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorAssets {
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	public function enqueue() {
		if ( ! function_exists( 'get_current_screen' ) ) { return; }
		$screen = get_current_screen();
		if ( ! $screen || ThemeBuilder::POST_TYPE !== $screen->post_type || ! $screen->is_block_editor() ) { return; }
		$asset_path = CRESCO_CANVAS_PATH . 'build/theme-builder.asset.php';
		$script_path = CRESCO_CANVAS_PATH . 'build/theme-builder.js';
		$style_path = CRESCO_CANVAS_PATH . 'assets/css/theme-builder.css';
		if ( ! is_readable( $asset_path ) || ! is_readable( $script_path ) || ! is_readable( $style_path ) ) { return; }
		$asset = require $asset_path;
		wp_enqueue_style( 'cresco-canvas-theme-builder', CRESCO_CANVAS_URL . 'assets/css/theme-builder.css', array( 'wp-components' ), (string) $asset['version'] );
		wp_enqueue_script( 'cresco-canvas-theme-builder', CRESCO_CANVAS_URL . 'build/theme-builder.js', (array) $asset['dependencies'], (string) $asset['version'], true );
		wp_set_script_translations( 'cresco-canvas-theme-builder', 'cresco-canvas' );
	}
}
