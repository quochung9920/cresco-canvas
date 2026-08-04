<?php
/**
 * Cresco Canvas uninstall policy.
 *
 * User content in post_content is never removed. Plugin-owned settings and
 * metadata are deleted only after an administrator explicitly opts in.
 *
 * @package CrescoCanvas
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin-owned data for the current site when explicitly requested.
 */
function cresco_canvas_uninstall_site_data() {
	$settings = (array) get_option( 'cresco_canvas_settings', array() );

	if ( empty( $settings['removeDataOnUninstall'] ) ) {
		return;
	}

	delete_option( 'cresco_canvas_settings' );
	delete_option( 'cresco_canvas_feature_flags' );
	delete_option( 'cresco_canvas_db_version' );
	delete_option( 'cresco_canvas_migration_state' );
	delete_option( 'cresco_canvas_migration_lock' );

	delete_post_meta_by_key( '_cresco_canvas_enabled' );
	// Clean legacy 0.2 dual-editor data retained for upgrade compatibility.
	delete_post_meta_by_key( '_cresco_canvas_editor_preference' );
	delete_metadata( 'user', 0, 'cresco_canvas_last_editor', '', true );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		cresco_canvas_uninstall_site_data();
		restore_current_blog();
	}
} else {
	cresco_canvas_uninstall_site_data();
}
