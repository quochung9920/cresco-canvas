<?php

namespace CrescoCanvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		require_once CRESCO_CANVAS_PATH . 'includes/class-admin.php';
		require_once CRESCO_CANVAS_PATH . 'includes/class-rest-api.php';
		require_once CRESCO_CANVAS_PATH . 'includes/class-global-styles.php';
		require_once CRESCO_CANVAS_PATH . 'includes/class-blocks.php';

		( new Admin() )->register();
		( new Rest_API() )->register();
		( new Global_Styles() )->register();
		( new Blocks() )->register();
	}
}
