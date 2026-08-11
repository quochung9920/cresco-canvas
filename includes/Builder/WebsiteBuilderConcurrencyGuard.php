<?php
/**
 * Optimistic concurrency and atomic write boundary for canonical Cresco documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Infrastructure\WordPress\Storage\WordPressDocumentRepository;
use CrescoCanvas\Page\PageSettings;
use CrescoCanvas\Theme\ThemeBuilder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderConcurrencyGuard {
	const LOCK_TTL = 15.0;
	const RETRIES  = 40;
	const SLEEP_US = 25000;

	/** @var array<int,array{key:string,token:string}> */
	private $owned = array();

	/** @var WordPressDocumentRepository|null */
	private $repository;

	public function __construct( $repository = null ) {
		$this->repository = $repository instanceof WordPressDocumentRepository ? $repository : new WordPressDocumentRepository();
	}

	public function register() {
		add_filter( 'rest_pre_dispatch', array( $this, 'guard_session_write' ), 9, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'verify_and_release' ), 90, 3 );
	}

	/** Reject stale or unversioned writes while holding the document write mutex. */
	public function guard_session_write( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request ) return $result;
		if ( 'POST' !== strtoupper( (string) $request->get_method() ) ) return $result;

		$post_id = $this->session_post_id( $request );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) return $result;

		$token = $this->claim_lock( $post_id );
		if ( '' === $token ) {
			return new WP_Error(
				'cresco_builder_write_busy',
				__( 'Another save is already being committed for this document. Wait a moment and save again.', 'cresco-canvas' ),
				array( 'status' => 423 )
			);
		}
		$request_id = spl_object_id( $request );
		$this->owned[ $request_id ] = array( 'key' => $this->lock_key( $post_id ), 'token' => $token );

		$payload = (array) $request->get_json_params();
		$base    = sanitize_text_field( (string) ( $payload['baseChecksum'] ?? '' ) );
		$current = $this->current_checksum( $post_id );
		if ( is_wp_error( $current ) ) {
			$this->release_request( $request_id );
			return $current;
		}
		if ( '' === $base ) {
			$this->release_request( $request_id );
			return new WP_Error(
				'cresco_builder_precondition_required',
				__( 'Reload this document before saving so Cresco can verify that no newer version exists.', 'cresco-canvas' ),
				array( 'status' => 428, 'currentChecksum' => $current )
			);
		}
		if ( '' === $current || ! hash_equals( $current, $base ) ) {
			$this->release_request( $request_id );
			return new WP_Error(
				'cresco_builder_conflict',
				__( 'This document changed after it was opened. Reload or reconcile the newer version before saving.', 'cresco-canvas' ),
				array( 'status' => 409, 'currentChecksum' => $current )
			);
		}
		return $result;
	}

	/** Verify persistence before the successful response leaves WordPress, then release the mutex. */
	public function verify_and_release( $response, $server, $request ) {
		unset( $server );
		if ( ! $request instanceof WP_REST_Request ) return $response;
		$request_id = spl_object_id( $request );
		if ( empty( $this->owned[ $request_id ] ) ) return $this->verify_settings_persistence( $response, $request );

		$post_id = $this->session_post_id( $request );
		$status  = is_object( $response ) && method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 500;
		if ( $post_id && $status >= 200 && $status < 300 ) {
			$data = is_object( $response ) && method_exists( $response, 'get_data' ) ? (array) $response->get_data() : array();
			$reported = sanitize_text_field( (string) ( $data['checksum'] ?? '' ) );
			$current  = $this->current_checksum( $post_id );
			if ( is_wp_error( $current ) || '' === $reported || '' === $current || ! hash_equals( $current, $reported ) ) {
				$this->release_request( $request_id );
				return new WP_REST_Response(
					array(
						'code'            => 'cresco_builder_persistence_mismatch',
						'message'         => __( 'The document write could not be verified. Your editor still has the local changes; save again after checking storage.', 'cresco-canvas' ),
						'currentChecksum' => is_wp_error( $current ) ? '' : $current,
					),
					500
				);
			}
		}
		$this->release_request( $request_id );
		return $response;
	}

	private function verify_settings_persistence( $response, WP_REST_Request $request ) {
		if ( 'POST' !== strtoupper( (string) $request->get_method() ) ) return $response;
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/cresco-canvas/v1/(?:page-settings|website-builder/theme-page-settings)/(\d+)$#', $route, $match ) ) return $response;
		$status = is_object( $response ) && method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 500;
		if ( $status < 200 || $status >= 300 ) return $response;
		$post_id = absint( $match[1] ?? 0 );
		$data = is_object( $response ) && method_exists( $response, 'get_data' ) ? (array) $response->get_data() : array();
		$reported = isset( $data['settings'] ) && is_array( $data['settings'] ) ? PageSettings::sanitize( $data['settings'] ) : null;
		$raw = get_post_meta( $post_id, PageSettings::META_KEY, true );
		$decoded = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null );
		$stored = PageSettings::sanitize( is_array( $decoded ) ? $decoded : array() );
		if ( ! is_array( $reported ) || ! hash_equals( hash( 'sha256', (string) wp_json_encode( $stored ) ), hash( 'sha256', (string) wp_json_encode( $reported ) ) ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'cresco_settings_persistence_mismatch',
					'message' => __( 'The settings write could not be verified. Keep this editor open and save again after checking storage.', 'cresco-canvas' ),
				),
				500
			);
		}
		return $response;
	}

	/** Resolve every public Session write path to the same document guard. */
	private function session_post_id( WP_REST_Request $request ) {
		$route = (string) $request->get_route();
		$post_id = 0;
		if ( preg_match( '#^/cresco-canvas/v1/session/(\d+)$#', $route, $match ) ) {
			$post_id = absint( $match[1] ?? 0 );
		} elseif ( preg_match( '#^/cresco-canvas/v1/website-builder/(?:theme-)?session/(\d+)$#', $route, $match ) ) {
			$post_id = absint( $match[1] ?? 0 );
		}
		$type = $post_id ? (string) get_post_type( $post_id ) : '';
		return $post_id && in_array( $type, array( 'page', ThemeBuilder::POST_TYPE ), true ) ? $post_id : 0;
	}

	private function lock_key( $post_id ) {
		return '_cresco_builder_write_' . substr( hash( 'sha256', (string) absint( $post_id ) ), 0, 40 );
	}

	private function claim_lock( $post_id ) {
		$key   = $this->lock_key( $post_id );
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

	private function release_request( $request_id ) {
		if ( empty( $this->owned[ $request_id ] ) ) return;
		$lock = $this->owned[ $request_id ];
		if ( hash_equals( (string) $lock['token'], (string) get_option( $lock['key'], '' ) ) ) delete_option( $lock['key'] );
		unset( $this->owned[ $request_id ] );
	}

	private function current_checksum( $post_id ) {
		return $this->repository->checksum( $post_id );
	}
}
