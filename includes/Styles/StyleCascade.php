<?php
/**
 * Deterministic style cascade with property provenance for Studio and renderer tooling.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StyleCascade {
	const BREAKPOINTS = array( 'wide', 'desktop', 'laptop', 'tablet', 'mobile' );
	const STATES      = array( 'normal', 'hover', 'focus', 'active' );
	const SOURCES     = array( 'token', 'global', 'component', 'local' );

	/**
	 * Resolve one flat Website Builder style property and explain where it came from.
	 *
	 * Each shared layer may contain base/responsive/states maps. The local layer is
	 * derived from the node's style/responsive/states maps. Responsive values are
	 * sparse: inherited values are never copied into narrower breakpoints.
	 */
	public static function resolve( $node, $property, $breakpoint = 'wide', $state = 'normal', $shared = array() ) {
		if ( ! is_array( $node ) ) return new WP_Error( 'cresco_style_node', __( 'Style resolution requires a Cresco node.', 'cresco-canvas' ) );
		$property   = sanitize_key( (string) $property );
		$breakpoint = sanitize_key( (string) $breakpoint );
		$state      = sanitize_key( (string) $state );
		if ( '' === $property ) return new WP_Error( 'cresco_style_property', __( 'Style resolution requires a property name.', 'cresco-canvas' ) );
		if ( ! in_array( $breakpoint, self::BREAKPOINTS, true ) ) return new WP_Error( 'cresco_style_breakpoint', __( 'Unsupported Cresco breakpoint.', 'cresco-canvas' ) );
		if ( ! in_array( $state, self::STATES, true ) ) return new WP_Error( 'cresco_style_state', __( 'Unsupported Cresco interaction state.', 'cresco-canvas' ) );

		$layers = array();
		foreach ( array( 'token', 'global', 'component' ) as $source ) {
			$layers[ $source ] = self::normalize_layer( isset( $shared[ $source ] ) && is_array( $shared[ $source ] ) ? $shared[ $source ] : array() );
		}
		$layers['local'] = self::normalize_layer(
			array(
				'base'       => (array) ( $node['style'] ?? array() ),
				'responsive' => (array) ( $node['responsive'] ?? array() ),
				'states'     => (array) ( $node['states'] ?? array() ),
			)
		);

		$winner = null;
		$chain  = array();
		foreach ( self::SOURCES as $source ) {
			$layer = $layers[ $source ];
			if ( array_key_exists( $property, $layer['base'] ) && null !== $layer['base'][ $property ] && '' !== $layer['base'][ $property ] ) {
				$winner = self::entry( $layer['base'][ $property ], $source, 'wide', 'normal', false );
				$chain[] = $winner;
			}
			foreach ( self::breakpoints_through( $breakpoint ) as $device ) {
				if ( 'wide' === $device ) continue;
				$map = (array) ( $layer['responsive'][ $device ] ?? array() );
				if ( array_key_exists( $property, $map ) && null !== $map[ $property ] && '' !== $map[ $property ] ) {
					$winner = self::entry( $map[ $property ], $source, $device, 'normal', $device !== $breakpoint );
					$chain[] = $winner;
				}
			}
		}

		if ( 'normal' !== $state ) {
			foreach ( self::SOURCES as $source ) {
				$layer = $layers[ $source ];
				$state_map = (array) ( $layer['states'][ $state ] ?? array() );
				if ( array_key_exists( $property, $state_map ) && null !== $state_map[ $property ] && '' !== $state_map[ $property ] ) {
					$winner = self::entry( $state_map[ $property ], $source, $breakpoint, $state, false );
					$chain[] = $winner;
				}
			}
		}

		$previous = self::previous_explicit_breakpoint( $layers['local'], $property, $breakpoint );
		return array(
			'value'              => $winner['value'] ?? null,
			'source'             => $winner['source'] ?? 'default',
			'breakpoint'         => $winner['breakpoint'] ?? 'wide',
			'state'              => $winner['state'] ?? 'normal',
			'inherited'          => $winner ? ( 'normal' === $state && $winner['breakpoint'] !== $breakpoint ) : true,
			'explicitAtCurrent'  => self::explicit_at_current( $layers['local'], $property, $breakpoint, $state ),
			'previousBreakpoint' => $previous,
			'chain'              => $chain,
		);
	}

	/** Return a validated first-class fluid length without exposing clamp syntax to callers. */
	public static function fluid( $minimum, $preferred, $maximum ) {
		$minimum   = self::fluid_part( $minimum, false );
		$preferred = self::fluid_part( $preferred, true );
		$maximum   = self::fluid_part( $maximum, false );
		if ( null === $minimum || null === $preferred || null === $maximum ) {
			return new WP_Error( 'cresco_style_fluid_value', __( 'Fluid values require safe CSS lengths for minimum, preferred and maximum.', 'cresco-canvas' ) );
		}
		return 'clamp(' . $minimum . ', ' . $preferred . ', ' . $maximum . ')';
	}

	private static function normalize_layer( $layer ) {
		$base = isset( $layer['base'] ) && is_array( $layer['base'] ) ? $layer['base'] : array();
		$responsive = isset( $layer['responsive'] ) && is_array( $layer['responsive'] ) ? $layer['responsive'] : array();
		$states = isset( $layer['states'] ) && is_array( $layer['states'] ) ? $layer['states'] : array();
		return array( 'base' => $base, 'responsive' => $responsive, 'states' => $states );
	}

	private static function breakpoints_through( $breakpoint ) {
		$index = array_search( $breakpoint, self::BREAKPOINTS, true );
		return array_slice( self::BREAKPOINTS, 0, false === $index ? 1 : $index + 1 );
	}

	private static function previous_explicit_breakpoint( $layer, $property, $breakpoint ) {
		$devices = self::breakpoints_through( $breakpoint );
		array_pop( $devices );
		for ( $index = count( $devices ) - 1; $index >= 0; $index-- ) {
			$device = $devices[ $index ];
			if ( 'wide' === $device ) {
				if ( array_key_exists( $property, $layer['base'] ) ) return 'wide';
				continue;
			}
			if ( array_key_exists( $property, (array) ( $layer['responsive'][ $device ] ?? array() ) ) ) return $device;
		}
		return null;
	}

	private static function explicit_at_current( $layer, $property, $breakpoint, $state ) {
		if ( 'normal' !== $state ) return array_key_exists( $property, (array) ( $layer['states'][ $state ] ?? array() ) );
		if ( 'wide' === $breakpoint ) return array_key_exists( $property, $layer['base'] );
		return array_key_exists( $property, (array) ( $layer['responsive'][ $breakpoint ] ?? array() ) );
	}

	private static function entry( $value, $source, $breakpoint, $state, $inherited ) {
		return array( 'value' => $value, 'source' => $source, 'breakpoint' => $breakpoint, 'state' => $state, 'inherited' => (bool) $inherited );
	}

	private static function fluid_part( $value, $allow_viewport ) {
		$value = trim( (string) $value );
		$units = $allow_viewport ? '(?:px|rem|em|%|vw|vh|vmin|vmax|ch)' : '(?:px|rem|em|%|vw|vh|vmin|vmax|ch)';
		return preg_match( '/^(?:0|-?\d+(?:\.\d+)?' . $units . ')$/', $value ) ? $value : null;
	}

	private function __construct() {}
}
