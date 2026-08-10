<?php
/**
 * Canonical scope resolution for Editor, AI, Import/Export, clipboard and components.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Scope;

use CrescoCanvas\AI\ScopeResolver;
use CrescoCanvas\Core\Document\Document;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopeEngine {
	const SCHEMA = 'cresco-scope/v1';
	const SCOPES = array( 'document', 'subtree', 'widget', 'selection' );

	/** Resolve a portable, minimal scope package from a Session. */
	public static function resolve( $session, $scope = 'document', $target = array(), $document_type = 'page' ) {
		$session = Document::session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$scope = sanitize_key( (string) $scope );
		if ( ! in_array( $scope, self::SCOPES, true ) ) {
			return new WP_Error( 'cresco_scope_unsupported', __( 'Unsupported Cresco scope.', 'cresco-canvas' ), array( 'status' => 400, 'scope' => $scope ) );
		}

		$legacy_scope = 'document' === $scope ? 'page' : $scope;
		$resolved = ScopeResolver::resolve( $session, $legacy_scope, is_array( $target ) ? $target : array() );
		if ( is_wp_error( $resolved ) ) return $resolved;
		$document = Document::from_session( $session, $document_type );
		if ( is_wp_error( $document ) ) return $document;

		return array(
			'schema'        => self::SCHEMA,
			'version'       => 1,
			'scope'         => $scope,
			'target'        => self::public_target( $scope, $resolved['target'] ?? array() ),
			'patchTarget'   => self::patch_target( $scope, $resolved['target'] ?? array() ),
			'document'      => array(
				'documentId'   => $document['documentId'],
				'documentType' => $document['documentType'],
			),
			'content'       => (array) ( $resolved['content'] ?? array() ),
			'nodeIds'       => array_values( (array) ( $resolved['nodeIds'] ?? array() ) ),
			'requiredTypes' => array_values( (array) ( $resolved['requiredTypes'] ?? array() ) ),
			'boundary'      => self::boundary( $scope ),
		);
	}

	/** Convert public document scope to the current Patch v1 page target. */
	public static function patch_target( $scope, $resolved_target = array() ) {
		if ( 'document' === $scope ) return array( 'scope' => 'page' );
		$output = (array) $resolved_target;
		unset( $output['type'] );
		return $output;
	}

	private static function public_target( $scope, $resolved_target ) {
		if ( 'document' === $scope ) return array( 'scope' => 'document', 'nodeId' => null, 'type' => 'document' );
		$output = (array) $resolved_target;
		$output['scope'] = $scope;
		return $output;
	}

	private static function boundary( $scope ) {
		$all = array( 'setProps', 'setStyle', 'setResponsive', 'setCustomCSS', 'insertNode', 'removeNode', 'moveNode', 'replaceSubtree' );
		return array(
			'enforced'          => true,
			'allowedOperations' => 'widget' === $scope ? array( 'setProps', 'setStyle', 'setResponsive', 'setCustomCSS' ) : $all,
			'globalMutation'    => false,
			'description'       => 'Operations outside the exported target are rejected server-side.',
		);
	}
}
