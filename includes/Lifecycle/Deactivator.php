<?php
/**
 * Plugin deactivation.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Lifecycle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {
	/**
	 * Deactivation deliberately preserves settings, metadata, and content.
	 */
	public static function deactivate() {
		delete_option( 'cresco_canvas_migration_lock' );
		do_action( 'cresco_canvas_deactivated' );
	}
}

