<?php
/**
 * Machine-readable widget contracts shared by the AI interchange layer.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WidgetCatalog;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContractRegistry {
	const RESPONSIVE_DEVICES = array( 'desktop', 'laptop', 'tablet', 'mobile' );
	const STATES             = array( 'hover', 'focus', 'active' );
	const CUSTOM_CSS_BUCKETS = array( 'base', 'desktop', 'laptop', 'tablet', 'mobile' );
	const NODE_FIELDS        = array( 'id', 'type', 'props', 'style', 'responsive', 'states', 'customCSS', 'meta', 'children' );
	const META_FIELDS        = array( 'label', 'componentId', 'locked', 'hidden' );

	/** Return the canonical machine-readable contract catalog. */
	public static function all() {
		$output = array();
		foreach ( WidgetCatalog::all() as $type => $contract ) {
			$allows_children = ! empty( $contract['allowsChildren'] );
			$output[ $type ] = array(
				'type'              => $type,
				'label'             => (string) ( $contract['label'] ?? $type ),
				'category'          => (string) ( $contract['category'] ?? 'content' ),
				'allowsChildren'    => $allows_children,
				'childBehavior'     => $allows_children ? 'cresco-widget-children' : 'none',
				'props'             => (array) ( $contract['props'] ?? array() ),
				'structuredStyle'   => array_values( (array) ( $contract['style'] ?? WidgetCatalog::style_properties() ) ),
				'responsive'        => array( 'allowed' => ! empty( $contract['responsive'] ), 'devices' => self::RESPONSIVE_DEVICES ),
				'states'            => array_values( (array) ( $contract['states'] ?? self::STATES ) ),
				'tokens'            => array( 'allowed' => true, 'syntax' => '{path}' ),
				'customCSS'         => self::custom_css_contract( $type ),
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
			if ( isset( $catalog[ $type ] ) ) $output[ $type ] = $catalog[ $type ];
		}
		return $output;
	}

	/** Strictly validate a node against the same contract used by Website Builder. */
	public static function validate_node( $node, $path = 'node' ) {
		if ( ! is_array( $node ) ) return self::error( 'cresco_ai_node_invalid', 'AI-authored node must be an object.', $path );
		foreach ( array_keys( $node ) as $field ) {
			if ( ! in_array( $field, self::NODE_FIELDS, true ) ) {
				return self::error( 'cresco_ai_node_field', 'AI-authored node contains an unsupported field.', $path . '.' . $field, array( 'field' => $field ) );
			}
		}

		$id = (string) ( $node['id'] ?? '' );
		if ( '' === trim( $id ) ) return self::error( 'cresco_ai_node_id', 'AI-authored node requires a stable id.', $path . '.id' );

		$type    = sanitize_key( (string) ( $node['type'] ?? '' ) );
		$catalog = self::all();
		if ( '' === $type || ! isset( $catalog[ $type ] ) ) {
			return self::error( 'cresco_ai_widget_unsupported', 'AI result contains an unsupported widget type.', $path . '.type', array( 'type' => $type ) );
		}
		$contract = $catalog[ $type ];

		$props = isset( $node['props'] ) ? $node['props'] : array();
		if ( ! is_array( $props ) ) return self::error( 'cresco_ai_props_invalid', 'Widget props must be an object.', $path . '.props' );
		foreach ( $props as $key => $value ) {
			if ( ! array_key_exists( $key, $contract['props'] ) ) {
				return self::error( 'cresco_ai_property_unsupported', 'AI result contains an unsupported widget property.', $path . '.props.' . $key, array( 'property' => $key, 'widgetType' => $type ) );
			}
			$valid = self::validate_prop_value( $value, $contract['props'][ $key ], $path . '.props.' . $key );
			if ( is_wp_error( $valid ) ) return $valid;
		}

		$valid = self::validate_style_map( $type, $node['style'] ?? array(), $path . '.style' );
		if ( is_wp_error( $valid ) ) return $valid;
		$valid = self::validate_responsive_map( $type, $node['responsive'] ?? array(), $path . '.responsive' );
		if ( is_wp_error( $valid ) ) return $valid;
		$valid = self::validate_states_map( $type, $node['states'] ?? array(), $path . '.states' );
		if ( is_wp_error( $valid ) ) return $valid;
		$valid = self::validate_custom_css_map( $node['customCSS'] ?? array(), $path . '.customCSS' );
		if ( is_wp_error( $valid ) ) return $valid;
		$valid = self::validate_meta( $node['meta'] ?? array(), $path . '.meta' );
		if ( is_wp_error( $valid ) ) return $valid;

		$children = isset( $node['children'] ) ? $node['children'] : array();
		if ( ! is_array( $children ) ) return self::error( 'cresco_ai_children_invalid', 'Widget children must be an array.', $path . '.children' );
		if ( $children && empty( $contract['allowsChildren'] ) ) {
			return self::error( 'cresco_ai_children_unsupported', 'This widget contract does not allow child nodes.', $path . '.children', array( 'widgetType' => $type ) );
		}
		foreach ( $children as $index => $child ) {
			$valid = self::validate_node( $child, $path . '.children.' . $index );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		return true;
	}

	/** Strict style-property validation. */
	public static function validate_style_map( $type, $style, $path = 'style' ) {
		if ( ! is_array( $style ) ) return self::error( 'cresco_ai_style_invalid', 'Structured style must be an object.', $path );
		$catalog = self::all();
		if ( ! isset( $catalog[ $type ] ) ) return self::error( 'cresco_ai_widget_unsupported', 'Unknown widget contract.', $path, array( 'widgetType' => $type ) );
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
		if ( ! is_array( $responsive ) ) return self::error( 'cresco_ai_responsive_invalid', 'Responsive styles must be an object.', $path );
		foreach ( $responsive as $device => $style ) {
			if ( ! in_array( $device, self::RESPONSIVE_DEVICES, true ) ) {
				return self::error( 'cresco_ai_device_unsupported', 'AI result contains an unsupported responsive device.', $path . '.' . $device, array( 'device' => $device ) );
			}
			$valid = self::validate_style_map( $type, $style, $path . '.' . $device );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		return true;
	}

	public static function validate_states_map( $type, $states, $path = 'states' ) {
		if ( ! is_array( $states ) ) return self::error( 'cresco_ai_states_invalid', 'Widget states must be an object.', $path );
		$catalog = self::all();
		$allowed = isset( $catalog[ $type ] ) ? (array) $catalog[ $type ]['states'] : self::STATES;
		foreach ( $states as $state => $style ) {
			if ( ! in_array( $state, $allowed, true ) ) {
				return self::error( 'cresco_ai_state_unsupported', 'AI result contains an unsupported widget state.', $path . '.' . $state, array( 'state' => $state ) );
			}
			$valid = self::validate_style_map( $type, $style, $path . '.' . $state );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		return true;
	}

	public static function validate_custom_css_map( $map, $path = 'customCSS' ) {
		if ( ! is_array( $map ) ) return self::error( 'cresco_ai_custom_css_invalid', 'Custom CSS must be an object keyed by device.', $path );
		foreach ( $map as $bucket => $css ) {
			if ( ! in_array( $bucket, self::CUSTOM_CSS_BUCKETS, true ) ) {
				return self::error( 'cresco_ai_custom_css_bucket', 'AI result contains an unsupported Custom CSS bucket.', $path . '.' . $bucket, array( 'bucket' => $bucket ) );
			}
			if ( ! is_string( $css ) ) return self::error( 'cresco_ai_custom_css_invalid', 'Custom CSS values must be strings.', $path . '.' . $bucket );
			$sanitized = WebsiteBuilder::sanitize_custom_css( $css );
			if ( is_wp_error( $sanitized ) ) return $sanitized;
		}
		return true;
	}

	private static function validate_meta( $meta, $path ) {
		if ( ! is_array( $meta ) ) return self::error( 'cresco_ai_meta_invalid', 'Widget meta must be an object.', $path );
		foreach ( array_keys( $meta ) as $field ) {
			if ( ! in_array( $field, self::META_FIELDS, true ) ) return self::error( 'cresco_ai_meta_field', 'Widget meta contains an unsupported field.', $path . '.' . $field, array( 'field' => $field ) );
		}
		if ( isset( $meta['componentId'] ) && ( ! is_numeric( $meta['componentId'] ) || (int) $meta['componentId'] < 0 ) ) return self::error( 'cresco_ai_meta_value', 'componentId must be a non-negative integer.', $path . '.componentId' );
		foreach ( array( 'locked', 'hidden' ) as $field ) {
			if ( isset( $meta[ $field ] ) && ! is_bool( $meta[ $field ] ) && ! in_array( $meta[ $field ], array( 0, 1, '0', '1' ), true ) ) return self::error( 'cresco_ai_meta_value', $field . ' must be boolean.', $path . '.' . $field );
		}
		if ( isset( $meta['label'] ) && ! is_scalar( $meta['label'] ) ) return self::error( 'cresco_ai_meta_value', 'Widget label must be text.', $path . '.label' );
		return true;
	}

	private static function validate_prop_value( $value, $schema, $path ) {
		$kind = (string) ( $schema['type'] ?? 'string' );
		if ( 'enum' === $kind && ! in_array( $value, (array) ( $schema['values'] ?? array() ), true ) ) {
			return self::error( 'cresco_ai_prop_value', 'AI result contains an unsupported enum value.', $path, array( 'value' => $value, 'allowed' => array_values( (array) ( $schema['values'] ?? array() ) ) );
		}
		if ( 'int' === $kind ) {
			if ( ! is_numeric( $value ) || (int) $value != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
				return self::error( 'cresco_ai_prop_value', 'AI result requires an integer value.', $path );
			}
			$number = (int) $value;
			if ( isset( $schema['min'] ) && $number < (int) $schema['min'] ) return self::error( 'cresco_ai_prop_value', 'AI integer is below the contract minimum.', $path );
			if ( isset( $schema['max'] ) && $number > (int) $schema['max'] ) return self::error( 'cresco_ai_prop_value', 'AI integer is above the contract maximum.', $path );
		}
		if ( 'number' === $kind ) {
			if ( ! is_numeric( $value ) ) return self::error( 'cresco_ai_prop_value', 'AI result requires a numeric value.', $path );
			$number = (float) $value;
			if ( isset( $schema['min'] ) && $number < (float) $schema['min'] ) return self::error( 'cresco_ai_prop_value', 'AI number is below the contract minimum.', $path );
			if ( isset( $schema['max'] ) && $number > (float) $schema['max'] ) return self::error( 'cresco_ai_prop_value', 'AI number is above the contract maximum.', $path );
		}
		if ( 'bool' === $kind && ! is_bool( $value ) && ! in_array( $value, array( 0, 1, '0', '1' ), true ) ) return self::error( 'cresco_ai_prop_value', 'AI result requires a boolean value.', $path );
		if ( 'string_list' === $kind ) {
			if ( ! is_array( $value ) ) return self::error( 'cresco_ai_prop_value', 'AI result requires an array of strings.', $path );
			foreach ( $value as $item ) if ( ! is_scalar( $item ) ) return self::error( 'cresco_ai_prop_value', 'AI string list contains a non-scalar value.', $path );
		}
		if ( 'json' === $kind && ! is_array( $value ) ) return self::error( 'cresco_ai_prop_value', 'AI result requires structured JSON data.', $path );
		if ( in_array( $kind, array( 'string', 'text', 'richtext', 'url', 'css' ), true ) && ! is_scalar( $value ) ) return self::error( 'cresco_ai_prop_value', 'AI result requires a scalar property value.', $path );
		if ( 'css' === $kind && ! self::valid_css_value( $value ) ) return self::error( 'cresco_ai_prop_value', 'AI result contains an invalid CSS value.', $path );
		return true;
	}

	private static function valid_css_value( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value ) return true;
		return '' !== WebsiteBuilder::sanitize_css_value( $value );
	}

	private static function custom_css_contract( $type ) {
		$parts = array( 'root' => '&' );
		if ( 'button' === $type ) $parts['text'] = '& [data-cresco-part="text"]';
		if ( 'image' === $type ) {
			$parts['media']   = '& [data-cresco-part="media"]';
			$parts['caption'] = '& [data-cresco-part="caption"]';
		}
		if ( 'list' === $type ) $parts['item'] = '& [data-cresco-part="item"]';
		return array( 'allowed' => true, 'selector' => '&', 'parts' => $parts );
	}

	private static function error( $code, $message, $path, $extra = array() ) {
		return new WP_Error( $code, __( $message, 'cresco-canvas' ), array_merge( array( 'status' => 400, 'path' => $path ), (array) $extra ) );
	}
}
