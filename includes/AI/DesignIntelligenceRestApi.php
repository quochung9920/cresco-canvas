<?php
/** REST access for Cresco Design Intelligence. */
namespace CrescoCanvas\AI;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DesignIntelligenceRestApi {
	public function register() { add_action( 'rest_api_init', array( $this, 'register_routes' ) ); }

	public function register_routes() {
		register_rest_route( 'cresco-canvas/v1', '/design-intelligence/recommend', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'recommend' ),
			'permission_callback' => array( $this, 'can_use' ),
		) );
		register_rest_route( 'cresco-canvas/v1', '/design-intelligence/catalog', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'catalog' ),
			'permission_callback' => array( $this, 'can_use' ),
		) );
	}

	public function can_use() { return current_user_can( 'edit_pages' ); }

	public function recommend( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();
		$query = (string) ( $payload['request'] ?? $payload['query'] ?? '' );
		$options = array();
		foreach ( array( 'variance', 'density', 'motion', 'mode' ) as $key ) if ( array_key_exists( $key, $payload ) ) $options[ $key ] = $payload[ $key ];
		return new WP_REST_Response( DesignIntelligence::recommend( $query, $options ) );
	}

	public function catalog() { return new WP_REST_Response( DesignIntelligenceCatalog::summary() ); }
}
