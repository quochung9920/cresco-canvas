<?php
/**
 * Scoped design settings and conditional frontend assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

use CrescoCanvas\Admin\EditorIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GlobalStyles {
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'add_block_editor_tokens' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'add_canvas_body_class' ) );
	}

	public static function defaults() {
		return array(
			'schemaVersion'         => 3,
			'primary'               => '#635bff',
			'text'                  => '#111827',
			'muted'                 => '#6b7280',
			'background'            => '#ffffff',
			'containerMax'          => 1440,
			'contentMax'            => 1200,
			'radius'                => 12,
			'fontFamily'            => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'customColors'          => array(),
			'aliases'               => array(),
			'removeDataOnUninstall' => false,
		);
	}

	public static function get_settings() {
		return self::sanitize_settings( (array) get_option( 'cresco_canvas_settings', array() ) );
	}

	public static function sanitize_settings( $input ) {
		$defaults      = self::defaults();
		$container_max = min( 2560, max( 960, absint( $input['containerMax'] ?? $defaults['containerMax'] ) ) );
		$content_max   = min( $container_max, max( 640, absint( $input['contentMax'] ?? $defaults['contentMax'] ) ) );

		return array(
			'schemaVersion'         => 3,
			'primary'               => sanitize_hex_color( $input['primary'] ?? '' ) ?: $defaults['primary'],
			'text'                  => sanitize_hex_color( $input['text'] ?? '' ) ?: $defaults['text'],
			'muted'                 => sanitize_hex_color( $input['muted'] ?? '' ) ?: $defaults['muted'],
			'background'            => sanitize_hex_color( $input['background'] ?? '' ) ?: $defaults['background'],
			'containerMax'          => $container_max,
			'contentMax'            => $content_max,
			'radius'                => min( 80, max( 0, absint( $input['radius'] ?? $defaults['radius'] ) ) ),
			'fontFamily'            => self::sanitize_font_family( $input['fontFamily'] ?? $defaults['fontFamily'] ),
			'customColors'          => self::sanitize_custom_colors( $input['customColors'] ?? array() ),
			'aliases'               => self::sanitize_aliases( $input['aliases'] ?? array() ),
			'removeDataOnUninstall' => rest_sanitize_boolean( $input['removeDataOnUninstall'] ?? false ),
		);
	}

	public static function css( $selector = '.cresco-canvas-scope' ) {
		$settings = self::get_settings();
		return $selector . '{' . DesignTokens::css_variables( $settings ) . '}';
	}

	public static function visual_css( $selector ) {
		return sprintf(
			'%1$s{background:var(--cc-background);color:var(--cc-text);font-family:var(--cc-font);}%1$s .wp-block-cresco-container a:not(.wp-block-button__link){color:var(--cc-primary);}%1$s .wp-block-cresco-container .wp-block-button__link:not(.has-background){background-color:var(--cc-primary);}%1$s .wp-block-cresco-container .wp-block-button__link{border-radius:var(--cc-radius);}',
			$selector
		);
	}

	public function enqueue_frontend_styles() {
		if ( ! $this->is_canvas_page() ) {
			return;
		}
		wp_enqueue_style( 'cresco-canvas-frontend', CRESCO_CANVAS_URL . 'assets/css/frontend.css', array(), CRESCO_CANVAS_VERSION );
		wp_add_inline_style( 'cresco-canvas-frontend', self::css( 'body.cresco-canvas-page' ) . self::visual_css( 'body.cresco-canvas-page' ) );
	}

	public function add_block_editor_tokens( $settings, $context ) {
		$post = isset( $context->post ) ? $context->post : null;
		if ( ! $post || 'page' !== $post->post_type ) {
			return $settings;
		}
		$settings['styles']   = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$settings['styles'][] = array(
			'css'            => self::css( '.editor-styles-wrapper' ) . self::visual_css( '.editor-styles-wrapper.cresco-canvas-editor-scope' ),
			'__unstableType' => 'theme',
		);
		return $settings;
	}

	public function add_canvas_body_class( $classes ) {
		if ( $this->is_canvas_page() ) {
			$classes[] = 'cresco-canvas-page';
		}
		return array_values( array_unique( $classes ) );
	}

	public function is_canvas_page() {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}
		$post_id = get_queried_object_id();
		return $post_id > 0 && $this->post_uses_canvas( $post_id );
	}

	public function post_uses_canvas( $post_id ) {
		if ( rest_sanitize_boolean( get_post_meta( $post_id, EditorIntegration::ENABLED_META, true ) ) ) {
			return true;
		}
		$post = get_post( $post_id );
		return $post && has_block( 'cresco/container', $post->post_content );
	}

	private static function sanitize_custom_colors( $value ) {
		$output = array();
		if ( ! is_array( $value ) ) {
			return $output;
		}
		foreach ( array_slice( $value, 0, 24, true ) as $slug => $color ) {
			$slug  = sanitize_key( $slug );
			$color = sanitize_hex_color( $color );
			if ( '' !== $slug && $color ) {
				$output[ $slug ] = $color;
			}
		}
		return $output;
	}

	private static function sanitize_aliases( $value ) {
		$output  = array();
		$allowed = array( 'primary', 'text', 'muted', 'background' );
		if ( ! is_array( $value ) ) {
			return $output;
		}
		foreach ( array_slice( $value, 0, 24, true ) as $alias => $target ) {
			$alias  = sanitize_key( $alias );
			$target = sanitize_key( $target );
			if ( '' !== $alias && ( in_array( $target, $allowed, true ) || str_starts_with( $target, 'custom-' ) ) ) {
				$output[ $alias ] = $target;
			}
		}
		return $output;
	}

	private static function sanitize_font_family( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		return preg_match( '/^[a-zA-Z0-9 _,-.\"\'()]+$/', $value ) ? $value : self::defaults()['fontFamily'];
	}
}
