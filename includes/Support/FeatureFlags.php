<?php
/**
 * Controlled feature flags for isolating experimental and overlapping runtime layers.
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
	 * Default feature flags.
	 *
	 * Existing production behavior stays enabled by default. The builder-layer
	 * flags exist so support and release engineering can isolate overlapping
	 * compatibility/presentation/architecture services without editing code.
	 * New experimental capabilities remain opt-in.
	 *
	 * @return bool[]
	 */
	public static function defaults() {
		return array(
			'experimentalEditorTools'   => false,
			'builderCompatibilityLayer' => true,
			'builderRendererParity'     => true,
			'builderPresentationLayers' => true,
			'builderArchitectureV2'     => true,
			'builderWorkflowExtensions' => true,
			'builderCorePlatformV2'     => true,
		);
	}

	/**
	 * Get normalized feature flags.
	 *
	 * Unknown stored keys are intentionally ignored so a removed flag cannot
	 * resurrect a retired runtime after an upgrade.
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
		$filtered = (array) apply_filters( 'cresco_canvas_feature_flags', $flags );
		foreach ( self::defaults() as $name => $default ) {
			$flags[ $name ] = array_key_exists( $name, $filtered ) ? rest_sanitize_boolean( $filtered[ $name ] ) : $default;
		}
		return $flags;
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

	/** Return true only for flags declared by this release. */
	public static function is_known( $name ) {
		return array_key_exists( (string) $name, self::defaults() );
	}
}
