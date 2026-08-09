<?php
/**
 * Declarative ownership boundary used by uninstall and lifecycle tests.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UninstallPolicy {
	/** Cresco-owned records that may be deleted only after explicit cleanup opt-in. */
	public static function owned_post_types() {
		return array( 'cresco_submission', 'cresco_upload', 'cresco_revision' );
	}

	/** Cresco-owned metadata; page records themselves are never owned. */
	public static function owned_post_meta_keys() {
		return array(
			'_cresco_canvas_document',
			'_cresco_canvas_enabled',
			'_cresco_canvas_page_settings',
			'_cresco_canvas_editor_preference',
			'_cresco_submission_data',
			'_cresco_form_id',
			'_cresco_delete_after',
			'_cresco_form_upload',
			'_cresco_submission_id',
			'_cresco_revision_document',
			'_cresco_revision_checksum',
			'_cresco_upload_path',
			'_cresco_upload_name',
			'_cresco_upload_mime',
			'_cresco_upload_size',
			'_cresco_upload_form_id',
			'_cresco_upload_delete_after',
			'_cresco_privacy_erase_pending',
		);
	}

	/** Site options exclusively owned by Cresco Canvas. */
	public static function owned_options() {
		return array(
			'cresco_canvas_settings',
			'cresco_canvas_settings_backup_v3',
			'cresco_canvas_migration_backup',
			'cresco_canvas_site_kit',
			'cresco_canvas_feature_flags',
			'cresco_canvas_db_version',
			'cresco_canvas_migration_state',
			'cresco_canvas_migration_lock',
			'cresco_canvas_upload_retention_days',
			'cresco_canvas_webhook_failures',
			'cresco_canvas_query_cache_generation',
		);
	}

	public static function owns_post_type( $post_type ) {
		return in_array( (string) $post_type, self::owned_post_types(), true );
	}
}
