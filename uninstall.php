<?php
/**
 * Cresco Canvas uninstall policy.
 *
 * User-authored page content in post_content is never removed. Plugin-owned
 * settings, private submissions, scheduled jobs, temporary caches, and
 * Cresco-created uploads are deleted only after an administrator explicitly
 * opts in through the Global Design setting.
 *
 * @package CrescoCanvas
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Delete one bounded batch of private Cresco submissions and owned uploads. */
function cresco_canvas_delete_private_records() {
	$submission_ids = get_posts(
		array(
			'post_type'      => 'cresco_submission',
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	foreach ( $submission_ids as $submission_id ) {
		wp_delete_post( (int) $submission_id, true );
	}

	$attachment_ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'meta_key'       => '_cresco_form_upload',
			'meta_value'     => '1',
			'no_found_rows'  => true,
		)
	);
	foreach ( $attachment_ids as $attachment_id ) {
		wp_delete_attachment( (int) $attachment_id, true );
	}
}

/** Remove plugin-owned data for the current site when explicitly requested. */
function cresco_canvas_uninstall_site_data() {
	$settings = (array) get_option( 'cresco_canvas_settings', array() );

	wp_clear_scheduled_hook( 'cresco_canvas_daily_cleanup' );
	wp_clear_scheduled_hook( 'cresco_canvas_retry_webhook' );

	if ( empty( $settings['removeDataOnUninstall'] ) ) {
		return;
	}

	cresco_canvas_delete_private_records();

	$options = array(
		'cresco_canvas_settings',
		'cresco_canvas_settings_backup_v3',
		'cresco_canvas_site_kit',
		'cresco_canvas_feature_flags',
		'cresco_canvas_db_version',
		'cresco_canvas_migration_state',
		'cresco_canvas_migration_lock',
		'cresco_canvas_upload_retention_days',
		'cresco_canvas_webhook_failures',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_post_meta_by_key( '_cresco_canvas_enabled' );
	delete_post_meta_by_key( '_cresco_canvas_editor_preference' );
	delete_post_meta_by_key( '_cresco_submission_data' );
	delete_post_meta_by_key( '_cresco_form_upload' );
	delete_post_meta_by_key( '_cresco_submission_id' );
	delete_metadata( 'user', 0, 'cresco_canvas_last_editor', '', true );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		cresco_canvas_uninstall_site_data();
		restore_current_blog();
	}
} else {
	cresco_canvas_uninstall_site_data();
}
