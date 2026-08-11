<?php
/**
 * Persistence port for Cresco visual documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface DocumentRepository {
	/** Load a sanitized cresco-session/v1 document or null when none exists. */
	public function load( $document_id );

	/** Resolve the portable Cresco document type for one stored document. */
	public function type( $document_id );

	/** Persist a sanitized Session and return the stored Session or WP_Error. */
	public function save( $document_id, $session );

	/** Return the canonical checksum for the currently persisted document. */
	public function checksum( $document_id );

	/** Verify that the persisted document still matches an expected checksum. */
	public function verify( $document_id, $expected_checksum );
}
