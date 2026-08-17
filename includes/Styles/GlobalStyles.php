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
	const MAX_CUSTOM_CSS = 20000;

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'add_block_editor_tokens' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'add_canvas_body_class' ) );
	}

	public static function defaults() {
		return array(
			'schemaVersion' => 4,
			'primary' => '#635bff',
			'text' => '#111827',
			'muted' => '#6b7280',
			'background' => '#ffffff',
			'containerMax' => 1440,
			'contentMax' => 1200,
			// Legacy radius settings stay in the schema for existing documents and token references.
			// Global Design no longer exposes a generic Radius / Shape editor.
			'radius' => 12,
			'fontFamily' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'fluidTokens' => array(
				'fontXs' => 'clamp(0.75rem, 0.72rem + 0.12vw, 0.8125rem)',
				'fontSm' => 'clamp(0.875rem, 0.84rem + 0.15vw, 0.9375rem)',
				'fontBase' => 'clamp(1rem, 0.95rem + 0.2vw, 1.125rem)',
				'fontLg' => 'clamp(1.125rem, 1.04rem + 0.35vw, 1.3125rem)',
				'fontXl' => 'clamp(1.25rem, 1.12rem + 0.55vw, 1.625rem)',
				'h1' => 'clamp(2.25rem, 1.45rem + 3.1vw, 4.75rem)',
				'h2' => 'clamp(1.875rem, 1.35rem + 2vw, 3.375rem)',
				'h3' => 'clamp(1.5rem, 1.15rem + 1.35vw, 2.5rem)',
				'h4' => 'clamp(1.25rem, 1.05rem + 0.85vw, 1.875rem)',
				'h5' => 'clamp(1.125rem, 1rem + 0.5vw, 1.5rem)',
				'h6' => 'clamp(1rem, 0.94rem + 0.25vw, 1.1875rem)',
				'space2xs' => 'clamp(0.25rem, 0.22rem + 0.12vw, 0.375rem)',
				'spaceXs' => 'clamp(0.5rem, 0.44rem + 0.2vw, 0.75rem)',
				'spaceSm' => 'clamp(0.75rem, 0.65rem + 0.35vw, 1rem)',
				'spaceMd' => 'clamp(1rem, 0.82rem + 0.7vw, 1.5rem)',
				'spaceLg' => 'clamp(1.5rem, 1.15rem + 1.25vw, 2.5rem)',
				'spaceXl' => 'clamp(2rem, 1.35rem + 2.4vw, 4rem)',
				'space2xl' => 'clamp(3rem, 1.75rem + 4vw, 6rem)',
				'space3xl' => 'clamp(4rem, 2rem + 6vw, 8rem)',
				'sectionBlock' => 'clamp(3rem, 1.5rem + 5vw, 8rem)',
				'containerGutter' => 'clamp(1rem, 0.5rem + 2vw, 2.5rem)',
				'gridGap' => 'clamp(1rem, 0.7rem + 1vw, 2rem)',
				'radiusSm' => 'clamp(0.375rem, 0.32rem + 0.15vw, 0.5rem)',
				'radiusMd' => 'clamp(0.5rem, 0.4rem + 0.25vw, 0.75rem)',
				'radiusLg' => 'clamp(0.75rem, 0.55rem + 0.5vw, 1.25rem)',
				'controlHeight' => 'clamp(2.75rem, 2.55rem + 0.5vw, 3.125rem)',
				'buttonPadding' => 'clamp(1rem, 0.8rem + 0.65vw, 1.5rem)',
			),
			'button' => array(
				'background' => '#635bff',
				'text' => '#ffffff',
				'hoverBackground' => '#635bff',
				'hoverText' => '#ffffff',
				'borderColor' => 'transparent',
				'borderWidth' => '0px',
				'radius' => 'clamp(0.5rem, 0.4rem + 0.25vw, 0.75rem)',
				'height' => 'clamp(2.75rem, 2.55rem + 0.5vw, 3.125rem)',
				'paddingInline' => 'clamp(1rem, 0.8rem + 0.65vw, 1.5rem)',
				'fontWeight' => '600',
			),
			'breakpoints' => array(
				'mobile' => 0,
				'tablet' => 768,
				'laptop' => 1025,
				'desktop' => 1440,
				'wide' => 1920,
			),
			'customColors' => array(),
			'aliases' => array(),
			'customCss' => '',
			'removeDataOnUninstall' => false,
		);
	}

	public static function get_settings() {
		return self::sanitize_settings( (array) get_option( 'cresco_canvas_settings', array() ) );
	}

	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$container_max = min( 2560, max( 960, absint( $input['containerMax'] ?? $defaults['containerMax'] ) ) );
		$content_max = min( $container_max, max( 640, absint( $input['contentMax'] ?? $defaults['contentMax'] ) ) );
		$fluid = array();
		foreach ( $defaults['fluidTokens'] as $key => $fallback ) {
			$fluid[ $key ] = self::sanitize_fluid_value( $input['fluidTokens'][ $key ] ?? $fallback, $fallback );
		}
		$breakpoints = self::sanitize_breakpoints( $input['breakpoints'] ?? array(), $defaults['breakpoints'] );
		$primary = self::sanitize_color_value( $input['primary'] ?? '' ) ?: $defaults['primary'];
		$text = self::sanitize_color_value( $input['text'] ?? '' ) ?: $defaults['text'];
		$muted = self::sanitize_color_value( $input['muted'] ?? '' ) ?: $defaults['muted'];
		$background = self::sanitize_color_value( $input['background'] ?? '' ) ?: $defaults['background'];

		// Existing sites did not persist a button object. Derive its first canonical
		// value from the legacy global primary/control/radius tokens so upgrading
		// does not visually change existing buttons before the user edits them.
		$button_fallback = array(
			'background' => $primary,
			'text' => '#ffffff',
			'hoverBackground' => $primary,
			'hoverText' => '#ffffff',
			'borderColor' => 'transparent',
			'borderWidth' => '0px',
			'radius' => $fluid['radiusMd'],
			'height' => $fluid['controlHeight'],
			'paddingInline' => $fluid['buttonPadding'],
			'fontWeight' => '600',
		);
		$button = self::sanitize_button_settings( $input['button'] ?? array(), $button_fallback );

		return array(
			'schemaVersion' => 4,
			'primary' => $primary,
			'text' => $text,
			'muted' => $muted,
			'background' => $background,
			'containerMax' => $container_max,
			'contentMax' => $content_max,
			'radius' => min( 80, max( 0, absint( $input['radius'] ?? $defaults['radius'] ) ) ),
			'fontFamily' => self::sanitize_font_family( $input['fontFamily'] ?? $defaults['fontFamily'] ),
			'fluidTokens' => $fluid,
			'button' => $button,
			'breakpoints' => $breakpoints,
			'customColors' => self::sanitize_custom_colors( $input['customColors'] ?? array() ),
			'aliases' => self::sanitize_aliases( $input['aliases'] ?? array() ),
			'customCss' => self::sanitize_custom_css( $input['customCss'] ?? '' ),
			'removeDataOnUninstall' => rest_sanitize_boolean( $input['removeDataOnUninstall'] ?? false ),
		);
	}

	public static function sanitize_color_value( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value || strlen( $value ) > 160 ) return '';
		$hex = sanitize_hex_color( $value );
		if ( $hex ) return $hex;
		if ( 'transparent' === strtolower( $value ) ) return 'transparent';
		if ( preg_match( '/[;{}<>]/', $value ) || preg_match( '/(?:url\s*\(|var\s*\(|env\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding)/i', $value ) ) return '';
		if ( ! preg_match( '/^(?:rgb|rgba|hsl|hsla|oklch|oklab)\(\s*[-+0-9.%\s,\/a-zA-Z]+\s*\)$/', $value ) ) return '';
		return preg_replace( '/\s+/', ' ', $value );
	}

	public static function sanitize_font_family_value( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value || strlen( $value ) > 220 ) return '';
		return preg_match( '/^[a-zA-Z0-9 _,-.\"\'()]+$/', $value ) ? $value : '';
	}

	public static function sanitize_custom_css( $value ) {
		$css = trim( (string) $value );
		if ( '' === $css ) return '';
		if ( strlen( $css ) > self::MAX_CUSTOM_CSS ) return '';
		if ( preg_match( '/(?:@import|@charset|@namespace|@media|@supports|@layer|url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding|<\/?style|<!--|-->)/i', $css ) ) return '';
		if ( preg_match( '/[<>]/', $css ) || substr_count( $css, '{' ) !== substr_count( $css, '}' ) ) return '';

		$cursor = 0;
		$found = false;
		while ( false !== ( $open = strpos( $css, '{', $cursor ) ) ) {
			$selector = trim( substr( $css, $cursor, $open - $cursor ) );
			$close = strpos( $css, '}', $open + 1 );
			if ( false === $close || '' === $selector ) return '';
			foreach ( explode( ',', $selector ) as $part ) {
				$part = trim( $part );
				if ( '' === $part || preg_match( '/^(?:html|body|:root|#wpwrap|#wpcontent)\b/i', $part ) ) return '';
			}
			$declarations = trim( substr( $css, $open + 1, $close - $open - 1 ) );
			if ( preg_match( '/[{}]/', $declarations ) ) return '';
			$found = true;
			$cursor = $close + 1;
		}
		if ( ! $found || '' !== trim( substr( $css, $cursor ) ) ) return '';
		return $css;
	}

	public static function css( $selector = '.cresco-canvas-scope' ) {
		return $selector . '{' . DesignTokens::css_variables( self::get_settings() ) . '}';
	}

	public static function visual_css( $selector ) {
		$settings = self::get_settings();
		$css = sprintf(
			'%1$s{background:var(--cc-background);color:var(--cc-text);font-family:var(--cc-font);font-size:var(--cc-font-base);line-height:1.65;}' .
			'%1$s h1{font-size:var(--cc-h1);line-height:1.12;}' .
			'%1$s h2{font-size:var(--cc-h2);line-height:1.12;}' .
			'%1$s h3{font-size:var(--cc-h3);line-height:1.15;}' .
			'%1$s h4{font-size:var(--cc-h4);}' .
			'%1$s h5{font-size:var(--cc-h5);}' .
			'%1$s h6{font-size:var(--cc-h6);}' .
			'%1$s .wp-block-cresco-container a:not(.wp-block-button__link),%1$s .cresco-widget-text a{color:var(--cc-primary);}' .
			'%1$s .wp-block-cresco-container .wp-block-button__link,%1$s .cresco-widget-button,%1$s .cresco-form button[type="submit"]{border:var(--cc-button-border-width) solid var(--cc-button-border);border-radius:var(--cc-button-radius);min-height:var(--cc-button-height);padding-inline:var(--cc-button-padding-x);font-weight:var(--cc-button-font-weight);}' .
			'%1$s .wp-block-cresco-container .wp-block-button__link:not(.has-background),%1$s .cresco-widget-button,%1$s .cresco-form button[type="submit"]{background-color:var(--cc-button-bg);}' .
			'%1$s .wp-block-cresco-container .wp-block-button__link:not(.has-text-color),%1$s .cresco-widget-button,%1$s .cresco-form button[type="submit"]{color:var(--cc-button-text);}' .
			'%1$s .wp-block-cresco-container .wp-block-button__link:hover,%1$s .cresco-widget-button:hover,%1$s .cresco-form button[type="submit"]:hover{background-color:var(--cc-button-hover-bg);color:var(--cc-button-hover-text);}' .
			'%1$s .cresco-widget-image img{border-radius:var(--cc-radius-md);}' .
			'%1$s .cresco-form{color:var(--cc-text);font-family:var(--cc-font);}' .
			'%1$s .cresco-form-field input,%1$s .cresco-form-field textarea,%1$s .cresco-form-field select{min-height:var(--cc-control-height);border-radius:var(--cc-radius-sm);background:var(--cc-background);color:var(--cc-text);}' .
			'%1$s .cresco-form button:focus-visible,%1$s .cresco-form input:focus-visible,%1$s .cresco-form textarea:focus-visible,%1$s .cresco-form select:focus-visible{outline-color:var(--cc-button-bg);}',
			$selector
		);
		return $css . self::scope_custom_css( $selector, $settings['customCss'] ?? '' );
	}

	public function enqueue_frontend_styles() {
		if ( ! $this->is_canvas_page() ) return;
		wp_enqueue_style( 'cresco-canvas-frontend', CRESCO_CANVAS_URL . 'assets/css/frontend.css', array(), CRESCO_CANVAS_VERSION );
		wp_add_inline_style( 'cresco-canvas-frontend', self::css( 'body.cresco-canvas-page' ) . self::visual_css( 'body.cresco-canvas-page' ) );
	}

	public function add_block_editor_tokens( $settings, $context ) {
		$post = isset( $context->post ) ? $context->post : null;
		if ( ! $post || 'page' !== $post->post_type ) return $settings;
		$settings['styles'] = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$settings['styles'][] = array( 'css' => self::css( '.editor-styles-wrapper' ) . self::visual_css( '.editor-styles-wrapper.cresco-canvas-editor-scope' ), '__unstableType' => 'theme' );
		return $settings;
	}

	public function add_canvas_body_class( $classes ) {
		if ( $this->is_canvas_page() ) $classes[] = 'cresco-canvas-page';
		return array_values( array_unique( $classes ) );
	}

	public function is_canvas_page() {
		if ( ! is_singular( 'page' ) ) return false;
		$post_id = get_queried_object_id();
		return $post_id > 0 && $this->post_uses_canvas( $post_id );
	}

	public function post_uses_canvas( $post_id ) {
		if ( rest_sanitize_boolean( get_post_meta( $post_id, EditorIntegration::ENABLED_META, true ) ) ) return true;
		$post = get_post( $post_id );
		return $post && has_block( 'cresco/container', $post->post_content );
	}

	private static function sanitize_breakpoints( $value, $defaults ) {
		$value = is_array( $value ) ? $value : array();
		$legacy = array( 'mobile' => 480, 'tablet' => 782, 'laptop' => 1200, 'desktop' => 1440, 'wide' => 1920 );
		$is_legacy_default = true;
		foreach ( $legacy as $key => $legacy_value ) {
			if ( absint( $value[ $key ] ?? $legacy_value ) !== $legacy_value ) {
				$is_legacy_default = false;
				break;
			}
		}
		if ( $is_legacy_default ) $value = $defaults;
		$mobile = min( 767, max( 0, absint( $value['mobile'] ?? $defaults['mobile'] ) ) );
		$tablet = min( 1024, max( $mobile + 1, absint( $value['tablet'] ?? $defaults['tablet'] ) ) );
		$laptop = min( 1439, max( $tablet + 1, absint( $value['laptop'] ?? $defaults['laptop'] ) ) );
		$desktop = min( 1919, max( $laptop + 1, absint( $value['desktop'] ?? $defaults['desktop'] ) ) );
		$wide = min( 3840, max( $desktop + 1, absint( $value['wide'] ?? $defaults['wide'] ) ) );
		return array( 'mobile' => $mobile, 'tablet' => $tablet, 'laptop' => $laptop, 'desktop' => $desktop, 'wide' => $wide );
	}

	private static function sanitize_fluid_value( $value, $fallback ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( strlen( $value ) > 160 ) return $fallback;
		return preg_match( '/^(?:clamp|min|max|calc)\([0-9a-zA-Z.%+\-*\/(),\s]+\)|-?[0-9.]+(?:px|rem|em|%|vw|vh|vmin|vmax)$/', $value ) ? $value : $fallback;
	}

	private static function sanitize_button_settings( $value, $fallback ) {
		$value = is_array( $value ) ? $value : array();
		$font_weight = (string) ( $value['fontWeight'] ?? $fallback['fontWeight'] );
		if ( ! preg_match( '/^[1-9]00$/', $font_weight ) ) $font_weight = (string) $fallback['fontWeight'];

		return array(
			'background' => self::sanitize_color_value( $value['background'] ?? '' ) ?: $fallback['background'],
			'text' => self::sanitize_color_value( $value['text'] ?? '' ) ?: $fallback['text'],
			'hoverBackground' => self::sanitize_color_value( $value['hoverBackground'] ?? '' ) ?: $fallback['hoverBackground'],
			'hoverText' => self::sanitize_color_value( $value['hoverText'] ?? '' ) ?: $fallback['hoverText'],
			'borderColor' => self::sanitize_color_value( $value['borderColor'] ?? '' ) ?: $fallback['borderColor'],
			'borderWidth' => self::sanitize_fluid_value( $value['borderWidth'] ?? $fallback['borderWidth'], $fallback['borderWidth'] ),
			'radius' => self::sanitize_fluid_value( $value['radius'] ?? $fallback['radius'], $fallback['radius'] ),
			'height' => self::sanitize_fluid_value( $value['height'] ?? $fallback['height'], $fallback['height'] ),
			'paddingInline' => self::sanitize_fluid_value( $value['paddingInline'] ?? $fallback['paddingInline'], $fallback['paddingInline'] ),
			'fontWeight' => $font_weight,
		);
	}

	private static function sanitize_custom_colors( $value ) {
		$output = array();
		if ( ! is_array( $value ) ) return $output;
		foreach ( array_slice( $value, 0, 24, true ) as $slug => $color ) {
			$slug = sanitize_key( $slug );
			$color = self::sanitize_color_value( $color );
			if ( '' !== $slug && '' !== $color ) $output[ $slug ] = $color;
		}
		return $output;
	}

	private static function sanitize_aliases( $value ) {
		$output = array();
		$allowed = array( 'primary', 'text', 'muted', 'background' );
		if ( ! is_array( $value ) ) return $output;
		foreach ( array_slice( $value, 0, 24, true ) as $alias => $target ) {
			$alias = sanitize_key( $alias );
			$target = sanitize_key( $target );
			if ( '' !== $alias && ( in_array( $target, $allowed, true ) || str_starts_with( $target, 'custom-' ) ) ) $output[ $alias ] = $target;
		}
		return $output;
	}

	private static function sanitize_font_family( $value ) {
		$value = self::sanitize_font_family_value( $value );
		return '' !== $value ? $value : self::defaults()['fontFamily'];
	}

	private static function scope_custom_css( $selector, $css ) {
		$css = self::sanitize_custom_css( $css );
		if ( '' === $css ) return '';
		$output = '';
		$cursor = 0;
		while ( false !== ( $open = strpos( $css, '{', $cursor ) ) ) {
			$raw_selector = trim( substr( $css, $cursor, $open - $cursor ) );
			$close = strpos( $css, '}', $open + 1 );
			if ( false === $close ) break;
			$scoped = array();
			foreach ( explode( ',', $raw_selector ) as $part ) {
				$part = trim( $part );
				$scoped[] = false !== strpos( $part, '&' ) ? str_replace( '&', $selector, $part ) : $selector . ' ' . $part;
			}
			$output .= implode( ',', $scoped ) . '{' . substr( $css, $open + 1, $close - $open - 1 ) . '}';
			$cursor = $close + 1;
		}
		return $output;
	}
}
