<?php
/**
 * Gutenberg assets for Templates, Components, and Site Kits.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorAssets {
	/** Register editor asset loading. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/** Load the checked-in runtime only in the native Page editor. */
	public function enqueue() {
		if ( ! $this->is_page_editor() ) {
			return;
		}

		$asset_path = CRESCO_CANVAS_PATH . 'build/templates.asset.php';
		$script     = CRESCO_CANVAS_PATH . 'build/templates.js';
		$style      = CRESCO_CANVAS_PATH . 'assets/css/templates.css';
		if ( ! is_readable( $asset_path ) || ! is_readable( $script ) || ! is_readable( $style ) ) {
			return;
		}

		$asset = require $asset_path;
		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-templates',
			CRESCO_CANVAS_URL . 'assets/css/templates.css',
			array( 'wp-components' ),
			(string) $asset['version']
		);
		wp_style_add_data( 'cresco-canvas-templates', 'rtl', 'replace' );
		wp_enqueue_script(
			'cresco-canvas-templates',
			CRESCO_CANVAS_URL . 'build/templates.js',
			(array) $asset['dependencies'],
			(string) $asset['version'],
			true
		);
		wp_set_script_translations( 'cresco-canvas-templates', 'cresco-canvas' );
	}

	/** @return bool */
	private function is_page_editor() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && 'post' === $screen->base && 'page' === $screen->post_type && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}
}
