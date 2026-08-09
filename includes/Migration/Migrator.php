<?php
/**
 * Versioned, idempotent data migrations and downgrade protection.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Migration;

use CrescoCanvas\Styles\GlobalStyles;
use CrescoCanvas\Support\FeatureFlags;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Migrator {
	const VERSION_OPTION  = 'cresco_canvas_db_version';
	const STATE_OPTION    = 'cresco_canvas_migration_state';
	const LOCK_OPTION     = 'cresco_canvas_migration_lock';
	const BACKUP_OPTION   = 'cresco_canvas_settings_backup_v3';
	const SNAPSHOT_OPTION = 'cresco_canvas_migration_backup';
	const LOCK_TTL        = 300;

	/** Run pending migrations only when the installed plugin understands the stored schema. */
	public static function maybe_run() {
		if ( self::is_downgrade() ) return;
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= CRESCO_CANVAS_SCHEMA_VERSION ) return;
		self::run();
	}

	/** Return true when site data was written by a newer plugin/schema. */
	public static function is_downgrade( $stored_version = null ) {
		$stored_version = null === $stored_version ? (int) get_option( self::VERSION_OPTION, 0 ) : (int) $stored_version;
		return $stored_version > (int) CRESCO_CANVAS_SCHEMA_VERSION;
	}

	/** Return a safe compatibility error without leaking migration internals. */
	public static function downgrade_error() {
		return new WP_Error(
			'cresco_canvas_schema_newer',
			__( 'Cresco Canvas data was created by a newer plugin version. This older version is paused to avoid writing an incompatible format.', 'cresco-canvas' ),
			array( 'status' => 409, 'storedSchema' => (int) get_option( self::VERSION_OPTION, 0 ), 'supportedSchema' => (int) CRESCO_CANVAS_SCHEMA_VERSION )
		);
	}

	/** Execute pending migrations, persisting each completed version for safe retry. */
	public static function run() {
		if ( self::is_downgrade() ) return self::downgrade_error();
		if ( ! self::acquire_lock() ) {
			return new WP_Error( 'cresco_canvas_migration_locked', __( 'Another Cresco Canvas migration is already running. Try again shortly.', 'cresco-canvas' ), array( 'status' => 409 ) );
		}

		$current_version = (int) get_option( self::VERSION_OPTION, 0 );
		$migrations      = self::migrations();
		self::ensure_backup( $current_version );
		try {
			for ( $version = $current_version + 1; $version <= CRESCO_CANVAS_SCHEMA_VERSION; $version++ ) {
				if ( ! isset( $migrations[ $version ] ) ) {
					throw new \RuntimeException( 'Missing Cresco migration step.' );
				}
				/** Allows deterministic failure testing and operational instrumentation. */
				do_action( 'cresco_canvas_before_migration', $version );
				call_user_func( $migrations[ $version ] );
				update_option( self::VERSION_OPTION, $version, false );
				do_action( 'cresco_canvas_after_migration', $version );
			}
			update_option( self::STATE_OPTION, array( 'status' => 'complete', 'version' => CRESCO_CANVAS_SCHEMA_VERSION, 'finishedAt' => gmdate( DATE_ATOM ) ), false );
		} catch ( Throwable $error ) {
			$persisted = (int) get_option( self::VERSION_OPTION, 0 );
			$fingerprint = substr( hash( 'sha256', get_class( $error ) . '|' . (string) $error->getCode() . '|' . (string) $persisted ), 0, 16 );
			update_option( self::STATE_OPTION, array( 'status' => 'failed', 'version' => $persisted, 'failedAt' => gmdate( DATE_ATOM ), 'errorCode' => $fingerprint ), false );
			self::release_lock();
			return new WP_Error( 'cresco_canvas_migration_failed', __( 'Cresco Canvas could not update its data safely. No page content was changed.', 'cresco-canvas' ), array( 'status' => 500, 'retryable' => true, 'errorCode' => $fingerprint ) );
		}
		self::release_lock();
		return true;
	}

	/** Render safe migration/downgrade guidance to plugin administrators. */
	public static function render_failure_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) return;
		if ( self::is_downgrade() ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html__( 'Cresco Canvas compatibility mode is active.', 'cresco-canvas' ),
				esc_html__( 'The database schema is newer than this plugin. Restore the compatible plugin version or a pre-upgrade backup before making Cresco changes.', 'cresco-canvas' )
			);
			return;
		}
		$state = (array) get_option( self::STATE_OPTION, array() );
		if ( 'failed' !== ( $state['status'] ?? '' ) ) return;
		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s %3$s</p></div>',
			esc_html__( 'Cresco Canvas migration is paused.', 'cresco-canvas' ),
			esc_html__( 'Page content was not modified. Resolve the migration error and retry; completed migration steps will not be repeated.', 'cresco-canvas' ),
			! empty( $state['errorCode'] ) ? esc_html( sprintf( __( 'Reference: %s', 'cresco-canvas' ), $state['errorCode'] ) ) : ''
		);
	}

	/** Persist a one-time pre-migration snapshot of Cresco-owned settings only. */
	private static function ensure_backup( $current_version ) {
		if ( get_option( self::SNAPSHOT_OPTION, false ) ) return;
		add_option(
			self::SNAPSHOT_OPTION,
			array(
				'dbVersion' => (int) $current_version,
				'targetVersion' => (int) CRESCO_CANVAS_SCHEMA_VERSION,
				'capturedAt' => gmdate( DATE_ATOM ),
				'settings' => (array) get_option( 'cresco_canvas_settings', array() ),
			),
			'',
			false
		);
	}

	private static function migrations() {
		return array(
			1 => array( self::class, 'migrate_to_version_one' ),
			2 => array( self::class, 'migrate_to_version_two' ),
			3 => array( self::class, 'migrate_to_version_three' ),
			4 => array( self::class, 'migrate_to_version_four' ),
		);
	}

	private static function migrate_to_version_one() {
		$settings = GlobalStyles::sanitize_settings( (array) get_option( 'cresco_canvas_settings', array() ) );
		update_option( 'cresco_canvas_settings', $settings, false );
		add_option( FeatureFlags::OPTION_NAME, FeatureFlags::defaults(), '', false );
	}

	private static function migrate_to_version_two() {
		$settings = GlobalStyles::sanitize_settings( (array) get_option( 'cresco_canvas_settings', array() ) );
		update_option( 'cresco_canvas_settings', $settings, false );
		delete_post_meta_by_key( '_cresco_canvas_editor_preference' );
		delete_metadata( 'user', 0, 'cresco_canvas_last_editor', '', true );
	}

	private static function migrate_to_version_three() {
		$legacy   = (array) get_option( 'cresco_canvas_settings', array() );
		$settings = GlobalStyles::sanitize_settings( $legacy );
		update_option( 'cresco_canvas_settings', $settings, false );
	}

	private static function migrate_to_version_four() {
		$legacy = (array) get_option( 'cresco_canvas_settings', array() );
		if ( ! get_option( self::BACKUP_OPTION, false ) ) add_option( self::BACKUP_OPTION, $legacy, '', false );
		$settings = GlobalStyles::sanitize_settings( $legacy );
		update_option( 'cresco_canvas_settings', $settings, false );
	}

	private static function acquire_lock() {
		$lock_time = (int) get_option( self::LOCK_OPTION, 0 );
		if ( $lock_time > 0 && ( time() - $lock_time ) > self::LOCK_TTL ) delete_option( self::LOCK_OPTION );
		return add_option( self::LOCK_OPTION, time(), '', false );
	}

	private static function release_lock() {
		delete_option( self::LOCK_OPTION );
	}
}
