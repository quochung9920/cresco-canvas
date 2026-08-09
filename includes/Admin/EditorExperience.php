<?php
/**
 * Editor Experience v2 progressive enhancement layer.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorExperience {
	const VERSION = '2.0.0';

	/** Register the experience layer after the standalone editor assets. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 40 );
	}

	/** Enqueue only on the Cresco standalone editor screen. */
	public function enqueue() {
		if ( ! isset( $_GET['page'] ) || VisualEditor::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$script = CRESCO_CANVAS_PATH . 'build/editor-experience-v2.js';
		$style  = CRESCO_CANVAS_PATH . 'assets/css/editor-experience-v2.css';
		if ( ! is_readable( $script ) || ! is_readable( $style ) ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-editor-experience-v2',
			CRESCO_CANVAS_URL . 'assets/css/editor-experience-v2.css',
			array( 'cresco-canvas-standalone-ui-v3', 'cresco-canvas-standalone-history' ),
			self::VERSION
		);

		wp_enqueue_script(
			'cresco-canvas-editor-experience-v2',
			CRESCO_CANVAS_URL . 'build/editor-experience-v2.js',
			array( 'cresco-canvas-standalone-ui-v3', 'cresco-canvas-standalone-history', 'cresco-canvas-standalone-page-settings' ),
			self::VERSION,
			true
		);
	}
}
