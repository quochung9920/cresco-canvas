<?php
/**
 * REST boundary for Cresco AI Interchange v1/v2/v3.
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

	/** Load the AI bridge and scoped-CSS preview on the standalone editor. */
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

		$bridge_version = CRESCO_CANVAS_VERSION . '-' . max( (int) filemtime( $script ), (int) filemtime( $style ) );
		wp_enqueue_style( 'cresco-canvas-standalone-ai-bridge', CRESCO_CANVAS_URL . 'assets/css/standalone-ai-bridge.css', array( 'cresco-canvas-website-builder-studio' ), $bridge_version );
		wp_enqueue_script( 'cresco-canvas-standalone-ai-bridge', CRESCO_CANVAS_URL . 'build/standalone-ai-bridge.js', array( 'cresco-canvas-website-builder', 'wp-api-fetch', 'wp-i18n' ), $bridge_version, true );
		wp_add_inline_script(
			'cresco-canvas-standalone-ai-bridge',
			'window.crescoCanvasStandaloneSettings=window.crescoCanvasStandaloneSettings||{};window.crescoCanvasStandaloneSettings.aiInterchangeContextPath=' . wp_json_encode( '/cresco-canvas/v1/ai-interchange/' . $post_id . '/context' ) . ';window.crescoCanvasStandaloneSettings.aiInterchangeValidatePath=' . wp_json_encode( '/cresco-canvas/v1/ai-interchange/' . $post_id . '/validate' ) . ';window.crescoCanvasStandaloneSettings.aiInterchangeVisualPath=' . wp_json_encode( '/cresco-canvas/v1/ai-interchange/' . $post_id . '/visual' ) . ';',
			'before'
		);
		wp_set_script_translations( 'cresco-canvas-standalone-ai-bridge', 'cresco-canvas' );
	}

	public function can_edit_post( $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	/** Export v1, v2, or the default high-fidelity One-Shot v3 package. */
	public function rest_export_context( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $this->saved_session( $post_id );
		if ( is_wp_error( $session ) ) return $session;

		$scope   = $payload['scope'] ?? 'page';
		$target  = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$mode    = $payload['mode'] ?? 'optimized';
		$profile = sanitize_key( (string) ( $payload['profile'] ?? '' ) );
		$version = absint( $payload['version'] ?? 0 );
		$request_text = (string) ( $payload['request'] ?? '' );

		// Explicit version=2 remains pinned for integrations that need the v2
		// payload. A profile-only One-Shot request resolves to the newest v3.
		if ( 2 === $version ) {
			$include_visual = array_key_exists( 'includeVisual', $payload ) ? (bool) $payload['includeVisual'] : true;
			$package = ContextBuilderV2::build(
				$post_id,
				$session,
				$scope,
				$target,
				$payload['purpose'] ?? 'redesign',
				$mode,
				array(),
				$include_visual
			);
			if ( is_wp_error( $package ) ) return $package;
			if ( in_array( $profile, array( 'one-shot', 'one-shot-v2' ), true ) ) {
				return new WP_REST_Response( array( 'package' => $package, 'prompt' => OneShotPrompt::build( $package, $request_text ) ) );
			}
			return new WP_REST_Response( $package );
		}

		if ( 3 === $version || in_array( $profile, array( 'one-shot', 'one-shot-v3' ), true ) ) {
			$include_visual = array_key_exists( 'includeVisual', $payload ) ? (bool) $payload['includeVisual'] : true;
			$package = ContextBuilderV3::build(
				$post_id,
				$session,
				$scope,
				$target,
				$payload['purpose'] ?? 'redesign',
				$mode,
				array(),
				$include_visual,
				$request_text
			);
			if ( is_wp_error( $package ) ) return $package;
			if ( in_array( $profile, array( 'one-shot', 'one-shot-v3' ), true ) ) {
				return new WP_REST_Response( array( 'package' => $package, 'prompt' => OneShotPrompt::build( $package, $request_text ) ) );
			}
			return new WP_REST_Response( $package );
		}

		$result = ContextBuilder::build( $post_id, $session, $scope, $target, $mode, array(), ! empty( $payload['includeVisual'] ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/** Standalone HTML rendering of a scope, preserving target context when possible. */
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

		$visual = VisualContext::build( $scope_data['content'], $session, $post_id, $scope_data['target'] );
		if ( null === $visual ) return new WP_Error( 'cresco_ai_visual_empty', __( 'The exported scope renders no content.', 'cresco-canvas' ), array( 'status' => 400 ) );

		$post  = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		$title = $post ? (string) get_the_title( $post ) : '';
		return new WP_REST_Response(
			array(
				'schema'   => 'cresco-ai-visual/v2',
				'version'  => 2,
				'scope'    => $scope_data['target']['scope'],
				'filename' => sanitize_file_name( ( $title ? $title : 'cresco-export' ) . '.html' ),
				'document' => VisualContext::document( $visual, $title ),
				'visual'   => $visual,
			)
		);
	}

	/** Validate raw AI output, then attach deterministic review data. */
	public function rest_validate_result( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$current = isset( $payload['currentSession'] ) && is_array( $payload['currentSession'] ) ? $payload['currentSession'] : $this->saved_session( $post_id );
		$current = WebsiteBuilderSessionSanitizer::sanitize_session( $current );
		if ( is_wp_error( $current ) ) return $current;

		$result = AIResultNormalizer::normalize( $payload['result'] ?? null );
		if ( is_wp_error( $result ) ) return $result;

		if ( PatchValidator::SCHEMA === ( $result['schema'] ?? '' ) ) {
			$validated = PatchValidator::validate( $current, $result );
			if ( is_wp_error( $validated ) ) return $validated;
			return new WP_REST_Response( $this->decorate_validation( $current, $validated, $post_id ) );
		}

		if ( SessionManager::SCHEMA === ( $result['schema'] ?? SessionManager::SCHEMA ) ) {
			$candidate = WebsiteBuilderSessionSanitizer::sanitize_session( $result );
			if ( is_wp_error( $candidate ) ) return $candidate;
			$validated = array(
				'valid'      => true,
				'resultType' => 'session',
				'schema'     => SessionManager::SCHEMA,
				'target'     => array( 'scope' => 'page', 'nodeId' => null, 'type' => 'page' ),
				'session'    => $candidate,
				'diff'       => DiffEngine::compare( $current, $candidate ),
				'idMap'      => array(),
			);
			return new WP_REST_Response( $this->decorate_validation( $current, $validated, $post_id ) );
		}

		return new WP_Error( 'cresco_ai_result_schema', __( 'AI result schema is not supported.', 'cresco-canvas' ), array( 'status' => 400, 'schema' => (string) ( $result['schema'] ?? '' ) ) );
	}

	private function decorate_validation( $current, $validated, $post_id ) {
		$validated = (array) $validated;
		$candidate = (array) ( $validated['session'] ?? array() );
		$target    = (array) ( $validated['target'] ?? array( 'scope' => 'page', 'nodeId' => null, 'type' => 'page' ) );
		$validated['quality'] = DesignQualityGate::inspect( $candidate, $target );

		$review = $this->visual_review( $current, $candidate, $target, $post_id );
		if ( $review ) $validated['visualReview'] = $review;
		return $validated;
	}

	private function visual_review( $before_session, $after_session, $target, $post_id ) {
		$before_scope = ScopeResolver::resolve( $before_session, $target['scope'] ?? 'page', $target );
		$after_scope  = ScopeResolver::resolve( $after_session, $target['scope'] ?? 'page', $target );
		if ( is_wp_error( $before_scope ) || is_wp_error( $after_scope ) ) return null;

		$before = VisualContext::build( $before_scope['content'], $before_session, $post_id, $before_scope['target'] );
		$after  = VisualContext::build( $after_scope['content'], $after_session, $post_id, $after_scope['target'] );
		if ( null === $before || null === $after ) return null;

		$post  = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		$title = $post ? (string) get_the_title( $post ) : '';
		return array(
			'target'         => $target,
			'beforeDocument' => VisualContext::document( $before, ( $title ? $title . ' — before' : 'Cresco — before' ) ),
			'afterDocument'  => VisualContext::document( $after, ( $title ? $title . ' — after' : 'Cresco — after' ) ),
			'breakpoints'    => $after['breakpoints'] ?? array(),
			'contextMode'    => $after['contextMode'] ?? 'scope-only',
		);
	}

	private function saved_session( $post_id ) {
		$raw     = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $decoded ) ) $decoded = array();
		return WebsiteBuilderSessionSanitizer::sanitize_session( $decoded );
	}
}
