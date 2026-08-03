<?php
/**
 * Experimental feature flags.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FeatureFlags {
	const OPTION_NAME = 'cresco_canvas_feature_flags';

	/**
	 * Default feature flags. Experimental features are opt-in.
	 *
	 * @return bool[]
	 */
	public static function defaults() {
		return array(
			'experimentalEditorTools' => false,
		);
	}

	/**
	 * Get normalized feature flags.
	 *
	 * @return bool[]
	 */
	public static function all() {
		$stored = (array) get_option( self::OPTION_NAME, array() );
		$flags  = array();

		foreach ( self::defaults() as $name => $default ) {
			$flags[ $name ] = isset( $stored[ $name ] ) ? rest_sanitize_boolean( $stored[ $name ] ) : $default;
		}

		/**
		 * Filter Cresco Canvas feature flags for controlled development builds.
		 *
		 * @param bool[] $flags Normalized flags.
		 */
		return (array) apply_filters( 'cresco_canvas_feature_flags', $flags );
	}

	/**
	 * Determine whether a known flag is enabled.
	 *
	 * @param string $name Flag name.
	 * @return bool
	 */
	public static function is_enabled( $name ) {
		$flags = self::all();
		return isset( $flags[ $name ] ) && true === $flags[ $name ];
	}
}
