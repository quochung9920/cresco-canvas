<?php
/**
 * Module capability registry for sustainable feature discovery.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ModuleRegistry {
	public static function all() {
		$modules = array(
			array( 'id' => 'forms', 'label' => 'Forms', 'active' => post_type_exists( 'cresco_submission' ) ),
			array( 'id' => 'theme', 'label' => 'Theme Builder', 'active' => post_type_exists( 'cresco_template' ) ),
			array( 'id' => 'loop', 'label' => 'Loop / Dynamic Data', 'active' => true ),
			array( 'id' => 'components', 'label' => 'Components', 'active' => post_type_exists( 'cresco_component' ) ),
			array( 'id' => 'woocommerce', 'label' => 'WooCommerce', 'active' => class_exists( '\\WooCommerce' ) || defined( 'WC_VERSION' ) || function_exists( 'WC' ) ),
			array( 'id' => 'ai', 'label' => 'Scoped AI', 'active' => true ),
		);
		$modules = apply_filters( 'cresco_canvas_module_registry', $modules );
		return is_array( $modules ) ? array_values( $modules ) : array();
	}
}
