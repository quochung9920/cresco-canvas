<?php
/**
 * Cresco Studio professional product-experience layer.
 *
 * Adds Design Director, Patterns, Quality, project-brief persistence, and a
 * progressive UI shell without taking ownership away from Studio 2.0.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StudioUxPro {
	const HANDLE      = 'cresco-canvas-studio-ux-pro';
	const SCRIPT      = 'build/studio-ux-pro.js';
	const GUARD_SCRIPT = 'build/studio-ux-pro-guard.js';
	const STYLE       = 'assets/css/studio-ux-pro.css';
	const OPTION_KEY  = 'cresco_canvas_project_brief';
	const META_KEY    = '_cresco_canvas_project_brief';
	const BRIEF_SCHEMA = 'cresco-project-brief/v1';

	/** Register the additive Studio UX layer and its small persistence API. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1410 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'inject_brief_request' ), 12, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'enrich_ai_payload' ), 90, 3 );
	}

	/** Enqueue only inside the canonical Studio runtime. */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::GUARD_SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$style_deps = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( 'cresco-canvas-website-builder-premium-polish', 'enqueued' ) ) {
			$style_deps[] = 'cresco-canvas-website-builder-premium-polish';
		}

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$style_deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);
		wp_add_inline_style(
			self::HANDLE,
			'#cresco-canvas-standalone-editor .cc-studio-ux-source.is-override{display:none!important}' .
			'#cresco-canvas-standalone-editor .cc-studio-property-header-row{width:100%!important;min-width:0!important;justify-content:flex-start!important}' .
			'#cresco-canvas-standalone-editor .cc-studio-property-header-row>.cc-studio-ux-source{order:10!important;margin-left:6px!important;margin-right:0!important;flex:0 0 auto!important}' .
			'#cresco-canvas-standalone-editor .cc-studio-property-header-row>.cc-studio-property-device-select{order:90!important;margin-left:auto!important;flex:0 0 auto!important}' .
			'#cresco-canvas-standalone-editor .cc-studio-property-header-row>button{order:100!important;margin-left:0!important;flex:0 0 auto!important}'
		);
		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array( WebsiteBuilderStudio::HANDLE, 'wp-element', 'wp-api-fetch' ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		wp_enqueue_script(
			self::HANDLE . '-guard',
			WebsiteBuilderAsset::url( self::GUARD_SCRIPT ),
			array( self::HANDLE ),
			WebsiteBuilderAsset::version( self::GUARD_SCRIPT ),
			true
		);

		$settings = array(
			'schema'              => 'cresco-studio-ux-pro/v1',
			'postId'              => $context->post_id(),
			'briefPath'           => '/cresco-canvas/v1/studio-ux/' . $context->post_id() . '/brief',
			'designRecommendPath' => '/cresco-canvas/v1/design-intelligence/recommend',
			'designCatalogPath'   => '/cresco-canvas/v1/design-intelligence/catalog',
			'canManageSiteBrief'  => current_user_can( 'edit_theme_options' ),
			'brief'               => self::get_brief( $context->post_id() ),
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.crescoStudioUxProSettings=' . wp_json_encode( $settings ) . ';',
			'before'
		);
	}

	/** Register project brief endpoints. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/studio-ux/(?P<postId>\d+)/brief',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_brief' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_save_brief' ),
					'permission_callback' => array( $this, 'can_edit_document' ),
				),
			)
		);
	}

	public function can_edit_document( $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) return false;
		return in_array( get_post_type( $post_id ), array( 'page', 'cresco_template' ), true );
	}

	public function rest_get_brief( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		return new WP_REST_Response(
			array(
				'schema' => self::BRIEF_SCHEMA,
				'brief'  => self::get_brief( $post_id ),
				'scope'  => self::brief_scope( $post_id ),
			)
		);
	}

	public function rest_save_brief( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$brief   = self::sanitize_brief( $payload['brief'] ?? $payload );
		$scope   = sanitize_key( (string) ( $payload['scope'] ?? '' ) );
		$site    = current_user_can( 'edit_theme_options' ) && 'document' !== $scope;

		if ( $site ) {
			update_option( self::OPTION_KEY, $brief, false );
			delete_post_meta( $post_id, self::META_KEY );
			$scope = 'site';
		} else {
			update_post_meta( $post_id, self::META_KEY, $brief );
			$scope = 'document';
		}

		return new WP_REST_Response(
			array(
				'schema' => self::BRIEF_SCHEMA,
				'brief'  => self::get_brief( $post_id ),
				'scope'  => $scope,
				'saved'  => true,
			)
		);
	}

	/**
	 * Supply the saved brief to legacy/export actions that otherwise send the
	 * generic default request. Explicit user requests are never overwritten.
	 */
	public function inject_brief_request( $response, $handler, $request ) {
		unset( $handler );
		if ( null !== $response || ! $request instanceof WP_REST_Request ) return $response;
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/cresco-canvas/v1/(?:ai-interchange/(\d+)/context|website-builder/interchange/(\d+)/export)$#', $route, $matches ) ) return $response;
		$post_id = absint( ! empty( $matches[1] ) ? $matches[1] : ( $matches[2] ?? 0 ) );
		if ( $post_id <= 0 ) return $response;

		$payload = (array) $request->get_json_params();
		if ( '' !== trim( (string) ( $payload['request'] ?? '' ) ) ) return $response;
		$brief_text = self::brief_text( self::get_brief( $post_id ) );
		if ( '' === $brief_text ) return $response;

		$payload['request'] = 'Use this project brief as persistent design context. ' . $brief_text . ' Improve this design while preserving existing meaning and making it responsive.';
		if ( ! isset( $payload['purpose'] ) ) $payload['purpose'] = 'redesign';
		$request->set_body_params( $payload );
		return $response;
	}

	/** Add non-secret project context to outgoing AI packages after hardening. */
	public function enrich_ai_payload( $response, $server, $request ) {
		unset( $server );
		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) return $response;
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/cresco-canvas/v1/(?:ai-interchange/(\d+)/context|website-builder/interchange/(\d+)/export)$#', $route, $matches ) ) return $response;
		$post_id = absint( ! empty( $matches[1] ) ? $matches[1] : ( $matches[2] ?? 0 ) );
		$brief   = self::get_brief( $post_id );
		if ( ! self::has_brief( $brief ) ) return $response;

		$data = $response->get_data();
		if ( ! is_array( $data ) ) return $response;
		$data = self::enrich_payload_with_brief( $data, $brief );
		$response->set_data( $data );
		return $response;
	}

	/** Return the effective site brief, with an optional document override. */
	public static function get_brief( $post_id ) {
		$site = self::sanitize_brief( get_option( self::OPTION_KEY, array() ) );
		$document = self::sanitize_brief( get_post_meta( absint( $post_id ), self::META_KEY, true ) );
		if ( ! self::has_brief( $document ) ) return $site;
		foreach ( $document as $key => $value ) {
			if ( '' !== $value ) $site[ $key ] = $value;
		}
		return $site;
	}

	public static function brief_scope( $post_id ) {
		$document = self::sanitize_brief( get_post_meta( absint( $post_id ), self::META_KEY, true ) );
		return self::has_brief( $document ) ? 'document' : 'site';
	}

	/** Pure sanitizer kept public so the contract can be regression-tested. */
	public static function sanitize_brief( $brief ) {
		$brief = is_array( $brief ) ? $brief : array();
		$limits = array(
			'business'    => 180,
			'industry'    => 120,
			'location'    => 160,
			'goal'        => 180,
			'audience'    => 240,
			'personality' => 240,
			'notes'       => 1200,
		);
		$output = array();
		foreach ( $limits as $key => $limit ) {
			$value = isset( $brief[ $key ] ) ? sanitize_textarea_field( (string) $brief[ $key ] ) : '';
			$output[ $key ] = self::limit_text( $value, $limit );
		}
		return $output;
	}

	/** Pure payload enrichment; never changes target/scope/content. */
	public static function enrich_payload_with_brief( $data, $brief ) {
		$data  = is_array( $data ) ? $data : array();
		$brief = self::sanitize_brief( $brief );
		if ( ! self::has_brief( $brief ) ) return $data;

		if ( isset( $data['package'] ) && is_array( $data['package'] ) ) {
			$package = $data['package'];
			if ( isset( $package['aiContext'] ) && is_array( $package['aiContext'] ) ) {
				$package['aiContext'] = self::enrich_context( $package['aiContext'], $brief );
			} else {
				$package = self::enrich_context( $package, $brief );
			}
			$data['package'] = $package;
			return $data;
		}
		return self::enrich_context( $data, $brief );
	}

	public static function brief_text( $brief ) {
		$brief = self::sanitize_brief( $brief );
		$labels = array(
			'business' => 'Business', 'industry' => 'Industry', 'location' => 'Location',
			'goal' => 'Primary goal', 'audience' => 'Audience', 'personality' => 'Brand personality', 'notes' => 'Notes',
		);
		$parts = array();
		foreach ( $labels as $key => $label ) {
			if ( '' !== $brief[ $key ] ) $parts[] = $label . ': ' . $brief[ $key ] . '.';
		}
		return implode( ' ', $parts );
	}

	private static function enrich_context( $context, $brief ) {
		$context = is_array( $context ) ? $context : array();
		$context['task'] = isset( $context['task'] ) && is_array( $context['task'] ) ? $context['task'] : array();
		$context['task']['projectBrief'] = $brief;
		if ( isset( $context['scopePackage'] ) && is_array( $context['scopePackage'] ) ) {
			$context['scopePackage']['projectBrief'] = $brief;
		}
		$context['authoringPolicy'] = isset( $context['authoringPolicy'] ) && is_array( $context['authoringPolicy'] ) ? $context['authoringPolicy'] : array();
		$context['authoringPolicy']['projectBrief'] = array(
			'role' => 'persistent-user-authored-context',
			'neverWidensScope' => true,
			'note' => 'Use the project brief to resolve design intent. Explicit task instructions and Cresco technical contracts still take priority.',
		);
		return $context;
	}

	private static function has_brief( $brief ) {
		foreach ( (array) $brief as $value ) if ( '' !== trim( (string) $value ) ) return true;
		return false;
	}

	private static function limit_text( $value, $limit ) {
		$value = (string) $value;
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit, 'UTF-8' ) : substr( $value, 0, $limit );
	}
}
