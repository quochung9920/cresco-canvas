<?php
/**
 * Explicit synchronization for linked Website Builder component instances.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\AI\IdRemapper;
use CrescoCanvas\AI\ScopeResolver;
use CrescoCanvas\Session\SessionManager;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderComponentSync {
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 32 );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/components/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_sync' ),
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
			)
		);
	}

	public function rest_sync() {
		$components = $this->components();
		if ( ! $components ) return new WP_REST_Response( array( 'syncedPages' => 0, 'syncedInstances' => 0, 'skippedPages' => 0 ) );

		$page_ids = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => WebsiteBuilder::BUILDER_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded maintenance action initiated by an editor.
			'meta_value'     => WebsiteBuilder::BUILDER_VERSION, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded maintenance action initiated by an editor.
		) );
		$synced_pages = 0;
		$synced_instances = 0;
		$skipped_pages = 0;

		foreach ( $page_ids as $page_id ) {
			$page_id = absint( $page_id );
			if ( ! current_user_can( 'edit_post', $page_id ) ) { ++$skipped_pages; continue; }
			$raw = (string) get_post_meta( $page_id, SessionManager::META_KEY, true );
			$session = $raw ? json_decode( $raw, true ) : null;
			if ( ! is_array( $session ) ) { ++$skipped_pages; continue; }
			$session = WebsiteBuilder::sanitize_session( $session );
			if ( is_wp_error( $session ) ) { ++$skipped_pages; continue; }

			$reserved = ScopeResolver::collect_ids( $session['nodes'] ?? array() );
			$changed = false;
			$count = 0;
			$session['nodes'] = $this->sync_nodes( $session['nodes'] ?? array(), $components, $reserved, $changed, $count );
			if ( ! $changed ) continue;
			$sanitized = WebsiteBuilder::sanitize_session( $session );
			if ( is_wp_error( $sanitized ) ) { ++$skipped_pages; continue; }
			$json = wp_json_encode( $sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( ! is_string( $json ) ) { ++$skipped_pages; continue; }
			update_post_meta( $page_id, SessionManager::META_KEY, $json );
			++$synced_pages;
			$synced_instances += $count;
		}

		return new WP_REST_Response( array(
			'syncedPages'     => $synced_pages,
			'syncedInstances' => $synced_instances,
			'skippedPages'    => $skipped_pages,
			'syncedAt'        => gmdate( 'c' ),
		) );
	}

	private function components() {
		$posts = get_posts( array(
			'post_type'      => WebsiteBuilder::COMPONENT_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'fields'         => 'ids',
		) );
		$output = array();
		foreach ( $posts as $component_id ) {
			$raw = (string) get_post_meta( $component_id, WebsiteBuilder::COMPONENT_META, true );
			$node = $raw ? json_decode( $raw, true ) : null;
			if ( ! is_array( $node ) ) continue;
			$session = WebsiteBuilder::sanitize_session( array( 'schema' => SessionManager::SCHEMA, 'version' => SessionManager::VERSION, 'documentId' => 'component', 'nodes' => array( $node ) ) );
			if ( ! is_wp_error( $session ) ) $output[ (int) $component_id ] = $session['nodes'][0];
		}
		return $output;
	}

	private function sync_nodes( $nodes, $components, &$reserved, &$changed, &$count ) {
		$output = array();
		foreach ( (array) $nodes as $node ) {
			$component_id = absint( $node['meta']['componentId'] ?? 0 );
			if ( $component_id && isset( $components[ $component_id ] ) ) {
				$old_ids = ScopeResolver::collect_ids( array( $node ) );
				$reserved = array_values( array_diff( (array) $reserved, $old_ids ) );
				$mapped = IdRemapper::remap_subtree( $components[ $component_id ], $reserved, (string) $node['id'] );
				$replacement = $mapped['node'];
				$replacement['meta'] = array_merge(
					(array) ( $replacement['meta'] ?? array() ),
					array(
						'componentId' => $component_id,
						'label'       => (string) ( $node['meta']['label'] ?? ( $replacement['meta']['label'] ?? '' ) ),
						'locked'      => ! empty( $node['meta']['locked'] ),
						'hidden'      => ! empty( $node['meta']['hidden'] ),
					)
				);
				$node = $replacement;
				$reserved = array_values( array_unique( array_merge( (array) $reserved, ScopeResolver::collect_ids( array( $node ) ) ) ) );
				$changed = true;
				++$count;
			} else {
				$node['children'] = $this->sync_nodes( $node['children'] ?? array(), $components, $reserved, $changed, $count );
			}
			$output[] = $node;
		}
		return $output;
	}
}
