<?php
/**
 * Cross-request mutexes for public rate-limit and idempotency state updates.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Security;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PublicRequestAtomicity {
	const LOCK_TTL = 3.0;
	const RETRIES  = 20;
	const SLEEP_US = 5000;

	/** @var array<int,array<string,string>> */
	private $owned = array();

	public function register() {
		add_filter( 'rest_pre_dispatch', array( $this, 'acquire' ), 4, 3 );
		add_filter( 'rest_pre_dispatch', array( $this, 'release' ), 6, 3 );
	}

	public function acquire( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request ) return $result;
		$route = (string) $request->get_route();
		if ( ! str_starts_with( $route, '/cresco-canvas/v1/' ) ) return $result;

		$keys = $this->lock_keys( $request );
		if ( ! $keys ) return $result;
		sort( $keys, SORT_STRING );
		$request_id = spl_object_id( $request );
		$this->owned[ $request_id ] = array();
		foreach ( $keys as $key ) {
			$token = $this->claim( $key );
			if ( '' === $token ) {
				$this->release_owned( $request_id );
				return new WP_Error(
					'cresco_request_busy',
					__( 'Another matching request is already being processed. Please retry shortly.', 'cresco-canvas' ),
					array( 'status' => 429 )
				);
			}
			$this->owned[ $request_id ][ $key ] = $token;
		}
		return $result;
	}

	public function release( $result, $server, $request ) {
		unset( $server );
		if ( $request instanceof WP_REST_Request ) $this->release_owned( spl_object_id( $request ) );
		return $result;
	}

	private function lock_keys( WP_REST_Request $request ) {
		$route = (string) $request->get_route();
		$keys  = array();
		if ( SecurityHardening::public_rate_limit_for_route( $route ) > 0 ) {
			$identity = $this->request_identity();
			$form_id  = $this->request_form_id( $request );
			$rate_key = 'cc_rate_' . substr( hash( 'sha256', $route . '|' . $form_id . '|' . $identity ), 0, 40 );
			$keys[]   = $this->option_lock_name( $rate_key );
		}
		$idempotency = $this->idempotency_key_for_request( $request );
		if ( '' !== $idempotency ) $keys[] = $this->option_lock_name( $idempotency );
		return array_values( array_unique( $keys ) );
	}

	private function claim( $key ) {
		$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'cresco-', true );
		for ( $attempt = 0; $attempt < self::RETRIES; $attempt++ ) {
			$value = $token . '|' . ( microtime( true ) + self::LOCK_TTL );
			if ( add_option( $key, $value, '', false ) ) return $value;
			$current = (string) get_option( $key, '' );
			$parts   = explode( '|', $current, 2 );
			$expires = isset( $parts[1] ) ? (float) $parts[1] : 0.0;
			if ( $expires > 0 && $expires < microtime( true ) ) {
				delete_option( $key );
				continue;
			}
			usleep( self::SLEEP_US );
		}
		return '';
	}

	private function release_owned( $request_id ) {
		if ( empty( $this->owned[ $request_id ] ) ) return;
		foreach ( $this->owned[ $request_id ] as $key => $token ) {
			if ( hash_equals( (string) $token, (string) get_option( $key, '' ) ) ) delete_option( $key );
		}
		unset( $this->owned[ $request_id ] );
	}

	private function option_lock_name( $state_key ) {
		return '_cresco_atomic_' . substr( hash( 'sha256', (string) $state_key ), 0, 48 );
	}

	private function request_identity() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		return substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 32 );
	}

	private function request_form_id( WP_REST_Request $request ) {
		$params = array_merge( (array) $request->get_json_params(), (array) $request->get_body_params() );
		if ( ! empty( $params['formId'] ) ) return substr( sanitize_key( (string) $params['formId'] ), 0, 48 );
		if ( ! empty( $params['payload'] ) ) return 'signed-' . substr( hash( 'sha256', (string) $params['payload'] ), 0, 12 );
		return 'unknown';
	}

	private function idempotency_key_for_request( WP_REST_Request $request ) {
		$route = (string) $request->get_route();
		if ( ! in_array( $route, array( '/cresco-canvas/v1/forms/submit', '/cresco-canvas/v1/forms/submit-multipart' ), true ) ) return '';
		$idempotency = substr( preg_replace( '/[^a-zA-Z0-9_-]/', '', sanitize_text_field( (string) $request->get_header( 'x-cresco-idempotency-key' ) ) ), 0, 80 );
		if ( '' === $idempotency ) return '';
		$body_hash = substr( hash( 'sha256', $this->request_fingerprint( $request ) ), 0, 24 );
		return 'cc_once_' . substr( hash( 'sha256', $this->request_form_id( $request ) . '|' . $idempotency . '|' . $body_hash ), 0, 40 );
	}

	private function request_fingerprint( WP_REST_Request $request ) {
		$body = (string) $request->get_body();
		if ( '' !== $body ) return $body;
		return (string) wp_json_encode( array( $request->get_body_params(), array_keys( (array) $request->get_file_params() ) ) );
	}
}
