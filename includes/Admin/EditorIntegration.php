<?php
/**
 * Minimal Gutenberg integration.
 *
 * Gutenberg remains the WordPress-native fallback and data model. Cresco's
 * visual application is rendered on its own admin screen by VisualEditor.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorIntegration {
	const ENABLED_META = '_cresco_canvas_enabled';

	/** Register only data-level integration. */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/** Register the Page-level switch consumed by Cresco render/style systems. */
	public function register_meta() {
		register_post_meta(
			'page',
			self::ENABLED_META,
			array(
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'default'           => false,
				'description'       => __( 'Whether Cresco Canvas global design tokens apply to this Page.', 'cresco-canvas' ),
				'label'             => __( 'Cresco Canvas Page styles', 'cresco-canvas' ),
				'revisions_enabled' => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
			)
		);
	}
}
