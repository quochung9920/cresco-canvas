<?php
/**
 * Canonical mutation gateway for Editor, AI, Import, clipboard and components.
 *
 * Every supported command is translated to cresco-patch/v1 and validated by
 * PatchValidator before a candidate Session can be returned. Nothing in this
 * class persists WordPress data or manipulates editor DOM.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Command;

use CrescoCanvas\AI\PatchValidator;
use CrescoCanvas\AI\ScopeResolver;
use CrescoCanvas\Core\Document\Document;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CommandBus {
	const SCHEMA = 'cresco-command/v1';

	/** Canonical names plus temporary aliases accepted during runtime migration. */
	const COMMANDS = array(
		'node.insert',
		'node.delete',
		'node.move',
		'node.duplicate',
		'node.group',
		'node.rename',
		'node.lock',
		'node.visibility',
		'props.update',
		'style.update',
		'style.reset',
		'responsive.update',
		'responsive.reset',
		'state.update',
		'state.reset',
		'customCss.update',
		'component.create',
		'component.apply',
		'component.detach',
		'ai.applyPatch',
		// Compatibility aliases. Do not add new aliases here.
		'node.props.set',
		'node.style.set',
		'node.responsive.set',
		'node.custom-css.set',
		'node.remove',
		'node.replace',
		'patch.apply',
	);

	const SOURCES = array( 'canvas', 'structure', 'inspector', 'keyboard', 'clipboard', 'component', 'ai', 'import', 'system', 'editor' );
	const STATES  = array( 'hover', 'focus', 'active' );
	const BREAKPOINTS = array( 'desktop', 'laptop', 'tablet', 'mobile' );

	/** Preview one command and return the same validated candidate/diff contract as PatchValidator. */
	public static function preview( $session, $command ) {
		$session = Document::session( $session );
		if ( is_wp_error( $session ) ) return $session;
		if ( ! is_array( $command ) || self::SCHEMA !== ( $command['schema'] ?? '' ) ) {
			return new WP_Error( 'cresco_command_schema', __( 'Expected a cresco-command/v1 object.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		$raw_name = (string) ( $command['command'] ?? '' );
		if ( ! in_array( $raw_name, self::COMMANDS, true ) ) {
			return new WP_Error( 'cresco_command_name', __( 'Unsupported Cresco command.', 'cresco-canvas' ), array( 'status' => 400, 'command' => $raw_name ) );
		}

		$source = sanitize_key( (string) ( $command['source'] ?? 'editor' ) );
		if ( ! in_array( $source, self::SOURCES, true ) ) $source = 'editor';
		$target  = self::normalize_target( (array) ( $command['target'] ?? array() ) );
		$payload = isset( $command['payload'] ) && is_array( $command['payload'] ) ? $command['payload'] : array();
		$patch   = self::patch_for_command( $session, $raw_name, $target, $payload );
		if ( is_wp_error( $patch ) ) return $patch;
		$result = PatchValidator::validate( $session, $patch );
		if ( is_wp_error( $result ) ) return $result;

		$now = gmdate( 'c' );
		$result['transaction'] = array(
			'id'             => sanitize_text_field( (string) ( $command['transactionId'] ?? '' ) ),
			'label'          => sanitize_text_field( (string) ( $command['transactionLabel'] ?? $raw_name ) ),
			'source'         => $source,
			'command'        => $raw_name,
			'startedAt'      => sanitize_text_field( (string) ( $command['startedAt'] ?? $now ) ),
			'committedAt'    => $now,
			'beforeChecksum' => Document::checksum( $session ),
			'afterChecksum'  => (string) ( $result['checksum'] ?? '' ),
		);
		return $result;
	}

	private static function normalize_target( $target ) {
		$scope = sanitize_key( (string) ( $target['scope'] ?? 'document' ) );
		if ( 'document' === $scope || 'page' === $scope ) return array( 'scope' => 'page' );
		if ( 'selection' === $scope ) {
			$ids = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $target['nodeIds'] ?? array() ) ) ) ) );
			sort( $ids, SORT_STRING );
			return array( 'scope' => 'selection', 'nodeIds' => $ids );
		}
		if ( 'subtree' === $scope || 'widget' === $scope ) return array( 'scope' => $scope, 'nodeId' => (string) ( $target['nodeId'] ?? '' ) );
		return array( 'scope' => 'page' );
	}

	private static function targets_match( $expected, $received ) {
		return self::normalize_target( $expected ) === self::normalize_target( $received );
	}

	private static function patch_for_command( $session, $name, $target, $payload ) {
		$name = self::canonical_name( $name );
		if ( 'ai.applyPatch' === $name ) return self::validated_patch_payload( $target, $payload );

		$operations = array();
		if ( 'props.update' === $name ) {
			$operations[] = array( 'op' => 'setProps', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'props' => (array) ( $payload['values'] ?? $payload['props'] ?? array() ) );
		} elseif ( 'style.update' === $name ) {
			$operations[] = array( 'op' => 'setStyle', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'style' => (array) ( $payload['values'] ?? $payload['style'] ?? array() ) );
		} elseif ( 'responsive.update' === $name ) {
			$operations[] = array( 'op' => 'setResponsive', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'responsive' => (array) ( $payload['values'] ?? $payload['responsive'] ?? array() ) );
		} elseif ( 'customCss.update' === $name ) {
			$operations[] = array( 'op' => 'setCustomCSS', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'customCSS' => (array) ( $payload['values'] ?? $payload['customCSS'] ?? array() ) );
		} elseif ( 'node.insert' === $name ) {
			$operations[] = self::insert_operation( $payload );
		} elseif ( 'node.delete' === $name ) {
			$operations[] = array( 'op' => 'removeNode', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ) );
		} elseif ( 'node.move' === $name ) {
			$operations[] = self::move_operation( $payload );
		} elseif ( 'node.duplicate' === $name ) {
			$node_id = (string) ( $payload['nodeId'] ?? '' );
			$node = ScopeResolver::find_node( $session['nodes'] ?? array(), $node_id );
			if ( ! $node ) return self::node_not_found( $node_id );
			$location = self::node_location( $session['nodes'] ?? array(), $node_id );
			$operations[] = array(
				'op'       => 'insertNode',
				'parentId' => array_key_exists( 'parentId', $payload ) ? $payload['parentId'] : ( $location['parentId'] ?? null ),
				'index'    => isset( $payload['index'] ) ? absint( $payload['index'] ) : ( isset( $location['index'] ) ? $location['index'] + 1 : null ),
				'node'     => self::clone_value( $node ),
			);
		} elseif ( 'node.group' === $name ) {
			$group = isset( $payload['node'] ) && is_array( $payload['node'] ) ? self::clone_value( $payload['node'] ) : self::default_group_node();
			$ids = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $payload['nodeIds'] ?? array() ) ) ) ) );
			if ( count( $ids ) < 2 ) return new WP_Error( 'cresco_command_group_nodes', __( 'Grouping requires at least two nodes.', 'cresco-canvas' ), array( 'status' => 400 ) );
			$locations = array();
			foreach ( $ids as $id ) {
				$location = self::node_location( $session['nodes'] ?? array(), $id );
				if ( ! $location ) return self::node_not_found( $id );
				$locations[] = $location;
			}
			$parent_id = $locations[0]['parentId'];
			foreach ( $locations as $location ) if ( $location['parentId'] !== $parent_id ) return new WP_Error( 'cresco_command_group_siblings', __( 'Grouping requires sibling nodes with the same parent.', 'cresco-canvas' ), array( 'status' => 400 ) );
			usort( $locations, static function ( $a, $b ) { return $a['index'] <=> $b['index']; } );
			$group['children'] = array();
			foreach ( $locations as $location ) $group['children'][] = self::clone_value( $location['node'] );
			foreach ( array_reverse( $locations ) as $location ) $operations[] = array( 'op' => 'removeNode', 'nodeId' => (string) $location['node']['id'] );
			$operations[] = array( 'op' => 'insertNode', 'parentId' => $parent_id, 'index' => $locations[0]['index'], 'node' => $group );
		} elseif ( in_array( $name, array( 'node.rename', 'node.lock', 'node.visibility', 'component.detach', 'state.update', 'state.reset', 'style.reset', 'responsive.reset', 'node.replace' ), true ) ) {
			$node_id = (string) ( $payload['nodeId'] ?? '' );
			$node = ScopeResolver::find_node( $session['nodes'] ?? array(), $node_id );
			if ( ! $node ) return self::node_not_found( $node_id );
			$replacement = self::clone_value( $node );
			if ( 'node.replace' === $name && isset( $payload['node'] ) && is_array( $payload['node'] ) ) {
				$replacement = self::clone_value( $payload['node'] );
			} elseif ( 'node.rename' === $name ) {
				$replacement['meta'] = (array) ( $replacement['meta'] ?? array() );
				$replacement['meta']['label'] = sanitize_text_field( (string) ( $payload['label'] ?? $payload['name'] ?? '' ) );
			} elseif ( 'node.lock' === $name ) {
				$replacement['meta'] = (array) ( $replacement['meta'] ?? array() );
				$replacement['meta']['locked'] = ! empty( $payload['locked'] );
			} elseif ( 'node.visibility' === $name ) {
				$replacement['meta'] = (array) ( $replacement['meta'] ?? array() );
				$replacement['meta']['hidden'] = array_key_exists( 'hidden', $payload ) ? ! empty( $payload['hidden'] ) : empty( $payload['visible'] );
			} elseif ( 'component.detach' === $name ) {
				$replacement['meta'] = (array) ( $replacement['meta'] ?? array() );
				$replacement['meta']['componentId'] = 0;
			} elseif ( 'state.update' === $name ) {
				$state = sanitize_key( (string) ( $payload['state'] ?? '' ) );
				if ( ! in_array( $state, self::STATES, true ) ) return new WP_Error( 'cresco_command_state', __( 'Unsupported interaction state.', 'cresco-canvas' ), array( 'status' => 400 ) );
				$replacement['states'] = (array) ( $replacement['states'] ?? array() );
				$replacement['states'][ $state ] = array_replace( (array) ( $replacement['states'][ $state ] ?? array() ), (array) ( $payload['values'] ?? $payload['style'] ?? array() ) );
			} elseif ( 'state.reset' === $name ) {
				$state = sanitize_key( (string) ( $payload['state'] ?? '' ) );
				$replacement['states'] = (array) ( $replacement['states'] ?? array() );
				if ( $state ) unset( $replacement['states'][ $state ] ); else $replacement['states'] = array();
			} elseif ( 'style.reset' === $name ) {
				$replacement['style'] = self::without_keys( (array) ( $replacement['style'] ?? array() ), (array) ( $payload['properties'] ?? array() ) );
			} elseif ( 'responsive.reset' === $name ) {
				$breakpoint = sanitize_key( (string) ( $payload['breakpoint'] ?? '' ) );
				if ( ! in_array( $breakpoint, self::BREAKPOINTS, true ) ) return new WP_Error( 'cresco_command_breakpoint', __( 'Unsupported responsive breakpoint.', 'cresco-canvas' ), array( 'status' => 400 ) );
				$replacement['responsive'] = (array) ( $replacement['responsive'] ?? array() );
				$properties = (array) ( $payload['properties'] ?? array() );
				if ( $properties ) {
					$replacement['responsive'][ $breakpoint ] = self::without_keys( (array) ( $replacement['responsive'][ $breakpoint ] ?? array() ), $properties );
					if ( ! $replacement['responsive'][ $breakpoint ] ) unset( $replacement['responsive'][ $breakpoint ] );
				} else {
					unset( $replacement['responsive'][ $breakpoint ] );
				}
			}
			$operations[] = array( 'op' => 'replaceSubtree', 'nodeId' => $node_id, 'node' => $replacement );
		} elseif ( 'component.apply' === $name ) {
			if ( empty( $payload['node'] ) || ! is_array( $payload['node'] ) ) return new WP_Error( 'cresco_command_component', __( 'component.apply requires a validated component node.', 'cresco-canvas' ), array( 'status' => 400 ) );
			if ( ! empty( $payload['nodeId'] ) ) {
				$operations[] = array( 'op' => 'replaceSubtree', 'nodeId' => (string) $payload['nodeId'], 'node' => self::clone_value( $payload['node'] ) );
			} else {
				$operations[] = self::insert_operation( $payload );
			}
		} elseif ( 'component.create' === $name ) {
			// Component persistence belongs to its resource service. The document command
			// only applies the resulting instance node when one is supplied.
			if ( empty( $payload['node'] ) || ! is_array( $payload['node'] ) ) return new WP_Error( 'cresco_command_component', __( 'component.create requires the resulting component instance node.', 'cresco-canvas' ), array( 'status' => 400 ) );
			$operations[] = self::insert_operation( $payload );
		} else {
			return new WP_Error( 'cresco_command_operation', __( 'The Cresco command could not be translated to a patch operation.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		return array(
			'schema'       => PatchValidator::SCHEMA,
			'baseChecksum' => Document::checksum( $session ),
			'target'       => $target,
			'operations'   => array_values( $operations ),
		);
	}

	private static function canonical_name( $name ) {
		$aliases = array(
			'node.props.set'       => 'props.update',
			'node.style.set'       => 'style.update',
			'node.responsive.set'  => 'responsive.update',
			'node.custom-css.set'  => 'customCss.update',
			'node.remove'          => 'node.delete',
			'patch.apply'          => 'ai.applyPatch',
		);
		return $aliases[ $name ] ?? $name;
	}

	private static function validated_patch_payload( $target, $payload ) {
		$patch = isset( $payload['patch'] ) && is_array( $payload['patch'] ) ? $payload['patch'] : $payload;
		if ( PatchValidator::SCHEMA !== ( $patch['schema'] ?? '' ) ) return new WP_Error( 'cresco_command_patch', __( 'ai.applyPatch requires a cresco-patch/v1 payload.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( ! self::targets_match( $target, (array) ( $patch['target'] ?? array() ) ) ) {
			return new WP_Error( 'cresco_command_scope_mismatch', __( 'The patch target does not match the active Cresco scope. Export fresh scoped context before applying it.', 'cresco-canvas' ), array( 'status' => 400, 'expectedTarget' => $target, 'receivedTarget' => (array) ( $patch['target'] ?? array() ) ) );
		}
		return $patch;
	}

	private static function insert_operation( $payload ) {
		return array(
			'op'       => 'insertNode',
			'parentId' => array_key_exists( 'parentId', $payload ) ? $payload['parentId'] : null,
			'index'    => isset( $payload['index'] ) ? absint( $payload['index'] ) : null,
			'node'     => (array) ( $payload['node'] ?? array() ),
		);
	}

	private static function move_operation( $payload ) {
		return array(
			'op'       => 'moveNode',
			'nodeId'   => (string) ( $payload['nodeId'] ?? '' ),
			'parentId' => array_key_exists( 'parentId', $payload ) ? $payload['parentId'] : null,
			'index'    => isset( $payload['index'] ) ? absint( $payload['index'] ) : null,
		);
	}

	private static function node_location( $nodes, $node_id, $parent_id = null ) {
		foreach ( array_values( (array) $nodes ) as $index => $node ) {
			if ( (string) ( $node['id'] ?? '' ) === (string) $node_id ) return array( 'node' => $node, 'parentId' => $parent_id, 'index' => $index );
			$child = self::node_location( $node['children'] ?? array(), $node_id, (string) ( $node['id'] ?? '' ) );
			if ( $child ) return $child;
		}
		return null;
	}

	private static function without_keys( $map, $keys ) {
		$map = (array) $map;
		$keys = array_values( array_filter( array_map( 'strval', $keys ) ) );
		if ( ! $keys ) return array();
		foreach ( $keys as $key ) unset( $map[ $key ] );
		return $map;
	}

	private static function default_group_node() {
		$id = function_exists( 'wp_generate_uuid4' ) ? 'container-' . wp_generate_uuid4() : uniqid( 'container-', true );
		return array(
			'id'         => sanitize_key( $id ),
			'type'       => 'container',
			'props'      => array( 'contentWidth' => 'full', 'layout' => 'flex', 'direction' => 'column', 'wrap' => 'nowrap', 'align' => 'stretch', 'justify' => 'flex-start', 'columns' => 2, 'gridTemplate' => 'repeat(2, minmax(0, 1fr))', 'tag' => 'div', 'ariaLabel' => '' ),
			'style'      => array(),
			'responsive' => array(),
			'states'     => array(),
			'customCSS'  => array(),
			'meta'       => array( 'label' => 'Group', 'componentId' => 0, 'locked' => false, 'hidden' => false ),
			'children'   => array(),
		);
	}

	private static function clone_value( $value ) {
		$copy = json_decode( (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), true );
		return is_array( $copy ) ? $copy : array();
	}

	private static function node_not_found( $node_id ) {
		return new WP_Error( 'cresco_command_node_not_found', __( 'The Cresco command references a node that does not exist.', 'cresco-canvas' ), array( 'status' => 400, 'nodeId' => $node_id ) );
	}
}
