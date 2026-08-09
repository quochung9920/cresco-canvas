<?php
/**
 * Per-site lifecycle coordination, scheduling, upgrades, and multisite support.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Lifecycle;

use CrescoCanvas\Migration\Migrator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LifecycleManager {
	const SITE_BATCH = 100;

	/** Register runtime lifecycle hooks. */
	public function register() {
		add_action( 'wp_initialize_site', array( $this, 'initialize_new_site' ), 20, 1 );
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 20, 2 );
	}

	/** Initialize one current blog with migrations and bounded recurring jobs. */
	public static function initialize_current_site() {
		$result = Migrator::run();
		if ( is_wp_error( $result ) ) return $result;
		self::schedule_current_site();
		return true;
	}

	/** Preserve data while removing locks and scheduled background work. */
	public static function deactivate_current_site() {
		delete_option( Migrator::LOCK_OPTION );
		foreach ( array( 'cresco_canvas_daily_cleanup', 'cresco_canvas_daily_retention', 'cresco_canvas_retry_webhook' ) as $hook ) self::clear_scheduled_hook( $hook );
		do_action( 'cresco_canvas_deactivated' );
	}

	/** Schedule periodic privacy/retention work idempotently. */
	public static function schedule_current_site() {
		if ( ! wp_next_scheduled( 'cresco_canvas_daily_cleanup' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cresco_canvas_daily_cleanup' );
		if ( ! wp_next_scheduled( 'cresco_canvas_daily_retention' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cresco_canvas_daily_retention' );
	}

	/** Apply a callback to every site in bounded site-list batches. */
	public static function for_each_site( $callback ) {
		if ( ! is_multisite() ) return call_user_func( $callback );
		$offset = 0;
		do {
			$sites = get_sites( array( 'number' => self::SITE_BATCH, 'offset' => $offset, 'fields' => 'ids', 'orderby' => 'id', 'order' => 'ASC' ) );
			foreach ( $sites as $site_id ) {
				switch_to_blog( absint( $site_id ) );
				try {
					$result = call_user_func( $callback );
				} finally {
					restore_current_blog();
				}
				if ( is_wp_error( $result ) ) return $result;
			}
			$offset += count( $sites );
		} while ( count( $sites ) === self::SITE_BATCH );
		return true;
	}

	/** Initialize sites created after network activation. */
	public function initialize_new_site( $new_site ) {
		if ( ! self::is_network_active() || ! is_object( $new_site ) || empty( $new_site->blog_id ) ) return;
		switch_to_blog( absint( $new_site->blog_id ) );
		try {
			self::initialize_current_site();
		} finally {
			restore_current_blog();
		}
	}

	/** Run schema work after a Cresco plugin upgrade, including network sites. */
	public function after_upgrade( $upgrader, $options ) {
		unset( $upgrader );
		if ( 'plugin' !== ( $options['type'] ?? '' ) ) return;
		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array( $options['plugin'] ?? '' );
		if ( ! in_array( plugin_basename( CRESCO_CANVAS_FILE ), $plugins, true ) ) return;
		if ( self::is_network_active() ) self::for_each_site( array( self::class, 'initialize_current_site' ) );
		else self::initialize_current_site();
	}

	/** Determine network activation without assuming plugin.php is loaded. */
	public static function is_network_active() {
		if ( ! is_multisite() ) return false;
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		return is_plugin_active_for_network( plugin_basename( CRESCO_CANVAS_FILE ) );
	}

	/** Clear all scheduled events for a hook, including events with retry arguments. */
	public static function clear_scheduled_hook( $hook ) {
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
}
