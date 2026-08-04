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
use CrescoCanvas\Dynamic\DynamicData;
use CrescoCanvas\Dynamic\EditorAssets as DynamicEditorAssets;
use CrescoCanvas\Migration\Migrator;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use CrescoCanvas\Templates\EditorAssets as TemplateEditorAssets;
use CrescoCanvas\Templates\TemplateLibrary;
use CrescoCanvas\Theme\EditorAssets as ThemeEditorAssets;
use CrescoCanvas\Theme\ThemeBuilder;
use CrescoCanvas\Theme\ThemeDiagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/** @var Plugin|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	/** @return Plugin */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register plugin services once. */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		$styles        = new GlobalStyles();
		$tokens        = new DesignTokens();

		( new EditorIntegration() )->register();
		( new RestApi() )->register();
		( new TemplateLibrary() )->register();
		( new TemplateEditorAssets() )->register();
		( new ThemeBuilder() )->register();
		( new ThemeDiagnostics() )->register();
		( new ThemeEditorAssets() )->register();
		( new DynamicData() )->register();
		( new DynamicEditorAssets() )->register();
		$styles->register();
		$tokens->register();
		( new Blocks() )->register();

		add_action( 'admin_init', array( Migrator::class, 'maybe_run' ), 1 );
		add_action( 'admin_notices', array( Migrator::class, 'render_failure_notice' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
	}

	/** Load translations from the release package. */
	public function load_textdomain() {
		load_plugin_textdomain( 'cresco-canvas', false, dirname( plugin_basename( CRESCO_CANVAS_FILE ) ) . '/languages' );
	}

	private function __construct() {}
}
