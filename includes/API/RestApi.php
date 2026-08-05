<?php
/**
 * REST API for Cresco-owned settings.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\API;

use CrescoCanvas\Styles\DesignTokens;
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
		register_rest_route( 'cresco-canvas/v1', '/settings/reset', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reset_settings' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ) );
		register_rest_route( 'cresco-canvas/v1', '/design-tokens', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_design_tokens' ), 'permission_callback' => array( $this, 'can_manage_settings' ) ) );
	}

	public function can_manage_settings() { return current_user_can( 'edit_theme_options' ); }
	public function get_settings() { return new WP_REST_Response( GlobalStyles::get_settings() ); }
	public function get_design_tokens() { return new WP_REST_Response( DesignTokens::catalog( GlobalStyles::get_settings() ) ); }

	public function save_settings( WP_REST_Request $request ) {
		$settings = GlobalStyles::sanitize_settings( (array) $request->get_json_params() );
		update_option( 'cresco_canvas_settings', $settings, false );
		return new WP_REST_Response( $settings );
	}

	public function reset_settings() {
		$settings = GlobalStyles::sanitize_settings( GlobalStyles::defaults() );
		update_option( 'cresco_canvas_settings', $settings, false );
		return new WP_REST_Response( $settings );
	}

	public function settings_schema() {
		$string_map = array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'string' ) );
		$integer_map = array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'integer' ) );
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'additionalProperties' => false,
			'title' => 'cresco-canvas-settings',
			'type' => 'object',
			'properties' => array(
				'background' => array( 'type' => 'string' ),
				'containerMax' => array( 'type' => 'integer' ),
				'contentMax' => array( 'type' => 'integer' ),
				'fontFamily' => array( 'type' => 'string' ),
				'muted' => array( 'type' => 'string' ),
				'primary' => array( 'type' => 'string' ),
				'radius' => array( 'type' => 'integer' ),
				'fluidTokens' => $string_map,
				'breakpoints' => $integer_map,
				'customColors' => $string_map,
				'aliases' => $string_map,
				'removeDataOnUninstall' => array( 'type' => 'boolean' ),
				'schemaVersion' => array( 'type' => 'integer' ),
				'text' => array( 'type' => 'string' ),
			),
		);
	}
}
