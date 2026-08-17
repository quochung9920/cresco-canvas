<?php
/**
 * REST API for Cresco-owned settings.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\API;

use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalConfigImporter;
use CrescoCanvas\Styles\GlobalStyles;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestApi {
	public function register() { add_action( 'rest_api_init', array( $this, 'register_routes' ) ); }

	public function register_routes() {
		register_rest_route( 'cresco-canvas/v1', '/settings', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_settings' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ),
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'save_settings' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ),
			'schema' => array( $this, 'settings_schema' ),
		) );
		register_rest_route( 'cresco-canvas/v1', '/settings/import-preview', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'preview_settings_import' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ) );
		register_rest_route( 'cresco-canvas/v1', '/settings/reset', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reset_settings' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ) );
		register_rest_route( 'cresco-canvas/v1', '/design-tokens', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_design_tokens' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ) );
		register_rest_route( 'cresco-canvas/v1', '/site-identity', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_site_identity' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ),
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'save_site_identity' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ),
		) );
	}

	public function can_manage_settings() { return current_user_can( 'edit_theme_options' ); }
	public function get_settings() { return new WP_REST_Response( GlobalStyles::get_settings() ); }
	public function get_design_tokens() { return new WP_REST_Response( DesignTokens::catalog( GlobalStyles::get_settings() ) ); }

	public function save_settings( WP_REST_Request $request ) {
		$settings = GlobalStyles::sanitize_settings( (array) $request->get_json_params() );
		update_option( 'cresco_canvas_settings', $settings, false );
		return new WP_REST_Response( $settings );
	}

	public function preview_settings_import( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();
		$input = array_key_exists( 'input', $payload ) ? $payload['input'] : $payload;
		$result = GlobalConfigImporter::preview( $input );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	public function reset_settings() {
		$settings = GlobalStyles::sanitize_settings( GlobalStyles::defaults() );
		update_option( 'cresco_canvas_settings', $settings, false );
		return new WP_REST_Response( $settings );
	}

	public function get_site_identity() {
		return new WP_REST_Response( $this->site_identity_data() );
	}

	public function save_site_identity( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();
		$name = sanitize_text_field( (string) ( $payload['name'] ?? '' ) );
		$description = function_exists( 'sanitize_textarea_field' )
			? sanitize_textarea_field( (string) ( $payload['description'] ?? '' ) )
			: sanitize_text_field( (string) ( $payload['description'] ?? '' ) );
		$logo_id = absint( $payload['logoId'] ?? 0 );
		$favicon_id = absint( $payload['faviconId'] ?? 0 );

		if ( $logo_id && function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $logo_id ) ) {
			$logo_id = 0;
		}
		if ( $favicon_id && function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $favicon_id ) ) {
			$favicon_id = 0;
		}

		update_option( 'blogname', $name, false );
		update_option( 'blogdescription', $description, false );
		update_option( 'site_icon', $favicon_id, false );

		if ( function_exists( 'set_theme_mod' ) && function_exists( 'remove_theme_mod' ) ) {
			if ( $logo_id ) {
				set_theme_mod( 'custom_logo', $logo_id );
			} else {
				remove_theme_mod( 'custom_logo' );
			}
		}

		return new WP_REST_Response( $this->site_identity_data() );
	}

	public function settings_schema() {
		$string_map = array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'string' ) );
		$integer_map = array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'integer' ) );
		$button_schema = array(
			'type' => 'object',
			'additionalProperties' => false,
			'properties' => array(
				'background' => array( 'type' => 'string' ),
				'text' => array( 'type' => 'string' ),
				'hoverBackground' => array( 'type' => 'string' ),
				'hoverText' => array( 'type' => 'string' ),
				'borderColor' => array( 'type' => 'string' ),
				'borderWidth' => array( 'type' => 'string' ),
				'radius' => array( 'type' => 'string' ),
				'height' => array( 'type' => 'string' ),
				'paddingInline' => array( 'type' => 'string' ),
				'fontWeight' => array( 'type' => 'string' ),
			),
		);
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'additionalProperties' => false,
			'title' => 'cresco-canvas-settings',
			'type' => 'object',
			'properties' => array(
				'background' => array( 'type' => 'string' ),
				'button' => $button_schema,
				'containerMax' => array( 'type' => 'integer' ),
				'contentMax' => array( 'type' => 'integer' ),
				'fontFamily' => array( 'type' => 'string' ),
				'muted' => array( 'type' => 'string' ),
				'primary' => array( 'type' => 'string' ),
				// Kept for backward-compatible settings round trips; no generic radius UI is exposed.
				'radius' => array( 'type' => 'integer' ),
				'fluidTokens' => $string_map,
				'breakpoints' => $integer_map,
				'customColors' => $string_map,
				'aliases' => $string_map,
				'customCss' => array( 'type' => 'string' ),
				'removeDataOnUninstall' => array( 'type' => 'boolean' ),
				'schemaVersion' => array( 'type' => 'integer' ),
				'text' => array( 'type' => 'string' ),
			),
		);
	}

	private function site_identity_data() {
		$logo_id = function_exists( 'get_theme_mod' ) ? absint( get_theme_mod( 'custom_logo', 0 ) ) : 0;
		$favicon_id = absint( get_option( 'site_icon', 0 ) );
		return array(
			'name' => (string) get_option( 'blogname', '' ),
			'description' => (string) get_option( 'blogdescription', '' ),
			'logo' => array( 'id' => $logo_id, 'url' => $this->attachment_url( $logo_id ) ),
			'favicon' => array( 'id' => $favicon_id, 'url' => $this->attachment_url( $favicon_id ) ),
		);
	}

	private function attachment_url( $attachment_id ) {
		if ( ! $attachment_id ) return '';
		if ( function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( is_string( $url ) ) return $url;
		}
		if ( function_exists( 'wp_get_attachment_url' ) ) {
			$url = wp_get_attachment_url( $attachment_id );
			return is_string( $url ) ? $url : '';
		}
		return '';
	}
}
