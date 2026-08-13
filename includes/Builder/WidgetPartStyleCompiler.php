<?php
/**
 * Compile Widget Architecture v2 scoped part styles and advanced visual effects.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WidgetPartStyleCompiler {
	public static function compile( $session, $architecture ) {
		$catalog = WidgetArchitectureV2::catalog();
		$settings = GlobalStyles::get_settings();
		$breakpoints = (array) ( $settings['breakpoints'] ?? array() );
		$configs = (array) ( $architecture['nodes'] ?? array() );
		$css = '';
		$walk = static function ( $nodes ) use ( &$walk, &$css, $configs, $catalog, $breakpoints ) {
			foreach ( (array) $nodes as $node ) {
				if ( ! is_array( $node ) ) continue;
				$id = self::node_id( $node['id'] ?? '' );
				$type = sanitize_key( (string) ( $node['type'] ?? '' ) );
				$config = isset( $configs[ $id ] ) && is_array( $configs[ $id ] ) ? $configs[ $id ] : array();
				if ( $id && isset( $catalog[ $type ] ) && ! empty( $config['partStyles'] ) ) {
					$root = '.cresco-website-builder-root [data-cresco-id="' . $id . '"]';
					foreach ( (array) $config['partStyles'] as $part_key => $part_config ) {
						$part = $catalog[ $type ]['parts'][ $part_key ] ?? null;
						if ( ! is_array( $part ) ) continue;
						$selector_template = (string) ( $part['selector'] ?? '&' );
						$selector = str_replace( '&', $root, $selector_template );
						$decl = self::declarations( $part_config['base'] ?? array() );
						if ( $decl ) $css .= $selector . '{' . $decl . '}';
						foreach ( array( 'hover', 'focus', 'active' ) as $state ) {
							$decl = self::declarations( $part_config['states'][ $state ] ?? array() );
							if ( $decl ) $css .= $selector . ':' . $state . '{' . $decl . '}';
						}
						foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
							$max = absint( $breakpoints[ $device ] ?? 0 );
							if ( $max < 1 ) continue;
							$body = '';
							$decl = self::declarations( $part_config['responsive'][ $device ] ?? array() );
							if ( $decl ) $body .= $selector . '{' . $decl . '}';
							foreach ( array( 'hover', 'focus', 'active' ) as $state ) {
								$decl = self::declarations( $part_config['responsiveStates'][ $device ][ $state ] ?? array() );
								if ( $decl ) $body .= $selector . ':' . $state . '{' . $decl . '}';
							}
							if ( $body ) $css .= '@media (max-width:' . $max . 'px){' . $body . '}';
						}
					}
				}
				$walk( $node['children'] ?? array() );
			}
		};
		$walk( is_array( $session ) ? ( $session['nodes'] ?? array() ) : array() );
		return $css;
	}

	private static function declarations( $styles ) {
		$allowed = array_fill_keys( array_merge( WidgetCatalog::style_properties(), WidgetArchitectureV2::advanced_style_properties() ), true );
		$enums = WidgetArchitectureV2::advanced_style_enums();
		$out = '';
		foreach ( (array) $styles as $key => $value ) {
			$key = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $key );
			if ( ! isset( $allowed[ $key ] ) ) continue;
			$value = WebsiteBuilder::sanitize_css_value( $value );
			if ( isset( $enums[ $key ] ) && ! in_array( $value, $enums[ $key ], true ) ) continue;
			if ( '' === $value ) continue;
			$property = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $key ) );
			$out .= $property . ':' . $value . ';';
		}
		return $out;
	}

	private static function node_id( $value ) {
		$value = preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( trim( (string) $value ) ) );
		return substr( trim( (string) $value, '-' ), 0, 80 );
	}

	private function __construct() {}
}
