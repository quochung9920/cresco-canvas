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
	/** Deactivation preserves data and clears only locks/background work. */
	public static function deactivate( $network_wide = false ) {
		if ( $network_wide && is_multisite() ) LifecycleManager::for_each_site( array( LifecycleManager::class, 'deactivate_current_site' ) );
		else LifecycleManager::deactivate_current_site();
	}
}
