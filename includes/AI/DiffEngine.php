<?php
/**
 * Structured, human-readable Cresco Session diff engine.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DiffEngine {
	public static function compare( $before, $after ) {
		$old   = self::flatten( $before['nodes'] ?? array() );
		$new   = self::flatten( $after['nodes'] ?? array() );
		$items = array();

		foreach ( $old as $id => $entry ) {
			if ( ! isset( $new[ $id ] ) ) {
				$items[] = self::item( 'removed', $entry['node'], 'node', $entry['node'], null );
				continue;
			}
			$next = $new[ $id ];
			if ( $entry['parentId'] !== $next['parentId'] || $entry['index'] !== $next['index'] ) {
				$items[] = self::item( 'moved', $next['node'], 'position', array( 'parentId' => $entry['parentId'], 'index' => $entry['index'] ), array( 'parentId' => $next['parentId'], 'index' => $next['index'] ) );
			}
			if ( ( $entry['node']['type'] ?? '' ) !== ( $next['node']['type'] ?? '' ) ) {
				$items[] = self::item( 'changed', $next['node'], 'type', $entry['node']['type'] ?? null, $next['node']['type'] ?? null );
			}
			foreach ( array( 'props', 'style', 'responsive', 'customCSS' ) as $group ) {
				self::diff_values( (array) ( $entry['node'][ $group ] ?? array() ), (array) ( $next['node'][ $group ] ?? array() ), $group, $next['node'], $items );
			}
		}
		foreach ( $new as $id => $entry ) {
			if ( ! isset( $old[ $id ] ) ) $items[] = self::item( 'inserted', $entry['node'], 'node', null, $entry['node'] );
		}

		$summary = array( 'changed' => 0, 'inserted' => 0, 'removed' => 0, 'moved' => 0, 'total' => count( $items ) );
		foreach ( $items as $item ) if ( isset( $summary[ $item['changeType'] ] ) ) $summary[ $item['changeType'] ]++;
		return array( 'summary' => $summary, 'items' => $items );
	}

	private static function flatten( $nodes, $parent_id = null, $output = array() ) {
		foreach ( array_values( (array) $nodes ) as $index => $node ) {
			$id = (string) ( $node['id'] ?? '' );
			if ( '' === $id ) continue;
			$output[ $id ] = array( 'node' => $node, 'parentId' => $parent_id, 'index' => $index );
			$output = self::flatten( $node['children'] ?? array(), $id, $output );
		}
		return $output;
	}

	private static function diff_values( $before, $after, $prefix, $node, &$items ) {
		$keys = array_values( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) );
		foreach ( $keys as $key ) {
			$old = array_key_exists( $key, $before ) ? $before[ $key ] : null;
			$new = array_key_exists( $key, $after ) ? $after[ $key ] : null;
			$field = $prefix . '.' . $key;
			if ( is_array( $old ) && is_array( $new ) ) {
				self::diff_values( $old, $new, $field, $node, $items );
			} elseif ( $old !== $new ) {
				$items[] = self::item( 'changed', $node, $field, $old, $new );
			}
		}
	}

	private static function item( $change_type, $node, $field, $before, $after ) {
		$type     = (string) ( $node['type'] ?? 'widget' );
		$contract = ContractRegistry::all();
		$label    = isset( $contract[ $type ] ) ? $contract[ $type ]['label'] : $type;
		return array(
			'changeType' => $change_type,
			'nodeId'     => (string) ( $node['id'] ?? '' ),
			'widgetType' => $type,
			'widgetLabel'=> $label,
			'field'      => $field,
			'before'     => $before,
			'after'      => $after,
		);
	}
}
