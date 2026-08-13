<?php
/**
 * Runtime integration for Widget Architecture v2.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Theme\ThemeBuilder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderArchitectureV2 {
	const SCRIPT = 'build/website-builder-architecture-v2.js';

	public function register() {
		add_action( 'init', array( $this, 'register_storage' ), 7 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 34 );
		add_filter( 'rest_post_dispatch', array( $this, 'enrich_rest_response' ), 35, 3 );
		add_filter( 'the_content', array( $this, 'render_frontend_content' ), 26 );
		add_filter( 'render_block_cresco/theme-session', array( $this, 'render_theme_session_block' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'replace_compiled_css' ), 49 );
		add_action( 'wp_enqueue_scripts', array( $this, 'append_theme_architecture_css' ), 51 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_studio_extension' ), 1405 );
	}

	public function register_storage() {
		$args = array(
			'single' => true,
			'type' => 'string',
			'default' => '',
			'show_in_rest' => false,
			'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
				unset( $allowed, $meta_key );
				return current_user_can( 'edit_post', (int) $post_id );
			},
		);
		register_post_meta( 'page', WidgetArchitectureV2::META_KEY, $args );
		register_post_meta( ThemeBuilder::POST_TYPE, WidgetArchitectureV2::META_KEY, $args );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/architecture-v2/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_get' ), 'permission_callback' => array( $this, 'can_edit_document' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save' ), 'permission_callback' => array( $this, 'can_edit_document' ) ),
			)
		);
	}

	public function can_edit_document( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && in_array( get_post_type( $post_id ), array( 'page', ThemeBuilder::POST_TYPE ), true ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_get( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session = self::load_session( $post_id );
		if ( ! $session ) return new WP_Error( 'cresco_architecture_session_missing', __( 'The Website Builder session could not be loaded.', 'cresco-canvas' ), array( 'status' => 404 ) );
		return new WP_REST_Response( array( 'architecture' => self::load_document( $post_id, $session ), 'catalogVersion' => '2.0.0', 'savedAt' => (string) get_post_meta( $post_id, WidgetArchitectureV2::META_KEY . '_saved_at', true ) ) );
	}

	public function rest_save( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session = self::load_session( $post_id );
		if ( ! $session ) return new WP_Error( 'cresco_architecture_session_missing', __( 'The Website Builder session could not be loaded.', 'cresco-canvas' ), array( 'status' => 404 ) );
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['architecture'] ) && is_array( $payload['architecture'] ) ? $payload['architecture'] : $payload;
		$document = WidgetArchitectureV2::sanitize_document( $input, $session );
		$json = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_architecture_encode', __( 'Widget architecture could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		update_post_meta( $post_id, WidgetArchitectureV2::META_KEY, $json );
		$saved_at = gmdate( 'c' );
		update_post_meta( $post_id, WidgetArchitectureV2::META_KEY . '_saved_at', $saved_at );
		return new WP_REST_Response( array( 'valid' => true, 'architecture' => $document, 'savedAt' => $saved_at ) );
	}

	/** Enrich editor/AI contexts without changing the Cresco Session v1 schema. */
	public function enrich_rest_response( $response, $server, $request ) {
		unset( $server );
		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) return $response;
		$route = (string) $request->get_route();
		if ( false === strpos( $route, '/cresco-canvas/v1/' ) ) return $response;
		$data = $response->get_data();
		if ( ! is_array( $data ) ) return $response;
		$post_id = absint( $request['postId'] ?? 0 );
		if ( preg_match( '#/website-builder/(?:theme-)?context/\d+$#', $route ) && $post_id ) {
			$session = isset( $data['session'] ) && is_array( $data['session'] ) ? $data['session'] : self::load_session( $post_id );
			$data['widgets'] = WidgetArchitectureV2::catalog();
			$data['architectureV2'] = $session ? self::load_document( $post_id, $session ) : WidgetArchitectureV2::empty_document();
			$data['capabilities']['widgetArchitectureV2'] = true;
			$data['instructions'][] = 'Widget Architecture v2 supports per-part styles, dynamic property bindings, component-backed nested slots, Query Builder v2, and Form Engine v2.';
			$response->set_data( $data );
			return $response;
		}
		if ( preg_match( '#/website-builder/interchange/\d+/export$#', $route ) && $post_id && ! empty( $data['package'] ) && is_array( $data['package'] ) ) {
			$session = self::load_session( $post_id );
			if ( $session ) {
				$architecture = self::load_document( $post_id, $session );
				$ids = self::package_node_ids( $data['package'] );
				$data['package']['architectureV2'] = $ids ? WidgetArchitectureV2::subset( $architecture, $ids ) : $architecture;
				$data['package']['aiContext']['widgetArchitectureV2'] = array(
					'schema' => WidgetArchitectureV2::SCHEMA,
					'architecture' => $data['package']['architectureV2'],
					'blueprints' => self::blueprint_index(),
				);
				$response->set_data( $data );
			}
		}
		return $response;
	}

	public function render_frontend_content( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) return $content;
		$post_id = get_the_ID();
		if ( WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return $content;
		$session = self::load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return $content;
		return WebsiteRendererV2::render_document( $session, $post_id, self::load_document( $post_id, $session ) );
	}

	public function render_theme_session_block( $content, $block ) {
		$template_id = absint( $block['attrs']['templateId'] ?? 0 );
		if ( ! $template_id || ThemeBuilder::POST_TYPE !== get_post_type( $template_id ) ) return $content;
		$session = self::load_session( $template_id );
		if ( ! $session ) return $content;
		return WebsiteRendererV2::render_document( $session, get_queried_object_id(), self::load_document( $template_id, $session ) );
	}

	public function append_theme_architecture_css() {
		if ( is_admin() ) return;
		$handle = 'cresco-canvas-website-builder-frontend';
		if ( ! wp_style_is( $handle, 'enqueued' ) ) return;
		$builder = new ThemeBuilder();
		$types = array( 'header', 'footer', self::document_type() );
		$css = '';
		foreach ( array_values( array_unique( array_filter( $types ) ) ) as $type ) {
			$template = $builder->resolve( $type );
			if ( ! $template instanceof \WP_Post ) continue;
			$session = self::load_session( $template->ID );
			if ( ! $session ) continue;
			$architecture = self::load_document( $template->ID, $session );
			$css .= WidgetPartStyleCompiler::compile( $session, $architecture );
			$css .= self::component_css( $architecture );
		}
		if ( $css ) wp_add_inline_style( $handle, $css );
	}

	/** Recompile root styles and append v2 part/component styles after the v3 compiler. */
	public function replace_compiled_css() {
		if ( ! is_singular( 'page' ) ) return;
		$post_id = get_queried_object_id();
		if ( WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;
		$session = self::load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return;
		$architecture = self::load_document( $post_id, $session );
		$handle = 'cresco-canvas-website-builder-frontend';
		$styles = wp_styles();
		if ( $styles && isset( $styles->registered[ $handle ] ) ) {
			$after = isset( $styles->registered[ $handle ]->extra['after'] ) ? (array) $styles->registered[ $handle ]->extra['after'] : array();
			$styles->registered[ $handle ]->extra['after'] = array_values( array_filter( $after, static function ( $fragment ) { return false === strpos( (string) $fragment, '.cresco-website-builder-root [data-cresco-id=' ); } ) );
		}
		$css = WebsiteBuilderCssCompiler::compile( $session );
		$css .= WidgetPartStyleCompiler::compile( $session, $architecture );
		$css .= self::component_css( $architecture );
		$css .= '.cresco-widget-loop-grid[data-cresco-loop-template="component"]{display:grid;grid-template-columns:repeat(var(--cresco-loop-columns,3),minmax(0,1fr));gap:var(--cc-grid-gap)}.cresco-product-template-item,.cresco-loop-template-item{min-width:0}.cresco-widget-woo-products[data-cresco-product-template="component"]{display:grid;grid-template-columns:repeat(var(--cresco-product-columns,4),minmax(0,1fr));gap:var(--cc-grid-gap)}@media(max-width:1024px){.cresco-widget-loop-grid[data-cresco-loop-template="component"],.cresco-widget-woo-products[data-cresco-product-template="component"]{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.cresco-widget-loop-grid[data-cresco-loop-template="component"],.cresco-widget-woo-products[data-cresco-product-template="component"]{grid-template-columns:1fr}}';
		if ( $css ) wp_add_inline_style( $handle, $css );
	}

	public function enqueue_studio_extension() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) || ! WebsiteBuilderAsset::readable( self::SCRIPT ) ) return;
		$dependency = wp_script_is( 'cresco-canvas-website-builder-responsive-properties', 'registered' ) ? 'cresco-canvas-website-builder-responsive-properties' : WebsiteBuilderStudio::HANDLE;
		wp_enqueue_script( 'cresco-canvas-website-builder-architecture-v2', WebsiteBuilderAsset::url( self::SCRIPT ), array( $dependency ), WebsiteBuilderAsset::version( self::SCRIPT ), true );
	}

	public static function load_document( $post_id, $session = null ) {
		if ( ! $session ) $session = self::load_session( $post_id );
		if ( ! $session ) return WidgetArchitectureV2::empty_document();
		$raw = (string) get_post_meta( $post_id, WidgetArchitectureV2::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : array();
		return WidgetArchitectureV2::sanitize_document( is_array( $decoded ) ? $decoded : array(), $session );
	}

	public static function load_session( $post_id ) {
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		if ( '' === $raw ) return null;
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) return null;
		$session = WebsiteBuilder::sanitize_session( $decoded );
		return is_wp_error( $session ) ? null : $session;
	}

	private static function package_node_ids( $package ) {
		$nodes = array();
		$content = (array) ( $package['content'] ?? array() );
		if ( isset( $content['session']['nodes'] ) ) $nodes = (array) $content['session']['nodes'];
		elseif ( isset( $content['nodes'] ) ) $nodes = (array) $content['nodes'];
		elseif ( isset( $content['node'] ) ) $nodes = array( $content['node'] );
		$ids = array();
		$walk = static function ( $items ) use ( &$walk, &$ids ) { foreach ( (array) $items as $node ) { if ( ! is_array( $node ) ) continue; if ( ! empty( $node['id'] ) ) $ids[] = (string) $node['id']; $walk( $node['children'] ?? array() ); } };
		$walk( $nodes );
		return array_values( array_unique( $ids ) );
	}

	private static function blueprint_index() {
		$out = array();
		foreach ( WidgetArchitectureV2::catalog() as $type => $widget ) $out[ $type ] = $widget['blueprint'] ?? array();
		return $out;
	}

	private static function document_type() {
		if ( is_404() ) return '404';
		if ( is_search() ) return 'search';
		if ( is_archive() || is_home() ) return 'archive';
		if ( is_page() ) return 'page';
		if ( is_singular() ) return 'single';
		return '';
	}

	private static function component_css( $architecture ) {
		$ids = array();
		foreach ( (array) ( $architecture['nodes'] ?? array() ) as $config ) {
			$slots = (array) ( $config['slots'] ?? array() );
			if ( ! empty( $slots['templateComponentId'] ) ) $ids[] = absint( $slots['templateComponentId'] );
			foreach ( (array) ( $slots['items'] ?? array() ) as $id ) $ids[] = absint( $id );
		}
		$css = '';
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $component_id ) {
			if ( WebsiteBuilder::COMPONENT_TYPE !== get_post_type( $component_id ) ) continue;
			$node = json_decode( (string) get_post_meta( $component_id, WebsiteBuilder::COMPONENT_META, true ), true );
			if ( is_array( $node ) ) $css .= WebsiteRenderer::compile_css( array( 'nodes' => array( $node ) ) );
		}
		return $css;
	}
}
