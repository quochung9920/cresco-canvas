<?php
/**
 * Authoritative structured CSS compiler for Website Builder documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Core\Responsive\ResponsiveResolver;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderCssCompiler {
	/** Compile base, state, responsive, and scoped Custom CSS for one document. */
	public static function compile( $session ) {
		$css = '';
		foreach ( (array) ( $session['nodes'] ?? array() ) as $node ) $css .= self::compile_node( $node );
		return $css;
	}

	private static function compile_node( $node ) {
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

		foreach ( ResponsiveResolver::OVERRIDE_DEVICES as $device ) {
			$decl = self::style_declarations( (array) ( $node['responsive'][ $device ] ?? array() ) );
			if ( '' !== $decl ) $css .= ResponsiveResolver::wrap( $device, $selector . '{' . $decl . '}' );
		}

		$custom = (array) ( $node['customCSS'] ?? array() );
		if ( ! empty( $custom['base'] ) ) $css .= self::scope_custom_css( $selector, $custom['base'] );
		foreach ( ResponsiveResolver::OVERRIDE_DEVICES as $device ) {
			if ( empty( $custom[ $device ] ) ) continue;
			$scoped = self::scope_custom_css( $selector, $custom[ $device ] );
			if ( '' !== $scoped ) $css .= ResponsiveResolver::wrap( $device, $scoped );
		}

		foreach ( (array) ( $node['children'] ?? array() ) as $child ) $css .= self::compile_node( $child );
		return $css;
	}

	/**
	 * Semantic layout defaults shared by frontend rendering.
	 *
	 * A full-width container is block-level by default, so width:auto already
	 * fills its containing block. Forcing width:100% is incorrect when that same
	 * container is a flex/grid item: it changes the flex basis and can squeeze a
	 * sibling to min-content width. Emit width:auto explicitly so this compiler
	 * also neutralizes legacy width:100% rules that may have been enqueued before
	 * the authoritative contract. Boxed containers intentionally keep width:100%
	 * together with max-width and auto margins.
	 */
	private static function props_style( $node ) {
		$type  = (string) ( $node['type'] ?? '' );
		$props = (array) ( $node['props'] ?? array() );
		if ( 'container' === $type ) {
			$layout = in_array( $props['layout'] ?? '', array( 'block', 'flex', 'grid' ), true ) ? $props['layout'] : 'flex';
			$style = array( 'display' => $layout, 'width' => 'auto' );
			if ( 'boxed' === ( $props['contentWidth'] ?? '' ) ) {
				$style['width'] = '100%';
				$style['maxWidth'] = '{layout.containerMax}';
				$style['marginLeft'] = 'auto';
				$style['marginRight'] = 'auto';
			}
			if ( 'flex' === $layout ) {
				$style['flexDirection'] = (string) ( $props['direction'] ?? 'column' );
				$style['flexWrap'] = (string) ( $props['wrap'] ?? 'nowrap' );
				$style['alignItems'] = (string) ( $props['align'] ?? 'stretch' );
				$style['justifyContent'] = (string) ( $props['justify'] ?? 'flex-start' );
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
