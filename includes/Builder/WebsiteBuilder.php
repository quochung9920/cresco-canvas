<?php
/**
 * Unified professional Website Builder layer for Cresco Session documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\Admin\VisualEditor;
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

final class WebsiteBuilder {
	const BUILDER_VERSION = 'website-core/v1';
	const BUILDER_META    = '_cresco_canvas_builder_version';
	const COMPONENT_TYPE  = 'cresco_component';
	const COMPONENT_META  = '_cresco_component_node';
	const MAX_NODES       = 1000;
	const MAX_DEPTH       = 16;
	const MAX_CUSTOM_CSS  = 16000;

	/** Register REST, persistence, rendering, and the standalone Website Builder UI. */
	public function register() {
		add_action( 'init', array( $this, 'register_storage' ), 6 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 120 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 45 );
		add_filter( 'the_content', array( $this, 'render_frontend_content' ), 25 );
	}

	/** Register builder version metadata and reusable component storage. */
	public function register_storage() {
		register_post_meta(
			'page',
			self::BUILDER_META,
			array(
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);

		register_post_type(
			self::COMPONENT_TYPE,
			array(
				'label'           => __( 'Cresco Components', 'cresco-canvas' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'revisions' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			)
		);

		register_post_meta(
			self::COMPONENT_TYPE,
			self::COMPONENT_META,
			array(
				'single'        => true,
				'type'          => 'string',
				'show_in_rest'  => false,
				'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}

	/** Register Website Builder REST endpoints without replacing legacy routes. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/session/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_get_session' ), 'permission_callback' => array( $this, 'can_edit_page' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save_session' ), 'permission_callback' => array( $this, 'can_edit_page' ) ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/session/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_validate_session' ),
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/context/(?P<postId>\d+)',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_context' ), 'permission_callback' => array( $this, 'can_edit_page' ) )
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/options',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_options' ), 'permission_callback' => static function () { return current_user_can( 'edit_pages' ); } )
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/components',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_components' ), 'permission_callback' => static function () { return current_user_can( 'edit_pages' ); } ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save_component' ), 'permission_callback' => static function () { return current_user_can( 'edit_pages' ); } ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/components/(?P<id>\d+)',
			array(
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'rest_save_component' ), 'permission_callback' => array( $this, 'can_edit_component' ) ),
				array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'rest_delete_component' ), 'permission_callback' => array( $this, 'can_edit_component' ) ),
			)
		);
	}

	public function can_edit_page( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function can_edit_component( WP_REST_Request $request ) {
		$id = absint( $request['id'] ?? 0 );
		return $id > 0 && self::COMPONENT_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}

	public function rest_get_session( WP_REST_Request $request ) {
		return new WP_REST_Response( $this->session_payload( absint( $request['postId'] ) ) );
	}

	public function rest_save_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input   = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $payload;
		$session = self::sanitize_session( $input );
		if ( is_wp_error( $session ) ) return $session;

		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_builder_encode_failed', __( 'The Website Builder document could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );

		update_post_meta( $post_id, SessionManager::META_KEY, $json );
		update_post_meta( $post_id, self::BUILDER_META, self::BUILDER_VERSION );
		update_post_meta( $post_id, EditorIntegration::ENABLED_META, true );
		if ( isset( $payload['postTitle'] ) ) {
			$title = sanitize_text_field( (string) $payload['postTitle'] );
			if ( '' !== $title && $title !== get_the_title( $post_id ) ) wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		}

		return new WP_REST_Response( array(
			'session'   => $session,
			'checksum'  => hash( 'sha256', $json ),
			'nodeCount' => self::count_nodes( $session['nodes'] ),
			'savedAt'   => gmdate( 'c' ),
			'builder'   => self::BUILDER_VERSION,
		) );
	}

	public function rest_validate_session( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();
		$input   = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $payload;
		$session = self::sanitize_session( $input );
		if ( is_wp_error( $session ) ) return $session;
		$json = (string) wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return new WP_REST_Response( array( 'valid' => true, 'session' => $session, 'nodeCount' => self::count_nodes( $session['nodes'] ), 'checksum' => hash( 'sha256', $json ) ) );
	}

	/** Return one complete, portable context for AI and editor tooling. */
	public function rest_context( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		return new WP_REST_Response( array(
			'format'      => 'cresco-website-builder-context/v1',
			'builder'     => self::BUILDER_VERSION,
			'global'      => DesignTokens::catalog( GlobalStyles::get_settings() ),
			'widgets'     => WidgetCatalog::all(),
			'session'     => $this->session_payload( $post_id )['session'],
			'postTitle'   => get_the_title( $post_id ),
			'capabilities'=> array(
				'themeBuilder' => post_type_exists( 'cresco_template' ),
				'forms'        => post_type_exists( 'cresco_submission' ),
				'woocommerce'  => class_exists( 'WooCommerce' ),
				'acf'          => function_exists( 'get_field' ),
			),
			'instructions' => array(
				'Return a complete cresco-session/v1 object when replacing a document.',
				'Use only widgets declared in widgets and keep node ids stable and unique.',
				'Prefer structured props, styles, responsive overrides, and states before Custom CSS.',
				'Prefer Global Design references such as {colors.primary}, {spacing.lg}, and {radius.md}.',
				'Do not introduce scripts, arbitrary HTML, shortcodes, iframes, external CSS imports, or global selectors.',
			),
		) );
	}

	/** Return safe selector options for dynamic, navigation, and commerce widgets. */
	public function rest_options() {
		$menus = array_values( array_map( static function ( $menu ) { return array( 'id' => (int) $menu->term_id, 'label' => (string) $menu->name ); }, wp_get_nav_menus() ) );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$post_types = array_values( array_map( static function ( $item ) { return array( 'slug' => $item->name, 'label' => $item->labels->singular_name ); }, $post_types ) );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$taxonomies = array_values( array_map( static function ( $item ) { return array( 'slug' => $item->name, 'label' => $item->labels->singular_name ); }, $taxonomies ) );
		return new WP_REST_Response( array(
			'menus'       => $menus,
			'postTypes'   => $post_types,
			'taxonomies'  => $taxonomies,
			'woocommerce' => class_exists( 'WooCommerce' ),
			'acf'         => function_exists( 'get_field' ),
			'siteName'    => get_bloginfo( 'name' ),
			'themeTypes'  => array( 'header', 'footer', 'single', 'page', 'archive', 'search', '404' ),
		) );
	}

	public function rest_components() {
		$posts = get_posts( array( 'post_type' => self::COMPONENT_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 200, 'orderby' => 'modified', 'order' => 'DESC' ) );
		return new WP_REST_Response( array_values( array_filter( array_map( array( $this, 'present_component' ), $posts ) ) ) );
	}

	public function rest_save_component( WP_REST_Request $request ) {
		$id      = absint( $request['id'] ?? 0 );
		$payload = (array) $request->get_json_params();
		$title   = sanitize_text_field( (string) ( $payload['title'] ?? '' ) );
		$node    = isset( $payload['node'] ) && is_array( $payload['node'] ) ? $payload['node'] : null;
		if ( '' === $title || ! $node ) return new WP_Error( 'cresco_component_invalid', __( 'Component title and node are required.', 'cresco-canvas' ), array( 'status' => 400 ) );

		$session = self::sanitize_session( array( 'schema' => SessionManager::SCHEMA, 'version' => SessionManager::VERSION, 'documentId' => 'component', 'nodes' => array( $node ) ) );
		if ( is_wp_error( $session ) ) return $session;
		$json = wp_json_encode( $session['nodes'][0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_component_encode_failed', __( 'Component could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );

		$post_id = wp_insert_post( array(
			'ID'          => $id,
			'post_type'   => self::COMPONENT_TYPE,
			'post_status' => 'publish',
			'post_title'  => $title,
		), true );
		if ( is_wp_error( $post_id ) ) return $post_id;
		update_post_meta( $post_id, self::COMPONENT_META, $json );
		return new WP_REST_Response( $this->present_component( get_post( $post_id ) ), $id ? 200 : 201 );
	}

	public function rest_delete_component( WP_REST_Request $request ) {
		$result = wp_trash_post( absint( $request['id'] ) );
		return $result ? new WP_REST_Response( array( 'deleted' => true ) ) : new WP_Error( 'cresco_component_delete_failed', __( 'Component could not be moved to Trash.', 'cresco-canvas' ), array( 'status' => 500 ) );
	}

	private function present_component( $post ) {
		if ( ! $post instanceof \WP_Post ) return null;
		$raw  = (string) get_post_meta( $post->ID, self::COMPONENT_META, true );
		$node = $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $node ) ) return null;
		$session = self::sanitize_session( array( 'schema' => SessionManager::SCHEMA, 'version' => SessionManager::VERSION, 'documentId' => 'component', 'nodes' => array( $node ) ) );
		if ( is_wp_error( $session ) ) return null;
		return array( 'id' => (int) $post->ID, 'title' => get_the_title( $post ), 'node' => $session['nodes'][0], 'modified' => get_post_modified_time( 'c', true, $post ) );
	}

	/** Replace legacy standalone runtime with one schema-driven Website Builder runtime. */
	public function enqueue_editor() {
		if ( ! isset( $_GET['page'] ) || VisualEditor::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
			return;
		}
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) return;

		$script = CRESCO_CANVAS_PATH . 'build/website-builder-editor.js';
		$style  = CRESCO_CANVAS_PATH . 'assets/css/website-builder.css';
		if ( ! is_readable( $script ) || ! is_readable( $style ) ) return;

		foreach ( array(
			'cresco-canvas-editor-experience-v2-tools', 'cresco-canvas-editor-experience-v2-sync', 'cresco-canvas-editor-experience-v2',
			'cresco-canvas-standalone-history', 'cresco-canvas-standalone-page-settings', 'cresco-canvas-standalone-ui-v3',
			'cresco-canvas-widget-control-enhancements', 'cresco-canvas-standalone-inspector-v2', 'cresco-canvas-global-config-import',
			'cresco-canvas-viewport-shell', 'cresco-canvas-standalone-visual-editor',
		) as $handle ) {
			wp_dequeue_script( $handle );
		}
		foreach ( array(
			'cresco-canvas-editor-experience-v2-tools', 'cresco-canvas-editor-experience-v2-polish', 'cresco-canvas-editor-experience-v2',
			'cresco-canvas-standalone-history', 'cresco-canvas-standalone-page-settings', 'cresco-canvas-standalone-ui-v3',
			'cresco-canvas-widget-control-enhancements', 'cresco-canvas-standalone-inspector-v2', 'cresco-canvas-global-config-import',
			'cresco-canvas-viewport-shell', 'cresco-canvas-standalone-visual-editor',
		) as $handle ) {
			wp_dequeue_style( $handle );
		}

		wp_enqueue_media( array( 'post' => $post_id ) );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'cresco-canvas-website-builder', CRESCO_CANVAS_URL . 'assets/css/website-builder.css', array( 'wp-components' ), CRESCO_CANVAS_VERSION );
		wp_add_inline_style( 'cresco-canvas-website-builder', GlobalStyles::css( '.cc-builder-canvas' ) . GlobalStyles::visual_css( '.cc-builder-canvas' ) );

		$settings = array(
			'postId'               => $post_id,
			'postTitle'            => (string) $post->post_title,
			'sessionPath'          => '/cresco-canvas/v1/website-builder/session/' . $post_id,
			'validatePath'         => '/cresco-canvas/v1/website-builder/session/validate',
			'contextPath'          => '/cresco-canvas/v1/website-builder/context/' . $post_id,
			'optionsPath'          => '/cresco-canvas/v1/website-builder/options',
			'componentsPath'       => '/cresco-canvas/v1/website-builder/components',
			'pageSettingsPath'     => '/cresco-canvas/v1/page-settings/' . $post_id,
			'settingsPath'         => '/cresco-canvas/v1/settings',
			'historyPath'          => '/cresco-canvas/v1/history/' . $post_id,
			'themeTemplatesPath'   => '/cresco-canvas/v1/theme-templates',
			'themeOptionsPath'     => '/cresco-canvas/v1/theme-builder/options',
			'previewUrl'           => get_preview_post_link( $post_id ),
			'adminPagesUrl'        => admin_url( 'edit.php?post_type=page' ),
			'widgetCatalog'        => WidgetCatalog::all(),
			'previewWidths'        => array( 'wide' => 1920, 'desktop' => 1440, 'laptop' => 1366, 'tablet' => 768, 'mobile' => 390 ),
			'canManageGlobal'      => current_user_can( 'edit_theme_options' ),
			'canManageComponents'  => current_user_can( 'edit_pages' ),
			'builderVersion'       => self::BUILDER_VERSION,
			'pluginVersion'        => CRESCO_CANVAS_VERSION,
		);

		wp_enqueue_script( 'cresco-canvas-website-builder', CRESCO_CANVAS_URL . 'build/website-builder-editor.js', array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ), CRESCO_CANVAS_VERSION, true );
		wp_add_inline_script( 'cresco-canvas-website-builder', 'window.crescoWebsiteBuilderSettings=' . wp_json_encode( $settings ) . ';', 'before' );
		wp_set_script_translations( 'cresco-canvas-website-builder', 'cresco-canvas' );
	}

	/** Enqueue interactive frontend behavior and compiled document styles only for builder documents. */
	public function enqueue_frontend() {
		if ( ! is_singular( 'page' ) ) return;
		$post_id = get_queried_object_id();
		if ( ! $this->has_builder_document( $post_id ) ) return;
		$session = $this->load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return;
		wp_enqueue_style( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'assets/css/website-builder-frontend.css', array( 'cresco-canvas-frontend' ), CRESCO_CANVAS_VERSION );
		wp_add_inline_style( 'cresco-canvas-website-builder-frontend', WebsiteRenderer::compile_css( $session ) );
		wp_enqueue_script( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'build/website-builder-frontend.js', array(), CRESCO_CANVAS_VERSION, true );
	}

	/** Render builder-owned documents after the legacy Session renderer. */
	public function render_frontend_content( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) return $content;
		$post_id = get_the_ID();
		if ( ! $this->has_builder_document( $post_id ) ) return $content;
		$session = $this->load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return $content;
		return WebsiteRenderer::render_document( $session, $post_id );
	}

	private function has_builder_document( $post_id ) {
		return self::BUILDER_VERSION === (string) get_post_meta( $post_id, self::BUILDER_META, true );
	}

	private function session_payload( $post_id ) {
		$session = $this->load_session( $post_id );
		if ( ! $session ) $session = self::empty_session( $post_id );
		$json = (string) wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return array(
			'session'   => $session,
			'checksum'  => hash( 'sha256', $json ),
			'nodeCount' => self::count_nodes( $session['nodes'] ),
			'postTitle' => get_the_title( $post_id ),
			'builder'   => $this->has_builder_document( $post_id ) ? self::BUILDER_VERSION : 'legacy-session',
		);
	}

	private function load_session( $post_id ) {
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		if ( '' === $raw ) return null;
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) return null;
		$session = self::sanitize_session( $decoded );
		return is_wp_error( $session ) ? null : $session;
	}

	public static function empty_session( $post_id = 0 ) {
		return array( 'schema' => SessionManager::SCHEMA, 'version' => SessionManager::VERSION, 'documentId' => $post_id ? 'page-' . absint( $post_id ) : 'untitled', 'nodes' => array() );
	}

	/** Validate and normalize an extended Cresco Session document. */
	public static function sanitize_session( $input ) {
		if ( ! is_array( $input ) ) return new WP_Error( 'cresco_builder_invalid', __( 'Website Builder document must be a JSON object.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( isset( $input['schema'] ) && SessionManager::SCHEMA !== (string) $input['schema'] ) return new WP_Error( 'cresco_builder_schema', __( 'Unsupported Cresco Session schema.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( isset( $input['version'] ) && SessionManager::VERSION !== absint( $input['version'] ) ) return new WP_Error( 'cresco_builder_version', __( 'Unsupported Cresco Session version.', 'cresco-canvas' ), array( 'status' => 400 ) );
		$state = array( 'count' => 0, 'ids' => array() );
		$nodes = self::sanitize_nodes( $input['nodes'] ?? array(), 0, $state );
		if ( is_wp_error( $nodes ) ) return $nodes;
		$document_id = sanitize_key( (string) ( $input['documentId'] ?? 'untitled' ) ) ?: 'untitled';
		return array( 'schema' => SessionManager::SCHEMA, 'version' => SessionManager::VERSION, 'documentId' => $document_id, 'nodes' => $nodes );
	}

	private static function sanitize_nodes( $nodes, $depth, &$state ) {
		if ( $depth > self::MAX_DEPTH ) return new WP_Error( 'cresco_builder_depth', __( 'Website Builder nesting is too deep.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( ! is_array( $nodes ) ) return new WP_Error( 'cresco_builder_nodes', __( 'Website Builder nodes must be an array.', 'cresco-canvas' ), array( 'status' => 400 ) );
		$catalog = WidgetCatalog::all();
		$output  = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) return new WP_Error( 'cresco_builder_node', __( 'Every Website Builder node must be an object.', 'cresco-canvas' ), array( 'status' => 400 ) );
			if ( ++$state['count'] > self::MAX_NODES ) return new WP_Error( 'cresco_builder_node_limit', __( 'Website Builder document contains too many widgets.', 'cresco-canvas' ), array( 'status' => 400 ) );
			$type = sanitize_key( (string) ( $node['type'] ?? '' ) );
			if ( ! isset( $catalog[ $type ] ) ) return new WP_Error( 'cresco_builder_widget', sprintf( __( 'Unknown Website Builder widget: %s', 'cresco-canvas' ), $type ?: '?' ), array( 'status' => 400 ) );
			$id = self::sanitize_node_id( $node['id'] ?? '' );
			if ( '' === $id ) return new WP_Error( 'cresco_builder_widget_id', __( 'Every Website Builder widget requires a stable id.', 'cresco-canvas' ), array( 'status' => 400 ) );
			if ( isset( $state['ids'][ $id ] ) ) return new WP_Error( 'cresco_builder_duplicate_id', sprintf( __( 'Duplicate Website Builder widget id: %s', 'cresco-canvas' ), $id ), array( 'status' => 400 ) );
			$state['ids'][ $id ] = true;

			$children = array();
			if ( ! empty( $node['children'] ) ) {
				if ( empty( $catalog[ $type ]['allowsChildren'] ) ) return new WP_Error( 'cresco_builder_children', sprintf( __( '%s does not allow child widgets.', 'cresco-canvas' ), $catalog[ $type ]['label'] ), array( 'status' => 400 ) );
				$children = self::sanitize_nodes( $node['children'], $depth + 1, $state );
				if ( is_wp_error( $children ) ) return $children;
			}
			$custom_css = self::sanitize_custom_css_map( $node['customCSS'] ?? array() );
			if ( is_wp_error( $custom_css ) ) return $custom_css;
			$output[] = array(
				'id'         => $id,
				'type'       => $type,
				'props'      => self::sanitize_props( $type, $node['props'] ?? array() ),
				'style'      => self::sanitize_style( $node['style'] ?? array() ),
				'responsive' => self::sanitize_responsive( $node['responsive'] ?? array() ),
				'states'     => self::sanitize_states( $node['states'] ?? array() ),
				'customCSS'  => $custom_css,
				'meta'       => self::sanitize_meta( $node['meta'] ?? array() ),
				'children'   => $children,
			);
		}
		return $output;
	}

	private static function sanitize_node_id( $value ) {
		$value = preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( trim( (string) $value ) ) );
		return substr( trim( (string) $value, '-' ), 0, 80 );
	}

	private static function sanitize_meta( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'label'       => substr( sanitize_text_field( (string) ( $input['label'] ?? '' ) ), 0, 120 ),
			'componentId' => absint( $input['componentId'] ?? 0 ),
			'locked'      => rest_sanitize_boolean( $input['locked'] ?? false ),
			'hidden'      => rest_sanitize_boolean( $input['hidden'] ?? false ),
		);
	}

	private static function sanitize_props( $type, $input ) {
		$input   = is_array( $input ) ? $input : array();
		$output  = array();
		$catalog = WidgetCatalog::all();
		foreach ( $catalog[ $type ]['props'] as $key => $schema ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : ( $schema['default'] ?? null );
			$kind  = $schema['type'] ?? 'string';
			if ( 'int' === $kind ) {
				$value = max( (int) ( $schema['min'] ?? PHP_INT_MIN ), min( (int) ( $schema['max'] ?? PHP_INT_MAX ), (int) $value ) );
			} elseif ( 'number' === $kind ) {
				$value = max( (float) ( $schema['min'] ?? -PHP_FLOAT_MAX ), min( (float) ( $schema['max'] ?? PHP_FLOAT_MAX ), (float) $value ) );
			} elseif ( 'enum' === $kind ) {
				$value = in_array( (string) $value, (array) ( $schema['values'] ?? array() ), true ) ? (string) $value : (string) ( $schema['default'] ?? '' );
			} elseif ( 'bool' === $kind ) {
				$value = rest_sanitize_boolean( $value );
			} elseif ( 'url' === $kind ) {
				$value = '#' === (string) $value ? '#' : esc_url_raw( (string) $value );
			} elseif ( 'text' === $kind ) {
				$value = substr( sanitize_textarea_field( (string) $value ), 0, 20000 );
			} elseif ( 'richtext' === $kind ) {
				$value = substr( wp_kses_post( (string) $value ), 0, 40000 );
			} elseif ( 'string_list' === $kind ) {
				$value = is_array( $value ) ? $value : preg_split( '/\r?\n/', (string) $value );
				$value = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) $value, 0, 100 ) ), 'strlen' ) );
			} elseif ( 'json' === $kind ) {
				$value = self::sanitize_json_shape( $value, (string) ( $schema['shape'] ?? '' ), $schema['default'] ?? array() );
			} elseif ( 'css' === $kind ) {
				$value = self::sanitize_css_value( $value );
			} else {
				$value = substr( sanitize_text_field( (string) $value ), 0, 5000 );
			}
			$output[ $key ] = self::sanitize_prop_identity( $type, $key, $value );
		}
		return $output;
	}

	private static function sanitize_prop_identity( $type, $key, $value ) {
		if ( in_array( $key, array( 'postType', 'taxonomy', 'term', 'icon' ), true ) ) return sanitize_key( (string) $value );
		if ( 'dynamic-field' === $type && 'key' === $key ) return substr( preg_replace( '/[^a-zA-Z0-9_\-.]/', '', (string) $value ), 0, 160 );
		if ( 'form' === $type && 'formId' === $key ) return substr( sanitize_key( (string) $value ), 0, 80 );
		if ( 'form' === $type && 'emailTo' === $key ) return sanitize_email( (string) $value );
		if ( 'button' === $type && 'rel' === $key ) {
			$allowed = array( 'noopener', 'noreferrer', 'nofollow', 'sponsored', 'ugc' );
			$tokens = preg_split( '/\s+/', strtolower( (string) $value ) );
			return implode( ' ', array_values( array_intersect( array_unique( $tokens ), $allowed ) ) );
		}
		return $value;
	}

	private static function sanitize_json_shape( $value, $shape, $fallback ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value = is_array( $decoded ) ? $decoded : $fallback;
		}
		$value = is_array( $value ) ? $value : $fallback;
		$output = array();
		if ( 'gallery' === $shape ) {
			foreach ( array_slice( $value, 0, 40 ) as $item ) {
				if ( ! is_array( $item ) ) continue;
				$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
				if ( '' === $url ) continue;
				$output[] = array( 'url' => $url, 'alt' => sanitize_text_field( (string) ( $item['alt'] ?? '' ) ), 'caption' => sanitize_text_field( (string) ( $item['caption'] ?? '' ) ) );
			}
		} elseif ( 'accordion' === $shape || 'tabs' === $shape ) {
			foreach ( array_slice( $value, 0, 24 ) as $item ) {
				if ( ! is_array( $item ) ) continue;
				$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
				if ( '' === $title ) continue;
				$row = array( 'title' => $title, 'content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ) );
				if ( 'accordion' === $shape ) $row['open'] = rest_sanitize_boolean( $item['open'] ?? false );
				$output[] = $row;
			}
		} elseif ( 'social' === $shape ) {
			foreach ( array_slice( $value, 0, 20 ) as $item ) {
				if ( ! is_array( $item ) ) continue;
				$url = '#' === (string) ( $item['url'] ?? '' ) ? '#' : esc_url_raw( (string) ( $item['url'] ?? '' ) );
				if ( '' === $url ) continue;
				$output[] = array( 'label' => sanitize_text_field( (string) ( $item['label'] ?? 'Link' ) ), 'url' => $url, 'icon' => sanitize_key( (string) ( $item['icon'] ?? 'admin-links' ) ) );
			}
		} elseif ( 'form_fields' === $shape ) {
			$types = array( 'text', 'email', 'tel', 'number', 'url', 'date', 'textarea', 'select', 'radio', 'checkbox_group', 'consent', 'file' );
			foreach ( array_slice( $value, 0, 50 ) as $item ) {
				if ( ! is_array( $item ) ) continue;
				$name = sanitize_key( (string) ( $item['name'] ?? '' ) );
				if ( '' === $name ) continue;
				$type = sanitize_key( (string) ( $item['type'] ?? 'text' ) );
				if ( ! in_array( $type, $types, true ) ) $type = 'text';
				$options = $item['options'] ?? '';
				if ( is_array( $options ) ) $options = implode( "\n", array_map( 'sanitize_text_field', array_slice( $options, 0, 50 ) ) );
				$output[] = array(
					'name'        => $name,
					'label'       => sanitize_text_field( (string) ( $item['label'] ?? ucfirst( $name ) ) ),
					'type'        => $type,
					'required'    => rest_sanitize_boolean( $item['required'] ?? false ),
					'placeholder' => sanitize_text_field( (string) ( $item['placeholder'] ?? '' ) ),
					'options'     => substr( sanitize_textarea_field( (string) $options ), 0, 5000 ),
					'min'         => isset( $item['min'] ) ? (float) $item['min'] : null,
					'max'         => isset( $item['max'] ) ? (float) $item['max'] : null,
				);
			}
		}
		return $output;
	}

	private static function sanitize_style( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$allowed = array_flip( WidgetCatalog::style_properties() );
		$output  = array();
		foreach ( $input as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) continue;
			$value = self::sanitize_css_value( $value );
			if ( '' !== $value ) $output[ $key ] = $value;
		}
		return $output;
	}

	private static function sanitize_responsive( $input ) {
		$input = is_array( $input ) ? $input : array();
		$output = array();
		foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			$style = isset( $input[ $device ] ) ? self::sanitize_style( $input[ $device ] ) : array();
			if ( $style ) $output[ $device ] = $style;
		}
		return $output;
	}

	private static function sanitize_states( $input ) {
		$input = is_array( $input ) ? $input : array();
		$output = array();
		foreach ( array( 'hover', 'focus', 'active' ) as $state ) {
			$style = isset( $input[ $state ] ) ? self::sanitize_style( $input[ $state ] ) : array();
			if ( $style ) $output[ $state ] = $style;
		}
		return $output;
	}

	public static function sanitize_css_value( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value || strlen( $value ) > 240 ) return '';
		if ( preg_match( '/^\{[a-zA-Z0-9._-]+\}$/', $value ) ) return $value;
		if ( preg_match( '/[;{}<>]/', $value ) || preg_match( '/(?:url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding)/i', $value ) ) return '';
		return preg_match( "/^[#a-zA-Z0-9.,:%+\-*\/() _\"']+$/", $value ) ? $value : '';
	}

	private static function sanitize_custom_css_map( $input ) {
		$input = is_array( $input ) ? $input : array();
		$output = array();
		foreach ( array( 'base', 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			if ( empty( $input[ $device ] ) ) continue;
			$css = self::sanitize_custom_css( $input[ $device ] );
			if ( is_wp_error( $css ) ) return $css;
			if ( '' !== $css ) $output[ $device ] = $css;
		}
		return $output;
	}

	public static function sanitize_custom_css( $value ) {
		$css = trim( (string) $value );
		if ( '' === $css ) return '';
		if ( strlen( $css ) > self::MAX_CUSTOM_CSS ) return new WP_Error( 'cresco_builder_css_size', __( 'Widget Custom CSS is too large.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( preg_match( '/(?:@import|@charset|@namespace|@media|@supports|@layer|url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding|<\/?style|<!--|-->)/i', $css ) ) return new WP_Error( 'cresco_builder_css_forbidden', __( 'Widget Custom CSS contains a forbidden construct.', 'cresco-canvas' ), array( 'status' => 400 ) );
		if ( substr_count( $css, '{' ) !== substr_count( $css, '}' ) ) return new WP_Error( 'cresco_builder_css_braces', __( 'Widget Custom CSS has unbalanced braces.', 'cresco-canvas' ), array( 'status' => 400 ) );
		$cursor = 0;
		while ( false !== ( $open = strpos( $css, '{', $cursor ) ) ) {
			$selector = trim( substr( $css, $cursor, $open - $cursor ) );
			$close = strpos( $css, '}', $open + 1 );
			if ( false === $close || '' === $selector || false === strpos( $selector, '&' ) ) return new WP_Error( 'cresco_builder_css_scope', __( 'Every Widget Custom CSS selector must include &.', 'cresco-canvas' ), array( 'status' => 400 ) );
			if ( preg_match( '/(?:^|,)\s*(?:html|body|:root|#wpwrap|#wpcontent)\b/i', $selector ) || preg_match( '/[<>]/', substr( $css, $open + 1, $close - $open - 1 ) ) ) return new WP_Error( 'cresco_builder_css_global', __( 'Widget Custom CSS cannot escape its widget scope.', 'cresco-canvas' ), array( 'status' => 400 ) );
			$cursor = $close + 1;
		}
		return $css;
	}

	public static function count_nodes( $nodes ) {
		$count = 0;
		foreach ( (array) $nodes as $node ) $count += 1 + self::count_nodes( $node['children'] ?? array() );
		return $count;
	}
}
