<?php
/**
 * Progressive professional UX enhancements for the unified Website Builder.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderProfessionalUx {
	const SCRIPT_HANDLE          = 'cresco-canvas-website-builder-professional-ux';
	const PREVIEW_SCRIPT_HANDLE  = 'cresco-canvas-website-builder-preview-fit';

	/** Register the professional UX layer after the compatibility controls. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ), 1001 );
	}

	/** Enqueue only on the Cresco Website Builder screen. */
	public function enqueue_editor_assets() {
		$post_id = $this->requested_editor_post_id();
		if ( ! $post_id ) return;

		$script_path         = CRESCO_CANVAS_PATH . 'build/website-builder-professional-ux.js';
		$style_path          = CRESCO_CANVAS_PATH . 'assets/css/website-builder-professional-ux.css';
		$preview_script_path = CRESCO_CANVAS_PATH . 'build/website-builder-preview-fit.js';
		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) ) return;

		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			CRESCO_CANVAS_URL . 'assets/css/website-builder-professional-ux.css',
			array( 'cresco-canvas-website-builder-controls' ),
			$this->asset_version( $style_path )
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CRESCO_CANVAS_URL . 'build/website-builder-professional-ux.js',
			array( 'cresco-canvas-website-builder-controls' ),
			$this->asset_version( $script_path ),
			true
		);

		if ( is_readable( $preview_script_path ) ) {
			wp_enqueue_script(
				self::PREVIEW_SCRIPT_HANDLE,
				CRESCO_CANVAS_URL . 'build/website-builder-preview-fit.js',
				array( self::SCRIPT_HANDLE ),
				$this->asset_version( $preview_script_path ),
				true
			);
		}
	}

	/** Resolve and authorize the current Website Builder Page. */
	private function requested_editor_post_id() {
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( VisualEditor::PAGE_SLUG !== $page || ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return 0;
		return $post_id;
	}

	/** Content-derived cache version so UX changes cannot be hidden by stale assets. */
	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}
}
