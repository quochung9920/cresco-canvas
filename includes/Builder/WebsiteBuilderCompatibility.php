<?php
/**
 * Compatibility boundary between the unified Website Builder and retired standalone UI layers.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderCompatibility {
	/** Keep the Website Builder as the only UI runtime on its standalone screen. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'remove_legacy_editor_assets' ), 999 );
	}

	public function remove_legacy_editor_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( VisualEditor::PAGE_SLUG !== $page || ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;

		$scripts = array(
			'cresco-canvas-standalone-ai-bridge',
			'cresco-canvas-editor-experience-v2-tools',
			'cresco-canvas-editor-experience-v2-sync',
			'cresco-canvas-editor-experience-v2',
			'cresco-canvas-standalone-history',
			'cresco-canvas-standalone-page-settings',
			'cresco-canvas-standalone-ui-v3',
			'cresco-canvas-widget-control-enhancements',
			'cresco-canvas-standalone-inspector-v2',
			'cresco-canvas-global-config-import',
			'cresco-canvas-viewport-shell',
			'cresco-canvas-standalone-visual-editor',
		);
		$styles = array(
			'cresco-canvas-standalone-ai-bridge',
			'cresco-canvas-editor-experience-v2-tools',
			'cresco-canvas-editor-experience-v2-polish',
			'cresco-canvas-editor-experience-v2',
			'cresco-canvas-standalone-history',
			'cresco-canvas-standalone-page-settings',
			'cresco-canvas-standalone-ui-v3',
			'cresco-canvas-widget-control-enhancements',
			'cresco-canvas-standalone-inspector-v2',
			'cresco-canvas-global-config-import',
			'cresco-canvas-viewport-shell',
			'cresco-canvas-standalone-visual-editor',
		);

		foreach ( $scripts as $handle ) wp_dequeue_script( $handle );
		foreach ( $styles as $handle ) wp_dequeue_style( $handle );
	}
}
