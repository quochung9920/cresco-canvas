<?php
/**
 * Applies validated cresco-patch/v1 operations to an in-memory Session clone.
 *
 * This class never persists WordPress data.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PatchApplier {
	public static function apply( $session, $patch ) {
		$next   = json_decode( json_encode( $session ), true );
		$id_map = array();
		foreach ( array_values( (array) ( $patch['operations'] ?? array() ) ) as $index => $operation ) {
			$operation = self::rewrite_operation_ids( $operation, $id_map );
			$result = self::apply_operation( $next, $operation, $index );
			if ( is_wp_error( $result ) ) return $result;
			$next = $result['session'];
			if ( ! empty( $result['idMap'] ) ) $id_map = array_merge( $id_map, $result['idMap'] );
		}
		return array( 'session' => $next, 'idMap' => $id_map );
	}

	/** Rewrite operation references when an earlier insert caused an ID collision. */
	public static function rewrite_operation_ids( $operation, $id_map ) {
		$operation = is_array( $operation ) ? $operation : array();
		foreach ( array( 'nodeId', 'parentId' ) as $key ) {
			if ( ! isset( $operation[ $key ] ) || null === $operation[ $key ] ) continue;
			$id = (string) $operation[ $key ];
			if ( isset( $id_map[ $id ] ) ) $operation[ $key ] = $id_map[ $id ];
		}
		return $operation;
	}

	private static function apply_operation( $session, $operation, $index ) {
		$op = (string) ( $operation['op'] ?? '' );
		if ( in_array( $op, array( 'setProps', 'setStyle', 'setResponsive', 'setStates', 'setCustomCSS' ), true ) ) {
			$node_id = (string) ( $operation['nodeId'] ?? '' );
			$key     = array(
				'setProps'      => 'props',
				'setStyle'      => 'style',
				'setResponsive' => 'responsive',
				'setStates'     => 'states',
				'setCustomCSS'  => 'customCSS',
			)[ $op ];
			$value   = isset( $operation[ $key ] ) && is_array( $operation[ $key ] ) ? $operation[ $key ] : array();
			$found   = false;
			$session['nodes'] = self::map_nodes( $session['nodes'] ?? array(), $node_id, static function ( $node ) use ( $op, $key, $value, &$found ) {
				$found = true;
				$node[ $key ] = in_array( $op, array( 'setResponsive', 'setStates' ), true )
					? array_replace_recursive( (array) ( $node[ $key ] ?? array() ), $value )
					: array_replace( (array) ( $node[ $key ] ?? array() ), $value );
				return $node;
			} );
			return $found ? array( 'session' => $session, 'idMap' => array() ) : self::not_found( $node_id, $index );
		}

		if ( 'insertNode' === $op ) {
			$node      = (array) ( $operation['node'] ?? array() );
			$parent_id = isset( $operation['parentId'] ) && null !== $operation['parentId'] ? (string) $operation['parentId'] : null;
			$position  = isset( $operation['index'] ) ? max( 0, (int) $operation['index'] ) : null;
			$reserved  = ScopeResolver::collect_ids( $session['nodes'] ?? array() );
			$mapped    = IdRemapper::remap_subtree( $node, $reserved );
			$node      = $mapped['node'];
			if ( null === $parent_id ) {
				$session['nodes'] = self::insert_at( (array) ( $session['nodes'] ?? array() ), $node, $position );
				return array( 'session' => $session, 'idMap' => $mapped['idMap'] );
			}
			$found = false;
			$session['nodes'] = self::map_nodes( $session['nodes'] ?? array(), $parent_id, static function ( $parent ) use ( $node, $position, &$found ) {
				$found = true;
				$parent['children'] = PatchApplier::insert_at( (array) ( $parent['children'] ?? array() ), $node, $position );
				return $parent;
			} );
			return $found ? array( 'session' => $session, 'idMap' => $mapped['idMap'] ) : self::not_found( $parent_id, $index );
		}

		if ( 'removeNode' === $op ) {
			$node_id = (string) ( $operation['nodeId'] ?? '' );
			$removed = false;
			$session['nodes'] = self::remove_node( $session['nodes'] ?? array(), $node_id, $removed );
			return $removed ? array( 'session' => $session, 'idMap' => array() ) : self::not_found( $node_id, $index );
		}

		if ( 'moveNode' === $op ) {
			$node_id   = (string) ( $operation['nodeId'] ?? '' );
			$parent_id = isset( $operation['parentId'] ) && null !== $operation['parentId'] ? (string) $operation['parentId'] : null;
			$position  = isset( $operation['index'] ) ? max( 0, (int) $operation['index'] ) : null;
			$node      = ScopeResolver::find_node( $session['nodes'] ?? array(), $node_id );
			if ( ! $node ) return self::not_found( $node_id, $index );
			if ( null !== $parent_id && in_array( $parent_id, ScopeResolver::collect_ids( array( $node ) ), true ) ) {
				return new WP_Error( 'cresco_ai_move_cycle', __( 'A node cannot be moved inside itself or one of its descendants.', 'cresco-canvas' ), array( 'status' => 400, 'operationIndex' => $index ) );
			}
			$removed = false;
			$session['nodes'] = self::remove_node( $session['nodes'] ?? array(), $node_id, $removed );
			if ( null === $parent_id ) {
				$session['nodes'] = self::insert_at( $session['nodes'], $node, $position );
				return array( 'session' => $session, 'idMap' => array() );
			}
			$found = false;
			$session['nodes'] = self::map_nodes( $session['nodes'], $parent_id, static function ( $parent ) use ( $node, $position, &$found ) {
				$found = true;
				$parent['children'] = PatchApplier::insert_at( (array) ( $parent['children'] ?? array() ), $node, $position );
				return $parent;
			} );
			return $found ? array( 'session' => $session, 'idMap' => array() ) : self::not_found( $parent_id, $index );
		}

		if ( 'replaceSubtree' === $op ) {
			$node_id = (string) ( $operation['nodeId'] ?? '' );
			$old     = ScopeResolver::find_node( $session['nodes'] ?? array(), $node_id );
			if ( ! $old ) return self::not_found( $node_id, $index );
			$old_ids  = ScopeResolver::collect_ids( array( $old ) );
			$reserved = array_values( array_diff( ScopeResolver::collect_ids( $session['nodes'] ?? array() ), $old_ids ) );
			$mapped   = IdRemapper::remap_subtree( (array) ( $operation['node'] ?? array() ), $reserved, $node_id );
			$found    = false;
			$session['nodes'] = self::map_nodes( $session['nodes'] ?? array(), $node_id, static function ( $node ) use ( $mapped, &$found ) {
				unset( $node );
				$found = true;
				return $mapped['node'];
			} );
			return $found ? array( 'session' => $session, 'idMap' => $mapped['idMap'] ) : self::not_found( $node_id, $index );
		}

		return new WP_Error( 'cresco_ai_patch_operation', __( 'Unsupported Cresco Patch operation.', 'cresco-canvas' ), array( 'status' => 400, 'operationIndex' => $index, 'operation' => $op ) );
	}

	private static function map_nodes( $nodes, $id, $mapper ) {
		$output = array();
		foreach ( (array) $nodes as $node ) {
			if ( (string) ( $node['id'] ?? '' ) === $id ) {
				$output[] = $mapper( $node );
				continue;
			}
			$node['children'] = self::map_nodes( $node['children'] ?? array(), $id, $mapper );
			$output[] = $node;
		}
		return $output;
	}

	private static function remove_node( $nodes, $id, &$removed ) {
		$output = array();
		foreach ( (array) $nodes as $node ) {
			if ( (string) ( $node['id'] ?? '' ) === $id ) {
				$removed = true;
				continue;
			}
			$node['children'] = self::remove_node( $node['children'] ?? array(), $id, $removed );
			$output[] = $node;
		}
		return $output;
	}

	public static function insert_at( $nodes, $node, $index = null ) {
		$nodes = array_values( (array) $nodes );
		if ( null === $index || $index >= count( $nodes ) ) {
			$nodes[] = $node;
			return $nodes;
		}
		array_splice( $nodes, max( 0, $index ), 0, array( $node ) );
		return $nodes;
	}

	private static function not_found( $node_id, $index ) {
		return new WP_Error( 'cresco_ai_patch_node_not_found', __( 'A Cresco Patch operation references a node that does not exist.', 'cresco-canvas' ), array( 'status' => 400, 'nodeId' => $node_id, 'operationIndex' => $index ) );
	}
}
