<?php
/**
 * Page-level shell settings for Cresco Canvas Pages.
 *
 * These settings intentionally live outside cresco-session/v1. The Session
 * owns the visual document; Page Settings own how WordPress/the active theme
 * hosts that document.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Page;

use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\Session\SessionManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageSettings {
	const META_KEY = '_cresco_canvas_page_settings';
	const VERSION  = 1;

	public function register() {
		add_action( 'init', array( $this, 'register_meta' ), 6 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_bridge' ), 40 );
		add_filter( 'body_class', array( $this, 'body_classes' ), 30 );
		add_filter( 'the_title', array( $this, 'filter_page_title' ), 30, 2 );
		add_filter( 'template_include', array( $this, 'template_include' ), 99 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'inject_ai_context' ), 20, 3 );
	}

	public function register_meta() {
		register_post_meta(
			'page',
			self::META_KEY,
			array(
				'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'default'      => '',
				'description'  => __( 'Cresco Canvas Page shell settings.', 'cresco-canvas' ),
				'show_in_rest' => false,
				'single'       => true,
				'type'         => 'string',
			)
		);
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/page-settings/(?P<postId>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get' ),
					'permission_callback' => array( $this, 'can_edit_post' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_save' ),
					'permission_callback' => array( $this, 'can_edit_post' ),
				),
			)
		);
	}

	public function can_edit_post( $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_get( WP_REST_Request $request ) {
		$post_id  = absint( $request['postId'] );
		$settings = self::get( $post_id );
		return new WP_REST_Response(
			array(
				'settings'  => $settings,
				'effective' => self::effective( $settings ),
			)
		);
	}

	public function rest_save( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input   = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : $payload;
		$settings = self::sanitize( $input );
		$json = wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return new WP_Error( 'cresco_page_settings_encode_failed', __( 'Page Settings could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}
		update_post_meta( $post_id, self::META_KEY, $json );
		update_post_meta( $post_id, EditorIntegration::ENABLED_META, true );
		return new WP_REST_Response(
			array(
				'settings'  => $settings,
				'effective' => self::effective( $settings ),
				'savedAt'   => gmdate( 'c' ),
			)
		);
	}

	public static function defaults() {
		return array(
			'version'     => self::VERSION,
			'layout'      => 'full-width',
			'pageTitle'   => 'hide',
			'header'      => 'inherit',
			'footer'      => 'inherit',
			'contentRoot' => 'viewport',
		);
	}

	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		return array(
			'version'     => self::VERSION,
			'layout'      => self::enum( $input['layout'] ?? $defaults['layout'], array( 'theme-default', 'full-width', 'canvas' ), $defaults['layout'] ),
			'pageTitle'   => self::enum( $input['pageTitle'] ?? $defaults['pageTitle'], array( 'show', 'hide' ), $defaults['pageTitle'] ),
			'header'      => self::enum( $input['header'] ?? $defaults['header'], array( 'inherit', 'show', 'hide' ), $defaults['header'] ),
			'footer'      => self::enum( $input['footer'] ?? $defaults['footer'], array( 'inherit', 'show', 'hide' ), $defaults['footer'] ),
			'contentRoot' => self::enum( $input['contentRoot'] ?? $defaults['contentRoot'], array( 'theme', 'viewport' ), $defaults['contentRoot'] ),
		);
	}

	public static function get( $post_id ) {
		$raw = get_post_meta( absint( $post_id ), self::META_KEY, true );
		if ( is_array( $raw ) ) return self::sanitize( $raw );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return self::sanitize( is_array( $decoded ) ? $decoded : array() );
	}

	/** Return settings after applying layout guarantees. */
	public static function effective( $settings ) {
		$settings = self::sanitize( $settings );
		if ( 'full-width' === $settings['layout'] ) {
			$settings['contentRoot'] = 'viewport';
		}
		if ( 'canvas' === $settings['layout'] ) {
			$settings['contentRoot'] = 'viewport';
			$settings['pageTitle']   = 'hide';
			$settings['header']      = 'hide';
			$settings['footer']      = 'hide';
		}
		return $settings;
	}

	public function body_classes( $classes ) {
		$post_id = $this->current_cresco_page_id();
		if ( ! $post_id ) return $classes;
		$settings  = self::effective( self::get( $post_id ) );
		$classes[] = 'cresco-page-layout-' . sanitize_html_class( $settings['layout'] );
		$classes[] = 'cresco-page-root-' . sanitize_html_class( $settings['contentRoot'] );
		if ( 'hide' === $settings['pageTitle'] ) $classes[] = 'cresco-page-title-hidden';
		if ( 'hide' === $settings['header'] ) $classes[] = 'cresco-page-header-hidden';
		if ( 'hide' === $settings['footer'] ) $classes[] = 'cresco-page-footer-hidden';
		return array_values( array_unique( $classes ) );
	}

	public function filter_page_title( $title, $post_id ) {
		if ( is_admin() || ! is_singular( 'page' ) || absint( $post_id ) !== (int) get_queried_object_id() ) return $title;
		if ( ! self::post_uses_cresco( $post_id ) ) return $title;
		$settings = self::effective( self::get( $post_id ) );
		if ( 'hide' !== $settings['pageTitle'] ) return $title;
		return in_the_loop() && is_main_query() ? '' : $title;
	}

	public function template_include( $template ) {
		$post_id = $this->current_cresco_page_id();
		if ( ! $post_id ) return $template;
		$settings = self::get( $post_id );
		if ( 'canvas' !== $settings['layout'] ) return $template;
		$canvas_template = CRESCO_CANVAS_PATH . 'includes/Page/canvas-template.php';
		return is_readable( $canvas_template ) ? $canvas_template : $template;
	}

	public function enqueue_frontend_bridge() {
		$post_id = $this->current_cresco_page_id();
		if ( ! $post_id ) return;
		if ( ! wp_style_is( 'cresco-canvas-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'cresco-canvas-frontend', CRESCO_CANVAS_URL . 'assets/css/frontend.css', array(), CRESCO_CANVAS_VERSION );
		}
		wp_add_inline_style( 'cresco-canvas-frontend', self::frontend_css() );
	}

	public function inject_ai_context( $response, $handler, $request ) {
		unset( $handler );
		if ( ! $request instanceof WP_REST_Request || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) return $response;
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/cresco-canvas/v1/ai-context/(\d+)$#', $route, $matches ) ) return $response;
		$post_id = absint( $matches[1] );
		$data = $response->get_data();
		if ( ! is_array( $data ) ) return $response;
		$data['pageSettings'] = self::get( $post_id );
		$data['pageSettingsEffective'] = self::effective( $data['pageSettings'] );
		$data['instructions'] = isset( $data['instructions'] ) && is_array( $data['instructions'] ) ? $data['instructions'] : array();
		$data['instructions'][] = 'Page Settings control the WordPress/theme shell and are not part of cresco-session/v1.';
		$response->set_data( $data );
		return $response;
	}

	private function current_cresco_page_id() {
		if ( is_admin() || ! is_singular( 'page' ) ) return 0;
		$post_id = (int) get_queried_object_id();
		return $post_id > 0 && self::post_uses_cresco( $post_id ) ? $post_id : 0;
	}

	public static function post_uses_cresco( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) ) return false;
		if ( rest_sanitize_boolean( get_post_meta( $post_id, EditorIntegration::ENABLED_META, true ) ) ) return true;
		if ( '' !== (string) get_post_meta( $post_id, SessionManager::META_KEY, true ) ) return true;
		$post = get_post( $post_id );
		return $post && has_block( 'cresco/container', $post->post_content );
	}

	private static function enum( $value, $allowed, $fallback ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private static function frontend_css() {
		return implode(
			'',
			array(
				'body.cresco-page-root-viewport{overflow-x:clip;}',
				'body.cresco-page-root-viewport .cresco-session-root{width:100vw!important;max-width:none!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important;}',
				'body.cresco-page-title-hidden .entry-title,body.cresco-page-title-hidden .page-title,body.cresco-page-title-hidden .wp-block-post-title{display:none!important;}',
				'body.cresco-page-header-hidden>#masthead,body.cresco-page-header-hidden>#page>header,body.cresco-page-header-hidden>#page>.site-header,body.cresco-page-header-hidden #masthead.site-header,body.cresco-page-header-hidden>.wp-site-blocks>header.wp-block-template-part{display:none!important;}',
				'body.cresco-page-footer-hidden>#colophon,body.cresco-page-footer-hidden>#page>footer,body.cresco-page-footer-hidden>#page>.site-footer,body.cresco-page-footer-hidden #colophon.site-footer,body.cresco-page-footer-hidden>.wp-site-blocks>footer.wp-block-template-part{display:none!important;}',
				'.cresco-canvas-page-shell{width:100%;min-height:100vh;margin:0;padding:0;}',
				'@supports(width:100dvw){body.cresco-page-root-viewport .cresco-session-root{width:100dvw!important;margin-left:calc(50% - 50dvw)!important;margin-right:calc(50% - 50dvw)!important;}}',
			)
		);
	}
}
