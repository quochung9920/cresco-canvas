<?php
/**
 * Stable document envelope for every Cresco visual document.
 *
 * Storage remains cresco-session/v1 during the consolidation period. The
 * document envelope adds a durable documentType without forcing a destructive
 * migration of existing Page, Theme, Component, or Loop content.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Document;

use CrescoCanvas\Builder\WebsiteBuilder;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Document {
	const SCHEMA  = 'cresco-document/v1';
	const VERSION = 1;
	const TYPES   = array( 'page', 'header', 'footer', 'single', 'archive', 'search', '404', 'loop-item', 'component', 'woo-single', 'woo-archive', 'popup' );

	/** Wrap a validated Session in the stable document envelope. */
	public static function from_session( $session, $document_type = 'page', $metadata = array() ) {
		$session = WebsiteBuilder::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$document_type = self::normalize_type( $document_type );
		if ( ! $document_type ) {
			return new WP_Error( 'cresco_document_type', __( 'Unsupported Cresco document type.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		return array(
			'schema'       => self::SCHEMA,
			'version'      => self::VERSION,
			'documentId'   => (string) ( $session['documentId'] ?? '' ),
			'documentType' => $document_type,
			'metadata'     => is_array( $metadata ) ? $metadata : array(),
			'session'      => $session,
		);
	}

	/** Accept either a document envelope or a raw cresco-session/v1 object. */
	public static function session( $value ) {
		if ( is_array( $value ) && self::SCHEMA === ( $value['schema'] ?? '' ) && isset( $value['session'] ) ) {
			$value = $value['session'];
		}
		return WebsiteBuilder::sanitize_session( $value );
	}

	public static function normalize_type( $type ) {
		$type = sanitize_key( (string) $type );
		return in_array( $type, self::TYPES, true ) ? $type : '';
	}
}
