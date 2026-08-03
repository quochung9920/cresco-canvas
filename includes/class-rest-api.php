<?php

namespace CrescoCanvas;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest_API {
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'cresco-canvas/v1',
			'/pages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'pages' ),
				'permission_callback' => static fn (): bool => current_user_can( 'edit_pages' ),
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/pages/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_page' ),
					'permission_callback' => array( $this, 'can_edit_page' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_page' ),
					'permission_callback' => array( $this, 'can_edit_page' ),
				),
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => static fn (): bool => current_user_can( 'edit_theme_options' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => static fn (): bool => current_user_can( 'edit_theme_options' ),
				),
			)
		);
	}

	public function can_edit_page( WP_REST_Request $request ): bool {
		return current_user_can( 'edit_post', absint( $request['id'] ) );
	}

	public function pages(): WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$data = array_map(
			static fn ( $post ): array => array(
				'id'       => $post->ID,
				'title'    => get_the_title( $post ) ?: __( '(Untitled)', 'cresco-canvas' ),
				'status'   => $post->post_status,
				'modified' => get_post_modified_time( DATE_ATOM, true, $post ),
				'preview'  => get_preview_post_link( $post ),
			),
			$posts
		);

		return new WP_REST_Response( $data );
	}

	public function get_page( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = get_post( absint( $request['id'] ) );
		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'cresco_page_missing', __( 'Page not found.', 'cresco-canvas' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'id'      => $post->ID,
				'title'   => get_the_title( $post ),
				'content' => $post->post_content,
				'status'  => $post->post_status,
				'preview' => get_preview_post_link( $post ),
			)
		);
	}

	public function save_page( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = absint( $request['id'] );
		$content = (string) $request->get_param( 'content' );
		$title   = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$status  = sanitize_key( (string) $request->get_param( 'status' ) );

		$allowed_statuses = array( 'draft', 'publish', 'pending', 'private' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = get_post_status( $id ) ?: 'draft';
		}

		$result = wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => $title,
				'post_content' => wp_slash( $content ),
				'post_status'  => $status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'saved'   => true,
				'id'      => $id,
				'preview' => get_preview_post_link( $id ),
			)
		);
	}

	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( Global_Styles::get_settings() );
	}

	public function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = Global_Styles::sanitize_settings( (array) $request->get_json_params() );
		update_option( 'cresco_canvas_settings', $settings, false );
		return new WP_REST_Response( $settings );
	}
}
