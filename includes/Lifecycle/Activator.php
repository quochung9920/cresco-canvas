<?php
/**
 * Plugin activation.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Lifecycle;

use CrescoCanvas\Requirements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	/** Validate requirements, migrate data, and schedule each activated site. */
	public static function activate( $network_wide = false ) {
		( new Requirements() )->assert_compatible();
		$result = $network_wide && is_multisite()
			? LifecycleManager::for_each_site( array( LifecycleManager::class, 'initialize_current_site' ) )
			: LifecycleManager::initialize_current_site();
		if ( is_wp_error( $result ) ) {
			deactivate_plugins( plugin_basename( CRESCO_CANVAS_FILE ), true, (bool) $network_wide );
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Cresco Canvas activation failed', 'cresco-canvas' ),
				array( 'back_link' => true )
			);
		}
	}
}
