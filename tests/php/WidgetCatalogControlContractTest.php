<?php
/** WidgetCatalog -> Studio Inspector contract regression tests. */

use CrescoCanvas\Builder\WidgetCatalog;
use PHPUnit\Framework\TestCase;

final class WidgetCatalogControlContractTest extends TestCase {
	public function test_every_widget_schema_is_renderable_by_the_canonical_inspector(): void {
		$catalog = WidgetCatalog::all();
		$style_properties = WidgetCatalog::style_properties();
		$allowed_types = array( 'string', 'text', 'richtext', 'url', 'css', 'bool', 'int', 'number', 'enum', 'string_list', 'json' );
		$allowed_controls = array( '', 'toggle', 'number', 'select', 'textarea', 'json', 'link', 'media', 'icon', 'repeater', 'option-select', 'email', 'richtext' );
		$allowed_states = array( 'hover', 'focus', 'active' );
		self::assertNotEmpty( $catalog );
		foreach ( $catalog as $widget_type => $definition ) {
			self::assertIsArray( $definition, "Widget {$widget_type} must be an array definition." );
			self::assertNotSame( '', (string) ( $definition['label'] ?? '' ), "Widget {$widget_type} requires a label." );
			self::assertIsArray( $definition['props'] ?? array(), "Widget {$widget_type} props must be an array." );
			foreach ( array_values( array_unique( (array) ( $definition['style'] ?? array() ) ) ) as $style_key ) self::assertContains( $style_key, $style_properties, "Widget {$widget_type} exposes unsupported style {$style_key}." );
			foreach ( array_values( array_unique( (array) ( $definition['states'] ?? array() ) ) ) as $state ) self::assertContains( $state, $allowed_states, "Widget {$widget_type} exposes unsupported state {$state}." );
			$props = (array) ( $definition['props'] ?? array() );
			foreach ( $props as $prop_key => $schema ) {
				self::assertIsArray( $schema, "{$widget_type}.{$prop_key} schema must be an array." );
				$type = (string) ( $schema['type'] ?? '' );
				self::assertContains( $type, $allowed_types, "{$widget_type}.{$prop_key} uses unsupported type {$type}." );
				self::assertArrayHasKey( 'default', $schema, "{$widget_type}.{$prop_key} requires a default." );
				self::assertNotSame( '', (string) ( $schema['label'] ?? '' ), "{$widget_type}.{$prop_key} requires a label." );
				$control = (string) ( $schema['control'] ?? '' );
				self::assertContains( $control, $allowed_controls, "{$widget_type}.{$prop_key} uses unsupported control {$control}." );
				if ( 'enum' === $type ) self::assertNotEmpty( $schema['values'] ?? array(), "{$widget_type}.{$prop_key} enum needs values." );
				if ( 'option-select' === $control ) self::assertNotSame( '', (string) ( $schema['optionsSource'] ?? '' ), "{$widget_type}.{$prop_key} option-select needs optionsSource." );
				if ( 'repeater' === $control ) self::assertContains( $type, array( 'json', 'string_list' ), "{$widget_type}.{$prop_key} repeater must persist json or string_list." );
				if ( 'media' === $control ) self::assertContains( $type, array( 'url', 'string' ), "{$widget_type}.{$prop_key} media control requires URL/string persistence." );
				if ( isset( $schema['styleKey'] ) && '' !== (string) $schema['styleKey'] ) self::assertContains( (string) $schema['styleKey'], $style_properties, "{$widget_type}.{$prop_key} maps to unsupported styleKey {$schema['styleKey']}." );
				if ( isset( $schema['condition']['key'] ) ) self::assertArrayHasKey( (string) $schema['condition']['key'], $props, "{$widget_type}.{$prop_key} condition references a missing prop." );
			}
		}
	}
}
