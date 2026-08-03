<?php
/**
 * Plugin Name:       Cresco Canvas
 * Plugin URI:        https://github.com/quochung9920/cresco-canvas
 * Description:       A fast, native visual website builder for WordPress.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Crescospec
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cresco-canvas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRESCO_CANVAS_VERSION', '0.1.0' );
define( 'CRESCO_CANVAS_FILE', __FILE__ );
define( 'CRESCO_CANVAS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRESCO_CANVAS_URL', plugin_dir_url( __FILE__ ) );

require_once CRESCO_CANVAS_PATH . 'includes/class-plugin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		CrescoCanvas\Plugin::instance()->boot();
	}
);
