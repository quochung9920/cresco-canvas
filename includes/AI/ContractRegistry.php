<?php
/**
 * Machine-readable widget contracts shared by the AI interchange layer.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Session\SessionManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContractRegistry {
	const RESPONSIVE_DEVICES = array( 'desktop', 'laptop', 'tablet', 'mobile' );
	const CUSTOM_CSS_BUCKETS = array( 'base', 'desktop', 'laptop', 'tablet', 'mobile' );

	/** Return the canonical machine-readable contract catalog. */
	public static function all() {
		$output = array();
		foreach ( SessionManager::widget_catalog() as $type => $contract ) {
			$allows_children = ! empty( $contract['allowsChildren'] );
			$output[ $type ] = array(
				'type'              => $type,
				'label'             => (string) ( $contract['label'] ?? $type ),
				'allowsChildren'    => $allows_children,
				'childBehavior'     => $allows_children ? 'cresco-widget-children' : 'none',
				'props'             => (array) ( $contract['props'] ?? array() ),
				'structuredStyle'   => array_values( (array) ( $contract['style'] ?? array() ) ),
				'responsive'        => array( 'allowed' => true, 'devices' => self::RESPONSIVE_DEVICES ),
				'tokens'            => array( 'allowed' => true, 'syntax' => '{path}' ),
				'customCSS'         => (array) ( $contract['css'] ?? array( 'allowed' => false ) ),
				'stableSelectorKey' => 'data-cresco-id',
			);
		}
		return $output;
	}

	/** Return only contracts needed by a scoped context. */
	public static function for_types( $types ) {
		$catalog = self::all();
		$output  = array();
		foreach ( array_values( array_unique( array_map( 'strval', (array) $types ) ) ) as $type ) {
			if ( isset( $catalog[ $type ] ) ) {
				$output[ $type ] = $catalog[ $type ];
			}
		}
		return $output;
	}

	/** Strictly validate node keys that AI is allowed to author. */
	public static function validate_node( $node, $path = 'node' ) {
		if ( ! is_array( $node ) ) {
			return self::error( 'cresco_ai_node_invalid', 'AI-authored node must be an object.', $path );
		}
		$type    = sanitize_key( (string) ( $node['type'] ?? '' ) );
		$catalog = self::all();
		if ( '' === $type || ! isset( $catalog[ $type ] ) ) {
			return self::error( 'cresco_ai_widget_unsupported', 'AI result contains an unsupported widget type.', $path . '.type', array( 'type' => $type ) );
		}
		$contract = $catalog[ $type ];

		$props = isset( $node['props'] ) ? $node['props'] : array();
		if ( ! is_array( $props ) ) {
			return self::error( 'cresco_ai_props_invalid', 'Widget props must be an object.', $path . '.props' );
		}
		foreach ( $props as $key => $value ) {
			if ( ! array_key_exists( $key, $contract['props'] ) ) {
				return self::error( 'cresco_ai_property_unsupported', 'AI result contains an unsupported widget property.', $path . '.props.' . $key, array( 'property' => $key, 'widgetType' => $type ) );
			}
			$valid = self::validate_prop_value( $value, $contract['props'][ $key ], $path . '.props.' . $key );
			if ( is_wp_error( $valid ) ) return $valid;
		}

		$style = isset( $node['style'] ) ? $node['style'] : array();
		$valid = self::validate_style_map( $type, $style, $path . '.style' );
		if ( is_wp_error( $valid ) ) return $valid;

		$responsive = isset( $node['responsive'] ) ? $node['responsive'] : array();
		$valid      = self::validate_responsive_map( $type, $responsive, $path . '.responsive' );
		if ( is_wp_error( $valid ) ) return $valid;

		$custom_css = isset( $node['customCSS'] ) ? $node['customCSS'] : array();
		$valid      = self::validate_custom_css_map( $custom_css, $path . '.customCSS' );
		if ( is_wp_error( $valid ) ) return $valid;

		$children = isset( $node['children'] ) ? $node['children'] : array();
		if ( ! is_array( $children ) ) {
			return self::error( 'cresco_ai_children_invalid', 'Widget children must be an array.', $path . '.children' );
		}
		if ( $children && empty( $contract['allowsChildren'] ) ) {
			return self::error( 'cresco_ai_children_unsupported', 'This widget contract does not allow child nodes.', $path . '.children', array( 'widgetType' => $type ) );
		}
		foreach ( $children as $index => $child ) {
			$valid = self::validate_node( $child, $path . '.children.' . $index );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		return true;
	}

	/** Strict style-property validation. Values are sanitized by SessionManager later. */
	public static function validate_style_map( $type, $style, $path = 'style' ) {
		if ( ! is_array( $style ) ) {
			return self::error( 'cresco_ai_style_invalid', 'Structured style must be an object.', $path );
		}
		$catalog = self::all();
		if ( ! isset( $catalog[ $type ] ) ) {
			return self::error( 'cresco_ai_widget_unsupported', 'Unknown widget contract.', $path, array( 'widgetType' => $type ) );
		}
		$allowed = array_flip( (array) $catalog[ $type ]['structuredStyle'] );
		foreach ( $style as $property => $value ) {
			if ( ! isset( $allowed[ $property ] ) ) {
				return self::error( 'cresco_ai_style_unsupported', 'AI result contains an unsupported structured style property.', $path . '.' . $property, array( 'property' => $property, 'widgetType' => $type ) );
			}
			if ( ! self::valid_css_value( $value ) ) {
				return self::error( 'cresco_ai_style_value', 'AI result contains an invalid structured style value.', $path . '.' . $property, array( 'property' => $property, 'widgetType' => $type ) );
			}
		}
		return true;
	}

	public static function validate_responsive_map( $type, $responsive, $path = 'responsive' ) {
		if ( ! is_array( $responsive ) ) {
			return self::error( 'cresco_ai_responsive_invalid', 'Responsive styles must be an object.', $path );
		}
		foreach ( $responsive as $device => $style ) {
			if ( ! in_array( $device, self::RESPONSIVE_DEVICES, true ) ) {
				return self::error( 'cresco_ai_device_unsupported', 'AI result contains an unsupported responsive device.', $path . '.' . $device, array( 'device' => $device ) );
			}
			$valid = self::validate_style_map( $type, $style, $path . '.' . $device );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		return true;
	}

	public static function validate_custom_css_map( $map, $path = 'customCSS' ) {
		if ( ! is_array( $map ) ) {
			return self::error( 'cresco_ai_custom_css_invalid', 'Custom CSS must be an object keyed by device.', $path );
		}
		foreach ( $map as $bucket => $css ) {
			if ( ! in_array( $bucket, self::CUSTOM_CSS_BUCKETS, true ) ) {
				return self::error( 'cresco_ai_custom_css_bucket', 'AI result contains an unsupported Custom CSS bucket.', $path . '.' . $bucket, array( 'bucket' => $bucket ) );
			}
			if ( ! is_string( $css ) ) {
				return self::error( 'cresco_ai_custom_css_invalid', 'Custom CSS values must be strings.', $path . '.' . $bucket );
			}
		}
		return true;
	}

	private static function validate_prop_value( $value, $schema, $path ) {
		$kind = (string) ( $schema['type'] ?? 'string' );
		if ( 'enum' === $kind && ! in_array( $value, (array) ( $schema['values'] ?? array() ), true ) ) {
			return self::error( 'cresco_ai_prop_value', 'AI result contains an unsupported enum value.', $path, array( 'value' => $value, 'allowed' => array_values( (array) ( $schema['values'] ?? array() ) ) ) );
		}
		if ( 'int' === $kind ) {
			if ( ! is_numeric( $value ) || (int) $value != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
				return self::error( 'cresco_ai_prop_value', 'AI result requires an integer value.', $path );
			}
			$number = (int) $value;
			if ( isset( $schema['min'] ) && $number < (int) $schema['min'] ) return self::error( 'cresco_ai_prop_value', 'AI integer is below the contract minimum.', $path );
			if ( isset( $schema['max'] ) && $number > (int) $schema['max'] ) return self::error( 'cresco_ai_prop_value', 'AI integer is above the contract maximum.', $path );
		}
		if ( 'string_list' === $kind && ! is_array( $value ) ) {
			return self::error( 'cresco_ai_prop_value', 'AI result requires an array of strings.', $path );
		}
		if ( in_array( $kind, array( 'string', 'text', 'url', 'css' ), true ) && ! is_scalar( $value ) ) {
			return self::error( 'cresco_ai_prop_value', 'AI result requires a scalar property value.', $path );
		}
		if ( 'css' === $kind && ! self::valid_css_value( $value ) ) {
			return self::error( 'cresco_ai_prop_value', 'AI result contains an invalid CSS value.', $path );
		}
		return true;
	}

	private static function valid_css_value( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) return true; // Empty clears a structured override.
		if ( strlen( $value ) > 180 ) return false;
		if ( preg_match( '/^\{[a-zA-Z0-9._-]+\}$/', $value ) ) return true;
		if ( preg_match( '/[;{}<>]/', $value ) || preg_match( '/(?:url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding)/i', $value ) ) return false;
		return (bool) preg_match( "/^[#a-zA-Z0-9.,%+\-*\/() _\"']+$/", $value );
	}

	private static function error( $code, $message, $path, $extra = array() ) {
		return new WP_Error( $code, __( $message, 'cresco-canvas' ), array_merge( array( 'status' => 400, 'path' => $path ), (array) $extra ) );
	}
}
