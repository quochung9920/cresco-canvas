<?php
/**
 * Explicit purge-only lifecycle cleanup for Website Builder data.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderLifecycle {
	/** Remove Website Builder-owned data only when the parent uninstall policy is purge. */
	public static function erase() {
		if ( is_multisite() ) {
			$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			foreach ( $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::erase_site();
				restore_current_blog();
			}
			return;
		}
		self::erase_site();
	}

	private static function erase_site() {
		$component_ids = get_posts(
			array(
				'post_type'      => WebsiteBuilder::COMPONENT_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $component_ids as $component_id ) wp_delete_post( (int) $component_id, true );

		global $wpdb;
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => WebsiteBuilder::BUILDER_META ), array( '%s' ) );
	}

	private function __construct() {}
}
