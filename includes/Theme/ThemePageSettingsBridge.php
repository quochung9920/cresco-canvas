<?php
/**
 * Durable Page Settings adapter for Theme Studio documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Theme;

use CrescoCanvas\Page\PageSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemePageSettingsBridge {
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 50 );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/theme-page-settings/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_get' ), 'permission_callback' => array( $this, 'can_edit' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save' ), 'permission_callback' => array( $this, 'can_edit' ) ),
			),
			true
		);
	}

	public function can_edit( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && ThemeBuilder::POST_TYPE === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_get( WP_REST_Request $request ) {
		$settings = $this->load( absint( $request['postId'] ) );
		return new WP_REST_Response( array( 'settings' => $settings, 'effective' => PageSettings::effective( $settings ) ) );
	}

	public function rest_save( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input   = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : $payload;
		$css     = PageSettings::sanitize_page_custom_css( $input['customCSS'] ?? '' );
		if ( is_wp_error( $css ) ) return $css;
		$input['customCSS'] = $css;
		$settings = PageSettings::sanitize( $input );
		$json = wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_theme_page_settings_encode', __( 'Theme Page Settings could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		update_post_meta( $post_id, PageSettings::META_KEY, $json );
		return new WP_REST_Response( array( 'settings' => $settings, 'effective' => PageSettings::effective( $settings ), 'savedAt' => gmdate( 'c' ) ) );
	}

	private function load( $post_id ) {
		$raw = get_post_meta( $post_id, PageSettings::META_KEY, true );
		if ( is_array( $raw ) ) return PageSettings::sanitize( $raw );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return PageSettings::sanitize( is_array( $decoded ) ? $decoded : array() );
	}
}
