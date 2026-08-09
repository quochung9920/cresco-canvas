<?php
/**
 * Short-lived cache and generation-based invalidation for public dynamic queries.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class QueryCache {
	const GENERATION_OPTION = 'cresco_canvas_query_cache_generation';
	const TTL = 60;
	const MAX_CACHED_BYTES = 524288;

	/** Register cache read/write hooks and invalidation signals. */
	public function register() {
		add_filter( 'rest_pre_dispatch', array( $this, 'read_rest_cache' ), 8, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'write_rest_cache' ), 20, 3 );
		add_action( 'save_post', array( $this, 'invalidate_post' ), 20, 2 );
		add_action( 'deleted_post', array( $this, 'invalidate' ) );
		add_action( 'set_object_terms', array( $this, 'invalidate' ) );
		add_action( 'created_term', array( $this, 'invalidate' ) );
		add_action( 'edited_term', array( $this, 'invalidate' ) );
		add_action( 'delete_term', array( $this, 'invalidate' ) );
	}

	/** Return a cached successful public-query response when available. */
	public function read_rest_cache( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request || ! self::cacheable_request( $request ) ) return $result;
		$cached = get_transient( self::key_for_request( $request ) );
		if ( ! is_array( $cached ) || ! array_key_exists( 'data', $cached ) ) return $result;
		$response = new WP_REST_Response( $cached['data'], absint( $cached['status'] ?? 200 ) );
		foreach ( (array) ( $cached['headers'] ?? array() ) as $name => $value ) $response->header( $name, $value );
		$response->header( 'X-Cresco-Cache', 'HIT' );
		return $response;
	}

	/** Cache only successful bounded responses from public signed-query routes. */
	public function write_rest_cache( $response, $server, $request ) {
		unset( $server );
		if ( ! $request instanceof WP_REST_Request || ! self::cacheable_request( $request ) || ! $response instanceof WP_REST_Response ) return $response;
		if ( 200 !== (int) $response->get_status() ) return $response;
		$data = $response->get_data();
		$encoded = wp_json_encode( $data );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_CACHED_BYTES ) return $response;
		set_transient(
			self::key_for_request( $request ),
			array( 'data' => $data, 'status' => 200, 'headers' => array() ),
			self::TTL
		);
		$response->header( 'X-Cresco-Cache', 'MISS' );
		return $response;
	}

	/** Invalidate when a public post changes; private submissions do not churn this cache. */
	public function invalidate_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ! $post || empty( $post->post_type ) ) return;
		$object = get_post_type_object( $post->post_type );
		if ( $object && ! empty( $object->public ) ) $this->invalidate();
	}

	/** Bump a per-site generation instead of scanning/deleting arbitrary transient keys. */
	public function invalidate() {
		$generation = max( 1, absint( get_option( self::GENERATION_OPTION, 1 ) ) );
		update_option( self::GENERATION_OPTION, $generation + 1, false );
	}

	/** Build a stable cache key from canonical request data and current generation. */
	public static function key_for_request( WP_REST_Request $request ) {
		$generation = max( 1, absint( get_option( self::GENERATION_OPTION, 1 ) ) );
		$params = self::canonicalize( (array) $request->get_json_params() );
		$material = array( 'site' => get_current_blog_id(), 'generation' => $generation, 'route' => $request->get_route(), 'params' => $params );
		return 'cc_dq_' . substr( hash( 'sha256', (string) wp_json_encode( $material ) ), 0, 40 );
	}

	private static function cacheable_request( WP_REST_Request $request ) {
		return 'POST' === strtoupper( (string) $request->get_method() ) && in_array( (string) $request->get_route(), array( '/cresco-canvas/v1/dynamic/interactive-query', '/cresco-canvas/v1/dynamic/facet-counts' ), true );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) return $value;
		if ( array_is_list( $value ) ) return array_map( array( self::class, 'canonicalize' ), $value );
		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) $value[ $key ] = self::canonicalize( $item );
		return $value;
	}
}
