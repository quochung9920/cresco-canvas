<?php
/**
 * Commercial license state and remote validation.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Commercial;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LicenseManager {
	const OPTION = 'cresco_canvas_license';
	const REST_NAMESPACE = 'cresco-canvas/v1';

	/** Register REST endpoints. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register licensing routes. */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/commercial/license',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'activate' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'licenseKey' => array( 'type' => 'string', 'required' => true ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'deactivate' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/** @return bool */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/** Return sanitized licensing state. */
	public function get_status() {
		return rest_ensure_response( self::public_state() );
	}

	/** Activate against a provider supplied through a filter. */
	public function activate( $request ) {
		$key = trim( (string) $request->get_param( 'licenseKey' ) );
		if ( strlen( $key ) < 8 || strlen( $key ) > 256 ) {
			return new \WP_Error( 'cresco_invalid_license', __( 'The license key format is invalid.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$endpoint = esc_url_raw( (string) apply_filters( 'cresco_canvas_license_endpoint', '' ) );
		if ( '' === $endpoint ) {
			return new \WP_Error( 'cresco_license_endpoint_missing', __( 'No licensing endpoint is configured.', 'cresco-canvas' ), array( 'status' => 503 ) );
		}

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'action'     => 'activate',
					'licenseKey' => $key,
					'siteUrl'    => home_url( '/' ),
					'version'    => CRESCO_CANVAS_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'cresco_license_transport', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['valid'] ) ) {
			return new \WP_Error( 'cresco_license_rejected', __( 'The licensing service rejected this key.', 'cresco-canvas' ), array( 'status' => 422 ) );
		}

		$state = array(
			'status'      => 'active',
			'last4'       => substr( $key, -4 ),
			'plan'        => sanitize_text_field( (string) ( $body['plan'] ?? '' ) ),
			'expiresAt'   => sanitize_text_field( (string) ( $body['expiresAt'] ?? '' ) ),
			'entitlement' => sanitize_text_field( (string) ( $body['entitlement'] ?? '' ) ),
			'token'       => sanitize_text_field( (string) ( $body['token'] ?? '' ) ),
			'checkedAt'   => gmdate( 'c' ),
		);
		update_option( self::OPTION, $state, false );
		return rest_ensure_response( self::public_state() );
	}

	/** Deactivate locally and remotely when possible. */
	public function deactivate() {
		$state    = self::state();
		$endpoint = esc_url_raw( (string) apply_filters( 'cresco_canvas_license_endpoint', '' ) );
		if ( '' !== $endpoint && ! empty( $state['token'] ) ) {
			wp_safe_remote_post(
				$endpoint,
				array(
					'timeout' => 10,
					'body'    => array(
						'action'  => 'deactivate',
						'token'   => $state['token'],
						'siteUrl' => home_url( '/' ),
					),
				)
			);
		}
		delete_option( self::OPTION );
		return rest_ensure_response( self::public_state() );
	}

	/** @return array */
	public static function state() {
		$value = get_option( self::OPTION, array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return array */
	public static function public_state() {
		$state = self::state();
		return array(
			'status'      => sanitize_key( (string) ( $state['status'] ?? 'inactive' ) ),
			'last4'       => sanitize_text_field( (string) ( $state['last4'] ?? '' ) ),
			'plan'        => sanitize_text_field( (string) ( $state['plan'] ?? '' ) ),
			'expiresAt'   => sanitize_text_field( (string) ( $state['expiresAt'] ?? '' ) ),
			'entitlement' => sanitize_text_field( (string) ( $state['entitlement'] ?? '' ) ),
			'checkedAt'   => sanitize_text_field( (string) ( $state['checkedAt'] ?? '' ) ),
		);
	}
}
