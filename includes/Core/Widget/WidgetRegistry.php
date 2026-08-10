<?php
/**
 * Widget registry boundary used by Core and future feature modules.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Widget;

use CrescoCanvas\Builder\WidgetCatalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WidgetRegistry {
	/** Return registered widget contracts, preserving the existing catalog as the compatibility source. */
	public static function all() {
		$widgets = WidgetCatalog::all();
		$widgets = apply_filters( 'cresco_canvas_widget_registry', $widgets );
		return is_array( $widgets ) ? $widgets : array();
	}

	public static function get( $type ) {
		$type = sanitize_key( (string) $type );
		$all = self::all();
		return isset( $all[ $type ] ) && is_array( $all[ $type ] ) ? $all[ $type ] : null;
	}

	public static function types() {
		return array_keys( self::all() );
	}
}
