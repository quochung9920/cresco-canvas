<?php
/**
 * Strict validator for cresco-patch/v1.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Session\SessionManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PatchValidator {
	const SCHEMA         = 'cresco-patch/v1';
	const MAX_OPERATIONS = 200;
	const OPERATIONS     = array( 'setProps', 'setStyle', 'setResponsive', 'setCustomCSS', 'insertNode', 'removeNode', 'moveNode', 'replaceSubtree' );

	public static function validate( $current_session, $patch ) {
		$current = SessionManager::sanitize_session( $current_session );
		if ( is_wp_error( $current ) ) return $current;
		if ( ! is_array( $patch ) || self::SCHEMA !== ( $patch['schema'] ?? '' ) ) {
			return self::error( 'cresco_ai_patch_schema', 'Expected a cresco-patch/v1 object.' );
		}
		$base_checksum = (string) ( $patch['baseChecksum'] ?? '' );
		$current_checksum = ContextBuilder::checksum( $current );
		if ( '' === $base_checksum || ! hash_equals( $current_checksum, $base_checksum ) ) {
			return new WP_Error( 'cresco_ai_patch_stale', __( 'This AI patch was created from a different Cresco Session. Export fresh context before applying it.', 'cresco-canvas' ), array( 'status' => 409, 'stale' => true, 'expectedChecksum' => $current_checksum, 'receivedChecksum' => $base_checksum ) );
		}

		$target = self::validate_target( $current, (array) ( $patch['target'] ?? array() ) );
		if ( is_wp_error( $target ) ) return $target;
		$operations = isset( $patch['operations'] ) && is_array( $patch['operations'] ) ? array_values( $patch['operations'] ) : null;
		if ( null === $operations || count( $operations ) > self::MAX_OPERATIONS ) {
			return self::error( 'cresco_ai_patch_operations', 'Cresco Patch operations must be an array with no more than 200 entries.' );
		}

		$working = $current;
		$id_map  = array();
		foreach ( $operations as $index => $operation ) {
			$operation = PatchApplier::rewrite_operation_ids( $operation, $id_map );
			$valid = self::validate_operation( $working, $target, $operation, $index );
			if ( is_wp_error( $valid ) ) return $valid;
			$single = array( 'operations' => array( $operation ) );
			$applied = PatchApplier::apply( $working, $single );
			if ( is_wp_error( $applied ) ) return $applied;
			$working = $applied['session'];
			if ( ! empty( $applied['idMap'] ) ) $id_map = array_merge( $id_map, $applied['idMap'] );
		}

		$candidate = SessionManager::sanitize_session( $working );
		if ( is_wp_error( $candidate ) ) return $candidate;

		return array(
			'valid'        => true,
			'resultType'   => 'patch',
			'schema'       => self::SCHEMA,
			'baseChecksum' => $base_checksum,
			'checksum'     => ContextBuilder::checksum( $candidate ),
			'stale'        => false,
			'target'       => $target,
			'idMap'        => $id_map,
			'session'      => $candidate,
			'diff'         => DiffEngine::compare( $current, $candidate ),
		);
	}

	private static function validate_target( $session, $target ) {
		$scope = sanitize_key( (string) ( $target['scope'] ?? '' ) );
		if ( ! in_array( $scope, ScopeResolver::SCOPES, true ) ) return self::error( 'cresco_ai_patch_target', 'Cresco Patch target has an unsupported scope.' );
		if ( 'page' === $scope ) return array( 'scope' => 'page', 'nodeId' => null, 'type' => 'page' );
		if ( 'selection' === $scope ) {
			$ids = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $target['nodeIds'] ?? array() ) ) ) ) );
			if ( ! $ids ) return self::error( 'cresco_ai_patch_target', 'Selection patch target requires nodeIds.' );
			foreach ( $ids as $id ) if ( ! ScopeResolver::find_node( $session['nodes'] ?? array(), $id ) ) return self::target_not_found( $id );
			return array( 'scope' => 'selection', 'nodeIds' => $ids, 'type' => 'selection' );
		}
		$node_id = (string) ( $target['nodeId'] ?? '' );
		$node    = ScopeResolver::find_node( $session['nodes'] ?? array(), $node_id );
		if ( ! $node ) return self::target_not_found( $node_id );
		return array( 'scope' => $scope, 'nodeId' => $node_id, 'type' => (string) $node['type'] );
	}

	private static function validate_operation( $session, $target, $operation, $index ) {
		if ( ! is_array( $operation ) ) return self::operation_error( 'Cresco Patch operation must be an object.', $index );
		$op = (string) ( $operation['op'] ?? '' );
		if ( ! in_array( $op, self::OPERATIONS, true ) ) return self::operation_error( 'Unsupported Cresco Patch operation.', $index, array( 'operation' => $op ) );
		$allowed_fields = array(
			'setProps' => array( 'op', 'nodeId', 'props' ),
			'setStyle' => array( 'op', 'nodeId', 'style' ),
			'setResponsive' => array( 'op', 'nodeId', 'responsive' ),
			'setCustomCSS' => array( 'op', 'nodeId', 'customCSS' ),
			'insertNode' => array( 'op', 'parentId', 'index', 'node' ),
			'removeNode' => array( 'op', 'nodeId' ),
			'moveNode' => array( 'op', 'nodeId', 'parentId', 'index' ),
			'replaceSubtree' => array( 'op', 'nodeId', 'node' ),
		);
		foreach ( array_keys( $operation ) as $field ) {
			if ( ! in_array( $field, $allowed_fields[ $op ], true ) ) return self::operation_error( 'Cresco Patch operation contains an unsupported field.', $index, array( 'field' => $field ) );
		}
		$scope = $target['scope'];
		if ( 'widget' === $scope && ! in_array( $op, array( 'setProps', 'setStyle', 'setResponsive', 'setCustomCSS' ), true ) ) {
			return self::escape_error( $index, 'Widget scope may change only that widget’s props, style, responsive style, and scoped Custom CSS.' );
		}

		if ( in_array( $op, array( 'setProps', 'setStyle', 'setResponsive', 'setCustomCSS', 'removeNode', 'moveNode', 'replaceSubtree' ), true ) ) {
			$node_id = (string) ( $operation['nodeId'] ?? '' );
			$node    = ScopeResolver::find_node( $session['nodes'] ?? array(), $node_id );
			if ( ! $node ) return self::target_not_found( $node_id, $index );
			if ( ! self::node_in_scope( $session, $target, $node_id ) ) return self::escape_error( $index );
		}

		if ( 'setProps' === $op ) {
			$node = ScopeResolver::find_node( $session['nodes'] ?? array(), (string) $operation['nodeId'] );
			$contract = ContractRegistry::all()[ $node['type'] ] ?? null;
			$props = $operation['props'] ?? null;
			if ( ! is_array( $props ) ) return self::operation_error( 'setProps requires a props object.', $index );
			foreach ( $props as $key => $value ) {
				if ( ! $contract || ! array_key_exists( $key, $contract['props'] ) ) return self::operation_error( 'setProps contains an unsupported widget property.', $index, array( 'property' => $key, 'widgetType' => $node['type'] ) );
				$probe = $node;
				$probe['props'] = array( $key => $value );
				$probe['style'] = array(); $probe['responsive'] = array(); $probe['customCSS'] = array(); $probe['children'] = array();
				$valid = ContractRegistry::validate_node( $probe, 'operations.' . $index . '.props' );
				if ( is_wp_error( $valid ) ) return $valid;
			}
		}
		if ( 'setStyle' === $op ) {
			$node  = ScopeResolver::find_node( $session['nodes'] ?? array(), (string) $operation['nodeId'] );
			$valid = ContractRegistry::validate_style_map( (string) $node['type'], $operation['style'] ?? null, 'operations.' . $index . '.style' );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		if ( 'setResponsive' === $op ) {
			$node  = ScopeResolver::find_node( $session['nodes'] ?? array(), (string) $operation['nodeId'] );
			$valid = ContractRegistry::validate_responsive_map( (string) $node['type'], $operation['responsive'] ?? null, 'operations.' . $index . '.responsive' );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		if ( 'setCustomCSS' === $op ) {
			$valid = ContractRegistry::validate_custom_css_map( $operation['customCSS'] ?? null, 'operations.' . $index . '.customCSS' );
			if ( is_wp_error( $valid ) ) return $valid;
			foreach ( (array) $operation['customCSS'] as $css ) {
				$sanitized = SessionManager::sanitize_custom_css( $css );
				if ( is_wp_error( $sanitized ) ) return $sanitized;
			}
		}
		if ( 'insertNode' === $op ) {
			$parent_id = isset( $operation['parentId'] ) && null !== $operation['parentId'] ? (string) $operation['parentId'] : null;
			if ( null === $parent_id && 'page' !== $scope ) return self::escape_error( $index, 'Only page-scoped patches may insert a root node.' );
			if ( null !== $parent_id ) {
				$parent = ScopeResolver::find_node( $session['nodes'] ?? array(), $parent_id );
				if ( ! $parent ) return self::target_not_found( $parent_id, $index );
				if ( ! self::node_in_scope( $session, $target, $parent_id ) ) return self::escape_error( $index );
				$contract = ContractRegistry::all()[ $parent['type'] ] ?? null;
				if ( ! $contract || empty( $contract['allowsChildren'] ) ) return self::operation_error( 'insertNode target does not allow children.', $index );
			}
			$valid = ContractRegistry::validate_node( $operation['node'] ?? null, 'operations.' . $index . '.node' );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		if ( 'moveNode' === $op ) {
			$parent_id = isset( $operation['parentId'] ) && null !== $operation['parentId'] ? (string) $operation['parentId'] : null;
			if ( null === $parent_id && 'page' !== $scope ) return self::escape_error( $index, 'Only page-scoped patches may move a node to the Session root.' );
			if ( null !== $parent_id ) {
				$parent = ScopeResolver::find_node( $session['nodes'] ?? array(), $parent_id );
				if ( ! $parent ) return self::target_not_found( $parent_id, $index );
				if ( ! self::node_in_scope( $session, $target, $parent_id ) ) return self::escape_error( $index );
				$contract = ContractRegistry::all()[ $parent['type'] ] ?? null;
				if ( ! $contract || empty( $contract['allowsChildren'] ) ) return self::operation_error( 'moveNode destination does not allow children.', $index );
			}
		}
		if ( 'replaceSubtree' === $op ) {
			$valid = ContractRegistry::validate_node( $operation['node'] ?? null, 'operations.' . $index . '.node' );
			if ( is_wp_error( $valid ) ) return $valid;
		}
		return true;
	}

	private static function node_in_scope( $session, $target, $node_id ) {
		$scope = $target['scope'];
		if ( 'page' === $scope ) return true;
		if ( 'widget' === $scope ) return (string) $target['nodeId'] === (string) $node_id;
		if ( 'selection' === $scope ) return in_array( (string) $node_id, (array) $target['nodeIds'], true );
		return ScopeResolver::is_descendant_or_self( $session['nodes'] ?? array(), (string) $target['nodeId'], (string) $node_id );
	}

	private static function error( $code, $message ) {
		return new WP_Error( $code, __( $message, 'cresco-canvas' ), array( 'status' => 400 ) );
	}
	private static function operation_error( $message, $index, $extra = array() ) {
		return new WP_Error( 'cresco_ai_patch_operation', __( $message, 'cresco-canvas' ), array_merge( array( 'status' => 400, 'operationIndex' => $index ), $extra ) );
	}
	private static function escape_error( $index, $message = 'Cresco Patch operation attempts to modify data outside the exported target scope.' ) {
		return new WP_Error( 'cresco_ai_patch_scope_escape', __( $message, 'cresco-canvas' ), array( 'status' => 400, 'operationIndex' => $index ) );
	}
	private static function target_not_found( $id, $index = null ) {
		$data = array( 'status' => 400, 'nodeId' => $id );
		if ( null !== $index ) $data['operationIndex'] = $index;
		return new WP_Error( 'cresco_ai_patch_target_not_found', __( 'Cresco Patch references a node that does not exist.', 'cresco-canvas' ), $data );
	}
}
