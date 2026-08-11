<?php
/**
 * Plugin service registration.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas;

use CrescoCanvas\Admin\EditorExperience;
use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\AI\AIInterchange;
use CrescoCanvas\API\RestApi;
use CrescoCanvas\Application\BuilderArchitecture;
use CrescoCanvas\Blocks\Blocks;
use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderBootstrapResilience;
use CrescoCanvas\Builder\WebsiteBuilderCompatibility;
use CrescoCanvas\Builder\WebsiteBuilderComponentSync;
use CrescoCanvas\Builder\WebsiteBuilderComprehensiveV3;
use CrescoCanvas\Builder\WebsiteBuilderInterchange;
use CrescoCanvas\Builder\WebsiteBuilderProfessionalUx;
use CrescoCanvas\Builder\WebsiteBuilderRendererParity;
use CrescoCanvas\Builder\WebsiteBuilderRuntimeGuard;
use CrescoCanvas\Builder\WebsiteBuilderWorkflowExtensions;
use CrescoCanvas\Commercial\CommercialManager;
use CrescoCanvas\Dynamic\AdvancedDynamicData;
use CrescoCanvas\Dynamic\AdvancedQuery;
use CrescoCanvas\Dynamic\DynamicCompletion;
use CrescoCanvas\Dynamic\DynamicData;
use CrescoCanvas\Dynamic\InteractiveQuery;
use CrescoCanvas\Dynamic\QueryCache;
use CrescoCanvas\Dynamic\StructuredDynamicData;
use CrescoCanvas\Forms\FormAdministration;
use CrescoCanvas\Forms\FormBuilder;
use CrescoCanvas\Forms\FormCompletion;
use CrescoCanvas\Forms\FormEnhancements;
use CrescoCanvas\Interactions\InteractiveComponents;
use CrescoCanvas\Lifecycle\LifecycleManager;
use CrescoCanvas\Migration\Migrator;
use CrescoCanvas\Page\PageSettings;
use CrescoCanvas\Security\SecurityHardening;
use CrescoCanvas\Security\UploadSecurity;
use CrescoCanvas\Session\HistoryManager;
use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Styles\ContainerWidth;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use CrescoCanvas\Styles\StyleEngine;
use CrescoCanvas\Templates\TemplateLibrary;
use CrescoCanvas\Theme\ThemeBuilder;
use CrescoCanvas\Theme\ThemeDiagnostics;
use CrescoCanvas\Theme\ThemeSessionBridge;

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
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	/** Register plugin services once. */
	public function boot() {
		if ( $this->booted ) return;
		$this->booted = true;

		// Fail closed on downgrade before any REST/editor/domain service can write
		// a data format older than the schema already stored on this site.
		if ( Migrator::is_downgrade() ) {
			add_action( 'admin_notices', array( Migrator::class, 'render_failure_notice' ) );
			add_action( 'network_admin_notices', array( Migrator::class, 'render_failure_notice' ) );
			return;
		}

		$styles = new GlobalStyles();
		$tokens = new DesignTokens();

		( new SecurityHardening() )->register();
		( new UploadSecurity() )->register();
		( new QueryCache() )->register();
		( new LifecycleManager() )->register();
		( new CommercialManager() )->register();

		// Cresco owns the standalone visual workflow and the authoritative
		// cresco-session/v1 document. WordPress remains the host, media layer,
		// permissions system, routing layer, and native fallback environment.
		( new EditorIntegration() )->register();
		( new SessionManager() )->register();
		( new AIInterchange() )->register();
		( new HistoryManager() )->register();
		( new PageSettings() )->register();
		( new VisualEditor() )->register();
		( new EditorExperience() )->register();
		( new WebsiteBuilder() )->register();
		( new WebsiteBuilderBootstrapResilience() )->register();
		( new WebsiteBuilderRuntimeGuard() )->register();
		( new WebsiteBuilderCompatibility() )->register();
		( new WebsiteBuilderRendererParity() )->register();
		( new WebsiteBuilderProfessionalUx() )->register();
		( new WebsiteBuilderInterchange() )->register();
		( new WebsiteBuilderComponentSync() )->register();
		( new WebsiteBuilderComprehensiveV3() )->register();
		( new WebsiteBuilderWorkflowExtensions() )->register();
		// Stable architecture boundary; V2/V3 services above remain compatibility adapters.
		( new BuilderArchitecture() )->register();
		( new ContainerWidth() )->register();

		// Dashicons are used by Site/Icon/Social widgets on public Website
		// Builder Pages. WordPress does not enqueue them for logged-out visitors.
		add_action(
			'wp_enqueue_scripts',
			static function () {
				if ( ! is_singular( 'page' ) ) return;
				$post_id = get_queried_object_id();
				if ( WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;
				wp_enqueue_style( 'dashicons' );
			},
			44
		);

		// Backend/domain services stay available to the editor and frontend.
		( new RestApi() )->register();
		( new TemplateLibrary() )->register();
		( new ThemeBuilder() )->register();
		( new ThemeSessionBridge() )->register();
		( new ThemeDiagnostics() )->register();
		( new DynamicData() )->register();
		( new AdvancedDynamicData() )->register();
		( new StructuredDynamicData() )->register();
		( new AdvancedQuery() )->register();
		( new InteractiveQuery() )->register();
		( new DynamicCompletion() )->register();
		( new InteractiveComponents() )->register();
		( new FormBuilder() )->register();
		( new FormEnhancements() )->register();
		( new FormCompletion() )->register();
		( new FormAdministration() )->register();
		( new StyleEngine() )->register();
		$styles->register();
		$tokens->register();
		( new Blocks() )->register();

		add_action( 'admin_init', array( Migrator::class, 'maybe_run' ), 1 );
		add_action( 'admin_notices', array( Migrator::class, 'render_failure_notice' ) );
		add_action( 'network_admin_notices', array( Migrator::class, 'render_failure_notice' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
	}

	/** Load translations from the release package. */
	public function load_textdomain() {
		load_plugin_textdomain( 'cresco-canvas', false, dirname( plugin_basename( CRESCO_CANVAS_FILE ) ) . '/languages' );
	}

	private function __construct() {}
}
