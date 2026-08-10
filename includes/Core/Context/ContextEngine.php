<?php
/**
 * Minimal, purpose-aware context builder for AI and portable workflows.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Context;

use CrescoCanvas\AI\ContextBuilder;
use CrescoCanvas\Core\Document\Document;
use CrescoCanvas\Core\Scope\ScopeEngine;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContextEngine {
	const SCHEMA   = 'cresco-ai-context/v2';
	const VERSION  = 2;
	const PURPOSES = array( 'edit', 'redesign', 'create', 'content', 'style', 'import' );

	/** Build an AI envelope that contains only the selected scope by default. */
	public static function build( $post_id, $session, $scope = 'document', $target = array(), $purpose = 'edit', $mode = 'auto', $resources = array() ) {
		$session = Document::session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$purpose = sanitize_key( (string) $purpose );
		if ( ! in_array( $purpose, self::PURPOSES, true ) ) $purpose = 'edit';
		$mode = self::resolve_mode( $mode, $scope, $purpose );
		$document_type = isset( $resources['documentType'] ) ? (string) $resources['documentType'] : 'page';
		$scope_package = ScopeEngine::resolve( $session, $scope, $target, $document_type );
		if ( is_wp_error( $scope_package ) ) return $scope_package;

		$legacy_scope = 'document' === $scope ? 'page' : $scope;
		$legacy = ContextBuilder::build( $post_id, $session, $legacy_scope, $target, $mode, $resources );
		if ( is_wp_error( $legacy ) ) return $legacy;

		$instructions = array_values( array_merge(
			array(
				'Return a cresco-patch/v1 object whenever the requested change can be expressed as a scoped edit.',
				'Never modify nodes outside scopePackage.patchTarget. Server-side scope validation is authoritative.',
				'Prefer structured props, style, responsive overrides, states, and semantic design tokens before scoped Custom CSS.',
				'Preserve stable node IDs unless inserting new nodes; inserted IDs may be remapped by Cresco to avoid collisions.',
			),
			(array) ( $legacy['instructions'] ?? array() )
		) );

		return array(
			'schema'        => self::SCHEMA,
			'version'       => self::VERSION,
			'purpose'       => $purpose,
			'mode'          => $mode,
			'baseChecksum'  => ContextBuilder::checksum( $session ),
			'environment'   => (array) ( $legacy['environment'] ?? array() ),
			'scopePackage'  => $scope_package,
			'designSystem'  => (array) ( $legacy['designSystem'] ?? array() ),
			'pageSettings'  => (array) ( $legacy['pageSettings'] ?? array() ),
			'contracts'     => (array) ( $legacy['contracts'] ?? array() ),
			'dependencies'  => (array) ( $legacy['dependencies'] ?? array() ),
			'instructions'  => $instructions,
			'returnContract'=> array(
				'preferred'     => 'cresco-patch/v1',
				'fallback'      => 'cresco-session/v1',
				'scopeEnforced' => true,
			),
		);
	}

	private static function resolve_mode( $mode, $scope, $purpose ) {
		$mode = sanitize_key( (string) $mode );
		if ( in_array( $mode, array( 'optimized', 'full' ), true ) ) return $mode;
		if ( 'create' === $purpose ) return 'full';
		if ( 'document' === $scope && 'redesign' === $purpose ) return 'full';
		return 'optimized';
	}
}
