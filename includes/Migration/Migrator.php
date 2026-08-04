<?php
/**
 * Versioned, idempotent data migrations.
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
	const VERSION_OPTION = 'cresco_canvas_db_version';
	const STATE_OPTION   = 'cresco_canvas_migration_state';
	const LOCK_OPTION    = 'cresco_canvas_migration_lock';
	const LOCK_TTL       = 300;

	/**
	 * Run migrations when the stored schema is behind the code schema.
	 */
	public static function maybe_run() {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= CRESCO_CANVAS_SCHEMA_VERSION ) {
			return;
		}

		self::run();
	}

	/**
	 * Run all pending migrations in order.
	 *
	 * @return true|WP_Error
	 */
	public static function run() {
		if ( ! self::acquire_lock() ) {
			return new WP_Error(
				'cresco_canvas_migration_locked',
				__( 'Another Cresco Canvas migration is already running. Try again shortly.', 'cresco-canvas' )
			);
		}

		$current_version = (int) get_option( self::VERSION_OPTION, 0 );
		$migrations      = self::migrations();

		try {
			for ( $version = $current_version + 1; $version <= CRESCO_CANVAS_SCHEMA_VERSION; $version++ ) {
				if ( ! isset( $migrations[ $version ] ) ) {
					throw new \RuntimeException(
						sprintf(
							/* translators: %d: missing schema version. */
							__( 'Migration for schema version %d is missing.', 'cresco-canvas' ),
							$version
						)
					);
				}

				call_user_func( $migrations[ $version ] );
				update_option( self::VERSION_OPTION, $version, false );
			}

			update_option(
				self::STATE_OPTION,
				array(
					'status'     => 'complete',
					'version'    => CRESCO_CANVAS_SCHEMA_VERSION,
					'finishedAt' => gmdate( DATE_ATOM ),
				),
				false
			);
		} catch ( Throwable $error ) {
			update_option(
				self::STATE_OPTION,
				array(
					'status'   => 'failed',
					'version'  => $current_version,
					'failedAt' => gmdate( DATE_ATOM ),
					'error'    => sanitize_text_field( $error->getMessage() ),
				),
				false
			);

			self::release_lock();

			return new WP_Error(
				'cresco_canvas_migration_failed',
				__( 'Cresco Canvas could not update its data safely. No page content was changed.', 'cresco-canvas' ),
				array( 'exception' => $error->getMessage() )
			);
		}

		self::release_lock();

		return true;
	}

	/**
	 * Render an actionable notice for a stored migration failure.
	 */
	public static function render_failure_notice() {
		$state = (array) get_option( self::STATE_OPTION, array() );

		if ( 'failed' !== ( $state['status'] ?? '' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Cresco Canvas migration is paused.', 'cresco-canvas' ),
			esc_html__( 'Page content was not modified. Review the debug log and retry after resolving the reported error.', 'cresco-canvas' )
		);
	}

	/**
	 * Get schema migrations.
	 *
	 * @return callable[]
	 */
	private static function migrations() {
		return array(
			1 => array( self::class, 'migrate_to_version_one' ),
			2 => array( self::class, 'migrate_to_version_two' ),
		);
	}

	/**
	 * Normalize legacy 0.1.x options without touching post content.
	 */
	private static function migrate_to_version_one() {
		$legacy_settings = (array) get_option( 'cresco_canvas_settings', array() );
		$settings        = GlobalStyles::sanitize_settings( $legacy_settings );

		update_option( 'cresco_canvas_settings', $settings, false );
		add_option( FeatureFlags::OPTION_NAME, FeatureFlags::defaults(), '', false );
	}

	/**
	 * Remove the retired dual-editor preferences without touching Page content.
	 */
	private static function migrate_to_version_two() {
		$settings = GlobalStyles::sanitize_settings( (array) get_option( 'cresco_canvas_settings', array() ) );

		update_option( 'cresco_canvas_settings', $settings, false );
		delete_post_meta_by_key( '_cresco_canvas_editor_preference' );
		delete_metadata( 'user', 0, 'cresco_canvas_last_editor', '', true );
	}

	/**
	 * Acquire an option-backed migration lock.
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		$lock_time = (int) get_option( self::LOCK_OPTION, 0 );

		if ( $lock_time > 0 && ( time() - $lock_time ) > self::LOCK_TTL ) {
			delete_option( self::LOCK_OPTION );
		}

		return add_option( self::LOCK_OPTION, time(), '', false );
	}

	/**
	 * Release the migration lock.
	 */
	private static function release_lock() {
		delete_option( self::LOCK_OPTION );
	}
}
