<?php
/**
 * Page-level shell, style, and advanced settings for Cresco Canvas Pages.
 *
 * These settings intentionally live outside cresco-session/v1. The Session
 * owns the visual document; Page Settings own how WordPress/the active theme
 * hosts and decorates that document.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Page;

use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Styles\GlobalStyles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageSettings {
	const META_KEY = '_cresco_canvas_page_settings';
	const VERSION  = 2;

	/** @var array<int,array<string,mixed>> */
	private static $settings_cache = array();

	/** @var array<int,array<string,mixed>> */
	private static $effective_cache = array();

	/** @var array<int,bool> */
	private static $uses_cresco_cache = array();

	/** @var int|null */
	private $current_page_id = null;

	public function register() {
		add_action( 'init', array( $this, 'register_meta' ), 6 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_bridge' ), 40 );
		add_filter( 'body_class', array( $this, 'body_classes' ), 30 );
		add_filter( 'the_title', array( $this, 'filter_page_title' ), 30, 2 );
		add_filter( 'pre_render_block', array( $this, 'filter_pre_render_block' ), 5, 3 );
		add_filter( 'render_block_core/template-part', array( $this, 'filter_rendered_template_part' ), 99, 3 );
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
				'description'  => __( 'Cresco Canvas Page settings.', 'cresco-canvas' ),
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
				'effective' => self::effective_for_post( $post_id ),
			)
		);
	}

	public function rest_save( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input   = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : $payload;

		$custom_css = self::sanitize_page_custom_css( $input['customCSS'] ?? '' );
		if ( is_wp_error( $custom_css ) ) {
			return $custom_css;
		}
		$input['customCSS'] = $custom_css;
		$settings           = self::sanitize( $input );
		$json               = wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return new WP_Error( 'cresco_page_settings_encode_failed', __( 'Page Settings could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, self::META_KEY, $json );
		update_post_meta( $post_id, EditorIntegration::ENABLED_META, true );
		self::$settings_cache[ $post_id ]    = $settings;
		self::$effective_cache[ $post_id ]   = self::effective( $settings );
		self::$uses_cresco_cache[ $post_id ] = true;

		return new WP_REST_Response(
			array(
				'settings'  => $settings,
				'effective' => self::$effective_cache[ $post_id ],
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
			'bodyStyle'   => array(
				'margin'     => self::spacing_defaults(),
				'padding'    => self::spacing_defaults(),
				'background' => array(
					'type'       => 'classic',
					'color'      => '',
					'image'      => array(
						'id'         => 0,
						'url'        => '',
						'position'   => 'center-center',
						'repeat'     => 'no-repeat',
						'size'       => 'cover',
						'attachment' => 'scroll',
					),
					'gradient'   => array(
						'color1' => '',
						'color2' => '',
						'angle'  => 180,
					),
				),
			),
			'customCSS'   => '',
			'scrollSnap'  => array(
				'enabled'    => false,
				'axis'       => 'y',
				'strictness' => 'proximity',
				'align'      => 'start',
				'stop'       => 'normal',
				'offset'     => 0,
			),
		);
	}

	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$custom   = self::sanitize_page_custom_css( $input['customCSS'] ?? $defaults['customCSS'] );

		return array(
			'version'     => self::VERSION,
			'layout'      => self::enum( $input['layout'] ?? $defaults['layout'], array( 'theme-default', 'full-width', 'canvas' ), $defaults['layout'] ),
			'pageTitle'   => self::enum( $input['pageTitle'] ?? $defaults['pageTitle'], array( 'show', 'hide' ), $defaults['pageTitle'] ),
			'header'      => self::enum( $input['header'] ?? $defaults['header'], array( 'inherit', 'show', 'hide' ), $defaults['header'] ),
			'footer'      => self::enum( $input['footer'] ?? $defaults['footer'], array( 'inherit', 'show', 'hide' ), $defaults['footer'] ),
			'contentRoot' => self::enum( $input['contentRoot'] ?? $defaults['contentRoot'], array( 'theme', 'viewport' ), $defaults['contentRoot'] ),
			'bodyStyle'   => self::sanitize_body_style( $input['bodyStyle'] ?? array() ),
			'customCSS'   => is_wp_error( $custom ) ? '' : $custom,
			'scrollSnap'  => self::sanitize_scroll_snap( $input['scrollSnap'] ?? array() ),
		);
	}

	public static function get( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) return self::defaults();
		if ( isset( self::$settings_cache[ $post_id ] ) ) return self::$settings_cache[ $post_id ];

		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_array( $raw ) ) {
			self::$settings_cache[ $post_id ] = self::sanitize( $raw );
			return self::$settings_cache[ $post_id ];
		}
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		self::$settings_cache[ $post_id ] = self::sanitize( is_array( $decoded ) ? $decoded : array() );
		return self::$settings_cache[ $post_id ];
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
		$settings  = self::effective_for_post( $post_id );
		$classes[] = 'cresco-page-layout-' . sanitize_html_class( $settings['layout'] );
		$classes[] = 'cresco-page-root-' . sanitize_html_class( $settings['contentRoot'] );
		if ( 'hide' === $settings['pageTitle'] ) $classes[] = 'cresco-page-title-hidden';
		if ( 'hide' === $settings['header'] ) $classes[] = 'cresco-page-header-hidden';
		if ( 'hide' === $settings['footer'] ) $classes[] = 'cresco-page-footer-hidden';
		if ( ! empty( $settings['scrollSnap']['enabled'] ) ) $classes[] = 'cresco-page-scroll-snap';
		return array_values( array_unique( $classes ) );
	}

	public function filter_page_title( $title, $post_id ) {
		if ( is_admin() || ! is_singular( 'page' ) || absint( $post_id ) !== (int) get_queried_object_id() ) return $title;
		if ( ! self::post_uses_cresco( $post_id ) ) return $title;
		$settings = self::effective_for_post( $post_id );
		if ( 'hide' !== $settings['pageTitle'] ) return $title;
		return in_the_loop() && is_main_query() ? '' : $title;
	}

	/**
	 * Stop hidden block-theme template parts before WordPress renders them.
	 *
	 * @param string|null $pre_render   Existing pre-rendered value.
	 * @param array       $parsed_block Parsed block data.
	 * @param mixed       $parent_block Parent WP_Block instance.
	 * @return string|null
	 */
	public function filter_pre_render_block( $pre_render, $parsed_block, $parent_block = null ) {
		unset( $parent_block );
		if ( null !== $pre_render || ! is_array( $parsed_block ) || 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) ) return $pre_render;
		return $this->should_suppress_template_part( $parsed_block ) ? '' : $pre_render;
	}

	/**
	 * Final safety net for block themes when another filter bypassed pre-render.
	 *
	 * @param string $block_content Rendered template-part HTML.
	 * @param array  $block         Parsed block data.
	 * @param mixed  $instance      WP_Block instance.
	 * @return string
	 */
	public function filter_rendered_template_part( $block_content, $block, $instance = null ) {
		unset( $instance );
		return is_array( $block ) && $this->should_suppress_template_part( $block ) ? '' : $block_content;
	}

	public function template_include( $template ) {
		$post_id = $this->current_cresco_page_id();
		if ( ! $post_id ) return $template;
		$settings = self::effective_for_post( $post_id );
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
		wp_add_inline_style( 'cresco-canvas-frontend', self::compile_frontend_css( self::effective_for_post( $post_id ) ) );
	}

	public function inject_ai_context( $response, $handler, $request ) {
		unset( $handler );
		if ( ! $request instanceof WP_REST_Request ) return $response;
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/cresco-canvas/v1/ai-context/(\d+)$#', $route, $matches ) ) return $response;
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) return $response;
		$post_id = absint( $matches[1] );
		$data    = $response->get_data();
		if ( ! is_array( $data ) ) return $response;
		$data['pageSettings']          = self::get( $post_id );
		$data['pageSettingsEffective'] = self::effective_for_post( $post_id );
		$data['instructions']          = isset( $data['instructions'] ) && is_array( $data['instructions'] ) ? $data['instructions'] : array();
		$data['instructions'][]        = 'Page Settings control the WordPress/theme shell, Page Body Style, Page Custom CSS, and Scroll Snap. They are not part of cresco-session/v1.';
		$response->set_data( $data );
		return $response;
	}

	/** Return true when the current Page Settings suppress this template part. */
	private function should_suppress_template_part( $block ) {
		$post_id = $this->current_cresco_page_id();
		if ( ! $post_id ) return false;
		$settings = self::effective_for_post( $post_id );
		$area     = self::template_part_area( $block );
		if ( 'header' === $area ) return 'hide' === $settings['header'];
		if ( 'footer' === $area ) return 'hide' === $settings['footer'];
		return false;
	}

	/**
	 * Resolve a template part to a semantic area without depending on its HTML.
	 *
	 * WordPress block themes may declare the area on the block, via tagName,
	 * or only on the underlying wp_template_part entity. Slug matching is the
	 * final compatibility fallback for older/custom themes.
	 *
	 * @param array $block Parsed core/template-part block.
	 * @return string header, footer, or empty string.
	 */
	public static function template_part_area( $block ) {
		static $entity_area_cache = array();

		$attrs = is_array( $block ) ? (array) ( $block['attrs'] ?? array() ) : array();
		$area  = sanitize_key( (string) ( $attrs['area'] ?? '' ) );
		if ( in_array( $area, array( 'header', 'footer' ), true ) ) return $area;

		$tag_name = sanitize_key( (string) ( $attrs['tagName'] ?? '' ) );
		if ( in_array( $tag_name, array( 'header', 'footer' ), true ) ) return $tag_name;

		$slug = sanitize_key( (string) ( $attrs['slug'] ?? '' ) );
		if ( $slug && function_exists( 'get_block_template' ) ) {
			$theme = sanitize_key( (string) ( $attrs['theme'] ?? '' ) );
			if ( ! $theme && function_exists( 'get_stylesheet' ) ) $theme = sanitize_key( (string) get_stylesheet() );
			if ( $theme ) {
				$cache_key = $theme . '//' . $slug;
				if ( ! array_key_exists( $cache_key, $entity_area_cache ) ) {
					$template                        = get_block_template( $cache_key, 'wp_template_part' );
					$entity_area_cache[ $cache_key ] = is_object( $template ) && isset( $template->area ) ? sanitize_key( (string) $template->area ) : '';
				}
				$resolved = $entity_area_cache[ $cache_key ];
				if ( in_array( $resolved, array( 'header', 'footer' ), true ) ) return $resolved;
			}
		}

		if ( $slug && preg_match( '/(?:^|[-_])(header|footer)(?:[-_]|$)/', $slug, $matches ) ) return $matches[1];
		return '';
	}

	private function current_cresco_page_id() {
		if ( null !== $this->current_page_id ) return $this->current_page_id;
		if ( is_admin() || ! is_singular( 'page' ) ) {
			$this->current_page_id = 0;
			return 0;
		}
		$post_id               = (int) get_queried_object_id();
		$this->current_page_id = $post_id > 0 && self::post_uses_cresco( $post_id ) ? $post_id : 0;
		return $this->current_page_id;
	}

	public static function post_uses_cresco( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) ) return false;
		if ( array_key_exists( $post_id, self::$uses_cresco_cache ) ) return self::$uses_cresco_cache[ $post_id ];

		$uses_cresco = rest_sanitize_boolean( get_post_meta( $post_id, EditorIntegration::ENABLED_META, true ) );
		if ( ! $uses_cresco ) $uses_cresco = '' !== (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		if ( ! $uses_cresco ) {
			$post        = get_post( $post_id );
			$uses_cresco = (bool) ( $post && has_block( 'cresco/container', $post->post_content ) );
		}
		self::$uses_cresco_cache[ $post_id ] = $uses_cresco;
		return $uses_cresco;
	}

	private static function effective_for_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) return self::effective( self::defaults() );
		if ( ! isset( self::$effective_cache[ $post_id ] ) ) {
			self::$effective_cache[ $post_id ] = self::effective( self::get( $post_id ) );
		}
		return self::$effective_cache[ $post_id ];
	}

	/**
	 * Sanitize Page Custom CSS while keeping it scoped to the Cresco Page root.
	 * Both `selector` and `&` are accepted as aliases for the page root.
	 *
	 * @param mixed $value Raw CSS.
	 * @return string|WP_Error
	 */
	public static function sanitize_page_custom_css( $value ) {
		$css = trim( (string) $value );
		if ( '' === $css ) return '';
		$normalized = preg_replace( '/\bselector\b/i', '&', $css );
		$sanitized  = SessionManager::sanitize_custom_css( $normalized );
		if ( is_wp_error( $sanitized ) ) return $sanitized;
		return $css;
	}

	/** Compile all Page Settings CSS for this one Cresco Page. */
	public static function compile_frontend_css( $settings ) {
		$settings   = self::effective( $settings );
		$body_style = (array) $settings['bodyStyle'];
		$css        = implode(
			'',
			array(
				'body.cresco-page-root-viewport{overflow-x:clip;}',
				'body.cresco-page-root-viewport .cresco-session-root{width:100vw!important;max-width:none!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important;}',
				'body.cresco-page-title-hidden .entry-title,body.cresco-page-title-hidden .page-title,body.cresco-page-title-hidden .wp-block-post-title{display:none!important;}',
				'body.cresco-page-header-hidden>#masthead,body.cresco-page-header-hidden>#page>header,body.cresco-page-header-hidden>#page>.site-header,body.cresco-page-header-hidden #masthead.site-header,body.cresco-page-header-hidden header.site-header,body.cresco-page-header-hidden header[role="banner"],body.cresco-page-header-hidden>.wp-site-blocks>header.wp-block-template-part,body.cresco-page-header-hidden .wp-block-site-title{display:none!important;}',
				'body.cresco-page-footer-hidden>#colophon,body.cresco-page-footer-hidden>#page>footer,body.cresco-page-footer-hidden>#page>.site-footer,body.cresco-page-footer-hidden #colophon.site-footer,body.cresco-page-footer-hidden footer.site-footer,body.cresco-page-footer-hidden footer[role="contentinfo"],body.cresco-page-footer-hidden>.wp-site-blocks>footer.wp-block-template-part{display:none!important;}',
				'.cresco-canvas-page-shell{width:100%;min-height:100vh;margin:0;padding:0;}',
				'.cresco-session-root{box-sizing:border-box;}',
				'@supports(width:100dvw){body.cresco-page-root-viewport .cresco-session-root{width:100dvw!important;margin-left:calc(50% - 50dvw)!important;margin-right:calc(50% - 50dvw)!important;}}',
			)
		);

		$css .= self::compile_body_device_css( $body_style, 'desktop', $settings['contentRoot'] );
		$css .= self::compile_background_css( (array) ( $body_style['background'] ?? array() ) );

		$breakpoints = (array) ( GlobalStyles::get_settings()['breakpoints'] ?? array() );
		$tablet      = max( 320, (int) ( $breakpoints['tablet'] ?? 768 ) );
		$laptop      = max( $tablet + 1, (int) ( $breakpoints['laptop'] ?? 1024 ) );
		$tablet_css  = self::compile_body_device_css( $body_style, 'tablet', $settings['contentRoot'] );
		$mobile_css  = self::compile_body_device_css( $body_style, 'mobile', $settings['contentRoot'] );
		if ( $tablet_css ) $css .= '@media (min-width:' . $tablet . 'px) and (max-width:' . ( $laptop - 1 ) . 'px){' . $tablet_css . '}';
		if ( $mobile_css ) $css .= '@media (max-width:' . ( $tablet - 1 ) . 'px){' . $mobile_css . '}';

		$custom_css = self::sanitize_page_custom_css( $settings['customCSS'] ?? '' );
		if ( is_string( $custom_css ) && '' !== $custom_css ) {
			$custom_css = preg_replace( '/\bselector\b/i', '.cresco-session-root', $custom_css );
			$custom_css = str_replace( '&', '.cresco-session-root', $custom_css );
			$css       .= $custom_css;
		}

		$snap = (array) $settings['scrollSnap'];
		if ( ! empty( $snap['enabled'] ) ) {
			$axis       = self::enum( $snap['axis'] ?? 'y', array( 'x', 'y', 'both' ), 'y' );
			$strictness = self::enum( $snap['strictness'] ?? 'proximity', array( 'proximity', 'mandatory' ), 'proximity' );
			$align      = self::enum( $snap['align'] ?? 'start', array( 'start', 'center', 'end' ), 'start' );
			$stop       = self::enum( $snap['stop'] ?? 'normal', array( 'normal', 'always' ), 'normal' );
			$offset     = max( 0, min( 500, absint( $snap['offset'] ?? 0 ) ) );
			$css       .= 'html{scroll-snap-type:' . $axis . ' ' . $strictness . ';scroll-padding-top:' . $offset . 'px;}body.cresco-page-scroll-snap .cresco-session-root>.cresco-session-node{scroll-snap-align:' . $align . ';scroll-snap-stop:' . $stop . ';}';
		}
		return $css;
	}

	private static function spacing_defaults() {
		$empty = array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' );
		return array(
			'unit'    => 'px',
			'linked'  => true,
			'desktop' => $empty,
			'tablet'  => $empty,
			'mobile'  => $empty,
		);
	}

	private static function sanitize_body_style( $input ) {
		$input      = is_array( $input ) ? $input : array();
		$background = is_array( $input['background'] ?? null ) ? $input['background'] : array();
		$image      = is_array( $background['image'] ?? null ) ? $background['image'] : array();
		$gradient   = is_array( $background['gradient'] ?? null ) ? $background['gradient'] : array();
		$angle      = is_numeric( $gradient['angle'] ?? null ) ? (int) round( (float) $gradient['angle'] ) : 180;
		$angle      = max( 0, min( 360, $angle ) );
		return array(
			'margin'     => self::sanitize_spacing_control( $input['margin'] ?? array(), true ),
			'padding'    => self::sanitize_spacing_control( $input['padding'] ?? array(), false ),
			'background' => array(
				'type'     => self::enum( $background['type'] ?? 'classic', array( 'none', 'classic', 'gradient' ), 'classic' ),
				'color'    => self::sanitize_color( $background['color'] ?? '' ),
				'image'    => array(
					'id'         => absint( $image['id'] ?? 0 ),
					'url'        => self::sanitize_url( $image['url'] ?? '' ),
					'position'   => self::enum( $image['position'] ?? 'center-center', array( 'left-top', 'center-top', 'right-top', 'left-center', 'center-center', 'right-center', 'left-bottom', 'center-bottom', 'right-bottom' ), 'center-center' ),
					'repeat'     => self::enum( $image['repeat'] ?? 'no-repeat', array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), 'no-repeat' ),
					'size'       => self::enum( $image['size'] ?? 'cover', array( 'auto', 'cover', 'contain' ), 'cover' ),
					'attachment' => self::enum( $image['attachment'] ?? 'scroll', array( 'scroll', 'fixed' ), 'scroll' ),
				),
				'gradient' => array(
					'color1' => self::sanitize_color( $gradient['color1'] ?? '' ),
					'color2' => self::sanitize_color( $gradient['color2'] ?? '' ),
					'angle'  => $angle,
				),
			),
		);
	}

	private static function sanitize_spacing_control( $input, $allow_negative ) {
		$input  = is_array( $input ) ? $input : array();
		$output = self::spacing_defaults();
		$output['unit']   = self::enum( $input['unit'] ?? 'px', array( 'px', '%', 'em', 'rem', 'vh', 'vw' ), 'px' );
		$output['linked'] = rest_sanitize_boolean( $input['linked'] ?? true );
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			$values = is_array( $input[ $device ] ?? null ) ? $input[ $device ] : array();
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$output[ $device ][ $side ] = self::sanitize_dimension_number( $values[ $side ] ?? '', $allow_negative );
			}
		}
		return $output;
	}

	private static function sanitize_dimension_number( $value, $allow_negative ) {
		$value = trim( (string) $value );
		if ( '' === $value ) return '';
		if ( ! preg_match( '/^-?\d+(?:\.\d{1,3})?$/', $value ) ) return '';
		$number = (float) $value;
		$number = $allow_negative ? max( -2000, min( 2000, $number ) ) : max( 0, min( 2000, $number ) );
		return rtrim( rtrim( number_format( $number, 3, '.', '' ), '0' ), '.' );
	}

	private static function resolved_spacing_values( $control, $device ) {
		$control = is_array( $control ) ? $control : self::spacing_defaults();
		$result  = array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' );
		$order   = array( 'desktop' );
		if ( in_array( $device, array( 'tablet', 'mobile' ), true ) ) $order[] = 'tablet';
		if ( 'mobile' === $device ) $order[] = 'mobile';
		foreach ( $order as $bucket ) {
			$values = is_array( $control[ $bucket ] ?? null ) ? $control[ $bucket ] : array();
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( '' !== (string) ( $values[ $side ] ?? '' ) ) $result[ $side ] = (string) $values[ $side ];
			}
		}
		return $result;
	}

	private static function sanitize_scroll_snap( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'enabled'    => rest_sanitize_boolean( $input['enabled'] ?? false ),
			'axis'       => self::enum( $input['axis'] ?? 'y', array( 'x', 'y', 'both' ), 'y' ),
			'strictness' => self::enum( $input['strictness'] ?? 'proximity', array( 'proximity', 'mandatory' ), 'proximity' ),
			'align'      => self::enum( $input['align'] ?? 'start', array( 'start', 'center', 'end' ), 'start' ),
			'stop'       => self::enum( $input['stop'] ?? 'normal', array( 'normal', 'always' ), 'normal' ),
			'offset'     => max( 0, min( 500, absint( $input['offset'] ?? 0 ) ) ),
		);
	}

	private static function sanitize_url( $value ) {
		$value = trim( (string) $value );
		return '' === $value ? '' : esc_url_raw( $value );
	}

	private static function sanitize_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || 'transparent' === strtolower( $value ) ) return '' === $value ? '' : 'transparent';
		$color = sanitize_hex_color( $value );
		return is_string( $color ) ? $color : '';
	}

	private static function compile_body_device_css( $body_style, $device, $content_root ) {
		$body_style = is_array( $body_style ) ? $body_style : array();
		$margin     = (array) ( $body_style['margin'] ?? self::spacing_defaults() );
		$padding    = (array) ( $body_style['padding'] ?? self::spacing_defaults() );
		$selector   = '.cresco-session-root';
		$decl       = '';

		$margin_values  = self::resolved_spacing_values( $margin, $device );
		$padding_values = self::resolved_spacing_values( $padding, $device );
		$margin_unit    = self::enum( $margin['unit'] ?? 'px', array( 'px', '%', 'em', 'rem', 'vh', 'vw' ), 'px' );
		$padding_unit   = self::enum( $padding['unit'] ?? 'px', array( 'px', '%', 'em', 'rem', 'vh', 'vw' ), 'px' );

		foreach ( array( 'top', 'bottom' ) as $side ) {
			if ( '' !== (string) ( $margin_values[ $side ] ?? '' ) ) $decl .= 'margin-' . $side . ':' . $margin_values[ $side ] . $margin_unit . '!important;';
		}
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( '' !== (string) ( $padding_values[ $side ] ?? '' ) ) $decl .= 'padding-' . $side . ':' . $padding_values[ $side ] . $padding_unit . '!important;';
		}

		$left  = (string) ( $margin_values['left'] ?? '' );
		$right = (string) ( $margin_values['right'] ?? '' );
		if ( '' !== $left || '' !== $right ) {
			$left_css  = ( '' === $left ? '0' : $left . $margin_unit );
			$right_css = ( '' === $right ? '0' : $right . $margin_unit );
			if ( 'viewport' === $content_root ) {
				$decl .= 'width:calc(100vw - ' . $left_css . ' - ' . $right_css . ')!important;max-width:none!important;margin-left:calc(50% - 50vw + ' . $left_css . ')!important;margin-right:' . $right_css . '!important;';
			} else {
				$decl .= 'width:calc(100% - ' . $left_css . ' - ' . $right_css . ')!important;margin-left:' . $left_css . '!important;margin-right:' . $right_css . '!important;';
			}
		}

		return '' === $decl ? '' : $selector . '{' . $decl . '}';
	}

	private static function compile_background_css( $background ) {
		$background = is_array( $background ) ? $background : array();
		$type       = self::enum( $background['type'] ?? 'classic', array( 'none', 'classic', 'gradient' ), 'classic' );
		if ( 'none' === $type ) return '';

		$decl  = '';
		$color = self::sanitize_color( $background['color'] ?? '' );
		if ( 'classic' === $type ) {
			if ( '' !== $color ) $decl .= 'background-color:' . $color . ';';
			$image = is_array( $background['image'] ?? null ) ? $background['image'] : array();
			$url   = self::sanitize_url( $image['url'] ?? '' );
			if ( '' !== $url ) {
				$decl .= 'background-image:url("' . addcslashes( $url, "\\\"" ) . '");';
				$decl .= 'background-position:' . str_replace( '-', ' ', self::enum( $image['position'] ?? 'center-center', array( 'left-top', 'center-top', 'right-top', 'left-center', 'center-center', 'right-center', 'left-bottom', 'center-bottom', 'right-bottom' ), 'center-center' ) ) . ';';
				$decl .= 'background-repeat:' . self::enum( $image['repeat'] ?? 'no-repeat', array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), 'no-repeat' ) . ';';
				$decl .= 'background-size:' . self::enum( $image['size'] ?? 'cover', array( 'auto', 'cover', 'contain' ), 'cover' ) . ';';
				$decl .= 'background-attachment:' . self::enum( $image['attachment'] ?? 'scroll', array( 'scroll', 'fixed' ), 'scroll' ) . ';';
			}
		} elseif ( 'gradient' === $type ) {
			$gradient = is_array( $background['gradient'] ?? null ) ? $background['gradient'] : array();
			$color1   = self::sanitize_color( $gradient['color1'] ?? '' );
			$color2   = self::sanitize_color( $gradient['color2'] ?? '' );
			$angle    = is_numeric( $gradient['angle'] ?? null ) ? max( 0, min( 360, (int) round( (float) $gradient['angle'] ) ) ) : 180;
			if ( '' !== $color1 && '' !== $color2 ) $decl .= 'background-image:linear-gradient(' . $angle . 'deg,' . $color1 . ',' . $color2 . ');';
			elseif ( '' !== $color1 || '' !== $color2 ) $decl .= 'background-color:' . ( '' !== $color1 ? $color1 : $color2 ) . ';';
		}
		return '' === $decl ? '' : '.cresco-session-root{' . $decl . '}';
	}

	private static function enum( $value, $allowed, $fallback ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
