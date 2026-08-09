<?php
/**
 * Cresco Canvas history and saved revision service.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Session;

use CrescoCanvas\Builder\WebsiteBuilder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HistoryManager {
	const POST_TYPE = 'cresco_revision';
	const DOCUMENT_META = '_cresco_revision_document';
	const CHECKSUM_META = '_cresco_revision_checksum';
	const MAX_REVISIONS = 50;

	/** @var bool */
	private $capturing = false;

	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ), 6 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'added_post_meta', array( $this, 'capture_session_update' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'capture_session_update' ), 20, 4 );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array( 'name' => __( 'Cresco Revisions', 'cresco-canvas' ) ),
				'public' => false,
				'show_ui' => false,
				'show_in_rest' => false,
				'supports' => array( 'author' ),
				'capability_type' => 'post',
				'map_meta_cap' => true,
			)
		);
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/history/(?P<postId>\d+)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'rest_list_revisions' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/history/(?P<postId>\d+)/(?P<revisionId>\d+)/restore',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'rest_restore_revision' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);
	}

	public function can_edit_post( $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function capture_session_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		if ( $this->capturing || SessionManager::META_KEY !== $meta_key || 'page' !== get_post_type( $object_id ) ) {
			return;
		}

		$decoded = is_string( $meta_value ) ? json_decode( $meta_value, true ) : null;
		$session = $this->sanitize_document( $decoded, $object_id );
		if ( ! is_array( $session ) ) {
			return;
		}

		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return;
		}

		$checksum = hash( 'sha256', $json );
		$latest = get_posts(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'private',
				'post_parent' => absint( $object_id ),
				'posts_per_page' => 1,
				'orderby' => 'date ID',
				'order' => 'DESC',
				'fields' => 'ids',
			)
		);
		if ( $latest && $checksum === (string) get_post_meta( (int) $latest[0], self::CHECKSUM_META, true ) ) {
			return;
		}

		$this->capturing = true;
		$revision_id = wp_insert_post(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'private',
				'post_parent' => absint( $object_id ),
				'post_author' => get_current_user_id() ?: (int) get_post_field( 'post_author', $object_id ),
				'post_title' => sprintf( 'Cresco revision for page %d', absint( $object_id ) ),
			),
			true
		);

		if ( ! is_wp_error( $revision_id ) ) {
			update_post_meta( $revision_id, self::DOCUMENT_META, $json );
			update_post_meta( $revision_id, self::CHECKSUM_META, $checksum );
			$this->trim_revisions( $object_id );
		}
		$this->capturing = false;
	}

	public function rest_list_revisions( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$items = array();
		$current_raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$current = $current_raw ? json_decode( $current_raw, true ) : null;
		$current = $this->sanitize_document( $current, $post_id );
		$current_checksum = '';

		if ( is_array( $current ) ) {
			$current_json = wp_json_encode( $current, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$current_checksum = is_string( $current_json ) ? hash( 'sha256', $current_json ) : '';
			$items[] = $this->revision_payload( get_post( $post_id ), 0, $current_checksum, true, count( $this->flatten_nodes( $current['nodes'] ?? array() ) ) );
		}

		$revisions = get_posts(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'private',
				'post_parent' => $post_id,
				'posts_per_page' => self::MAX_REVISIONS,
				'orderby' => 'date ID',
				'order' => 'DESC',
			)
		);

		$seen = array();
		if ( $current_checksum ) {
			$seen[ $current_checksum ] = true;
		}
		foreach ( $revisions as $revision ) {
			$checksum = (string) get_post_meta( $revision->ID, self::CHECKSUM_META, true );
			if ( '' === $checksum || isset( $seen[ $checksum ] ) ) {
				continue;
			}
			$raw = (string) get_post_meta( $revision->ID, self::DOCUMENT_META, true );
			$decoded = $raw ? json_decode( $raw, true ) : null;
			$session = $this->sanitize_document( $decoded, $post_id );
			if ( ! is_array( $session ) ) {
				continue;
			}
			$seen[ $checksum ] = true;
			$items[] = $this->revision_payload( $revision, (int) $revision->ID, $checksum, false, count( $this->flatten_nodes( $session['nodes'] ?? array() ) ) );
		}

		return new WP_REST_Response( array( 'revisions' => $items ) );
	}

	public function rest_restore_revision( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$revision_id = absint( $request['revisionId'] );
		$revision = get_post( $revision_id );
		if ( ! $revision || self::POST_TYPE !== $revision->post_type || $post_id !== (int) $revision->post_parent ) {
			return new WP_Error( 'cresco_history_revision_not_found', __( 'That Cresco revision could not be found.', 'cresco-canvas' ), array( 'status' => 404 ) );
		}

		$raw = (string) get_post_meta( $revision_id, self::DOCUMENT_META, true );
		$decoded = $raw ? json_decode( $raw, true ) : null;
		$session = $this->sanitize_document( $decoded, $post_id );
		if ( ! is_array( $session ) ) {
			return new WP_Error( 'cresco_history_revision_invalid', __( 'That Cresco revision is invalid.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return new WP_Error( 'cresco_history_revision_encode_failed', __( 'The Cresco revision could not be restored.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, SessionManager::META_KEY, $json );
		if ( $this->uses_builder_contract( $decoded, $post_id ) ) {
			update_post_meta( $post_id, WebsiteBuilder::BUILDER_META, WebsiteBuilder::BUILDER_VERSION );
		}
		return new WP_REST_Response(
			array(
				'restored' => true,
				'revisionId' => $revision_id,
				'checksum' => hash( 'sha256', $json ),
			)
		);
	}

	/** Preserve legacy checksum semantics while understanding the expanded Website Builder contract. */
	private function sanitize_document( $decoded, $post_id = 0 ) {
		if ( ! is_array( $decoded ) ) return null;
		$builder = $this->uses_builder_contract( $decoded, $post_id );
		if ( $builder && class_exists( WebsiteBuilder::class ) ) {
			$session = WebsiteBuilder::sanitize_session( $decoded );
			if ( is_array( $session ) ) return $session;
		}
		$session = SessionManager::sanitize_session( $decoded );
		if ( is_array( $session ) ) return $session;
		if ( class_exists( WebsiteBuilder::class ) ) {
			$session = WebsiteBuilder::sanitize_session( $decoded );
			if ( is_array( $session ) ) return $session;
		}
		return null;
	}

	/** Detect the expanded builder document without relying solely on save-order metadata. */
	private function uses_builder_contract( $decoded, $post_id = 0 ) {
		if ( $post_id && class_exists( WebsiteBuilder::class ) && WebsiteBuilder::BUILDER_VERSION === (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return true;
		$legacy = array_fill_keys( array_keys( SessionManager::widget_catalog() ), true );
		$walk = static function ( $nodes ) use ( &$walk, $legacy ) {
			foreach ( (array) $nodes as $node ) {
				if ( ! is_array( $node ) ) continue;
				$type = sanitize_key( (string) ( $node['type'] ?? '' ) );
				if ( ! isset( $legacy[ $type ] ) || array_key_exists( 'states', $node ) || array_key_exists( 'meta', $node ) ) return true;
				if ( $walk( $node['children'] ?? array() ) ) return true;
			}
			return false;
		};
		return $walk( $decoded['nodes'] ?? array() );
	}

	private function revision_payload( $post, $id, $checksum, $current, $node_count ) {
		$author = $post ? get_userdata( (int) $post->post_author ) : false;
		$author_name = $author ? $author->display_name : __( 'Unknown user', 'cresco-canvas' );
		$author_email = $author && current_user_can( 'list_users' ) ? $author->user_email : '';
		$date_gmt = $post ? get_gmt_from_date( $post->post_date, 'c' ) : gmdate( 'c' );
		$date_local = $post ? get_date_from_gmt( get_gmt_from_date( $post->post_date ), 'Y-m-d H:i:s' ) : current_time( 'mysql' );

		return array(
			'id' => $id,
			'current' => (bool) $current,
			'checksum' => $checksum,
			'dateGmt' => $date_gmt,
			'dateLocal' => $date_local,
			'author' => array(
				'id' => $author ? (int) $author->ID : 0,
				'name' => $author_name,
				'email' => $author_email,
			),
			'nodeCount' => (int) $node_count,
		);
	}

	private function trim_revisions( $post_id ) {
		$ids = get_posts(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'private',
				'post_parent' => absint( $post_id ),
				'posts_per_page' => -1,
				'orderby' => 'date ID',
				'order' => 'DESC',
				'fields' => 'ids',
			)
		);
		foreach ( array_slice( $ids, self::MAX_REVISIONS ) as $id ) {
			wp_delete_post( $id, true );
		}
	}

	private function flatten_nodes( $nodes ) {
		$output = array();
		foreach ( (array) $nodes as $node ) {
			$output[] = $node;
			$output = array_merge( $output, $this->flatten_nodes( $node['children'] ?? array() ) );
		}
		return $output;
	}
}
