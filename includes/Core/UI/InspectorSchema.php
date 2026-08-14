<?php
/**
 * Schema-driven Inspector manifest derived from the canonical WidgetCatalog.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\UI;

use CrescoCanvas\Builder\WidgetCatalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InspectorSchema {
	/** Return one normalized Inspector contract for every registered widget. */
	public static function manifest() {
		$out = array();
		foreach ( WidgetCatalog::all() as $type => $widget ) {
			$tabs = array(
				'content'  => array(),
				'layout'   => array(),
				'style'    => array(),
				'advanced' => array(),
			);
			foreach ( (array) ( $widget['props'] ?? array() ) as $key => $control ) {
				$panel = sanitize_key( (string) ( $control['panel'] ?? 'content' ) );
				if ( ! isset( $tabs[ $panel ] ) ) $panel = 'content';
				$group = sanitize_text_field( (string) ( $control['group'] ?? ucfirst( $panel ) ) );
				if ( '' === $group ) $group = ucfirst( $panel );
				if ( ! isset( $tabs[ $panel ][ $group ] ) ) $tabs[ $panel ][ $group ] = array();
				$tabs[ $panel ][ $group ][] = array_merge(
					array(
						'key'   => (string) $key,
						'label' => sanitize_text_field( (string) ( $control['label'] ?? $key ) ),
					),
					(array) $control
				);
			}

			$tabs['style']['Parts'] = array_values(
				array_map(
					static function ( $key, $part ) {
						return array(
							'key'      => (string) $key,
							'label'    => sanitize_text_field( (string) ( $part['label'] ?? $key ) ),
							'selector' => (string) ( $part['selector'] ?? '&' ),
						);
					},
					array_keys( (array) ( $widget['parts'] ?? array() ) ),
					array_values( (array) ( $widget['parts'] ?? array() ) )
				)
			);
			$tabs['style']['States'] = array_values( array_map( 'sanitize_key', (array) ( $widget['states'] ?? array() ) ) );
			$tabs['style']['Properties'] = array_values( array_map( static function ( $property ) { return (string) $property; }, (array) ( $widget['style'] ?? array() ) ) );
			$tabs['advanced']['Document'] = array(
				array( 'key' => 'customCSS', 'label' => 'Custom CSS', 'type' => 'scoped_css', 'responsive' => true ),
				array( 'key' => 'meta.hidden', 'label' => 'Hidden', 'type' => 'bool' ),
				array( 'key' => 'meta.locked', 'label' => 'Locked', 'type' => 'bool' ),
			);

			$out[ $type ] = array(
				'label'          => sanitize_text_field( (string) ( $widget['label'] ?? $type ) ),
				'category'       => sanitize_key( (string) ( $widget['category'] ?? 'content' ) ),
				'allowsChildren' => ! empty( $widget['allowsChildren'] ),
				'responsive'     => ! empty( $widget['responsive'] ),
				'tabs'           => $tabs,
			);
		}
		return array( 'schema' => 'cresco-inspector/v2', 'widgets' => $out );
	}

	private function __construct() {}
}
