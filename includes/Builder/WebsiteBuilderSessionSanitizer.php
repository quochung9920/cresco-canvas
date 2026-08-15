<?php
/**
 * Canonical Website Builder Session sanitizer with advanced scoped Custom CSS.
 *
 * WebsiteBuilder keeps the stable node/property contract. This adapter removes
 * Custom CSS before the legacy sanitizer runs, validates it with ScopedCss,
 * then restores the validated buckets by stable node id.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Styles\ScopedCss;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderSessionSanitizer {
	const CUSTOM_CSS_BUCKETS = array( 'base', 'desktop', 'laptop', 'tablet', 'mobile' );

	public static function sanitize_session( $input ) {
		if ( ! is_array( $input ) ) return WebsiteBuilder::sanitize_session( $input );

		$custom_css = array();
		$stripped   = $input;
		$stripped['nodes'] = self::strip_custom_css( $input['nodes'] ?? array(), $custom_css );
		$session = WebsiteBuilder::sanitize_session( $stripped );
		if ( is_wp_error( $session ) ) return $session;

		$validated = array();
		foreach ( $custom_css as $id => $map ) {
			$result = self::sanitize_custom_css_map( $map );
			if ( is_wp_error( $result ) ) return $result;
			if ( $result ) $validated[ $id ] = $result;
		}
		$session['nodes'] = self::restore_custom_css( $session['nodes'] ?? array(), $validated );
		return $session;
	}

	public static function sanitize_custom_css_map( $input ) {
		if ( null === $input || array() === $input ) return array();
		if ( ! is_array( $input ) ) return new WP_Error( 'cresco_builder_css_map', __( 'Widget Custom CSS must be an object keyed by device.', 'cresco-canvas' ), array( 'status' => 400 ) );
		$output = array();
		foreach ( $input as $bucket => $css ) {
			if ( ! in_array( $bucket, self::CUSTOM_CSS_BUCKETS, true ) ) return new WP_Error( 'cresco_builder_css_bucket', __( 'Widget Custom CSS contains an unsupported device bucket.', 'cresco-canvas' ), array( 'status' => 400, 'bucket' => (string) $bucket ) );
			if ( ! is_string( $css ) ) return new WP_Error( 'cresco_builder_css_value', __( 'Widget Custom CSS values must be strings.', 'cresco-canvas' ), array( 'status' => 400, 'bucket' => (string) $bucket ) );
			$clean = ScopedCss::sanitize( $css, WebsiteBuilder::MAX_CUSTOM_CSS );
			if ( is_wp_error( $clean ) ) return $clean;
			if ( '' !== $clean ) $output[ $bucket ] = $clean;
		}
		return $output;
	}

	private static function strip_custom_css( $nodes, &$custom_css ) {
		$output = array();
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				$output[] = $node;
				continue;
			}
			$id = self::normalize_node_id( $node['id'] ?? '' );
			if ( '' !== $id && array_key_exists( 'customCSS', $node ) ) $custom_css[ $id ] = $node['customCSS'];
			$node['customCSS'] = array();
			if ( isset( $node['children'] ) ) $node['children'] = self::strip_custom_css( $node['children'], $custom_css );
			$output[] = $node;
		}
		return $output;
	}

	private static function restore_custom_css( $nodes, $custom_css ) {
		$output = array();
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			$id = (string) ( $node['id'] ?? '' );
			$node['customCSS'] = isset( $custom_css[ $id ] ) ? $custom_css[ $id ] : array();
			if ( ! empty( $node['children'] ) ) $node['children'] = self::restore_custom_css( $node['children'], $custom_css );
			$output[] = $node;
		}
		return $output;
	}

	private static function normalize_node_id( $value ) {
		$value = preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( trim( (string) $value ) ) );
		return substr( trim( (string) $value, '-' ), 0, 80 );
	}

	private function __construct() {}
}
