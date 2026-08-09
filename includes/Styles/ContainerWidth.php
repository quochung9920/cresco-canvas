<?php
/**
 * Authoritative Full Width / Boxed container semantics for Cresco Session.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

use CrescoCanvas\Session\SessionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContainerWidth {
	/** Register editor and frontend styles. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 40 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 40 );
	}

	/** Load width semantics on the standalone Cresco editor only. */
	public function enqueue_editor() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( 'cresco-canvas-editor' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-container-width',
			CRESCO_CANVAS_URL . 'assets/css/container-width.css',
			array( 'cresco-canvas-standalone-visual-editor' ),
			CRESCO_CANVAS_VERSION
		);
	}

	/** Load width semantics only on Pages with an actual Cresco Session. */
	public function enqueue_frontend() {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 || '' === (string) get_post_meta( $post_id, SessionManager::META_KEY, true ) ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-container-width',
			CRESCO_CANVAS_URL . 'assets/css/container-width.css',
			array(),
			CRESCO_CANVAS_VERSION
		);
	}
}
