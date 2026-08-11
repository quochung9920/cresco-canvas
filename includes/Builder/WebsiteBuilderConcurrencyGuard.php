<?php
/**
 * Optimistic concurrency boundary for canonical Website Builder documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Theme\ThemeBuilder;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderConcurrencyGuard {
	public function register() {
		add_filter( 'rest_pre_dispatch', array( $this, 'guard_session_write' ), 9, 3 );
	}

	/** Reject stale or unversioned writes before they can replace the document. */
	public function guard_session_write( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request ) return $result;
		if ( 'POST' !== strtoupper( (string) $request->get_method() ) ) return $result;

		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/cresco-canvas/v1/website-builder/(?:theme-)?session/(\d+)$#', $route, $match ) ) return $result;

		$post_id = absint( $match[1] ?? 0 );
		$type    = $post_id ? (string) get_post_type( $post_id ) : '';
		if ( ! $post_id || ! in_array( $type, array( 'page', ThemeBuilder::POST_TYPE ), true ) || ! current_user_can( 'edit_post', $post_id ) ) return $result;

		$payload = (array) $request->get_json_params();
		$base    = sanitize_text_field( (string) ( $payload['baseChecksum'] ?? '' ) );
		$current = $this->current_checksum( $post_id );
		if ( '' === $base ) {
			return new WP_Error(
				'cresco_builder_precondition_required',
				__( 'Reload this document before saving so Cresco can verify that no newer version exists.', 'cresco-canvas' ),
				array( 'status' => 428, 'currentChecksum' => $current )
			);
		}
		if ( '' === $current || ! hash_equals( $current, $base ) ) {
			return new WP_Error(
				'cresco_builder_conflict',
				__( 'This document changed after it was opened. Reload or reconcile the newer version before saving.', 'cresco-canvas' ),
				array( 'status' => 409, 'currentChecksum' => $current )
			);
		}
		return $result;
	}

	private function current_checksum( $post_id ) {
		$raw     = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : null;
		$session = is_array( $decoded ) ? WebsiteBuilder::sanitize_session( $decoded ) : WebsiteBuilder::empty_session( $post_id );
		if ( is_wp_error( $session ) ) return '';
		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return is_string( $json ) ? hash( 'sha256', $json ) : '';
	}
}
