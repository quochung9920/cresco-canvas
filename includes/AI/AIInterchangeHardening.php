<?php
/**
 * Security and fidelity hardening for AI/import/export REST boundaries.
 *
 * Keeps the public interchange schemas stable while enforcing scoped AI return
 * targets, strict portable-node contracts, safe page replacement, and the
 * current high-fidelity AI Context v3 payload.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use CrescoCanvas\Session\SessionManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIInterchangeHardening {
	const INTERCHANGE_SCHEMA  = 'cresco-interchange/v1';
	const INTERCHANGE_VERSION = 1;

	public function register() {
		add_filter( 'rest_request_before_callbacks', array( $this, 'before_callbacks' ), 20, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'after_dispatch' ), 60, 3 );
	}

	/** Reject ambiguous or scope-widening AI/import requests before they reach the callback. */
	public function before_callbacks( $response, $handler, $request ) {
		unset( $handler );
		if ( null !== $response || ! $request instanceof WP_REST_Request ) return $response;

		$route = (string) $request->get_route();
		if ( preg_match( '#^/cresco-canvas/v1/ai-interchange/(\d+)/validate$#', $route, $matches ) ) {
			$error = self::validate_ai_request( absint( $matches[1] ), (array) $request->get_json_params() );
			return is_wp_error( $error ) ? $error : $response;
		}
		if ( preg_match( '#^/cresco-canvas/v1/website-builder/interchange/(\d+)/preview$#', $route, $matches ) ) {
			$error = self::validate_interchange_request( absint( $matches[1] ), (array) $request->get_json_params() );
			return is_wp_error( $error ) ? $error : $response;
		}
		return $response;
	}

	/** Upgrade outgoing AI payloads after all canonical data enrichers have run. */
	public function after_dispatch( $response, $server, $request ) {
		unset( $server );
		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) return $response;

		$route = (string) $request->get_route();
		$data  = $response->get_data();
		if ( ! is_array( $data ) ) return $response;

		if ( preg_match( '#^/cresco-canvas/v1/ai-interchange/(\d+)/context$#', $route ) ) {
			$payload      = (array) $request->get_json_params();
			$request_text = (string) ( $payload['request'] ?? '' );
			if ( isset( $data['package'] ) && is_array( $data['package'] ) && ContextBuilderV3::SCHEMA === ( $data['package']['schema'] ?? '' ) ) {
				$data['package'] = DesignIntelligence::augment_context( $data['package'] );
				if ( array_key_exists( 'prompt', $data ) ) $data['prompt'] = OneShotPrompt::build( $data['package'], $request_text );
				$response->set_data( $data );
				return $response;
			}
			if ( ContextBuilderV3::SCHEMA === ( $data['schema'] ?? '' ) ) {
				$response->set_data( DesignIntelligence::augment_context( $data ) );
			}
			return $response;
		}

		if ( preg_match( '#^/cresco-canvas/v1/website-builder/interchange/(\d+)/export$#', $route, $matches ) && ! empty( $data['package'] ) && is_array( $data['package'] ) ) {
			$post_id = absint( $matches[1] );
			$payload = (array) $request->get_json_params();
			$current = self::current_session( $post_id, $payload );
			if ( is_wp_error( $current ) ) return $response;

			$package = $data['package'];
			$scope   = sanitize_key( (string) ( $package['scope'] ?? 'page' ) );
			$target  = isset( $package['target'] ) && is_array( $package['target'] ) ? $package['target'] : array();
			$context = ContextBuilderV3::build(
				$post_id,
				$current,
				$scope,
				$target,
				$payload['purpose'] ?? 'redesign',
				'optimized',
				array(),
				false,
				(string) ( $payload['request'] ?? '' )
			);
			if ( is_wp_error( $context ) ) return $response;
			$context = DesignIntelligence::augment_context( $context );

			$legacy_ai    = isset( $package['aiContext'] ) && is_array( $package['aiContext'] ) ? $package['aiContext'] : array();
			$architecture = isset( $legacy_ai['widgetArchitectureV2'] ) && is_array( $legacy_ai['widgetArchitectureV2'] ) ? $legacy_ai['widgetArchitectureV2'] : null;
			if ( $architecture ) {
				$context['widgetArchitectureV2'] = self::prune_architecture_blueprints( $architecture, $context );
			}
			$package['aiContext'] = $context;
			if ( isset( $context['scopePackage']['dependencies'] ) ) $package['dependencies'] = (array) $context['scopePackage']['dependencies'];
			$package['source'] = array_merge( (array) ( $package['source'] ?? array() ), array( 'aiContextSchema' => ContextBuilderV3::SCHEMA ) );
			$data['package']   = $package;
			$response->set_data( $data );
		}
		return $response;
	}

	/** Strictly require an explicit supported result schema and preserve the exported target. */
	public static function validate_ai_request( $post_id, $payload ) {
		$result = AIResultNormalizer::normalize( $payload['result'] ?? null );
		if ( is_wp_error( $result ) ) return $result;

		$schema = (string) ( $result['schema'] ?? '' );
		if ( ! in_array( $schema, array( PatchValidator::SCHEMA, SessionManager::SCHEMA ), true ) ) {
			return new WP_Error(
				'cresco_ai_result_schema',
				__( 'AI result must explicitly declare cresco-patch/v1 or cresco-session/v1.', 'cresco-canvas' ),
				array( 'status' => 400, 'schema' => $schema )
			);
		}

		$expected_input = isset( $payload['expectedTarget'] ) && is_array( $payload['expectedTarget'] ) ? $payload['expectedTarget'] : null;
		if ( null === $expected_input ) return true;

		$current = self::current_session( $post_id, $payload );
		if ( is_wp_error( $current ) ) return $current;
		$expected = self::canonical_target( $current, $expected_input );
		if ( is_wp_error( $expected ) ) return $expected;

		if ( SessionManager::SCHEMA === $schema ) {
			if ( 'page' !== (string) ( $expected['scope'] ?? '' ) ) {
				return new WP_Error(
					'cresco_ai_scoped_session_forbidden',
					__( 'A scoped AI task must return a cresco-patch/v1 result. A full Session would widen the edit to the whole document.', 'cresco-canvas' ),
					array( 'status' => 409, 'expectedTarget' => $expected )
				);
			}
			return true;
		}

		$actual = self::canonical_target( $current, isset( $result['target'] ) && is_array( $result['target'] ) ? $result['target'] : array() );
		if ( is_wp_error( $actual ) ) return $actual;
		if ( ! self::same_target( $expected, $actual ) ) {
			return new WP_Error(
				'cresco_ai_target_mismatch',
				__( 'AI result target no longer matches the target that was exported. Re-export the intended target instead of widening the patch.', 'cresco-canvas' ),
				array( 'status' => 409, 'expectedTarget' => $expected, 'actualTarget' => $actual )
			);
		}
		return true;
	}

	/** Validate portable content before the interchange callback can normalize/drop unknown fields. */
	public static function validate_interchange_request( $post_id, $payload ) {
		unset( $post_id );
		$package = $payload['package'] ?? null;
		if ( is_string( $package ) ) $package = json_decode( $package, true );
		if ( ! is_array( $package ) || self::INTERCHANGE_SCHEMA !== ( $package['schema'] ?? '' ) || self::INTERCHANGE_VERSION !== absint( $package['version'] ?? 0 ) ) {
			return new WP_Error( 'cresco_interchange_schema', __( 'Expected a cresco-interchange/v1 package.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$scope = sanitize_key( (string) ( $package['scope'] ?? '' ) );
		if ( ! in_array( $scope, ScopeResolver::SCOPES, true ) ) {
			return new WP_Error( 'cresco_interchange_scope', __( 'The interchange package declares an unsupported scope.', 'cresco-canvas' ), array( 'status' => 400, 'scope' => $scope ) );
		}
		$target = isset( $package['target'] ) && is_array( $package['target'] ) ? $package['target'] : array();
		if ( isset( $target['scope'] ) && sanitize_key( (string) $target['scope'] ) !== $scope ) {
			return new WP_Error( 'cresco_interchange_target_scope', __( 'The interchange package target does not match its exported scope.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$destination = sanitize_key( (string) ( $payload['destination'] ?? 'after' ) );
		if ( 'replace-page' === $destination && 'page' !== $scope ) {
			return new WP_Error(
				'cresco_interchange_scope_widening',
				__( 'A widget, subtree, or selection package cannot replace the entire page. Export the whole page or choose a widget destination.', 'cresco-canvas' ),
				array( 'status' => 409, 'scope' => $scope, 'destination' => $destination )
			);
		}

		$nodes = self::portable_nodes( $package );
		if ( is_wp_error( $nodes ) ) return $nodes;
		foreach ( $nodes as $index => $node ) {
			$valid = ContractRegistry::validate_node( $node, 'package.content.nodes.' . $index );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		return true;
	}

	private static function current_session( $post_id, $payload ) {
		$provided = null;
		if ( isset( $payload['currentSession'] ) && is_array( $payload['currentSession'] ) ) $provided = $payload['currentSession'];
		elseif ( isset( $payload['session'] ) && is_array( $payload['session'] ) ) $provided = $payload['session'];
		if ( is_array( $provided ) ) return WebsiteBuilderSessionSanitizer::sanitize_session( $provided );

		$raw = (string) get_post_meta( absint( $post_id ), SessionManager::META_KEY, true );
		if ( '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) return WebsiteBuilderSessionSanitizer::sanitize_session( $decoded );
		}
		return WebsiteBuilderSessionSanitizer::sanitize_session(
			array(
				'schema'     => SessionManager::SCHEMA,
				'version'    => SessionManager::VERSION,
				'documentId' => 'page-' . absint( $post_id ),
				'nodes'      => array(),
			)
		);
	}

	private static function canonical_target( $session, $target ) {
		$scope = sanitize_key( (string) ( $target['scope'] ?? '' ) );
		if ( '' === $scope ) return new WP_Error( 'cresco_ai_expected_target', __( 'AI target scope is missing.', 'cresco-canvas' ), array( 'status' => 400 ) );
		$resolved = ScopeResolver::resolve( $session, $scope, $target );
		return is_wp_error( $resolved ) ? $resolved : (array) $resolved['target'];
	}

	private static function same_target( $expected, $actual ) {
		$scope = (string) ( $expected['scope'] ?? '' );
		if ( $scope !== (string) ( $actual['scope'] ?? '' ) ) return false;
		if ( 'page' === $scope ) return true;
		if ( in_array( $scope, array( 'selection', 'selection-subtrees' ), true ) ) {
			return array_values( (array) ( $expected['nodeIds'] ?? array() ) ) === array_values( (array) ( $actual['nodeIds'] ?? array() ) );
		}
		return (string) ( $expected['nodeId'] ?? '' ) === (string) ( $actual['nodeId'] ?? '' );
	}

	private static function portable_nodes( $package ) {
		$content = isset( $package['content'] ) && is_array( $package['content'] ) ? $package['content'] : array();
		if ( isset( $content['session'] ) ) {
			if ( ! is_array( $content['session'] ) ) return new WP_Error( 'cresco_interchange_session', __( 'Portable Session content must be an object.', 'cresco-canvas' ), array( 'status' => 400 ) );
			$session = $content['session'];
			if ( isset( $session['schema'] ) && SessionManager::SCHEMA !== (string) $session['schema'] ) return new WP_Error( 'cresco_interchange_session_schema', __( 'Portable Session content uses an unsupported schema.', 'cresco-canvas' ), array( 'status' => 400 ) );
			if ( isset( $session['version'] ) && SessionManager::VERSION !== absint( $session['version'] ) ) return new WP_Error( 'cresco_interchange_session_version', __( 'Portable Session content uses an unsupported version.', 'cresco-canvas' ), array( 'status' => 400 ) );
			return isset( $session['nodes'] ) && is_array( $session['nodes'] ) ? array_values( $session['nodes'] ) : array();
		}
		if ( isset( $content['node'] ) ) return is_array( $content['node'] ) ? array( $content['node'] ) : new WP_Error( 'cresco_interchange_node', __( 'Portable widget content must be an object.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( isset( $content['nodes'] ) ) return is_array( $content['nodes'] ) ? array_values( $content['nodes'] ) : new WP_Error( 'cresco_interchange_nodes', __( 'Portable selection content must be an array.', 'cresco-canvas' ), array( 'status' => 400 ) );
		return new WP_Error( 'cresco_interchange_content', __( 'The interchange package has no portable content.', 'cresco-canvas' ), array( 'status' => 400 ) );
	}

	private static function prune_architecture_blueprints( $architecture, $context ) {
		$blueprints = isset( $architecture['blueprints'] ) && is_array( $architecture['blueprints'] ) ? $architecture['blueprints'] : array();
		if ( ! $blueprints ) return $architecture;
		$contracts = (array) ( $context['scopePackage']['contracts'] ?? array() );
		$types     = array();
		foreach ( array( 'current', 'recommended', 'creationCatalog' ) as $bucket ) {
			$types = array_merge( $types, array_keys( (array) ( $contracts[ $bucket ] ?? array() ) ) );
		}
		$types = array_values( array_unique( array_filter( array_map( 'strval', $types ) ) ) );
		if ( ! $types ) return $architecture;

		$allowed = array_fill_keys( $types, true );
		$architecture['blueprints'] = array_intersect_key( $blueprints, $allowed );
		return $architecture;
	}
}
