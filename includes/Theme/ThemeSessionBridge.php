<?php
/**
 * Session-native editing bridge for Cresco Theme Builder templates.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Theme;

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderCssCompiler;
use CrescoCanvas\Builder\WebsiteRenderer;
use CrescoCanvas\Builder\WidgetCatalog;
use CrescoCanvas\Page\PageSettings;
use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemeSessionBridge {
	const PAGE_SLUG = 'cresco-canvas-theme-editor';
	const BLOCK     = 'cresco/theme-session';

	public function register() {
		add_action( 'init', array( $this, 'register_block' ), 12 );
		add_action( 'admin_menu', array( $this, 'register_editor_screen' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 120 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 34 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_active_template_assets' ), 49 );
		add_action( 'template_redirect', array( $this, 'render_preview' ), 0 );
		add_filter( 'rest_post_dispatch', array( $this, 'decorate_theme_template_responses' ), 25, 3 );
	}

	public function register_block() {
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array( 'templateId' => array( 'type' => 'integer', 'default' => 0 ) ),
				'render_callback' => array( $this, 'render_session_block' ),
			)
		);
	}

	public function register_editor_screen() {
		add_submenu_page( null, __( 'Cresco Theme Template', 'cresco-canvas' ), __( 'Cresco Theme Template', 'cresco-canvas' ), 'edit_pages', self::PAGE_SLUG, array( $this, 'render_editor_screen' ) );
	}

	public function render_editor_screen() {
		$template_id = $this->requested_template_id();
		if ( ! $this->can_edit_template_id( $template_id ) ) wp_die( esc_html__( 'You do not have permission to edit this Theme Template with Cresco Canvas.', 'cresco-canvas' ) );
		echo '<div id="cresco-canvas-standalone-editor" class="cresco-canvas-standalone-editor" aria-live="polite"><div class="cc-standalone-loading"><span class="spinner is-active" aria-hidden="true"></span><span>' . esc_html__( 'Loading Theme Template Session…', 'cresco-canvas' ) . '</span></div></div>';
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/theme-session/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_get_session' ), 'permission_callback' => array( $this, 'can_edit_template' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save_session' ), 'permission_callback' => array( $this, 'can_edit_template' ) ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/theme-context/(?P<postId>\d+)',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_context' ), 'permission_callback' => array( $this, 'can_edit_template' ) )
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/theme-page-settings/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_template' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_template' ) ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/theme-history/(?P<postId>\d+)',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => static function () { return new WP_REST_Response( array() ); }, 'permission_callback' => array( $this, 'can_edit_template' ) )
		);
	}

	public function can_edit_template( WP_REST_Request $request ) {
		return $this->can_edit_template_id( absint( $request['postId'] ?? 0 ) );
	}

	private function can_edit_template_id( $post_id ) {
		return $post_id > 0 && ThemeBuilder::POST_TYPE === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_get_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session = $this->load_session( $post_id );
		if ( ! $session ) $session = WebsiteBuilder::empty_session( $post_id );
		$json = (string) wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return new WP_REST_Response( array( 'session' => $session, 'checksum' => hash( 'sha256', $json ), 'nodeCount' => $this->count_nodes( $session['nodes'] ?? array() ), 'postTitle' => get_the_title( $post_id ), 'builder' => WebsiteBuilder::BUILDER_VERSION ) );
	}

	public function rest_save_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $payload;
		$session = WebsiteBuilder::sanitize_session( $input );
		if ( is_wp_error( $session ) ) return $session;
		$session['documentId'] = 'theme-' . $post_id;
		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_theme_session_encode', __( 'Theme Template Session could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		update_post_meta( $post_id, SessionManager::META_KEY, $json );
		update_post_meta( $post_id, WebsiteBuilder::BUILDER_META, WebsiteBuilder::BUILDER_VERSION );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => self::block_markup( $post_id ), 'post_title' => isset( $payload['postTitle'] ) ? sanitize_text_field( (string) $payload['postTitle'] ) : get_the_title( $post_id ) ) );
		return new WP_REST_Response( array( 'session' => $session, 'checksum' => hash( 'sha256', $json ), 'nodeCount' => $this->count_nodes( $session['nodes'] ?? array() ), 'savedAt' => gmdate( 'c' ), 'builder' => WebsiteBuilder::BUILDER_VERSION ) );
	}

	public function rest_context( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session = $this->load_session( $post_id );
		if ( ! $session ) $session = WebsiteBuilder::empty_session( $post_id );
		return new WP_REST_Response( array(
			'format' => 'cresco-website-builder-context/v1',
			'builder' => WebsiteBuilder::BUILDER_VERSION,
			'global' => DesignTokens::catalog( GlobalStyles::get_settings() ),
			'widgets' => WidgetCatalog::all(),
			'session' => $session,
			'postTitle' => get_the_title( $post_id ),
			'capabilities' => array( 'themeBuilder' => true, 'forms' => post_type_exists( 'cresco_submission' ), 'woocommerce' => $this->has_woocommerce(), 'acf' => function_exists( 'get_field' ) ),
			'instructions' => array( 'Return a cresco-session/v1 document for this Theme Template.', 'Use only declared widgets and structured responsive styles.', 'Keep Dynamic and WooCommerce widgets bound to the frontend query context.' ),
		) );
	}

	public function rest_page_settings() {
		$settings = PageSettings::defaults();
		return new WP_REST_Response( array( 'settings' => $settings, 'effective' => $settings, 'savedAt' => gmdate( 'c' ) ) );
	}

	public function enqueue_editor( $hook ) {
		unset( $hook );
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post_id = $this->requested_template_id();
		if ( ! $this->can_edit_template_id( $post_id ) ) return;
		$editor_js = CRESCO_CANVAS_PATH . 'build/website-builder-editor.js';
		$editor_css = CRESCO_CANVAS_PATH . 'assets/css/website-builder.css';
		if ( ! is_readable( $editor_js ) || ! is_readable( $editor_css ) ) return;

		wp_enqueue_media( array( 'post' => $post_id ) );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'cresco-canvas-website-builder', CRESCO_CANVAS_URL . 'assets/css/website-builder.css', array( 'wp-components' ), $this->asset_version( $editor_css ) );
		wp_add_inline_style( 'cresco-canvas-website-builder', 'html.wp-toolbar{padding-top:0!important}body.admin_page_' . self::PAGE_SLUG . '{overflow:hidden;margin:0!important;background:#f3f5f8}body.admin_page_' . self::PAGE_SLUG . ' #wpadminbar,body.admin_page_' . self::PAGE_SLUG . ' #adminmenumain,body.admin_page_' . self::PAGE_SLUG . ' #wpfooter{display:none!important}body.admin_page_' . self::PAGE_SLUG . ' #wpcontent,body.admin_page_' . self::PAGE_SLUG . ' #wpbody-content{margin:0!important;padding:0!important}' . GlobalStyles::css( '.cc-builder-canvas' ) . GlobalStyles::visual_css( '.cc-builder-canvas' ) );
		wp_enqueue_script( 'cresco-canvas-website-builder', CRESCO_CANVAS_URL . 'build/website-builder-editor.js', array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ), $this->asset_version( $editor_js ), true );
		wp_add_inline_script( 'cresco-canvas-website-builder', 'window.crescoWebsiteBuilderSettings=' . wp_json_encode( $this->editor_settings( $post_id ) ) . ';', 'before' );

		$this->enqueue_optional_editor_asset( 'cresco-canvas-website-builder-controls', 'build/website-builder-controls.js', 'assets/css/website-builder-controls.css', array( 'cresco-canvas-website-builder' ) );
		$this->enqueue_optional_editor_asset( 'cresco-canvas-website-builder-professional-ux', 'build/website-builder-professional-ux.js', 'assets/css/website-builder-professional-ux.css', array( 'cresco-canvas-website-builder-controls' ) );
		$fit = CRESCO_CANVAS_PATH . 'build/website-builder-preview-fit.js';
		if ( is_readable( $fit ) ) wp_enqueue_script( 'cresco-canvas-website-builder-preview-fit', CRESCO_CANVAS_URL . 'build/website-builder-preview-fit.js', array( 'cresco-canvas-website-builder-professional-ux' ), $this->asset_version( $fit ), true );
		$this->enqueue_optional_editor_asset( 'cresco-canvas-website-builder-comprehensive-v3', 'build/website-builder-comprehensive-v3.js', 'assets/css/website-builder-comprehensive-v3.css', array( 'cresco-canvas-website-builder' ) );
		wp_add_inline_script( 'cresco-canvas-website-builder-comprehensive-v3', 'window.crescoWebsiteBuilderV3Settings=' . wp_json_encode( $this->v3_settings( $post_id ) ) . ';', 'before' );
	}

	private function enqueue_optional_editor_asset( $handle, $script_rel, $style_rel, $dependencies ) {
		$script = CRESCO_CANVAS_PATH . $script_rel;
		$style = CRESCO_CANVAS_PATH . $style_rel;
		if ( is_readable( $style ) ) wp_enqueue_style( $handle, CRESCO_CANVAS_URL . $style_rel, array( 'cresco-canvas-website-builder' ), $this->asset_version( $style ) );
		if ( is_readable( $script ) ) wp_enqueue_script( $handle, CRESCO_CANVAS_URL . $script_rel, $dependencies, $this->asset_version( $script ), true );
	}

	private function editor_settings( $post_id ) {
		return array(
			'postId' => $post_id, 'postTitle' => get_the_title( $post_id ),
			'sessionPath' => '/cresco-canvas/v1/website-builder/theme-session/' . $post_id,
			'validatePath' => '/cresco-canvas/v1/website-builder/session/validate',
			'contextPath' => '/cresco-canvas/v1/website-builder/theme-context/' . $post_id,
			'optionsPath' => '/cresco-canvas/v1/website-builder/options', 'componentsPath' => '/cresco-canvas/v1/website-builder/components',
			'pageSettingsPath' => '/cresco-canvas/v1/website-builder/theme-page-settings/' . $post_id,
			'settingsPath' => '/cresco-canvas/v1/settings', 'historyPath' => '/cresco-canvas/v1/website-builder/theme-history/' . $post_id,
			'themeTemplatesPath' => '/cresco-canvas/v1/theme-templates', 'themeOptionsPath' => '/cresco-canvas/v1/theme-builder/options',
			'previewUrl' => $this->preview_url( $post_id ), 'adminPagesUrl' => admin_url( 'edit.php?post_type=' . ThemeBuilder::POST_TYPE ),
			'widgetCatalog' => WidgetCatalog::all(), 'previewWidths' => array( 'wide' => 1920, 'desktop' => 1440, 'laptop' => 1366, 'tablet' => 768, 'mobile' => 390 ),
			'canManageGlobal' => current_user_can( 'edit_theme_options' ), 'canManageComponents' => current_user_can( 'edit_pages' ),
			'builderVersion' => WebsiteBuilder::BUILDER_VERSION, 'pluginVersion' => CRESCO_CANVAS_VERSION,
		);
	}

	private function v3_settings( $post_id ) {
		return array(
			'postId' => $post_id,
			'exportPath' => '/cresco-canvas/v1/website-builder/interchange/' . $post_id . '/export',
			'previewImportPath' => '/cresco-canvas/v1/website-builder/interchange/' . $post_id . '/preview',
			'componentSyncPath' => '/cresco-canvas/v1/website-builder/components/sync',
			'diagnosticsPath' => '/cresco-canvas/v1/website-builder/v3/diagnostics/' . $post_id,
			'themeTemplatesPath' => '/cresco-canvas/v1/theme-templates', 'componentsPath' => '/cresco-canvas/v1/website-builder/components',
			'woocommerce' => $this->has_woocommerce(), 'version' => 'comprehensive-v3-theme',
		);
	}

	public function render_session_block( $attributes ) {
		$template_id = absint( $attributes['templateId'] ?? 0 );
		$session = $this->load_session( $template_id );
		if ( ! $session ) return '';
		return WebsiteRenderer::render_document( $session, get_queried_object_id() );
	}

	public function enqueue_active_template_assets() {
		if ( is_admin() ) return;
		$builder = new ThemeBuilder();
		$types = array( 'header', 'footer', $this->document_type() );
		$sessions = array();
		foreach ( array_values( array_unique( array_filter( $types ) ) ) as $type ) {
			$template = $builder->resolve( $type );
			if ( ! $template instanceof \WP_Post ) continue;
			$session = $this->load_session( $template->ID );
			if ( $session ) $sessions[ $template->ID ] = $session;
		}
		if ( ! $sessions ) return;
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'assets/css/website-builder-frontend.css', array( 'cresco-canvas-frontend' ), CRESCO_CANVAS_VERSION );
		wp_enqueue_script( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'build/website-builder-frontend.js', array(), CRESCO_CANVAS_VERSION, true );
		foreach ( $sessions as $session ) wp_add_inline_style( 'cresco-canvas-website-builder-frontend', WebsiteBuilderCssCompiler::compile( $session ) );
	}

	public function decorate_theme_template_responses( $result, $server, $request ) {
		unset( $server );
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( false === strpos( $route, '/cresco-canvas/v1/theme-templates' ) || ! $result instanceof WP_REST_Response ) return $result;
		$data = $result->get_data();
		if ( isset( $data['id'] ) ) $data = $this->decorate_template_item( $data );
		elseif ( is_array( $data ) ) foreach ( $data as $index => $item ) if ( is_array( $item ) && isset( $item['id'] ) ) $data[ $index ] = $this->decorate_template_item( $item );
		$result->set_data( $data );
		return $result;
	}

	private function decorate_template_item( $item ) {
		$id = absint( $item['id'] ?? 0 );
		if ( ! $id ) return $item;
		$item['nativeEditUrl'] = $item['editUrl'] ?? '';
		$item['crescoEditUrl'] = $this->editor_url( $id );
		$item['editUrl'] = $item['crescoEditUrl'];
		$item['sessionNative'] = (bool) $this->load_session( $id );
		return $item;
	}

	public function render_preview() {
		$template_id = isset( $_GET['cresco_theme_preview'] ) ? absint( wp_unslash( $_GET['cresco_theme_preview'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified immediately below.
		if ( ! $template_id ) return;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Explicit preview nonce.
		if ( ! $this->can_edit_template_id( $template_id ) || ! wp_verify_nonce( $nonce, 'cresco_theme_preview_' . $template_id ) ) wp_die( esc_html__( 'Invalid Theme Template preview request.', 'cresco-canvas' ) );
		$session = $this->load_session( $template_id );
		if ( ! $session ) wp_die( esc_html__( 'This Theme Template has no Cresco Session yet.', 'cresco-canvas' ) );
		wp_enqueue_style( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'assets/css/website-builder-frontend.css', array( 'cresco-canvas-frontend' ), CRESCO_CANVAS_VERSION );
		wp_add_inline_style( 'cresco-canvas-website-builder-frontend', WebsiteBuilderCssCompiler::compile( $session ) );
		wp_enqueue_script( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'build/website-builder-frontend.js', array(), CRESCO_CANVAS_VERSION, true );
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head><body <?php body_class( 'cresco-theme-session-preview' ); ?>><?php wp_body_open(); echo WebsiteRenderer::render_document( $session, get_queried_object_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WebsiteRenderer escapes widget output. ?><?php wp_footer(); ?></body></html><?php
		exit;
	}

	private function load_session( $post_id ) {
		if ( ! $post_id || ThemeBuilder::POST_TYPE !== get_post_type( $post_id ) ) return null;
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = $raw ? json_decode( $raw, true ) : null;
		$session = is_array( $decoded ) ? WebsiteBuilder::sanitize_session( $decoded ) : null;
		return is_array( $session ) ? $session : null;
	}

	private function requested_template_id() {
		return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor routing value.
	}

	private function editor_url( $post_id ) {
		return add_query_arg( array( 'page' => self::PAGE_SLUG, 'post' => absint( $post_id ) ), admin_url( 'admin.php' ) );
	}

	private function preview_url( $post_id ) {
		return wp_nonce_url( add_query_arg( 'cresco_theme_preview', absint( $post_id ), home_url( '/' ) ), 'cresco_theme_preview_' . absint( $post_id ) );
	}

	public static function block_markup( $post_id ) {
		$json = wp_json_encode( array( 'templateId' => absint( $post_id ) ), JSON_UNESCAPED_SLASHES );
		return '<!-- wp:' . self::BLOCK . ' ' . $json . ' /-->';
	}

	private function document_type() {
		if ( is_404() ) return '404';
		if ( is_search() ) return 'search';
		if ( is_archive() || is_home() ) return 'archive';
		if ( is_page() ) return 'page';
		if ( is_singular() ) return 'single';
		return '';
	}

	private function count_nodes( $nodes ) {
		$count = 0;
		foreach ( (array) $nodes as $node ) { ++$count; $count += $this->count_nodes( $node['children'] ?? array() ); }
		return $count;
	}

	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}

	private function has_woocommerce() {
		return class_exists( '\WooCommerce' ) || defined( 'WC_VERSION' ) || function_exists( 'WC' );
	}
}
