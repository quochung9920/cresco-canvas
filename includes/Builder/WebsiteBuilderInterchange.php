<?php
/**
 * Portable page / section / widget interchange for Website Builder documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\AI\ContextBuilder;
use CrescoCanvas\AI\DiffEngine;
use CrescoCanvas\AI\PatchValidator;
use CrescoCanvas\AI\ScopeResolver;
use CrescoCanvas\Session\SessionManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderInterchange {
	const SCHEMA = 'cresco-interchange/v1';
	const VERSION = 1;
	const DESTINATIONS = array( 'replace-page', 'replace', 'before', 'after', 'inside' );

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/interchange/(?P<postId>\d+)/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_export' ),
				'permission_callback' => array( $this, 'can_edit_page' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/interchange/(?P<postId>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_preview_import' ),
				'permission_callback' => array( $this, 'can_edit_page' ),
			)
		);
	}

	public function can_edit_page( $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && in_array( get_post_type( $post_id ), array( 'page', 'cresco_template' ), true ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_export( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = $this->current_session( $post_id, $payload['currentSession'] ?? null );
		if ( is_wp_error( $session ) ) return $session;

		$scope    = sanitize_key( (string) ( $payload['scope'] ?? 'page' ) );
		$target   = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$resolved = ScopeResolver::resolve( $session, $scope, $target );
		if ( is_wp_error( $resolved ) ) return $resolved;
		$context = ContextBuilder::build( $post_id, $session, $scope, $target, 'optimized' );
		if ( is_wp_error( $context ) ) return $context;

		$content = array();
		if ( 'page' === $scope ) {
			$content['session'] = $session;
		} elseif ( in_array( $scope, array( 'selection', 'selection-subtrees' ), true ) ) {
			$content['nodes'] = (array) ( $resolved['content']['nodes'] ?? array() );
		} else {
			$content['node'] = (array) ( $resolved['content']['node'] ?? array() );
		}

		$package = array(
			'schema'       => self::SCHEMA,
			'version'      => self::VERSION,
			'kind'         => $this->kind_for_scope( $scope ),
			'scope'        => $scope,
			'target'       => $resolved['target'],
			'source'       => array(
				'builder'       => WebsiteBuilder::BUILDER_VERSION,
				'sessionSchema' => SessionManager::SCHEMA,
				'postId'        => $post_id,
				'postTitle'     => get_the_title( $post_id ),
				'exportedAt'    => gmdate( 'c' ),
			),
			'content'      => $content,
			'dependencies' => (array) ( $context['dependencies'] ?? array() ),
			'designSystem' => (array) ( $context['designSystem'] ?? array() ),
			'aiContext'    => $context,
		);

		return new WP_REST_Response( array( 'valid' => true, 'package' => $package ) );
	}

	public function rest_preview_import( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$current = $this->current_session( $post_id, $payload['currentSession'] ?? null );
		if ( is_wp_error( $current ) ) return $current;
		$package = $this->parse_package( $payload['package'] ?? null );
		if ( is_wp_error( $package ) ) return $package;

		$destination = sanitize_key( (string) ( $payload['destination'] ?? 'after' ) );
		if ( ! in_array( $destination, self::DESTINATIONS, true ) ) {
			return new WP_Error( 'cresco_interchange_destination', __( 'Unsupported import destination.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		$target_id = (string) ( $payload['targetNodeId'] ?? '' );

		if ( 'replace-page' === $destination ) {
			$candidate = $this->package_session( $package, $current );
			if ( is_wp_error( $candidate ) ) return $candidate;
			return new WP_REST_Response( $this->preview_response( $current, $candidate, array(), $package ) );
		}

		$nodes = $this->package_nodes( $package );
		if ( is_wp_error( $nodes ) ) return $nodes;
		if ( ! $nodes ) return new WP_Error( 'cresco_interchange_empty', __( 'The import package does not contain any widgets.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( '' === $target_id || ! ScopeResolver::find_node( $current['nodes'] ?? array(), $target_id ) ) {
			return new WP_Error( 'cresco_interchange_target', __( 'Choose a valid target widget before importing.', 'cresco-canvas' ), array( 'status' => 400, 'nodeId' => $target_id ) );
		}
		if ( 'replace' === $destination && 1 !== count( $nodes ) ) {
			return new WP_Error( 'cresco_interchange_replace_count', __( 'Replace import accepts exactly one widget or section.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$operations = $this->import_operations( $current, $nodes, $destination, $target_id );
		if ( is_wp_error( $operations ) ) return $operations;
		$patch = array(
			'schema'     => PatchValidator::SCHEMA,
			'target'     => array( 'scope' => 'page' ),
			'operations' => $operations,
		);
		$validated = PatchValidator::validate( $current, $patch );
		if ( is_wp_error( $validated ) ) return $validated;
		$validated['packageSchema'] = self::SCHEMA;
		$validated['warnings']      = $this->dependency_warnings( $package );
		return new WP_REST_Response( $validated );
	}

	private function current_session( $post_id, $provided ) {
		if ( is_array( $provided ) ) return WebsiteBuilder::sanitize_session( $provided );
		$raw     = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : WebsiteBuilder::empty_session( $post_id );
		if ( ! is_array( $decoded ) ) return new WP_Error( 'cresco_interchange_session', __( 'The current Website Builder document is invalid.', 'cresco-canvas' ), array( 'status' => 400 ) );
		return WebsiteBuilder::sanitize_session( $decoded );
	}

	private function parse_package( $package ) {
		if ( is_string( $package ) ) $package = json_decode( $package, true );
		if ( ! is_array( $package ) || self::SCHEMA !== ( $package['schema'] ?? '' ) || self::VERSION !== absint( $package['version'] ?? 0 ) ) {
			return new WP_Error( 'cresco_interchange_schema', __( 'Expected a cresco-interchange/v1 package.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( empty( $package['content'] ) || ! is_array( $package['content'] ) ) {
			return new WP_Error( 'cresco_interchange_content', __( 'The interchange package has no portable content.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		return $package;
	}

	private function package_session( $package, $current ) {
		if ( isset( $package['content']['session'] ) && is_array( $package['content']['session'] ) ) {
			$candidate               = $package['content']['session'];
			$candidate['documentId'] = $current['documentId'];
			return WebsiteBuilder::sanitize_session( $candidate );
		}
		$nodes = $this->package_nodes( $package );
		if ( is_wp_error( $nodes ) ) return $nodes;
		return WebsiteBuilder::sanitize_session(
			array(
				'schema'     => SessionManager::SCHEMA,
				'version'    => SessionManager::VERSION,
				'documentId' => $current['documentId'],
				'nodes'      => $nodes,
			)
		);
	}

	private function package_nodes( $package ) {
		if ( isset( $package['content']['session']['nodes'] ) && is_array( $package['content']['session']['nodes'] ) ) return array_values( $package['content']['session']['nodes'] );
		if ( isset( $package['content']['node'] ) && is_array( $package['content']['node'] ) ) return array( $package['content']['node'] );
		if ( isset( $package['content']['nodes'] ) && is_array( $package['content']['nodes'] ) ) return array_values( $package['content']['nodes'] );
		return new WP_Error( 'cresco_interchange_nodes', __( 'The interchange package does not contain portable widgets.', 'cresco-canvas' ), array( 'status' => 400 ) );
	}

	private function import_operations( $current, $nodes, $destination, $target_id ) {
		if ( 'replace' === $destination ) return array( array( 'op' => 'replaceSubtree', 'nodeId' => $target_id, 'node' => $nodes[0] ) );
		$map    = ScopeResolver::parent_map( $current['nodes'] ?? array() );
		$target = $map[ $target_id ] ?? null;
		if ( ! $target ) return new WP_Error( 'cresco_interchange_target', __( 'The target widget could not be resolved.', 'cresco-canvas' ), array( 'status' => 400 ) );

		if ( 'inside' === $destination ) {
			$target_node = ScopeResolver::find_node( $current['nodes'] ?? array(), $target_id );
			$start       = count( (array) ( $target_node['children'] ?? array() ) );
			$parent_id   = $target_id;
		} else {
			$parent_id = $target['parentId'];
			$start     = (int) $target['index'] + ( 'after' === $destination ? 1 : 0 );
		}
		$operations = array();
		foreach ( array_values( $nodes ) as $offset => $node ) {
			$operations[] = array( 'op' => 'insertNode', 'parentId' => $parent_id, 'index' => $start + $offset, 'node' => $node );
		}
		return $operations;
	}

	private function preview_response( $current, $candidate, $id_map, $package ) {
		return array(
			'valid'      => true,
			'resultType' => 'interchange',
			'schema'     => self::SCHEMA,
			'idMap'      => $id_map,
			'session'    => $candidate,
			'diff'       => DiffEngine::compare( $current, $candidate ),
			'warnings'   => $this->dependency_warnings( $package ),
		);
	}

	private function dependency_warnings( $package ) {
		$dependencies = (array) ( $package['dependencies'] ?? array() );
		$warnings     = array();
		if ( ! empty( $dependencies['media'] ) ) $warnings[] = __( 'Media references are site-local; verify imported images and attachments.', 'cresco-canvas' );
		if ( ! empty( $dependencies['tokens'] ) ) $warnings[] = __( 'This design uses Global Design tokens; verify token mappings on the destination site.', 'cresco-canvas' );
		return $warnings;
	}

	private function kind_for_scope( $scope ) {
		return array(
			'page'               => 'page',
			'subtree'            => 'section',
			'widget'             => 'widget',
			'selection'          => 'selection',
			'selection-subtrees' => 'selection',
		)[ $scope ] ?? 'selection';
	}
}
