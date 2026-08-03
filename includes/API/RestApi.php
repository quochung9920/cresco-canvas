<?php
/**
 * Transitional REST API for the 0.2 editor foundation.
 *
 * Page persistence moves to @wordpress/core-data in milestone 0.3. Until then,
 * this endpoint enforces capabilities, schemas, and optimistic concurrency.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\API;

use CrescoCanvas\Styles\GlobalStyles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestApi {
	/**
	 * Register REST initialization.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register versioned routes with explicit schemas and permissions.
	 */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pages' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/pages/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_page' ),
					'permission_callback' => array( $this, 'can_edit_page' ),
					'args'                => $this->page_id_args(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_page' ),
					'permission_callback' => array( $this, 'can_edit_page' ),
					'args'                => array_merge( $this->page_id_args(), $this->save_args() ),
				),
				'schema' => array( $this, 'page_schema' ),
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'can_manage_settings' ),
				),
				'schema' => array( $this, 'settings_schema' ),
			)
		);
	}

	/**
	 * Verify Page type and edit permission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function can_edit_page( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );
		return $post && 'page' === $post->post_type && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Verify design-setting capability.
	 *
	 * @return bool
	 */
	public function can_manage_settings() {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Return editable Pages, bounded to 100 recent records.
	 *
	 * @return WP_REST_Response
	 */
	public function get_pages() {
		$posts = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$data = array();

		foreach ( $posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$data[] = $this->page_data( $post );
			}
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * Return a Page record.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_page( WP_REST_Request $request ) {
		$post = get_post( absint( $request['id'] ) );

		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'cresco_canvas_page_missing', __( 'Page not found.', 'cresco-canvas' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->page_data( $post ) );
	}

	/**
	 * Save a Page without silently overwriting a newer revision.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_page( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'cresco_canvas_page_missing', __( 'Page not found.', 'cresco-canvas' ), array( 'status' => 404 ) );
		}

		$expected = sanitize_text_field( (string) $request->get_param( 'revision' ) );
		$current  = $this->revision_token( $post );

		if ( $expected && ! hash_equals( $current, $expected ) ) {
			return new WP_Error(
				'cresco_canvas_edit_conflict',
				__( 'This Page changed after you opened it. Reload before saving so newer content is not overwritten.', 'cresco-canvas' ),
				array(
					'status'          => 409,
					'currentRevision' => $current,
				)
			);
		}

		$status = sanitize_key( (string) $request->get_param( 'status' ) );

		if ( in_array( $status, array( 'publish', 'private', 'future' ), true ) && ! current_user_can( 'publish_pages' ) ) {
			return new WP_Error(
				'cresco_canvas_cannot_publish',
				__( 'You are not allowed to publish this Page.', 'cresco-canvas' ),
				array( 'status' => 403 )
			);
		}

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => sanitize_text_field( (string) $request->get_param( 'title' ) ),
				'post_content' => wp_slash( (string) $request->get_param( 'content' ) ),
				'post_status'  => $status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$updated = get_post( $post_id );

		return new WP_REST_Response(
			array(
				'id'          => $post_id,
				'modifiedGmt' => $this->modified_gmt( $updated ),
				'preview'     => (string) get_preview_post_link( $updated ),
				'revision'    => $this->revision_token( $updated ),
				'saved'       => true,
			)
		);
	}

	/**
	 * Return design and editor settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return new WP_REST_Response( GlobalStyles::get_settings() );
	}

	/**
	 * Save normalized design and editor settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ) {
		$settings = GlobalStyles::sanitize_settings( (array) $request->get_json_params() );
		update_option( 'cresco_canvas_settings', $settings, false );

		return new WP_REST_Response( $settings );
	}

	/**
	 * Page ID route arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function page_id_args() {
		return array(
			'id' => array(
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => static function ( $value ) {
					return absint( $value ) > 0;
				},
			),
		);
	}

	/**
	 * Save route arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function save_args() {
		return array(
			'content' => array(
				'required' => true,
				'type'     => 'string',
			),
			'revision' => array(
				'pattern'  => '^[a-f0-9]{64}$',
				'required' => true,
				'type'     => 'string',
			),
			'status' => array(
				'enum'     => array( 'draft', 'publish', 'pending', 'private', 'future' ),
				'required' => true,
				'type'     => 'string',
			),
			'title' => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'type'              => 'string',
			),
		);
	}

	/**
	 * Normalize a Page for the transitional client.
	 *
	 * @param \WP_Post $post Page object.
	 * @return array<string, mixed>
	 */
	private function page_data( $post ) {
		return array(
			'content'     => $post->post_content,
			'id'          => $post->ID,
			'modifiedGmt' => $this->modified_gmt( $post ),
			'preview'     => (string) get_preview_post_link( $post ),
			'revision'    => $this->revision_token( $post ),
			'status'      => $post->post_status,
			'title'       => get_the_title( $post ),
		);
	}

	/**
	 * Return an RFC3339 UTC modified time.
	 *
	 * @param \WP_Post|null $post Page object.
	 * @return string
	 */
	private function modified_gmt( $post ) {
		return $post ? mysql2date( DATE_ATOM, $post->post_modified_gmt, false ) : '';
	}

	/**
	 * Build an exact optimistic-concurrency token from persisted Page state.
	 *
	 * @param \WP_Post|null $post Page object.
	 * @return string
	 */
	private function revision_token( $post ) {
		if ( ! $post ) {
			return '';
		}

		return hash(
			'sha256',
			implode(
				"\0",
				array(
					(string) $post->ID,
					(string) $post->post_modified_gmt,
					(string) $post->post_title,
					(string) $post->post_status,
					(string) $post->post_content,
				)
			)
		);
	}

	/**
	 * Page response schema.
	 *
	 * @return array<string, mixed>
	 */
	public function page_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'cresco-canvas-page',
			'type'       => 'object',
			'properties' => array(
				'content'     => array( 'type' => 'string' ),
				'id'          => array( 'type' => 'integer' ),
				'modifiedGmt' => array( 'format' => 'date-time', 'type' => 'string' ),
				'preview'     => array( 'format' => 'uri', 'type' => 'string' ),
				'revision'    => array( 'pattern' => '^[a-f0-9]{64}$', 'type' => 'string' ),
				'status'      => array( 'type' => 'string' ),
				'title'       => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Settings response schema.
	 *
	 * @return array<string, mixed>
	 */
	public function settings_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'cresco-canvas-settings',
			'type'       => 'object',
			'properties' => array(
				'background'            => array( 'type' => 'string' ),
				'containerMax'          => array( 'type' => 'integer' ),
				'contentMax'            => array( 'type' => 'integer' ),
				'editorPreference'      => array( 'enum' => array( 'canvas', 'wordpress', 'remember' ), 'type' => 'string' ),
				'fontFamily'            => array( 'type' => 'string' ),
				'muted'                 => array( 'type' => 'string' ),
				'primary'               => array( 'type' => 'string' ),
				'radius'                => array( 'type' => 'integer' ),
				'removeDataOnUninstall' => array( 'type' => 'boolean' ),
				'schemaVersion'         => array( 'type' => 'integer' ),
				'text'                  => array( 'type' => 'string' ),
			),
		);
	}
}
