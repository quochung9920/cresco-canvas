<?php
/**
 * Cresco Canvas uninstall policy.
 *
 * User-authored posts and page body content are never removed. Plugin-owned
 * records/settings are deleted only after explicit removeDataOnUninstall opt-in.
 *
 * @package CrescoCanvas
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Lifecycle/UninstallPolicy.php';

use CrescoCanvas\Lifecycle\UninstallPolicy;

/** Normalize a path and verify it remains under a Cresco private root. */
function cresco_canvas_uninstall_path_within( $path, $root ) {
	$path = untrailingslashit( wp_normalize_path( (string) $path ) );
	$root = untrailingslashit( wp_normalize_path( (string) $root ) );
	return '' !== $root && ( $path === $root || str_starts_with( $path, $root . '/' ) );
}

/** Resolve the same outside-web-root private upload directory used at runtime. */
function cresco_canvas_uninstall_private_root() {
	$root = defined( 'CRESCO_CANVAS_PRIVATE_UPLOAD_DIR' ) ? (string) CRESCO_CANVAS_PRIVATE_UPLOAD_DIR : dirname( rtrim( ABSPATH, '/\\' ) ) . '/.cresco-canvas-private';
	return untrailingslashit( wp_normalize_path( $root ) );
}

/** Delete all records for one plugin-owned post type in bounded batches. */
function cresco_canvas_uninstall_delete_owned_post_type( $post_type ) {
	if ( ! UninstallPolicy::owns_post_type( $post_type ) ) return;
	do {
		$ids = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 100, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $ids as $post_id ) {
			if ( 'cresco_upload' === $post_type ) {
				$path = (string) get_post_meta( $post_id, '_cresco_upload_path', true );
				$root = cresco_canvas_uninstall_private_root();
				if ( $path && is_file( $path ) && cresco_canvas_uninstall_path_within( $path, $root ) ) @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			wp_delete_post( (int) $post_id, true );
		}
	} while ( count( $ids ) === 100 );
}

/** Delete all legacy Cresco-owned Media Library uploads in bounded batches. */
function cresco_canvas_uninstall_delete_legacy_uploads() {
	do {
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 100, 'fields' => 'ids', 'meta_key' => '_cresco_form_upload', 'meta_value' => '1', 'no_found_rows' => true ) );
		foreach ( $ids as $attachment_id ) wp_delete_attachment( (int) $attachment_id, true );
	} while ( count( $ids ) === 100 );
}

/** Clear all events for a hook including events with retry arguments. */
function cresco_canvas_uninstall_clear_hook( $hook ) {
	if ( function_exists( 'wp_unschedule_hook' ) ) {
		wp_unschedule_hook( $hook );
		return;
	}
	$cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
	foreach ( is_array( $cron ) ? $cron : array() as $timestamp => $hooks ) {
		if ( empty( $hooks[ $hook ] ) ) continue;
		foreach ( $hooks[ $hook ] as $event ) wp_unschedule_event( $timestamp, $hook, (array) ( $event['args'] ?? array() ) );
	}
}

/** Remove transient rows that use strictly Cresco-owned prefixes. */
function cresco_canvas_uninstall_transients() {
	global $wpdb;
	if ( ! isset( $wpdb->options ) ) return;
	$prefixes = array( '_transient_cc_rate_', '_transient_timeout_cc_rate_', '_transient_cc_once_', '_transient_timeout_cc_once_', '_transient_cc_dq_', '_transient_timeout_cc_dq_', '_transient_cresco_form_', '_transient_timeout_cresco_form_', '_transient_cresco_iq_', '_transient_timeout_cresco_iq_', '_transient_cresco_webhook_retry_', '_transient_timeout_cresco_webhook_retry_' );
	foreach ( $prefixes as $prefix ) {
		$like = $wpdb->esc_like( $prefix ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- uninstall cleanup of plugin-owned transient prefixes.
	}
}

/** Remove plugin-owned data for the current site only when explicitly requested. */
function cresco_canvas_uninstall_site_data() {
	foreach ( array( 'cresco_canvas_daily_cleanup', 'cresco_canvas_daily_retention', 'cresco_canvas_retry_webhook' ) as $hook ) cresco_canvas_uninstall_clear_hook( $hook );

	$settings = (array) get_option( 'cresco_canvas_settings', array() );
	if ( empty( $settings['removeDataOnUninstall'] ) ) return;

	// Delete only post types explicitly declared as Cresco-owned. Page/post records
	// are intentionally absent from this allowlist.
	foreach ( UninstallPolicy::owned_post_types() as $post_type ) cresco_canvas_uninstall_delete_owned_post_type( $post_type );
	cresco_canvas_uninstall_delete_legacy_uploads();

	foreach ( UninstallPolicy::owned_options() as $option ) delete_option( $option );
	foreach ( UninstallPolicy::owned_post_meta_keys() as $meta_key ) delete_post_meta_by_key( $meta_key );
	delete_metadata( 'user', 0, 'cresco_canvas_last_editor', '', true );
	cresco_canvas_uninstall_transients();
}

/** Process multisite in bounded site-list batches without crossing site tables. */
function cresco_canvas_uninstall_all_sites() {
	$offset = 0;
	do {
		$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $offset, 'orderby' => 'id', 'order' => 'ASC' ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				cresco_canvas_uninstall_site_data();
			} finally {
				restore_current_blog();
			}
		}
		$offset += count( $site_ids );
	} while ( count( $site_ids ) === 100 );
}

if ( is_multisite() ) cresco_canvas_uninstall_all_sites();
else cresco_canvas_uninstall_site_data();
