<?php
/**
 * Canonical registry for Website Builder browser modules.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderModuleRegistry {
	private function __construct() {}

	public static function all() {
		return array(
			'bootstrap' => array(
				'label' => 'Bootstrap resilience', 'required' => true,
				'scripts' => array( array( 'handle' => 'cresco-canvas-website-builder-bootstrap', 'file' => 'build/website-builder-bootstrap.js' ) ), 'styles' => array(),
			),
			'core' => array(
				'label' => 'Cresco Studio core', 'required' => true,
				'scripts' => array(
					array( 'handle' => 'cresco-canvas-website-builder-document-store', 'file' => 'build/website-builder-document-store.js' ),
					array( 'handle' => 'cresco-canvas-website-builder', 'file' => 'build/website-builder-studio.js' ),
					array( 'handle' => 'cresco-canvas-website-builder-responsive-properties', 'file' => 'build/website-builder-responsive-properties.js' ),
					array( 'handle' => 'cresco-canvas-website-builder-ui-correction', 'file' => 'build/website-builder-ui-correction.js' ),
				),
				'styles' => array(
					array( 'handle' => 'cresco-canvas-website-builder', 'file' => 'assets/css/website-builder.css' ),
					array( 'handle' => 'cresco-canvas-website-builder-studio', 'file' => 'assets/css/website-builder-studio.css' ),
					array( 'handle' => 'cresco-canvas-website-builder-ui-correction', 'file' => 'assets/css/website-builder-ui-correction.css' ),
					array( 'handle' => 'cresco-canvas-website-builder-premium-polish', 'file' => 'assets/css/website-builder-premium-polish.css' ),
					array( 'handle' => 'cresco-canvas-website-builder-structure-v3', 'file' => 'assets/css/website-builder-structure-v3.css' ),
				),
			),
			'pointer-drag' => array(
				'label' => 'Canvas pointer drag', 'coreExtension' => true,
				'scripts' => array(
					array(
						'handle'   => 'cresco-canvas-website-builder-pointer-drag',
						'file'     => 'build/website-builder-pointer-drag.js',
						'register' => true,
						'deps'     => array( 'cresco-canvas-website-builder-responsive-properties' ),
					),
				),
				'styles' => array(),
			),
			'controls' => array(
				'label' => 'Controls compatibility (opt-in)', 'required' => false, 'transitional' => true, 'enabledDefault' => false,
				'scripts' => array( array( 'handle' => 'cresco-canvas-website-builder-controls', 'file' => 'build/website-builder-controls.js' ) ),
				'styles' => array( array( 'handle' => 'cresco-canvas-website-builder-controls', 'file' => 'assets/css/website-builder-controls.css' ) ),
			),
			'professional-ux' => array(
				'label' => 'Professional UX compatibility (opt-in)', 'required' => false, 'transitional' => true, 'enabledDefault' => false,
				'scripts' => array(
					array( 'handle' => 'cresco-canvas-website-builder-professional-ux', 'file' => 'build/website-builder-professional-ux.js' ),
					array( 'handle' => 'cresco-canvas-website-builder-preview-fit', 'file' => 'build/website-builder-preview-fit.js' ),
				),
				'styles' => array( array( 'handle' => 'cresco-canvas-website-builder-professional-ux', 'file' => 'assets/css/website-builder-professional-ux.css' ) ),
			),
			'architecture' => array(
				'label' => 'Architecture', 'required' => false, 'quarantinedDefault' => true,
				'scripts' => array( array( 'handle' => 'cresco-canvas-builder-architecture', 'file' => 'build/website-builder-architecture.js' ) ),
				'styles' => array( array( 'handle' => 'cresco-canvas-builder-architecture', 'file' => 'assets/css/website-builder-architecture.css' ) ),
			),
			'comprehensive-v3' => array(
				'label' => 'Comprehensive V3 compatibility (opt-in)', 'required' => false, 'transitional' => true, 'enabledDefault' => false,
				'scripts' => array( array( 'handle' => 'cresco-canvas-website-builder-comprehensive-v3', 'file' => 'build/website-builder-comprehensive-v3.js' ) ),
				'styles' => array( array( 'handle' => 'cresco-canvas-website-builder-comprehensive-v3', 'file' => 'assets/css/website-builder-comprehensive-v3.css' ) ),
			),
			'workflow' => array(
				'label' => 'Workflow compatibility (opt-in)', 'required' => false, 'transitional' => true, 'enabledDefault' => false,
				'scripts' => array( array( 'handle' => 'cresco-canvas-website-builder-workflow-extensions', 'file' => 'build/website-builder-workflow-extensions.js' ) ), 'styles' => array(),
			),
		);
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/** Determine the only module keys allowed to execute for this request. */
	public static function enabled_keys( WebsiteBuilderRuntimeContext $context ) {
		$all      = self::all();
		$required = array_keys( array_filter( $all, static function ( $module ) {
			return ! empty( $module['required'] ) || ! empty( $module['coreExtension'] );
		} ) );
		$mode     = $context->isolation_mode();

		if ( 'core' === $mode ) return $required;
		if ( 'controls' === $mode ) return array_values( array_unique( array_merge( $required, array( 'controls' ) ) ) );
		if ( 'professional-ux' === $mode ) return array_values( array_unique( array_merge( $required, array( 'controls', 'professional-ux' ) ) ) );
		if ( 'architecture' === $mode ) return array_values( array_unique( array_merge( $required, array( 'architecture' ) ) ) );
		if ( 'all' === $mode ) return array_keys( $all );

		$enabled = array();
		foreach ( $all as $key => $module ) {
			if ( array_key_exists( 'enabledDefault', $module ) && false === $module['enabledDefault'] ) continue;
			if ( ! empty( $module['quarantinedDefault'] ) && ! $context->architecture_debug_enabled() ) continue;
			$enabled[] = $key;
		}
		return $enabled;
	}

	public static function is_enabled( $key, WebsiteBuilderRuntimeContext $context ) {
		return in_array( $key, self::enabled_keys( $context ), true );
	}

	public static function asset_reports() {
		$output = array();
		foreach ( self::all() as $key => $module ) {
			$assets = array();
			foreach ( array_merge( $module['scripts'], $module['styles'] ) as $asset ) {
				$assets[] = WebsiteBuilderAsset::report( $asset['file'] );
			}
			$output[ $key ] = array(
				'label'              => $module['label'],
				'required'           => ! empty( $module['required'] ),
				'coreExtension'      => ! empty( $module['coreExtension'] ),
				'transitional'       => ! empty( $module['transitional'] ),
				'defaultEnabled'     => ! array_key_exists( 'enabledDefault', $module ) || false !== $module['enabledDefault'],
				'quarantinedDefault' => ! empty( $module['quarantinedDefault'] ),
				'assets'              => $assets,
			);
		}
		return $output;
	}
}
