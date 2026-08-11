<?php
/**
 * Extension, collaboration, and document-adapter platform for Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderPlatform {
	const COMMENTS_META = '_cresco_canvas_collab_comments';
	const PRESENCE_TTL  = 90;
	const MAX_COMMENTS  = 200;

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 34 );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/platform/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_platform' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/platform/(?P<postId>\d+)/presence',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_presence' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_presence_update' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/platform/(?P<postId>\d+)/comments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_comments' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_comment_create' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/platform/(?P<postId>\d+)/comments/(?P<commentId>[a-f0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_comment_update' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_comment_delete' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
			)
		);
	}

	public function can_edit_document( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && in_array( get_post_type( $post_id ), array( 'page', 'cresco_template' ), true ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_platform( WP_REST_Request $request ) {
		$post_id    = absint( $request['postId'] );
		$extensions = apply_filters( 'cresco_canvas_extension_manifest', array() );
		$adapters   = apply_filters(
			'cresco_canvas_document_adapters',
			array(
				'wordpress' => array(
					'id'           => 'wordpress',
					'label'        => __( 'WordPress', 'cresco-canvas' ),
					'capabilities' => array( 'read', 'write', 'history', 'media', 'permissions' ),
					'active'       => true,
				),
			)
		);
		return new WP_REST_Response(
			array(
				'schema'       => 'cresco-studio-platform/v1',
				'postId'       => $post_id,
				'extensions'   => array_values( is_array( $extensions ) ? $extensions : array() ),
				'adapters'     => array_values( is_array( $adapters ) ? $adapters : array() ),
				'capabilities' => array(
					'extensionSdk' => true,
					'presence'     => true,
					'comments'     => true,
					'broadcast'    => true,
					'cloudAdapters'=> true,
				),
			)
		);
	}

	public function rest_presence( WP_REST_Request $request ) {
		return new WP_REST_Response( array_values( $this->presence_for_post( absint( $request['postId'] ) ) ) );
	}

	public function rest_presence_update( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$payload = (array) $request->get_json_params();
		$presence = $this->presence_for_post( $post_id );
		$presence[ (string) $user_id ] = array(
			'userId'    => $user_id,
			'name'      => sanitize_text_field( (string) $user->display_name ),
			'avatar'    => esc_url_raw( get_avatar_url( $user_id, array( 'size' => 64 ) ) ),
			'nodeId'    => substr( sanitize_key( (string) ( $payload['nodeId'] ?? '' ) ), 0, 80 ),
			'device'    => sanitize_key( (string) ( $payload['device'] ?? 'wide' ) ),
			'updatedAt' => time(),
		);
		set_transient( $this->presence_key( $post_id ), $presence, self::PRESENCE_TTL );
		return new WP_REST_Response( array_values( $presence ) );
	}

	public function rest_comments( WP_REST_Request $request ) {
		return new WP_REST_Response( $this->comments_for_post( absint( $request['postId'] ) ) );
	}

	public function rest_comment_create( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$text    = trim( sanitize_textarea_field( (string) ( $payload['text'] ?? '' ) ) );
		if ( '' === $text ) return new WP_Error( 'cresco_comment_empty', __( 'Comment text is required.', 'cresco-canvas' ), array( 'status' => 400 ) );
		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$comments = $this->comments_for_post( $post_id );
		$comments[] = array(
			'id'        => wp_generate_uuid4(),
			'userId'    => $user_id,
			'name'      => sanitize_text_field( (string) $user->display_name ),
			'nodeId'    => substr( sanitize_key( (string) ( $payload['nodeId'] ?? '' ) ), 0, 80 ),
			'text'      => substr( $text, 0, 4000 ),
			'createdAt' => gmdate( 'c' ),
			'resolved'  => false,
		);
		$comments = array_slice( $comments, -self::MAX_COMMENTS );
		$this->save_comments( $post_id, $comments );
		return new WP_REST_Response( end( $comments ), 201 );
	}

	public function rest_comment_update( WP_REST_Request $request ) {
		$post_id    = absint( $request['postId'] );
		$comment_id = sanitize_text_field( (string) ( $request['commentId'] ?? '' ) );
		$payload    = (array) $request->get_json_params();
		$comments   = $this->comments_for_post( $post_id );
		$updated    = null;
		foreach ( $comments as &$comment ) {
			if ( ! hash_equals( (string) ( $comment['id'] ?? '' ), $comment_id ) ) continue;
			if ( array_key_exists( 'resolved', $payload ) ) $comment['resolved'] = rest_sanitize_boolean( $payload['resolved'] );
			if ( array_key_exists( 'text', $payload ) ) {
				$text = trim( sanitize_textarea_field( (string) $payload['text'] ) );
				if ( '' !== $text ) $comment['text'] = substr( $text, 0, 4000 );
			}
			$comment['updatedAt'] = gmdate( 'c' );
			$updated = $comment;
			break;
		}
		unset( $comment );
		if ( null === $updated ) return new WP_Error( 'cresco_comment_missing', __( 'Comment was not found.', 'cresco-canvas' ), array( 'status' => 404 ) );
		$this->save_comments( $post_id, $comments );
		return new WP_REST_Response( $updated );
	}

	public function rest_comment_delete( WP_REST_Request $request ) {
		$post_id    = absint( $request['postId'] );
		$comment_id = sanitize_text_field( (string) ( $request['commentId'] ?? '' ) );
		$comments   = $this->comments_for_post( $post_id );
		$remaining  = array_values( array_filter( $comments, static function ( $comment ) use ( $comment_id ) { return ! is_array( $comment ) || ! hash_equals( (string) ( $comment['id'] ?? '' ), $comment_id ); } ) );
		if ( count( $remaining ) === count( $comments ) ) return new WP_Error( 'cresco_comment_missing', __( 'Comment was not found.', 'cresco-canvas' ), array( 'status' => 404 ) );
		$this->save_comments( $post_id, $remaining );
		return new WP_REST_Response( array( 'deleted' => true, 'id' => $comment_id ) );
	}

	private function save_comments( $post_id, $comments ) {
		$comments = array_values( array_slice( is_array( $comments ) ? $comments : array(), -self::MAX_COMMENTS ) );
		update_post_meta( $post_id, self::COMMENTS_META, wp_json_encode( $comments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private function presence_for_post( $post_id ) {
		$presence = get_transient( $this->presence_key( $post_id ) );
		$presence = is_array( $presence ) ? $presence : array();
		$cutoff   = time() - self::PRESENCE_TTL;
		foreach ( $presence as $key => $item ) {
			if ( ! is_array( $item ) || (int) ( $item['updatedAt'] ?? 0 ) < $cutoff ) unset( $presence[ $key ] );
		}
		return $presence;
	}

	private function comments_for_post( $post_id ) {
		$raw = (string) get_post_meta( $post_id, self::COMMENTS_META, true );
		$data = '' !== $raw ? json_decode( $raw, true ) : array();
		return is_array( $data ) ? array_values( array_slice( $data, -self::MAX_COMMENTS ) ) : array();
	}

	private function presence_key( $post_id ) {
		return 'cresco_presence_' . absint( $post_id );
	}
}
