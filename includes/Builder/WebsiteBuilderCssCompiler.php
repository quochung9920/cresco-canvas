<?php
/**
 * Authoritative structured CSS compiler for Website Builder documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderCssCompiler {
	/** Compile base, state, responsive, and scoped Custom CSS for one document. */
	public static function compile( $session ) {
		$settings    = GlobalStyles::get_settings();
		$breakpoints = (array) ( $settings['breakpoints'] ?? array() );
		$css = '';
		foreach ( (array) ( $session['nodes'] ?? array() ) as $node ) $css .= self::compile_node( $node, $breakpoints );
		return $css;
	}

	private static function compile_node( $node, $breakpoints ) {
		$id       = preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) ( $node['id'] ?? '' ) );
		$selector = '.cresco-website-builder-root [data-cresco-id="' . $id . '"]';
		$css      = '';
		$base     = array_merge( self::props_style( $node ), (array) ( $node['style'] ?? array() ) );
		$decl     = self::style_declarations( $base );
		if ( '' !== $decl ) $css .= $selector . '{' . $decl . '}';

		foreach ( array( 'hover', 'focus', 'active' ) as $state ) {
			$decl = self::style_declarations( (array) ( $node['states'][ $state ] ?? array() ) );
			if ( '' !== $decl ) $css .= $selector . ':' . $state . '{' . $decl . '}';
		}

		foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			$decl = self::style_declarations( (array) ( $node['responsive'][ $device ] ?? array() ) );
			if ( '' !== $decl ) $css .= self::wrap_range( $device, $selector . '{' . $decl . '}', $breakpoints );
		}

		$custom = (array) ( $node['customCSS'] ?? array() );
		if ( ! empty( $custom['base'] ) ) $css .= self::scope_custom_css( $selector, $custom['base'] );
		foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			if ( empty( $custom[ $device ] ) ) continue;
			$scoped = self::scope_custom_css( $selector, $custom[ $device ] );
			if ( '' !== $scoped ) $css .= self::wrap_range( $device, $scoped, $breakpoints );
		}

		foreach ( (array) ( $node['children'] ?? array() ) as $child ) $css .= self::compile_node( $child, $breakpoints );
		return $css;
	}

	/**
	 * Compile the same desktop-first cascade used by Studio's effectiveStyle().
	 *
	 * GlobalStyles stores breakpoint *starts*: mobile=0, tablet=768,
	 * laptop=1025, desktop=1440, wide=1920 by default. A responsive bucket is
	 * therefore active from the next larger range downwards. Emitting cumulative
	 * max-width queries means a 390px viewport receives desktop, laptop, tablet,
	 * then mobile overrides in that exact order, matching Studio inheritance.
	 */
	private static function wrap_range( $device, $css, $breakpoints ) {
		$mobile  = max( 0, absint( $breakpoints['mobile'] ?? 0 ) );
		$tablet  = max( $mobile + 1, absint( $breakpoints['tablet'] ?? 768 ) );
		$laptop  = max( $tablet + 1, absint( $breakpoints['laptop'] ?? 1025 ) );
		$desktop = max( $laptop + 1, absint( $breakpoints['desktop'] ?? 1440 ) );
		$wide    = max( $desktop + 1, absint( $breakpoints['wide'] ?? 1920 ) );
		$max_widths = array(
			'desktop' => $wide - 1,
			'laptop'  => $desktop - 1,
			'tablet'  => $laptop - 1,
			'mobile'  => $tablet - 1,
		);
		if ( ! isset( $max_widths[ $device ] ) || '' === $css ) return '';
		return '@media (max-width:' . max( 0, (int) $max_widths[ $device ] ) . 'px){' . $css . '}';
	}

	private static function props_style( $node ) {
		$type  = (string) ( $node['type'] ?? '' );
		$props = (array) ( $node['props'] ?? array() );
		if ( 'container' === $type ) {
			$layout = in_array( $props['layout'] ?? '', array( 'block', 'flex', 'grid' ), true ) ? $props['layout'] : 'flex';
			$style = array( 'display' => $layout, 'width' => '100%' );
			if ( 'boxed' === ( $props['contentWidth'] ?? '' ) ) {
				$style['maxWidth'] = '{layout.containerMax}';
				$style['marginLeft'] = 'auto';
				$style['marginRight'] = 'auto';
			}
			if ( 'flex' === $layout ) {
				$style['flexDirection']  = (string) ( $props['direction'] ?? 'column' );
				$style['flexWrap']       = (string) ( $props['wrap'] ?? 'nowrap' );
				$style['alignItems']      = (string) ( $props['align'] ?? 'stretch' );
				$style['justifyContent']  = (string) ( $props['justify'] ?? 'flex-start' );
			}
			if ( 'grid' === $layout ) {
				$template = WebsiteBuilder::sanitize_css_value( $props['gridTemplate'] ?? '' );
				$style['gridTemplateColumns'] = $template ?: 'repeat(' . min( 12, max( 1, absint( $props['columns'] ?? 2 ) ) ) . ',minmax(0,1fr))';
			}
			return $style;
		}
		if ( 'columns' === $type ) return array( 'display' => 'grid', 'gridTemplateColumns' => 'repeat(' . min( 12, max( 1, absint( $props['columns'] ?? 2 ) ) ) . ',minmax(0,1fr))', 'gap' => '{layout.gridGap}' );
		if ( 'spacer' === $type ) return array( 'minHeight' => (string) ( $props['height'] ?? '48px' ) );
		return array();
	}

	private static function style_declarations( $styles ) {
		$output  = '';
		$allowed = array_flip( WidgetCatalog::style_properties() );
		foreach ( (array) $styles as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) continue;
			$value = self::resolve_token( WebsiteBuilder::sanitize_css_value( $value ) );
			if ( '' === $value ) continue;
			$property = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', (string) $key ) );
			$output .= $property . ':' . $value . ';';
		}
		return $output;
	}

	private static function resolve_token( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\{([a-zA-Z0-9._-]+)\}$/', $value, $matches ) ) return (string) $value;
		$current = DesignTokens::catalog( GlobalStyles::get_settings() );
		foreach ( explode( '.', $matches[1] ) as $part ) {
			if ( ! is_array( $current ) || ! array_key_exists( $part, $current ) ) return '';
			$current = $current[ $part ];
		}
		return is_scalar( $current ) ? WebsiteBuilder::sanitize_css_value( (string) $current ) : '';
	}

	private static function scope_custom_css( $selector, $css ) {
		$clean = WebsiteBuilder::sanitize_custom_css( $css );
		if ( is_wp_error( $clean ) || '' === $clean ) return '';
		$output = '';
		$cursor = 0;
		while ( false !== ( $open = strpos( $clean, '{', $cursor ) ) ) {
			$raw_selector = trim( substr( $clean, $cursor, $open - $cursor ) );
			$close = strpos( $clean, '}', $open + 1 );
			if ( false === $close ) break;
			$scoped = array();
			foreach ( explode( ',', $raw_selector ) as $part ) $scoped[] = str_replace( '&', $selector, trim( $part ) );
			$output .= implode( ',', $scoped ) . '{' . substr( $clean, $open + 1, $close - $open - 1 ) . '}';
			$cursor = $close + 1;
		}
		return $output;
	}

	private function __construct() {}
}
