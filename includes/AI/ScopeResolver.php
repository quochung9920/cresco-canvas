<?php
/**
 * Resolves page, subtree, widget, selection, and multi-subtree export scopes.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopeResolver {
	const SCOPES = array( 'page', 'subtree', 'widget', 'selection', 'selection-subtrees' );

	public static function resolve( $session, $scope, $target = array() ) {
		$scope = sanitize_key( (string) $scope );
		if ( ! in_array( $scope, self::SCOPES, true ) ) {
			return new WP_Error( 'cresco_ai_scope', __( 'Unsupported AI export scope.', 'cresco-canvas' ), array( 'status' => 400, 'scope' => $scope ) );
		}
		$nodes      = (array) ( $session['nodes'] ?? array() );
		$parent_map = self::parent_map( $nodes );

		if ( 'page' === $scope ) {
			return array(
				'target'        => array( 'scope' => 'page', 'nodeId' => null, 'type' => 'page' ),
				'content'       => array( 'session' => $session ),
				'nodeIds'       => self::collect_ids( $nodes ),
				'requiredTypes' => self::collect_types( $nodes ),
			);
		}

		if ( in_array( $scope, array( 'selection', 'selection-subtrees' ), true ) ) {
			$ids = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $target['nodeIds'] ?? array() ) ) ) ) );
			if ( ! $ids ) {
				return new WP_Error( 'cresco_ai_selection_empty', __( 'Selection export requires at least one node ID.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}
			foreach ( $ids as $id ) if ( ! self::find_node( $nodes, $id ) ) return self::not_found( $id );

			// When entire subtrees are requested, collapse overlapping roots. If a
			// selected ancestor already contains another selected ID, exporting both
			// would duplicate the same document nodes and make AI patches ambiguous.
			if ( 'selection-subtrees' === $scope ) {
				$id_set = array_fill_keys( $ids, true );
				$ids    = array_values(
					array_filter(
						$ids,
						static function ( $id ) use ( $parent_map, $id_set ) {
							$cursor = $parent_map[ $id ]['parentId'] ?? null;
							while ( $cursor && isset( $parent_map[ $cursor ] ) ) {
								if ( isset( $id_set[ $cursor ] ) ) return false;
								$cursor = $parent_map[ $cursor ]['parentId'];
							}
							return true;
						}
					)
				);
			}

			$selected = array();
			$types    = array();
			$node_ids = array();
			$ancestry = array();
			foreach ( $ids as $id ) {
				$node = self::find_node( $nodes, $id );
				if ( 'selection' === $scope ) {
					$selected_node             = $node;
					$selected_node['children'] = array();
					$selected[]                = $selected_node;
					$node_ids[]                = $id;
					$types[]                   = (string) $node['type'];
				} else {
					$selected[] = $node;
					$node_ids   = array_merge( $node_ids, self::collect_ids( array( $node ) ) );
					$types      = array_merge( $types, self::collect_types( array( $node ) ) );
				}
				foreach ( self::ancestry_for( $id, $parent_map ) as $ancestor ) {
					$ancestry[ $ancestor['id'] ] = $ancestor;
					$types[] = $ancestor['type'];
				}
			}
			return array(
				'target'        => array( 'scope' => $scope, 'nodeIds' => $ids, 'type' => $scope ),
				'content'       => array( 'nodes' => $selected, 'ancestry' => array_values( $ancestry ) ),
				'nodeIds'       => array_values( array_unique( $node_ids ) ),
				'requiredTypes' => array_values( array_unique( $types ) ),
			);
		}

		$node_id = (string) ( $target['nodeId'] ?? '' );
		$node    = $node_id ? self::find_node( $nodes, $node_id ) : null;
		if ( ! $node ) return self::not_found( $node_id );
		$ancestry = self::ancestry_for( $node_id, $parent_map );
		$types    = array_merge( array( (string) $node['type'] ), array_column( $ancestry, 'type' ) );

		if ( 'widget' === $scope ) {
			$widget             = $node;
			$widget['children'] = array();
			return array(
				'target'        => array( 'scope' => 'widget', 'nodeId' => $node_id, 'type' => (string) $node['type'] ),
				'content'       => array( 'node' => $widget, 'parentContext' => $ancestry ? end( $ancestry ) : null, 'ancestry' => $ancestry ),
				'nodeIds'       => array( $node_id ),
				'requiredTypes' => array_values( array_unique( $types ) ),
			);
		}

		$types = array_merge( $types, self::collect_types( (array) ( $node['children'] ?? array() ) ) );
		return array(
			'target'        => array( 'scope' => 'subtree', 'nodeId' => $node_id, 'type' => (string) $node['type'] ),
			'content'       => array( 'node' => $node, 'ancestry' => $ancestry ),
			'nodeIds'       => self::collect_ids( array( $node ) ),
			'requiredTypes' => array_values( array_unique( $types ) ),
		);
	}

	public static function find_node( $nodes, $id ) {
		foreach ( (array) $nodes as $node ) {
			if ( (string) ( $node['id'] ?? '' ) === (string) $id ) return $node;
			$found = self::find_node( $node['children'] ?? array(), $id );
			if ( $found ) return $found;
		}
		return null;
	}

	public static function collect_ids( $nodes ) {
		$ids = array();
		foreach ( (array) $nodes as $node ) {
			$ids[] = (string) ( $node['id'] ?? '' );
			$ids = array_merge( $ids, self::collect_ids( $node['children'] ?? array() ) );
		}
		return array_values( array_filter( $ids, 'strlen' ) );
	}

	public static function collect_types( $nodes ) {
		$types = array();
		foreach ( (array) $nodes as $node ) {
			if ( ! empty( $node['type'] ) ) $types[] = (string) $node['type'];
			$types = array_merge( $types, self::collect_types( $node['children'] ?? array() ) );
		}
		return array_values( array_unique( $types ) );
	}

	public static function parent_map( $nodes, $parent_id = null, $map = array() ) {
		foreach ( array_values( (array) $nodes ) as $index => $node ) {
			$id = (string) ( $node['id'] ?? '' );
			if ( '' === $id ) continue;
			$map[ $id ] = array(
				'id'         => $id,
				'type'       => (string) ( $node['type'] ?? '' ),
				'parentId'   => $parent_id,
				'index'      => $index,
				'props'      => self::ancestry_props( $node ),
				'style'      => self::ancestry_style( $node['style'] ?? array() ),
				'responsive' => self::ancestry_responsive( $node['responsive'] ?? array() ),
				'customCSS'  => (array) ( $node['customCSS'] ?? array() ),
			);
			$map = self::parent_map( $node['children'] ?? array(), $id, $map );
		}
		return $map;
	}

	public static function is_descendant_or_self( $nodes, $root_id, $candidate_id ) {
		$root = self::find_node( $nodes, $root_id );
		if ( ! $root ) return false;
		return in_array( (string) $candidate_id, self::collect_ids( array( $root ) ), true );
	}

	private static function ancestry_for( $id, $parent_map ) {
		$chain  = array();
		$cursor = $parent_map[ $id ]['parentId'] ?? null;
		while ( $cursor && isset( $parent_map[ $cursor ] ) ) {
			$item = $parent_map[ $cursor ];
			array_unshift( $chain, $item );
			$cursor = $item['parentId'];
		}
		return $chain;
	}

	private static function ancestry_props( $node ) {
		$props   = (array) ( $node['props'] ?? array() );
		$allowed = array( 'contentWidth', 'layout', 'direction', 'align', 'justify', 'columns' );
		$output  = array();
		foreach ( $allowed as $key ) if ( array_key_exists( $key, $props ) ) $output[ $key ] = $props[ $key ];
		return $output;
	}

	private static function ancestry_style( $style ) {
		$allowed = array( 'display', 'width', 'maxWidth', 'minHeight', 'gap', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'alignItems', 'justifyContent', 'flexDirection', 'flexWrap', 'gridTemplateColumns', 'position', 'overflow' );
		$output  = array();
		foreach ( $allowed as $key ) if ( array_key_exists( $key, (array) $style ) ) $output[ $key ] = $style[ $key ];
		return $output;
	}

	private static function ancestry_responsive( $responsive ) {
		$output = array();
		foreach ( ContractRegistry::RESPONSIVE_DEVICES as $device ) {
			if ( ! empty( $responsive[ $device ] ) ) {
				$style = self::ancestry_style( $responsive[ $device ] );
				if ( $style ) $output[ $device ] = $style;
			}
		}
		return $output;
	}

	private static function not_found( $id ) {
		return new WP_Error( 'cresco_ai_target_not_found', __( 'The requested AI target node could not be found.', 'cresco-canvas' ), array( 'status' => 404, 'nodeId' => $id ) );
	}
}
