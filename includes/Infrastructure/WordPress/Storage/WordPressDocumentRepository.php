<?php
/**
 * WordPress storage adapter for Cresco documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Infrastructure\WordPress\Storage;

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Core\Storage\DocumentRepository;
use CrescoCanvas\Session\SessionManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WordPressDocumentRepository implements DocumentRepository {
	public function load( $document_id ) {
		$document_id = absint( $document_id );
		if ( ! $document_id ) return null;
		$raw = (string) get_post_meta( $document_id, SessionManager::META_KEY, true );
		if ( '' === $raw ) return null;
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) return new WP_Error( 'cresco_document_storage_decode', __( 'Stored Cresco document JSON is invalid.', 'cresco-canvas' ), array( 'status' => 500 ) );
		return WebsiteBuilder::sanitize_session( $decoded );
	}

	public function type( $document_id ) {
		$document_id = absint( $document_id );
		$post_type = $document_id ? (string) get_post_type( $document_id ) : '';
		if ( 'cresco_component' === $post_type ) return 'component';
		if ( 'cresco_template' === $post_type ) {
			$type = sanitize_key( (string) get_post_meta( $document_id, '_cresco_template_type', true ) );
			return in_array( $type, array( 'header', 'footer', 'single', 'page', 'archive', 'search', '404' ), true ) ? $type : 'single';
		}
		return 'page';
	}

	public function save( $document_id, $session ) {
		$document_id = absint( $document_id );
		if ( ! $document_id || ! current_user_can( 'edit_post', $document_id ) ) return new WP_Error( 'cresco_document_storage_permission', __( 'You cannot save this Cresco document.', 'cresco-canvas' ), array( 'status' => 403 ) );
		$session = WebsiteBuilder::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_document_storage_encode', __( 'The Cresco document could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		update_post_meta( $document_id, SessionManager::META_KEY, $json );
		update_post_meta( $document_id, WebsiteBuilder::BUILDER_META, WebsiteBuilder::BUILDER_VERSION );
		return $session;
	}
}
