<?php
/**
 * Application boundary for the consolidated Cresco document architecture.
 *
 * This service exposes stable Core contracts to the editor while legacy V3
 * services remain as compatibility adapters during the migration period.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Application;

use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Core\Command\CommandBus;
use CrescoCanvas\Core\Context\ContextEngine;
use CrescoCanvas\Core\Module\ModuleRegistry;
use CrescoCanvas\Core\UI\UiRegistry;
use CrescoCanvas\Core\Widget\WidgetRegistry;
use CrescoCanvas\Infrastructure\WordPress\Storage\WordPressDocumentRepository;
use CrescoCanvas\Rendering\RenderEngine;
use CrescoCanvas\Theme\ThemeBuilder;
use CrescoCanvas\Theme\ThemeSessionBridge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BuilderArchitecture {
	const SCRIPT_HANDLE = 'cresco-canvas-builder-architecture';
	const STYLE_HANDLE  = 'cresco-canvas-builder-architecture';

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 36 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 1080 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enforce_frontend_render_css' ), 48 );
		add_filter( 'the_content', array( $this, 'render_frontend_document' ), 26 );
	}

	/** Route Page frontend output through the same RenderEngine exposed to editor preview. */
	public function render_frontend_document( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) return $content;
		$post_id = get_the_ID();
		if ( WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return $content;
		$session = $this->stored_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return $content;
		$html = RenderEngine::html( $session, $post_id, $this->document_type( $post_id ) );
		return is_wp_error( $html ) ? $content : $html;
	}

	/** Ensure the frontend handle contains exactly one authoritative document CSS fragment. */
	public function enforce_frontend_render_css() {
		if ( is_admin() || ! is_singular( 'page' ) ) return;
		$post_id = get_queried_object_id();
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;
		$session = $this->stored_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return;
		$handle = 'cresco-canvas-website-builder-frontend';
		$styles = wp_styles();
		if ( ! isset( $styles->registered[ $handle ] ) ) return;
		$after = isset( $styles->registered[ $handle ]->extra['after'] ) && is_array( $styles->registered[ $handle ]->extra['after'] ) ? $styles->registered[ $handle ]->extra['after'] : array();
		$styles->registered[ $handle ]->extra['after'] = array_values( array_filter( $after, static function ( $css ) {
			return false === strpos( (string) $css, '.cresco-website-builder-root [data-cresco-id=' );
		} ) );
		$css = RenderEngine::css( $session );
		if ( ! is_wp_error( $css ) && '' !== $css ) wp_add_inline_style( $handle, $css );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/architecture/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_architecture' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/scoped-context/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_scoped_context' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/commands/(?P<postId>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_preview_command' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/render/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_render' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
	}

	public function can_edit_document( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		$post_type = $post_id ? (string) get_post_type( $post_id ) : '';
		return $post_id > 0 && in_array( $post_type, array( 'page', ThemeBuilder::POST_TYPE ), true ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_architecture( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		return new WP_REST_Response(
			array(
				'schema'       => 'cresco-builder-architecture/v1',
				'documentType' => $this->document_type( $post_id ),
				'contracts'    => array(
					'document'  => 'contracts/document/v1.schema.json',
					'scope'     => 'contracts/scope/v1.schema.json',
					'command'   => 'contracts/command/v1.schema.json',
					'aiContext' => 'contracts/ai-context/v2.schema.json',
					'patch'     => 'cresco-patch/v1',
					'interchange'=> 'cresco-interchange/v1',
				),
				'ui'           => UiRegistry::manifest(),
				'modules'      => ModuleRegistry::all(),
				'documents'    => $this->document_index(),
				'widgetTypes'  => WidgetRegistry::types(),
				'capabilities' => array(
					'scopedAI'       => true,
					'commandBus'     => true,
					'unifiedRender'  => true,
					'interchange'    => true,
					'themeDocuments' => post_type_exists( ThemeBuilder::POST_TYPE ),
					'woocommerce'    => class_exists( '\\WooCommerce' ) || defined( 'WC_VERSION' ) || function_exists( 'WC' ),
				),
			)
		);
	}

	public function rest_scoped_context( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = $this->session_from_payload( $post_id, $payload );
		if ( is_wp_error( $session ) ) return $session;
		$scope   = sanitize_key( (string) ( $payload['scope'] ?? 'document' ) );
		$target  = isset( $payload['target'] ) && is_array( $payload['target'] ) ? $payload['target'] : array();
		$purpose = sanitize_key( (string) ( $payload['purpose'] ?? 'edit' ) );
		$mode    = sanitize_key( (string) ( $payload['mode'] ?? 'auto' ) );
		$context = ContextEngine::build(
			$post_id,
			$session,
			$scope,
			$target,
			$purpose,
			$mode,
			array( 'documentType' => $this->document_type( $post_id ), 'postTitle' => get_the_title( $post_id ) )
		);
		return is_wp_error( $context ) ? $context : new WP_REST_Response( array( 'valid' => true, 'context' => $context ) );
	}

	public function rest_preview_command( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = $this->session_from_payload( $post_id, $payload );
		if ( is_wp_error( $session ) ) return $session;
		$command = isset( $payload['command'] ) && is_array( $payload['command'] ) ? $payload['command'] : $payload;
		$result = CommandBus::preview( $session, $command );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	public function rest_render( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = $this->session_from_payload( $post_id, $payload );
		if ( is_wp_error( $session ) ) return $session;
		$result = RenderEngine::render( $session, $post_id, $this->document_type( $post_id ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'valid' => true, 'render' => $result ) );
	}

	public function enqueue_editor() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor routing.
		if ( ! in_array( $page, array( VisualEditor::PAGE_SLUG, ThemeSessionBridge::PAGE_SLUG ), true ) ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor routing.
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) return;
		$script = CRESCO_CANVAS_PATH . 'build/website-builder-architecture.js';
		$style  = CRESCO_CANVAS_PATH . 'assets/css/website-builder-architecture.css';
		if ( ! is_readable( $script ) || ! is_readable( $style ) ) return;

		wp_enqueue_style( self::STYLE_HANDLE, CRESCO_CANVAS_URL . 'assets/css/website-builder-architecture.css', array( 'cresco-canvas-website-builder' ), $this->asset_version( $style ) );
		wp_enqueue_script( self::SCRIPT_HANDLE, CRESCO_CANVAS_URL . 'build/website-builder-architecture.js', array( 'cresco-canvas-website-builder', 'wp-api-fetch' ), $this->asset_version( $script ), true );
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.crescoBuilderArchitectureSettings=' . wp_json_encode(
				array(
					'postId'             => $post_id,
					'documentType'       => $this->document_type( $post_id ),
					'architecturePath'   => '/cresco-canvas/v1/website-builder/architecture/' . $post_id,
					'scopedContextPath'  => '/cresco-canvas/v1/website-builder/scoped-context/' . $post_id,
					'commandPreviewPath' => '/cresco-canvas/v1/website-builder/commands/' . $post_id . '/preview',
					'renderPath'         => '/cresco-canvas/v1/website-builder/render/' . $post_id,
					'version'            => 'architecture-v1',
				)
			) . ';',
			'before'
		);
	}


	private function document_type( $post_id ) {
		return ( new WordPressDocumentRepository() )->type( $post_id );
	}

	private function stored_session( $post_id ) {
		$session = ( new WordPressDocumentRepository() )->load( $post_id );
		return is_wp_error( $session ) ? null : $session;
	}

	private function document_index() {
		$items = array();
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 100,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_key'       => WebsiteBuilder::BUILDER_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small builder-owned document index.
			'meta_value'     => WebsiteBuilder::BUILDER_VERSION, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small builder-owned document index.
		) );
		foreach ( $pages as $page ) {
			if ( ! current_user_can( 'edit_post', $page->ID ) ) continue;
			$items[] = array(
				'id' => (int) $page->ID, 'type' => 'page', 'group' => 'Pages', 'title' => get_the_title( $page ), 'status' => $page->post_status,
				'editUrl' => add_query_arg( array( 'page' => VisualEditor::PAGE_SLUG, 'post' => (int) $page->ID ), admin_url( 'admin.php' ) ),
			);
		}
		$templates = get_posts( array( 'post_type' => ThemeBuilder::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 100, 'orderby' => 'modified', 'order' => 'DESC' ) );
		foreach ( $templates as $template ) {
			if ( ! current_user_can( 'edit_post', $template->ID ) ) continue;
			$type = $this->document_type( $template->ID );
			$items[] = array(
				'id' => (int) $template->ID, 'type' => $type, 'group' => in_array( $type, array( 'header', 'footer' ), true ) ? 'Theme' : 'Templates', 'title' => get_the_title( $template ), 'status' => $template->post_status,
				'editUrl' => add_query_arg( array( 'page' => ThemeSessionBridge::PAGE_SLUG, 'post' => (int) $template->ID ), admin_url( 'admin.php' ) ),
			);
		}
		return $items;
	}

	private function session_from_payload( $post_id, $payload ) {
		if ( isset( $payload['currentSession'] ) && is_array( $payload['currentSession'] ) ) {
			return WebsiteBuilder::sanitize_session( $payload['currentSession'] );
		}
		$session = ( new WordPressDocumentRepository() )->load( $post_id );
		if ( is_wp_error( $session ) ) return $session;
		return $session ?: WebsiteBuilder::empty_session( $post_id );
	}

	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}
}
