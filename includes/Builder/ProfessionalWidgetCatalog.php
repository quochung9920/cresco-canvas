<?php
/**
 * Professional widget catalog expansion for Cresco Canvas.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProfessionalWidgetCatalog {
	/** Extend the canonical catalog without changing the saved session schema. */
	public static function extend( $widgets ) {
		$widgets = is_array( $widgets ) ? $widgets : array();
		$styles = array(
			'layout' => (array) ( $widgets['container']['style'] ?? array() ),
			'text'   => (array) ( $widgets['text']['style'] ?? array() ),
			'card'   => (array) ( $widgets['icon-box']['style'] ?? array() ),
			'media'  => (array) ( $widgets['image']['style'] ?? array() ),
		);
		if ( ! $styles['text'] ) $styles['text'] = $styles['layout'];
		if ( ! $styles['card'] ) $styles['card'] = $styles['layout'];
		if ( ! $styles['media'] ) $styles['media'] = $styles['layout'];

		$widgets = ProfessionalWidgetCatalogContent::extend( $widgets, $styles );
		$widgets = ProfessionalWidgetCatalogMedia::extend( $widgets, $styles );
		return ProfessionalWidgetCatalogInteractive::extend( $widgets, $styles );
	}

	public static function widget( $label, $category, $icon, $allows_children, $props, $style, $states = array( 'hover' ), $parts = array() ) {
		return array(
			'label' => $label, 'category' => $category, 'icon' => $icon,
			'allowsChildren' => (bool) $allows_children, 'props' => $props,
			'style' => array_values( array_unique( (array) $style ) ),
			'responsive' => true, 'states' => array_values( array_unique( (array) $states ) ),
			'parts' => $parts ?: array( 'root' => self::part( 'Root', '&' ) ),
			'description' => $label . ' professional widget.',
		);
	}

	public static function part( $label, $selector ) { return array( 'label' => $label, 'selector' => $selector ); }
	public static function schema( $type, $default, $label, $extra = array() ) { return array_merge( array( 'type' => $type, 'default' => $default, 'label' => $label ), $extra ); }
	public static function string( $default, $label, $extra = array() ) { return self::schema( 'string', $default, $label, $extra ); }
	public static function textarea( $default, $label, $extra = array() ) { return self::schema( 'text', $default, $label, array_merge( array( 'control' => 'textarea' ), $extra ) ); }
	public static function richtext( $default, $label, $extra = array() ) { return self::schema( 'richtext', $default, $label, array_merge( array( 'control' => 'textarea' ), $extra ) ); }
	public static function url( $default, $label, $extra = array() ) { return self::schema( 'url', $default, $label, $extra ); }
	public static function css( $default, $label, $extra = array() ) { return self::schema( 'css', $default, $label, $extra ); }
	public static function boolean( $default, $label, $extra = array() ) { return self::schema( 'bool', (bool) $default, $label, array_merge( array( 'control' => 'toggle' ), $extra ) ); }
	public static function integer( $min, $max, $default, $label, $extra = array() ) { return self::schema( 'int', $default, $label, array_merge( array( 'min' => $min, 'max' => $max, 'control' => 'number' ), $extra ) ); }
	public static function number( $min, $max, $default, $label, $extra = array() ) { return self::schema( 'number', $default, $label, array_merge( array( 'min' => $min, 'max' => $max, 'control' => 'number' ), $extra ) ); }
	public static function enum( $values, $default, $label, $extra = array() ) { return self::schema( 'enum', $default, $label, array_merge( array( 'values' => $values, 'control' => 'select' ), $extra ) ); }
	public static function string_list( $default, $label, $extra = array() ) { return self::schema( 'string_list', $default, $label, array_merge( array( 'control' => 'textarea' ), $extra ) ); }
	public static function json( $default, $label, $shape, $extra = array() ) { return self::schema( 'json', $default, $label, array_merge( array( 'control' => 'json', 'shape' => $shape ), $extra ) ); }

	private function __construct() {}
}
