<?php
/**
 * REST API for Cresco-owned settings.
 *
 * Page documents are intentionally absent: Gutenberg and WordPress Core own
 * their native entity, autosave, revision, lock, and publishing workflows.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\API;

use CrescoCanvas\Styles\GlobalStyles;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestApi {
	/**
	 * Register REST initialization.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register only genuinely custom Cresco domain data.
	 */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'can_manage_settings' ),
				),
				'schema' => array( $this, 'settings_schema' ),
			)
		);
	}

	/**
	 * Verify the same capability WordPress uses for site-wide design changes.
	 *
	 * @return bool
	 */
	public function can_manage_settings() {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Return normalized design settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return new WP_REST_Response( GlobalStyles::get_settings() );
	}

	/**
	 * Save normalized design settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ) {
		$settings = GlobalStyles::sanitize_settings( (array) $request->get_json_params() );
		update_option( 'cresco_canvas_settings', $settings, false );

		return new WP_REST_Response( $settings );
	}

	/**
	 * Settings response schema.
	 *
	 * @return array<string, mixed>
	 */
	public function settings_schema() {
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'additionalProperties' => false,
			'title'                => 'cresco-canvas-settings',
			'type'                 => 'object',
			'properties'           => array(
				'background'            => array( 'type' => 'string' ),
				'containerMax'          => array( 'type' => 'integer' ),
				'contentMax'            => array( 'type' => 'integer' ),
				'fontFamily'            => array( 'type' => 'string' ),
				'muted'                 => array( 'type' => 'string' ),
				'primary'               => array( 'type' => 'string' ),
				'radius'                => array( 'type' => 'integer' ),
				'removeDataOnUninstall' => array( 'type' => 'boolean' ),
				'schemaVersion'         => array( 'type' => 'integer' ),
				'text'                  => array( 'type' => 'string' ),
			),
		);
	}
}
