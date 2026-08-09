<?php
/**
 * Collision-safe Cresco node ID remapping for AI-authored inserts/replacements.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IdRemapper {
	/** Remap duplicate IDs while preserving non-conflicting stable IDs. */
	public static function remap_subtree( $node, $reserved_ids, $forced_root_id = null ) {
		$reserved = array_fill_keys( array_map( 'strval', (array) $reserved_ids ), true );
		$id_map   = array();
		$next     = self::walk( $node, $reserved, $id_map, true, $forced_root_id );
		return array( 'node' => $next, 'idMap' => $id_map );
	}

	private static function walk( $node, &$reserved, &$id_map, $is_root, $forced_root_id ) {
		$node   = is_array( $node ) ? $node : array();
		$old_id = (string) ( $node['id'] ?? '' );
		$type   = sanitize_key( (string) ( $node['type'] ?? 'widget' ) ) ?: 'widget';
		if ( $is_root && null !== $forced_root_id ) {
			$new_id = (string) $forced_root_id;
		} else {
			$base    = self::safe_id( $old_id ?: $type . '-ai' );
			$new_id  = $base;
			$attempt = 1;
			while ( isset( $reserved[ $new_id ] ) ) {
				$new_id = $base . '-ai' . ( $attempt > 1 ? '-' . $attempt : '' );
				$attempt++;
			}
		}
		if ( '' !== $old_id && $old_id !== $new_id ) $id_map[ $old_id ] = $new_id;
		$node['id']       = $new_id;
		$reserved[ $new_id ] = true;
		$children         = array();
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) {
			$children[] = self::walk( $child, $reserved, $id_map, false, null );
		}
		$node['children'] = $children;
		return $node;
	}

	private static function safe_id( $id ) {
		$id = strtolower( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', (string) $id ) );
		$id = trim( $id, '-' );
		return '' !== $id ? substr( $id, 0, 80 ) : 'widget-ai';
	}
}
