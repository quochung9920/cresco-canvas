<?php
/**
 * Plugin Name:       Cresco Canvas
 * Plugin URI:        https://github.com/quochung9920/cresco-canvas
 * Description:       A fast, native visual website builder for WordPress.
 * Version:           1.0.0-rc.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Crescospec
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cresco-canvas
 * Domain Path:       /languages
 *
 * @package CrescoCanvas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRESCO_CANVAS_VERSION', '1.0.0-rc.1' );
define( 'CRESCO_CANVAS_SCHEMA_VERSION', 4 );
define( 'CRESCO_CANVAS_MINIMUM_WORDPRESS', '6.7' );
define( 'CRESCO_CANVAS_MINIMUM_PHP', '8.1' );
define( 'CRESCO_CANVAS_FILE', __FILE__ );
define( 'CRESCO_CANVAS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRESCO_CANVAS_URL', plugin_dir_url( __FILE__ ) );

$cresco_canvas_composer_autoloader = CRESCO_CANVAS_PATH . 'vendor/autoload.php';

if ( is_readable( $cresco_canvas_composer_autoloader ) ) {
	require_once $cresco_canvas_composer_autoloader;
} else {
	require_once CRESCO_CANVAS_PATH . 'includes/Autoloader.php';
	CrescoCanvas\Autoloader::register();
}

register_activation_hook( CRESCO_CANVAS_FILE, array( CrescoCanvas\Lifecycle\Activator::class, 'activate' ) );
register_deactivation_hook( CRESCO_CANVAS_FILE, array( CrescoCanvas\Lifecycle\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$requirements = new CrescoCanvas\Requirements();
		if ( ! $requirements->is_compatible() ) {
			add_action( 'admin_notices', array( $requirements, 'render_admin_notice' ) );
			return;
		}
		CrescoCanvas\Plugin::instance()->boot();
		( new CrescoCanvas\Builder\ProfessionalWidgets() )->register();
		( new CrescoCanvas\Builder\ProfessionalWidgetExpansion() )->register();
		( new CrescoCanvas\Builder\StudioDimensionControls() )->register();
		( new CrescoCanvas\Builder\StudioStructureLayout() )->register();
	}
);
