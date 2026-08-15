<?php
/**
 * REST boundary for Cresco AI Interchange v1.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use CrescoCanvas\Session\SessionManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AIInterchange {
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ), 30 );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/ai-interchange/(?P<postId>\d+)/context',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_export_context' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/ai-interchange/(?P<postId>\d+)/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_validate_result' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/ai-interchange/(?P<postId>\d+)/visual',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_export_visual' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);
	}

	/** Load the isolated AI bridge and advanced scoped-CSS preview on the standalone editor. */
	public function enqueue_editor_assets( $hook_suffix ) {
		unset( $hook_suffix );
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( VisualEditor::PAGE_SLUG !== $page || ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;

		$scoped_css_preview = CRESCO_CANVAS_PATH . 'build/website-builder-scoped-css-preview.js';
		if ( is_readable( $scoped_css_preview ) ) {
			wp_enqueue_script( 'cresco-canvas-website-builder-scoped-css-preview', CRESCO_CANVAS_URL . 'build/website-builder-scoped-css-preview.js', array( 'cresco-canvas-website-builder' ), CRESCO_CANVAS_VERSION, true );
		}

		$script = CRESCO_CANVAS_PATH . 'build/standalone-ai-bridge.js';
		$style  = CRESCO_CANVAS_PATH . 'assets/css/standalone-ai-bridge.css';
		if ( ! is_readable( $script ) || ! is_readable( $style ) ) return;

		wp_enqueue_style( 'cresco-canvas-standalone-ai-bridge', CRESCO_CANVAS_URL . 'assets/css/standalone-ai-bridge.css', array( 'cresco-canvas-standalone-visual-editor' ), CRESCO_CANVAS_VERSION );
		wp_enqueue_script( 'cresco-canvas-standalone-ai-bridge', CRESCO_CANVAS_URL . 'build/standalone-ai-bridge.js', array( 'cresco-canvas-standalone-visual-editor', 'wp-api-fetch', 'wp-i18n' ), CRESCO_CANVAS_VERSION, true );
		wp_add_inline_script(
			'cresco-canvas-standalone-ai-bridge',
			'window.crescoCanvasStandaloneSettings=window.crescoCanvasStandaloneSettings||{};window.crescoCanvasStandaloneSettings.aiInterchangeContextPath=' . wp_json_encode( '/cresco-canvas/v1/ai-interchange/' . $post_id . '/context' ) . ';window.crescoCanvasStandaloneSettings.aiInterchangeValidatePath=' . wp_json_encode( '/cresco-canvas/v1/ai-interchange/' . $post_id . '/validate' ) . ';',
			'before'
		);
		wp_set_script_translations( 'cresco-canvas-standalone-ai-bridge', 'cresco-canvas' );
	}

	public function can_edit_post( $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_export_context( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $this->saved_session( $post_id );
		if ( is_wp_error( $session ) ) return $session;
		$result = ContextBuilder::build(
			$post_id,
			$session,
			$payload['scope'] ?? 'page',
			isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array(),
			$payload['mode'] ?? 'optimized',
			array(),
			! empty( $payload['includeVisual'] )
		);
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/**
	 * Standalone HTML rendering of a scope.
	 *
	 * Returns a document that opens in any browser without WordPress, so the
	 * exported design can be inspected visually or handed to a reader that looks
	 * at pages rather than reading JSON. Nothing leaves the site on its own; the
	 * editor saves the response to a file the operator carries.
	 */
	public function rest_export_visual( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $this->saved_session( $post_id );
		if ( is_wp_error( $session ) ) return $session;

		$scope_data = ScopeResolver::resolve(
			$session,
			$payload['scope'] ?? 'page',
			isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array()
		);
		if ( is_wp_error( $scope_data ) ) return $scope_data;

		$visual = VisualContext::build( $scope_data['content'], $session, $post_id );
		if ( null === $visual ) {
			return new WP_Error( 'cresco_ai_visual_empty', __( 'The exported scope renders no content.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$post  = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		$title = $post ? (string) get_the_title( $post ) : '';

		return new WP_REST_Response(
			array(
				'schema'   => 'cresco-ai-visual/v1',
				'version'  => 1,
				'scope'    => $scope_data['target']['scope'],
				'filename' => sanitize_file_name( ( $title ? $title : 'cresco-export' ) . '.html' ),
				'document' => VisualContext::document( $visual, $title ),
				'visual'   => $visual,
			)
		);
	}

	public function rest_validate_result( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$current = isset( $payload['currentSession'] ) && is_array( $payload['currentSession'] ) ? $payload['currentSession'] : $this->saved_session( $post_id );
		$current = WebsiteBuilderSessionSanitizer::sanitize_session( $current );
		if ( is_wp_error( $current ) ) return $current;

		$result = $payload['result'] ?? null;
		if ( is_string( $result ) ) {
			$result = json_decode( $result, true );
			if ( ! is_array( $result ) ) return new WP_Error( 'cresco_ai_result_json', __( 'AI result is not valid JSON.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( is_array( $result ) && isset( $result['session'] ) && is_array( $result['session'] ) && empty( $result['schema'] ) ) $result = $result['session'];
		if ( ! is_array( $result ) ) return new WP_Error( 'cresco_ai_result', __( 'AI result must be a Cresco Session or Cresco Patch object.', 'cresco-canvas' ), array( 'status' => 400 ) );

		if ( PatchValidator::SCHEMA === ( $result['schema'] ?? '' ) ) {
			$validated = PatchValidator::validate( $current, $result );
			return is_wp_error( $validated ) ? $validated : new WP_REST_Response( $validated );
		}
		if ( SessionManager::SCHEMA === ( $result['schema'] ?? SessionManager::SCHEMA ) ) {
			$candidate = WebsiteBuilderSessionSanitizer::sanitize_session( $result );
			if ( is_wp_error( $candidate ) ) return $candidate;
			return new WP_REST_Response(
				array(
					'valid'        => true,
					'resultType'   => 'session',
					'schema'       => SessionManager::SCHEMA,
					'baseChecksum' => ContextBuilder::checksum( $current ),
					'checksum'     => ContextBuilder::checksum( $candidate ),
					'stale'        => false,
					'session'      => $candidate,
					'diff'         => DiffEngine::compare( $current, $candidate ),
					'idMap'        => array(),
				)
			);
		}
		return new WP_Error( 'cresco_ai_result_schema', __( 'AI result schema is not supported.', 'cresco-canvas' ), array( 'status' => 400, 'schema' => (string) ( $result['schema'] ?? '' ) ) );
	}

	private function saved_session( $post_id ) {
		$raw     = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $decoded ) ) $decoded = array();
		return WebsiteBuilderSessionSanitizer::sanitize_session( $decoded );
	}
}
