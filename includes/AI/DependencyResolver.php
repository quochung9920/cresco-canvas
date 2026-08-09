<?php
/**
 * Resolves design-token, responsive, and media dependencies for AI exports.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DependencyResolver {
	public static function resolve( $content, $design_system ) {
		$tokens = self::token_paths( $content );
		return array(
			'tokens'     => array_map( static function ( $path ) use ( $design_system ) {
				return array( 'path' => $path, 'fallback' => self::read_path( $design_system, $path ) );
			}, $tokens ),
			'media'      => self::media_descriptors( $content ),
			'responsive' => self::responsive_devices( $content ),
		);
	}

	public static function optimized_design_system( $design_system, $dependencies ) {
		$output = array( 'schemaVersion' => $design_system['schemaVersion'] ?? null );
		foreach ( (array) ( $dependencies['tokens'] ?? array() ) as $dependency ) {
			$path = (string) ( $dependency['path'] ?? '' );
			if ( '' === $path ) continue;
			$value = self::read_path( $design_system, $path );
			if ( null !== $value ) self::write_path( $output, $path, $value );
		}
		if ( ! empty( $dependencies['responsive'] ) && isset( $design_system['breakpoints'] ) ) {
			$output['breakpoints'] = $design_system['breakpoints'];
		}
		return $output;
	}

	private static function token_paths( $value ) {
		$found = array();
		self::walk_strings( $value, static function ( $string ) use ( &$found ) {
			if ( preg_match_all( '/\{([a-zA-Z0-9._-]+)\}/', $string, $matches ) ) {
				foreach ( $matches[1] as $path ) $found[ $path ] = true;
			}
		} );
		return array_keys( $found );
	}

	private static function responsive_devices( $value ) {
		$devices = array();
		self::walk_nodes( $value, static function ( $node ) use ( &$devices ) {
			foreach ( (array) ( $node['responsive'] ?? array() ) as $device => $style ) {
				if ( $style ) $devices[ $device ] = true;
			}
			foreach ( (array) ( $node['customCSS'] ?? array() ) as $device => $css ) {
				if ( 'base' !== $device && $css ) $devices[ $device ] = true;
			}
		} );
		return array_keys( $devices );
	}

	private static function media_descriptors( $value ) {
		$media = array();
		self::walk_nodes( $value, static function ( $node ) use ( &$media ) {
			if ( 'image' !== ( $node['type'] ?? '' ) ) return;
			$props = (array) ( $node['props'] ?? array() );
			$url   = (string) ( $props['url'] ?? '' );
			if ( '' === $url ) return;
			$descriptor = array(
				'nodeId' => (string) ( $node['id'] ?? '' ),
				'id'     => 0,
				'url'    => $url,
				'alt'    => (string) ( $props['alt'] ?? '' ),
				'width'  => 0,
				'height' => 0,
				'policy' => 'URL is descriptive only; cross-site import must map media explicitly and must not auto-download remote URLs.',
			);
			if ( function_exists( 'attachment_url_to_postid' ) ) {
				$attachment_id = absint( attachment_url_to_postid( $url ) );
				if ( $attachment_id ) {
					$descriptor['id'] = $attachment_id;
					if ( function_exists( 'wp_get_attachment_metadata' ) ) {
						$metadata = (array) wp_get_attachment_metadata( $attachment_id );
						$descriptor['width']  = absint( $metadata['width'] ?? 0 );
						$descriptor['height'] = absint( $metadata['height'] ?? 0 );
					}
				}
			}
			$media[] = $descriptor;
		} );
		return $media;
	}

	private static function walk_nodes( $value, $callback ) {
		if ( ! is_array( $value ) ) return;
		if ( isset( $value['id'], $value['type'] ) ) $callback( $value );
		foreach ( $value as $child ) if ( is_array( $child ) ) self::walk_nodes( $child, $callback );
	}

	private static function walk_strings( $value, $callback ) {
		if ( is_string( $value ) ) {
			$callback( $value );
			return;
		}
		if ( ! is_array( $value ) ) return;
		foreach ( $value as $child ) self::walk_strings( $child, $callback );
	}

	private static function read_path( $source, $path ) {
		$current = $source;
		foreach ( explode( '.', (string) $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) return null;
			$current = $current[ $segment ];
		}
		return $current;
	}

	private static function write_path( &$target, $path, $value ) {
		$segments = explode( '.', (string) $path );
		$cursor   =& $target;
		foreach ( $segments as $segment ) {
			if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) $cursor[ $segment ] = array();
			$cursor =& $cursor[ $segment ];
		}
		$cursor = $value;
	}
}
