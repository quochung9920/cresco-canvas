<?php
/**
 * Plugin activation.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Lifecycle;

use CrescoCanvas\Migration\Migrator;
use CrescoCanvas\Requirements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	/**
	 * Validate requirements and run idempotent migrations.
	 */
	public static function activate() {
		( new Requirements() )->assert_compatible();

		$result = Migrator::run();

		if ( is_wp_error( $result ) ) {
			deactivate_plugins( plugin_basename( CRESCO_CANVAS_FILE ) );

			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Cresco Canvas migration failed', 'cresco-canvas' ),
				array( 'back_link' => true )
			);
		}
	}
}

