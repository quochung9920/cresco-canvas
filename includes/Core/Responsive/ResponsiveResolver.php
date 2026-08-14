<?php
/**
 * Canonical responsive inheritance and breakpoint resolver.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Responsive;

use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ResponsiveResolver {
	const DEVICES = array( 'wide', 'desktop', 'laptop', 'tablet', 'mobile' );
	const OVERRIDE_DEVICES = array( 'desktop', 'laptop', 'tablet', 'mobile' );

	/** Return normalized breakpoint starts. */
	public static function breakpoints( $settings = null ) {
		if ( null === $settings ) $settings = GlobalStyles::get_settings();
		$raw = is_array( $settings ) ? (array) ( $settings['breakpoints'] ?? array() ) : array();
		$mobile  = max( 0, absint( $raw['mobile'] ?? 0 ) );
		$tablet  = max( $mobile + 1, absint( $raw['tablet'] ?? 768 ) );
		$laptop  = max( $tablet + 1, absint( $raw['laptop'] ?? 1025 ) );
		$desktop = max( $laptop + 1, absint( $raw['desktop'] ?? 1440 ) );
		$wide    = max( $desktop + 1, absint( $raw['wide'] ?? 1920 ) );
		return array(
			'mobile'  => $mobile,
			'tablet'  => $tablet,
			'laptop'  => $laptop,
			'desktop' => $desktop,
			'wide'    => $wide,
		);
	}

	/** Return max-width boundaries used by the downward-inheriting cascade. */
	public static function max_widths( $settings = null ) {
		$bp = self::breakpoints( $settings );
		return array(
			'desktop' => $bp['wide'] - 1,
			'laptop'  => $bp['desktop'] - 1,
			'tablet'  => $bp['laptop'] - 1,
			'mobile'  => $bp['tablet'] - 1,
		);
	}

	/** Wrap CSS in the canonical range for one responsive override bucket. */
	public static function wrap( $device, $css, $settings = null ) {
		$device = sanitize_key( (string) $device );
		$css = (string) $css;
		$max = self::max_widths( $settings );
		if ( '' === $css || ! isset( $max[ $device ] ) ) return '';
		return '@media (max-width:' . max( 0, (int) $max[ $device ] ) . 'px){' . $css . '}';
	}

	/** Return the responsive buckets inherited by one viewport, in merge order. */
	public static function cascade_for( $device ) {
		$device = sanitize_key( (string) $device );
		$map = array(
			'wide'    => array(),
			'desktop' => array( 'desktop' ),
			'laptop'  => array( 'desktop', 'laptop' ),
			'tablet'  => array( 'desktop', 'laptop', 'tablet' ),
			'mobile'  => array( 'desktop', 'laptop', 'tablet', 'mobile' ),
		);
		return $map[ $device ] ?? array();
	}

	/** Merge a base style with inherited overrides for one viewport. */
	public static function effective_style( $base, $responsive, $device ) {
		$style = is_array( $base ) ? $base : array();
		$responsive = is_array( $responsive ) ? $responsive : array();
		foreach ( self::cascade_for( $device ) as $bucket ) {
			if ( isset( $responsive[ $bucket ] ) && is_array( $responsive[ $bucket ] ) ) {
				$style = array_merge( $style, $responsive[ $bucket ] );
			}
		}
		return $style;
	}

	/** Public contract consumed by Studio, Inspector and AI tooling. */
	public static function manifest( $settings = null ) {
		$starts = self::breakpoints( $settings );
		return array(
			'schema'      => 'cresco-responsive/v2',
			'devices'     => self::DEVICES,
			'baseDevice'  => 'wide',
			'starts'      => $starts,
			'maxWidths'   => self::max_widths( $settings ),
			'cascade'     => array(
				'wide'    => self::cascade_for( 'wide' ),
				'desktop' => self::cascade_for( 'desktop' ),
				'laptop'  => self::cascade_for( 'laptop' ),
				'tablet'  => self::cascade_for( 'tablet' ),
				'mobile'  => self::cascade_for( 'mobile' ),
			),
			'previewWidths' => array(
				'wide'    => max( 1920, $starts['wide'] ),
				'desktop' => min( 1440, $starts['wide'] - 1 ),
				'laptop'  => min( 1366, $starts['desktop'] - 1 ),
				'tablet'  => min( 768, $starts['laptop'] - 1 ),
				'mobile'  => min( 390, $starts['tablet'] - 1 ),
			),
		);
	}

	private function __construct() {}
}
