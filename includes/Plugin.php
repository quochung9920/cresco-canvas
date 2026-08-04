<?php
/**
 * Plugin service registration.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas;

use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\API\RestApi;
use CrescoCanvas\Blocks\Blocks;
use CrescoCanvas\Migration\Migrator;
use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether services have been registered.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register plugin services once.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$styles = new GlobalStyles();

		( new EditorIntegration() )->register();
		( new RestApi() )->register();
		$styles->register();
		( new Blocks() )->register();

		add_action( 'admin_init', array( Migrator::class, 'maybe_run' ), 1 );
		add_action( 'admin_notices', array( Migrator::class, 'render_failure_notice' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
	}

	/**
	 * Load translations from the release package.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cresco-canvas', false, dirname( plugin_basename( CRESCO_CANVAS_FILE ) ) . '/languages' );
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}
}
