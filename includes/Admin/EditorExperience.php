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

		$script      = CRESCO_CANVAS_PATH . 'build/editor-experience-v2.js';
		$sync_script = CRESCO_CANVAS_PATH . 'build/editor-experience-v2-sync.js';
		$style       = CRESCO_CANVAS_PATH . 'assets/css/editor-experience-v2.css';
		$polish      = CRESCO_CANVAS_PATH . 'assets/css/editor-experience-v2-polish.css';
		if ( ! is_readable( $script ) || ! is_readable( $sync_script ) || ! is_readable( $style ) || ! is_readable( $polish ) ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-editor-experience-v2',
			CRESCO_CANVAS_URL . 'assets/css/editor-experience-v2.css',
			array( 'cresco-canvas-standalone-ui-v3', 'cresco-canvas-standalone-history' ),
			self::VERSION
		);
		wp_enqueue_style(
			'cresco-canvas-editor-experience-v2-polish',
			CRESCO_CANVAS_URL . 'assets/css/editor-experience-v2-polish.css',
			array( 'cresco-canvas-editor-experience-v2' ),
			self::VERSION
		);

		wp_enqueue_script(
			'cresco-canvas-editor-experience-v2',
			CRESCO_CANVAS_URL . 'build/editor-experience-v2.js',
			array( 'cresco-canvas-standalone-ui-v3', 'cresco-canvas-standalone-history', 'cresco-canvas-standalone-page-settings' ),
			self::VERSION,
			true
		);

		// Inspector v2 and the control engine intentionally strip the existing
		// responsive-badge class while resolving labels. Reuse that compatibility
		// marker so visual source badges never alter a control's accessible/name
		// contract or the legacy label-to-control mapping.
		wp_add_inline_script(
			'cresco-canvas-editor-experience-v2',
			"(function(d){var r=d.getElementById('cresco-canvas-standalone-editor');if(!r)return;function tag(){r.querySelectorAll('.cc-experience-source-badge').forEach(function(n){n.classList.add('cc-inspector-v2-responsive-badge');n.setAttribute('aria-hidden','true');});}tag();if(window.MutationObserver)new MutationObserver(tag).observe(r,{childList:true,subtree:true});})(document);",
			'after'
		);

		wp_enqueue_script(
			'cresco-canvas-editor-experience-v2-sync',
			CRESCO_CANVAS_URL . 'build/editor-experience-v2-sync.js',
			array( 'cresco-canvas-editor-experience-v2' ),
			self::VERSION,
			true
		);
	}
}
