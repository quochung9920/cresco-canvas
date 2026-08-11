<?php
/**
 * Groups multiple canonical commands into one deterministic document transaction.
 *
 * A transaction is an in-memory mutation boundary. It never persists WordPress
 * data; callers decide when the validated candidate is committed and saved.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Command;

use CrescoCanvas\AI\DiffEngine;
use CrescoCanvas\Core\Document\Document;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransactionManager {
	const SCHEMA       = 'cresco-transaction/v1';
	const MAX_COMMANDS = 200;

	/** Preview a complete transaction as one candidate/diff/history unit. */
	public static function preview( $session, $transaction ) {
		$base = Document::session( $session );
		if ( is_wp_error( $base ) ) return $base;
		if ( ! is_array( $transaction ) || self::SCHEMA !== ( $transaction['schema'] ?? '' ) ) {
			return new WP_Error( 'cresco_transaction_schema', __( 'Expected a cresco-transaction/v1 object.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$commands = isset( $transaction['commands'] ) && is_array( $transaction['commands'] ) ? array_values( $transaction['commands'] ) : array();
		if ( ! $commands || count( $commands ) > self::MAX_COMMANDS ) {
			return new WP_Error( 'cresco_transaction_commands', __( 'A Cresco transaction requires between 1 and 200 commands.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$id      = sanitize_text_field( (string) ( $transaction['id'] ?? '' ) );
		$label   = sanitize_text_field( (string) ( $transaction['label'] ?? 'Document change' ) );
		$source  = sanitize_key( (string) ( $transaction['source'] ?? 'editor' ) );
		$started = sanitize_text_field( (string) ( $transaction['startedAt'] ?? gmdate( 'c' ) ) );
		if ( ! in_array( $source, CommandBus::SOURCES, true ) ) $source = 'editor';
		if ( '' === $id ) $id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'transaction-', true );

		$working = $base;
		$applied = array();
		foreach ( $commands as $index => $command ) {
			if ( ! is_array( $command ) ) {
				return new WP_Error( 'cresco_transaction_command', __( 'Every Cresco transaction command must be an object.', 'cresco-canvas' ), array( 'status' => 400, 'commandIndex' => $index ) );
			}
			$command = array_merge(
				$command,
				array(
					'schema'           => CommandBus::SCHEMA,
					'source'           => $command['source'] ?? $source,
					'transactionId'    => $id,
					'transactionLabel' => $label,
					'startedAt'        => $started,
				)
			);
			$result = CommandBus::preview( $working, $command );
			if ( is_wp_error( $result ) ) {
				$data = (array) $result->get_error_data();
				$data['commandIndex'] = $index;
				return new WP_Error( $result->get_error_code(), $result->get_error_message(), $data );
			}
			$working = $result['session'];
			$applied[] = array(
				'command'  => (string) ( $command['command'] ?? '' ),
				'checksum' => (string) ( $result['checksum'] ?? '' ),
			);
		}

		$committed = gmdate( 'c' );
		return array(
			'valid'       => true,
			'schema'      => self::SCHEMA,
			'session'     => $working,
			'checksum'    => Document::checksum( $working ),
			'diff'        => DiffEngine::compare( $base, $working ),
			'transaction' => array(
				'id'             => $id,
				'label'          => $label,
				'source'         => $source,
				'startedAt'      => $started,
				'committedAt'    => $committed,
				'beforeChecksum' => Document::checksum( $base ),
				'afterChecksum'  => Document::checksum( $working ),
				'commands'       => $applied,
			),
		);
	}

	private function __construct() {}
}
