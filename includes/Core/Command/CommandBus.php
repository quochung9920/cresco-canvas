<?php
/**
 * Canonical mutation gateway for Editor, AI, Import, clipboard and components.
 *
 * Every supported command is translated to cresco-patch/v1 and validated by
 * PatchValidator before a candidate Session can be returned. Nothing in this
 * class persists WordPress data.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Command;

use CrescoCanvas\AI\PatchValidator;
use CrescoCanvas\Core\Document\Document;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CommandBus {
	const SCHEMA = 'cresco-command/v1';
	const COMMANDS = array(
		'node.props.set',
		'node.style.set',
		'node.responsive.set',
		'node.custom-css.set',
		'node.insert',
		'node.remove',
		'node.move',
		'node.replace',
		'patch.apply',
	);
	const SOURCES = array( 'editor', 'ai', 'import', 'clipboard', 'component', 'system' );

	/** Preview a command and return the same validated candidate/diff contract as PatchValidator. */
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
		$target = self::normalize_target( (array) ( $command['target'] ?? array() ) );
		$payload = isset( $command['payload'] ) && is_array( $command['payload'] ) ? $command['payload'] : array();
		$patch = self::patch_for_command( $session, $raw_name, $target, $payload );
		if ( is_wp_error( $patch ) ) return $patch;
		$result = PatchValidator::validate( $session, $patch );
		if ( is_wp_error( $result ) ) return $result;
		$result['transaction'] = array(
			'id'        => sanitize_text_field( (string) ( $command['transactionId'] ?? '' ) ),
			'source'    => $source,
			'command'   => $raw_name,
			'createdAt' => gmdate( 'c' ),
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
		$expected = self::normalize_target( $expected );
		$received = self::normalize_target( $received );
		return $expected === $received;
	}

	private static function patch_for_command( $session, $name, $target, $payload ) {
		if ( 'patch.apply' === $name ) {
			$patch = isset( $payload['patch'] ) && is_array( $payload['patch'] ) ? $payload['patch'] : $payload;
			if ( PatchValidator::SCHEMA !== ( $patch['schema'] ?? '' ) ) {
				return new WP_Error( 'cresco_command_patch', __( 'patch.apply requires a cresco-patch/v1 payload.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}
			if ( ! self::targets_match( $target, (array) ( $patch['target'] ?? array() ) ) ) {
				return new WP_Error(
					'cresco_command_scope_mismatch',
					__( 'The patch target does not match the active Cresco scope. Export fresh scoped context before applying it.', 'cresco-canvas' ),
					array( 'status' => 400, 'expectedTarget' => $target, 'receivedTarget' => (array) ( $patch['target'] ?? array() ) )
				);
			}
			return $patch;
		}

		$operation = null;
		if ( 'node.props.set' === $name ) $operation = array( 'op' => 'setProps', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'props' => (array) ( $payload['values'] ?? $payload['props'] ?? array() ) );
		if ( 'node.style.set' === $name ) $operation = array( 'op' => 'setStyle', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'style' => (array) ( $payload['values'] ?? $payload['style'] ?? array() ) );
		if ( 'node.responsive.set' === $name ) $operation = array( 'op' => 'setResponsive', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'responsive' => (array) ( $payload['values'] ?? $payload['responsive'] ?? array() ) );
		if ( 'node.custom-css.set' === $name ) $operation = array( 'op' => 'setCustomCSS', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'customCSS' => (array) ( $payload['values'] ?? $payload['customCSS'] ?? array() ) );
		if ( 'node.insert' === $name ) $operation = array( 'op' => 'insertNode', 'parentId' => array_key_exists( 'parentId', $payload ) ? $payload['parentId'] : null, 'index' => isset( $payload['index'] ) ? absint( $payload['index'] ) : null, 'node' => (array) ( $payload['node'] ?? array() ) );
		if ( 'node.remove' === $name ) $operation = array( 'op' => 'removeNode', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ) );
		if ( 'node.move' === $name ) $operation = array( 'op' => 'moveNode', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'parentId' => array_key_exists( 'parentId', $payload ) ? $payload['parentId'] : null, 'index' => isset( $payload['index'] ) ? absint( $payload['index'] ) : null );
		if ( 'node.replace' === $name ) $operation = array( 'op' => 'replaceSubtree', 'nodeId' => (string) ( $payload['nodeId'] ?? '' ), 'node' => (array) ( $payload['node'] ?? array() ) );
		if ( ! $operation ) return new WP_Error( 'cresco_command_operation', __( 'The Cresco command could not be translated to a patch operation.', 'cresco-canvas' ), array( 'status' => 400 ) );

		return array(
			'schema'       => PatchValidator::SCHEMA,
			'baseChecksum' => Document::checksum( $session ),
			'target'       => $target,
			'operations'   => array( $operation ),
		);
	}
}
