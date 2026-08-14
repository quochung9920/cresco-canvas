<?php
/**
 * Consolidated Website Builder core platform.
 *
 * This service is the single Page frontend owner. Legacy services remain
 * registered for compatibility/editor tooling, but their Page render/CSS hooks
 * are removed before WordPress renders the request.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\Application\BuilderArchitecture;
use CrescoCanvas\Core\Command\TransactionManager;
use CrescoCanvas\Core\Design\DesignSystemAnalyzer;
use CrescoCanvas\Core\Document\Document;
use CrescoCanvas\Core\Responsive\ResponsiveResolver;
use CrescoCanvas\Core\UI\InspectorSchema;
use CrescoCanvas\Infrastructure\WordPress\Storage\WordPressDocumentRepository;
use CrescoCanvas\Page\PageSettings;
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

final class WebsiteBuilderCorePlatform {
	const SCHEMA = 'cresco-builder-core/v2';
	const VERSION = '2.0.0';
	const STYLE_CONTRACT = 'authoritative-v5';

	/** Register the consolidated runtime after compatibility services exist. */
	public function register() {
		add_action( 'init', array( $this, 'prune_legacy_frontend_hooks' ), 9999 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_bridge' ), 1800 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 5000 );
		add_action( 'template_redirect', array( $this, 'render_theme_preview' ), -2 );
		add_filter( 'the_content', array( $this, 'render_frontend_content' ), 5000 );
	}

	/** Public feature flag used by tests and future compatibility adapters. */
	public static function owns_frontend() {
		return true;
	}

	/**
	 * Remove only legacy Page frontend callbacks. Editor, REST, Theme and storage
	 * capabilities stay registered while frontend ownership is consolidated.
	 */
	public function prune_legacy_frontend_hooks() {
		$this->remove_class_callbacks(
			'the_content',
			array(
				WebsiteBuilder::class,
				WebsiteBuilderArchitectureV2::class,
				WebsiteBuilderRendererParity::class,
				WebsiteBuilderVisualParity::class,
				BuilderArchitecture::class,
			)
		);
		$this->remove_class_callbacks( 'template_redirect', array( ThemeSessionBridge::class ) );
		$this->remove_class_callbacks(
			'wp_enqueue_scripts',
			array(
				WebsiteBuilder::class,
				WebsiteBuilderCompatibility::class,
				WebsiteBuilderComprehensiveV3::class,
				WebsiteBuilderArchitectureV2::class,
				WebsiteBuilderRendererParity::class,
				WebsiteBuilderVisualParity::class,
				BuilderArchitecture::class,
			)
		);
	}

	private function remove_class_callbacks( $hook, $classes ) {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks ) ) return;
		foreach ( (array) $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( (array) $callbacks as $callback ) {
				$function = $callback['function'] ?? null;
				if ( ! is_array( $function ) || ! isset( $function[0] ) || ! is_object( $function[0] ) ) continue;
				if ( ! in_array( get_class( $function[0] ), $classes, true ) ) continue;
				remove_filter( $hook, $function, $priority );
			}
		}
	}

	public function register_routes() {
		// Replace the legacy Session route with verified repository persistence.
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/session/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_get_session' ), 'permission_callback' => array( $this, 'can_edit_document' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save_session' ), 'permission_callback' => array( $this, 'can_edit_document' ) ),
			),
			true
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/theme-session/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_get_theme_session' ), 'permission_callback' => array( $this, 'can_edit_theme_settings' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save_theme_session' ), 'permission_callback' => array( $this, 'can_edit_theme_settings' ) ),
			),
			true
		);

		// Component payloads can contain escaped CSS/rich text too, so they share
		// the same slash-safe, read-back-verified persistence rule.
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/components',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_components' ), 'permission_callback' => static function () { return current_user_can( 'edit_pages' ); } ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save_component' ), 'permission_callback' => static function () { return current_user_can( 'edit_pages' ); } ),
			),
			true
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/components/(?P<id>\d+)',
			array(
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'rest_save_component' ), 'permission_callback' => array( $this, 'can_edit_component' ) ),
				array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'rest_delete_component' ), 'permission_callback' => array( $this, 'can_edit_component' ) ),
			),
			true
		);

		// Architecture v2 is a persisted sidecar. Replace its write route so
		// nested slots, bindings and part styles cannot be lost by WP unslashing.
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/architecture-v2/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_get_architecture_v2' ), 'permission_callback' => array( $this, 'can_edit_document' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_save_architecture_v2' ), 'permission_callback' => array( $this, 'can_edit_document' ) ),
			),
			true
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/page-settings/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_page_settings' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_page_settings' ) ),
			),
			true
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/theme-page-settings/(?P<postId>\d+)',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_theme_settings' ) ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_theme_settings' ) ),
			),
			true
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/core/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_manifest' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/transactions/(?P<postId>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_preview_transaction' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/transactions/(?P<postId>\d+)/commit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_commit_transaction' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/system-status/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_system_status' ),
				'permission_callback' => array( $this, 'can_edit_document' ),
			)
		);
	}

	public function rest_get_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$repository = new WordPressDocumentRepository();
		$session = $repository->load( $post_id );
		if ( is_wp_error( $session ) ) return $session;
		if ( ! is_array( $session ) ) $session = WebsiteBuilder::empty_session( $post_id );
		return new WP_REST_Response( array(
			'session'   => $session,
			'checksum'  => Document::checksum( $session ),
			'nodeCount' => self::count_nodes( $session['nodes'] ?? array() ),
			'postTitle' => get_the_title( $post_id ),
			'builder'   => $this->has_builder_document( $post_id ) ? WebsiteBuilder::BUILDER_VERSION : 'legacy-session',
		) );
	}

	public function rest_save_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $payload;
		$repository = new WordPressDocumentRepository();
		$if_match = sanitize_text_field( (string) ( $payload['ifMatch'] ?? '' ) );
		if ( '' !== $if_match ) {
			$current = $repository->checksum( $post_id );
			if ( is_wp_error( $current ) ) return $current;
			if ( ! hash_equals( $if_match, (string) $current ) ) {
				return new WP_Error( 'cresco_document_save_conflict', __( 'The document changed after it was loaded. Refresh before saving.', 'cresco-canvas' ), array( 'status' => 409, 'expectedChecksum' => $if_match, 'currentChecksum' => $current ) );
			}
		}
		$saved = $repository->save( $post_id, $input );
		if ( is_wp_error( $saved ) ) return $saved;
		update_post_meta( $post_id, EditorIntegration::ENABLED_META, true );
		if ( isset( $payload['postTitle'] ) ) {
			$title = sanitize_text_field( (string) $payload['postTitle'] );
			if ( '' !== $title && $title !== get_the_title( $post_id ) ) wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		}
		return new WP_REST_Response( array(
			'session'   => $saved,
			'checksum'  => Document::checksum( $saved ),
			'nodeCount' => self::count_nodes( $saved['nodes'] ?? array() ),
			'savedAt'   => gmdate( 'c' ),
			'builder'   => WebsiteBuilder::BUILDER_VERSION,
			'verified'  => true,
		) );
	}

	public function rest_get_theme_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$repository = new WordPressDocumentRepository();
		$session = $repository->load( $post_id );
		if ( is_wp_error( $session ) ) return $session;
		if ( ! is_array( $session ) ) $session = WebsiteBuilder::empty_session( $post_id );
		return new WP_REST_Response( array(
			'session'   => $session,
			'checksum'  => Document::checksum( $session ),
			'nodeCount' => self::count_nodes( $session['nodes'] ?? array() ),
			'postTitle' => get_the_title( $post_id ),
			'builder'   => WebsiteBuilder::BUILDER_VERSION,
		) );
	}

	public function rest_save_theme_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $payload;
		$session = WebsiteBuilder::sanitize_session( $input );
		if ( is_wp_error( $session ) ) return $session;
		$session['documentId'] = 'theme-' . $post_id;
		$repository = new WordPressDocumentRepository();
		$saved = $repository->save( $post_id, $session );
		if ( is_wp_error( $saved ) ) return $saved;
		$title = isset( $payload['postTitle'] ) ? sanitize_text_field( (string) $payload['postTitle'] ) : get_the_title( $post_id );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => ThemeSessionBridge::block_markup( $post_id ), 'post_title' => $title ) );
		return new WP_REST_Response( array(
			'session' => $saved, 'checksum' => Document::checksum( $saved ), 'nodeCount' => self::count_nodes( $saved['nodes'] ?? array() ),
			'savedAt' => gmdate( 'c' ), 'builder' => WebsiteBuilder::BUILDER_VERSION, 'verified' => true,
		) );
	}

	public function can_edit_component( WP_REST_Request $request ) {
		$id = absint( $request['id'] ?? 0 );
		return $id > 0 && WebsiteBuilder::COMPONENT_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}

	public function rest_components() {
		$posts = get_posts( array( 'post_type' => WebsiteBuilder::COMPONENT_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 200, 'orderby' => 'modified', 'order' => 'DESC' ) );
		$items = array();
		foreach ( $posts as $post ) {
			$item = $this->present_component( $post );
			if ( $item ) $items[] = $item;
		}
		return new WP_REST_Response( $items );
	}

	public function rest_save_component( WP_REST_Request $request ) {
		$id = absint( $request['id'] ?? 0 );
		$payload = (array) $request->get_json_params();
		$title = sanitize_text_field( (string) ( $payload['title'] ?? '' ) );
		$node = isset( $payload['node'] ) && is_array( $payload['node'] ) ? $payload['node'] : null;
		if ( '' === $title || ! $node ) return new WP_Error( 'cresco_component_invalid', __( 'Component title and node are required.', 'cresco-canvas' ), array( 'status' => 400 ) );
		$session = WebsiteBuilder::sanitize_session( array( 'schema' => 'cresco-session/v1', 'version' => 1, 'documentId' => 'component', 'nodes' => array( $node ) ) );
		if ( is_wp_error( $session ) ) return $session;
		$json = wp_json_encode( $session['nodes'][0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_component_encode_failed', __( 'Component could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		$post_id = wp_insert_post( array( 'ID' => $id, 'post_type' => WebsiteBuilder::COMPONENT_TYPE, 'post_status' => 'publish', 'post_title' => $title ), true );
		if ( is_wp_error( $post_id ) ) return $post_id;
		update_post_meta( $post_id, WebsiteBuilder::COMPONENT_META, wp_slash( $json ) );
		$raw = (string) get_post_meta( $post_id, WebsiteBuilder::COMPONENT_META, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) || ! hash_equals( hash( 'sha256', $json ), hash( 'sha256', (string) wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) ) {
			return new WP_Error( 'cresco_component_verify_failed', __( 'The Component write could not be verified.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}
		$item = $this->present_component( get_post( $post_id ) );
		return new WP_REST_Response( $item, $id ? 200 : 201 );
	}

	public function rest_delete_component( WP_REST_Request $request ) {
		$result = wp_trash_post( absint( $request['id'] ) );
		return $result ? new WP_REST_Response( array( 'deleted' => true ) ) : new WP_Error( 'cresco_component_delete_failed', __( 'Component could not be moved to Trash.', 'cresco-canvas' ), array( 'status' => 500 ) );
	}

	private function present_component( $post ) {
		if ( ! $post instanceof \WP_Post ) return null;
		$raw = (string) get_post_meta( $post->ID, WebsiteBuilder::COMPONENT_META, true );
		$node = '' !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $node ) ) return null;
		$session = WebsiteBuilder::sanitize_session( array( 'schema' => 'cresco-session/v1', 'version' => 1, 'documentId' => 'component', 'nodes' => array( $node ) ) );
		if ( is_wp_error( $session ) ) return null;
		$canonical = $session['nodes'][0];
		return array(
			'id'       => (int) $post->ID,
			'title'    => get_the_title( $post ),
			'node'     => $canonical,
			'checksum' => hash( 'sha256', (string) wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ),
			'modified' => get_post_modified_time( 'c', true, $post ),
		);
	}

	public function rest_get_architecture_v2( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session = $this->load_session( $post_id );
		if ( ! $session ) return new WP_Error( 'cresco_architecture_session_missing', __( 'The Website Builder session could not be loaded.', 'cresco-canvas' ), array( 'status' => 404 ) );
		return new WP_REST_Response( array(
			'architecture' => WebsiteBuilderArchitectureV2::load_document( $post_id, $session ),
			'catalogVersion' => self::VERSION,
			'savedAt' => (string) get_post_meta( $post_id, WidgetArchitectureV2::META_KEY . '_saved_at', true ),
		) );
	}

	public function rest_save_architecture_v2( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session = $this->load_session( $post_id );
		if ( ! $session ) return new WP_Error( 'cresco_architecture_session_missing', __( 'The Website Builder session could not be loaded.', 'cresco-canvas' ), array( 'status' => 404 ) );
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['architecture'] ) && is_array( $payload['architecture'] ) ? $payload['architecture'] : $payload;
		$document = WidgetArchitectureV2::sanitize_document( $input, $session );
		$json = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_architecture_encode', __( 'Widget architecture could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		update_post_meta( $post_id, WidgetArchitectureV2::META_KEY, wp_slash( $json ) );
		$raw = (string) get_post_meta( $post_id, WidgetArchitectureV2::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : null;
		$verified = is_array( $decoded ) ? WidgetArchitectureV2::sanitize_document( $decoded, $session ) : null;
		$verified_json = is_array( $verified ) ? wp_json_encode( $verified, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : false;
		if ( ! is_string( $verified_json ) || ! hash_equals( hash( 'sha256', $json ), hash( 'sha256', $verified_json ) ) ) {
			return new WP_Error( 'cresco_architecture_verify', __( 'Widget architecture persistence verification failed.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}
		$saved_at = gmdate( 'c' );
		update_post_meta( $post_id, WidgetArchitectureV2::META_KEY . '_saved_at', $saved_at );
		return new WP_REST_Response( array( 'valid' => true, 'architecture' => $verified, 'savedAt' => $saved_at, 'verified' => true ) );
	}

	private static function count_nodes( $nodes ) {
		$count = 0;
		foreach ( (array) $nodes as $node ) {
			++$count;
			$count += self::count_nodes( $node['children'] ?? array() );
		}
		return $count;
	}

	public function can_edit_page_settings( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function can_edit_theme_settings( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && ThemeBuilder::POST_TYPE === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_page_settings( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		if ( 'GET' === $request->get_method() ) return new WP_REST_Response( self::page_settings_payload( PageSettings::get( $post_id ) ) );
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : $payload;
		if ( ! array_key_exists( 'customCSS', $input ) && array_key_exists( 'customCss', $input ) ) $input['customCSS'] = $input['customCss'];
		unset( $input['customCss'] );
		if ( 'inherit' === ( $input['pageTitle'] ?? '' ) ) $input['pageTitle'] = 'show';
		if ( 'content' === ( $input['contentRoot'] ?? '' ) ) $input['contentRoot'] = 'theme';
		$custom_css = PageSettings::sanitize_page_custom_css( $input['customCSS'] ?? '' );
		if ( is_wp_error( $custom_css ) ) return $custom_css;
		$input['customCSS'] = $custom_css;
		$settings = PageSettings::sanitize( $input );
		$json = wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_page_settings_encode_failed', __( 'Page Settings could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		update_post_meta( $post_id, PageSettings::META_KEY, wp_slash( $json ) );
		$raw = (string) get_post_meta( $post_id, PageSettings::META_KEY, true );
		$decoded = '' !== $raw ? json_decode( $raw, true ) : null;
		$verified = is_array( $decoded ) ? PageSettings::sanitize( $decoded ) : null;
		$verified_json = is_array( $verified ) ? wp_json_encode( $verified, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : false;
		if ( ! is_string( $verified_json ) || ! hash_equals( hash( 'sha256', $json ), hash( 'sha256', $verified_json ) ) ) {
			return new WP_Error( 'cresco_page_settings_verify_failed', __( 'Page Settings persistence verification failed.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}
		if ( 'page' === get_post_type( $post_id ) ) update_post_meta( $post_id, EditorIntegration::ENABLED_META, true );
		$response = self::page_settings_payload( $verified );
		$response['savedAt'] = gmdate( 'c' );
		$response['verified'] = true;
		return new WP_REST_Response( $response );
	}

	private static function page_settings_payload( $settings ) {
		$settings = PageSettings::sanitize( $settings );
		$effective = PageSettings::effective( $settings );
		$settings['customCss'] = (string) ( $settings['customCSS'] ?? '' );
		$effective['customCss'] = (string) ( $effective['customCSS'] ?? '' );
		return array( 'settings' => $settings, 'effective' => $effective );
	}

	public function can_edit_document( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		$post_type = $post_id ? (string) get_post_type( $post_id ) : '';
		return $post_id > 0
			&& in_array( $post_type, array( 'page', ThemeBuilder::POST_TYPE ), true )
			&& current_user_can( 'edit_post', $post_id );
	}

	public function rest_manifest( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$repository = new WordPressDocumentRepository();
		$session = $repository->load( $post_id );
		if ( is_wp_error( $session ) ) return $session;
		if ( ! is_array( $session ) ) $session = WebsiteBuilder::empty_session( $post_id );
		$architecture = WebsiteBuilderArchitectureV2::load_document( $post_id, $session );
		return new WP_REST_Response(
			array(
				'schema'       => self::SCHEMA,
				'version'      => self::VERSION,
				'documentType' => $repository->type( $post_id ),
				'frontendOwner'=> 'WebsiteBuilderCorePlatform',
				'styleContract'=> self::STYLE_CONTRACT,
				'responsive'   => ResponsiveResolver::manifest(),
				'designSystem' => DesignSystemAnalyzer::manifest( $session, $architecture ),
				'inspector'    => InspectorSchema::manifest(),
				'widgets'      => WidgetCatalog::all(),
				'architectureV2' => array(
					'schema'  => WidgetArchitectureV2::SCHEMA,
					'catalog' => WidgetArchitectureV2::catalog(),
				),
				'endpoints' => array(
					'render'             => '/cresco-canvas/v1/website-builder/render/' . $post_id,
					'transactionPreview' => '/cresco-canvas/v1/website-builder/transactions/' . $post_id . '/preview',
					'transactionCommit'  => '/cresco-canvas/v1/website-builder/transactions/' . $post_id . '/commit',
					'systemStatus'       => '/cresco-canvas/v1/website-builder/system-status/' . $post_id,
				),
				'capabilities' => array(
					'canonicalRenderer'      => true,
					'schemaDrivenInspector'  => true,
					'responsiveInheritance'  => true,
					'designTokenUsage'       => true,
					'partStyling'            => true,
					'dynamicBindings'        => true,
					'nestedComponentSlots'   => true,
					'loopTemplates'          => true,
					'formEngineV2'           => true,
					'transactions'           => true,
					'aiPatch'                => true,
					'verifiedPersistence'    => true,
				),
				'budgets' => array(
					'maxNodes'              => WebsiteBuilder::MAX_NODES,
					'maxDepth'              => WebsiteBuilder::MAX_DEPTH,
					'maxCustomCssBytes'     => WebsiteBuilder::MAX_CUSTOM_CSS,
					'maxTransactionCommands'=> TransactionManager::MAX_COMMANDS,
				),
			)
		);
	}

	public function rest_preview_transaction( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$session = $this->session_from_payload( $post_id, $payload );
		if ( is_wp_error( $session ) ) return $session;
		$transaction = isset( $payload['transaction'] ) && is_array( $payload['transaction'] ) ? $payload['transaction'] : $payload;
		$result = TransactionManager::preview( $session, $transaction );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	public function rest_commit_transaction( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$repository = new WordPressDocumentRepository();
		$stored = $repository->load( $post_id );
		if ( is_wp_error( $stored ) ) return $stored;
		if ( ! is_array( $stored ) ) $stored = WebsiteBuilder::empty_session( $post_id );

		$if_match = sanitize_text_field( (string) ( $payload['ifMatch'] ?? '' ) );
		$current_checksum = Document::checksum( $stored );
		if ( '' !== $if_match && ! hash_equals( $if_match, $current_checksum ) ) {
			return new WP_Error(
				'cresco_transaction_conflict',
				__( 'The document changed after this transaction was prepared. Refresh and try again.', 'cresco-canvas' ),
				array( 'status' => 409, 'expectedChecksum' => $if_match, 'currentChecksum' => $current_checksum )
			);
		}

		$transaction = isset( $payload['transaction'] ) && is_array( $payload['transaction'] ) ? $payload['transaction'] : $payload;
		$result = TransactionManager::preview( $stored, $transaction );
		if ( is_wp_error( $result ) ) return $result;
		$saved = $repository->save( $post_id, $result['session'] );
		if ( is_wp_error( $saved ) ) return $saved;

		return new WP_REST_Response(
			array(
				'valid'       => true,
				'committed'   => true,
				'session'     => $saved,
				'checksum'    => Document::checksum( $saved ),
				'diff'        => $result['diff'],
				'transaction' => $result['transaction'],
				'savedAt'     => gmdate( 'c' ),
			)
		);
	}

	public function rest_system_status( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$repository = new WordPressDocumentRepository();
		$session = $repository->load( $post_id );
		if ( is_wp_error( $session ) ) return $session;
		if ( ! is_array( $session ) ) $session = WebsiteBuilder::empty_session( $post_id );
		$architecture = WebsiteBuilderArchitectureV2::load_document( $post_id, $session );
		$stats = array( 'nodes' => 0, 'maxDepth' => 0, 'customCssBytes' => 0, 'forms' => 0, 'loops' => 0, 'wooWidgets' => 0, 'hiddenNodes' => 0 );
		$this->inspect_nodes( $session['nodes'] ?? array(), 1, $stats );
		$warnings = array();
		if ( $stats['nodes'] > 600 ) $warnings[] = __( 'Large document: consider reusable Components and smaller nested sections.', 'cresco-canvas' );
		if ( $stats['maxDepth'] > 12 ) $warnings[] = __( 'Deep nesting can make responsive editing harder to maintain.', 'cresco-canvas' );
		if ( $stats['customCssBytes'] > 8000 ) $warnings[] = __( 'Heavy Custom CSS detected; prefer structured controls and Global Design tokens.', 'cresco-canvas' );

		return new WP_REST_Response(
			array(
				'schema'        => 'cresco-system-status/v1',
				'privacySafe'   => true,
				'coreVersion'   => self::VERSION,
				'styleContract' => self::STYLE_CONTRACT,
				'documentType'  => $repository->type( $post_id ),
				'checksum'      => Document::checksum( $session ),
				'stats'         => $stats,
				'tokenUsage'    => DesignSystemAnalyzer::usage( $session, $architecture ),
				'capabilities'  => array(
					'woocommerce' => class_exists( '\\WooCommerce' ) || defined( 'WC_VERSION' ) || function_exists( 'WC' ),
					'acf'         => function_exists( 'get_field' ),
					'objectCache' => wp_using_ext_object_cache(),
					'themeBuilder'=> post_type_exists( ThemeBuilder::POST_TYPE ),
				),
				'warnings' => $warnings,
			)
		);
	}

	/** Enqueue one editor bridge exposing the consolidated manifest and node index. */
	public function enqueue_editor_bridge() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! wp_script_is( 'cresco-canvas-website-builder', 'enqueued' ) ) return;
		$post_id = $context->post_id();
		if ( ! $post_id ) return;
		$config = array(
			'postId'             => $post_id,
			'manifestPath'       => '/cresco-canvas/v1/website-builder/core/' . $post_id,
			'transactionPreview' => '/cresco-canvas/v1/website-builder/transactions/' . $post_id . '/preview',
			'transactionCommit'  => '/cresco-canvas/v1/website-builder/transactions/' . $post_id . '/commit',
			'systemStatusPath'   => '/cresco-canvas/v1/website-builder/system-status/' . $post_id,
			'version'            => self::VERSION,
		);
		wp_add_inline_script( 'cresco-canvas-website-builder', 'window.crescoBuilderCoreV2Settings=' . wp_json_encode( $config ) . ';', 'before' );
		wp_add_inline_script( 'cresco-canvas-website-builder', self::editor_bridge_script(), 'after' );
	}

	/** Render Theme Template preview through the same V2 RenderEngine as Studio. */
	public function render_theme_preview() {
		$template_id = isset( $_GET['cresco_theme_preview'] ) ? absint( wp_unslash( $_GET['cresco_theme_preview'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Explicit preview route.
		if ( ! $template_id ) return;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Explicit preview nonce.
		if ( ThemeBuilder::POST_TYPE !== get_post_type( $template_id ) || ! current_user_can( 'edit_post', $template_id ) || ! wp_verify_nonce( $nonce, 'cresco_theme_preview_' . $template_id ) ) {
			wp_die( esc_html__( 'Invalid Theme Template preview request.', 'cresco-canvas' ) );
		}
		$repository = new WordPressDocumentRepository();
		$session = $repository->load( $template_id );
		if ( is_wp_error( $session ) || ! is_array( $session ) || empty( $session['nodes'] ) ) wp_die( esc_html__( 'This Theme Template has no valid Cresco Session.', 'cresco-canvas' ) );
		$result = RenderEngine::render( $session, $template_id, $repository->type( $template_id ) );
		if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'assets/css/website-builder-frontend.css', array( 'cresco-canvas-frontend' ), CRESCO_CANVAS_VERSION );
		wp_enqueue_script( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'build/website-builder-frontend.js', array(), CRESCO_CANVAS_VERSION, true );
		if ( $this->contains_widget_type( $session['nodes'] ?? array(), 'form' ) ) { wp_enqueue_style( 'cresco-canvas-forms' ); wp_enqueue_script( 'cresco-canvas-forms-frontend' ); }
		$css = (string) ( $result['css'] ?? '' );
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?><style data-cresco-style-contract="<?php echo esc_attr( self::STYLE_CONTRACT ); ?>"><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized compiler output. ?></style></head><body <?php body_class( 'cresco-theme-session-preview' ); ?>><?php wp_body_open(); echo $result['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Canonical renderer escapes widget output. ?><?php wp_footer(); ?></body></html><?php
		exit;
	}

	/** Enqueue only the canonical frontend assets. Document CSS is body-bound below. */
	public function enqueue_frontend_assets() {
		$post_id = self::frontend_page_id();
		if ( ! $post_id || ! $this->has_builder_document( $post_id ) ) return;
		$session = $this->load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return;
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'assets/css/website-builder-frontend.css', array( 'cresco-canvas-frontend' ), CRESCO_CANVAS_VERSION );
		wp_enqueue_script( 'cresco-canvas-website-builder-frontend', CRESCO_CANVAS_URL . 'build/website-builder-frontend.js', array(), CRESCO_CANVAS_VERSION, true );
		if ( $this->contains_widget_type( $session['nodes'] ?? array(), 'form' ) ) {
			wp_enqueue_style( 'cresco-canvas-forms' );
			wp_enqueue_script( 'cresco-canvas-forms-frontend' );
		}
	}

	/** Render Page content and CSS from the same canonical Session/Architecture snapshot. */
	public function render_frontend_content( $content ) {
		$post_id = self::frontend_page_id();
		if ( ! $post_id || ! $this->has_builder_document( $post_id ) ) return $content;
		$session = $this->load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return $content;
		$result = RenderEngine::render( $session, $post_id, 'page' );
		if ( is_wp_error( $result ) ) return $content;
		$css = (string) ( $result['css'] ?? '' );
		$hash = substr( hash( 'sha256', $css ), 0, 16 );
		return '<style data-cresco-style-contract="' . esc_attr( self::STYLE_CONTRACT ) . '" data-cresco-style-hash="' . esc_attr( $hash ) . '">' . $css . '</style>' . (string) $result['html'];
	}

	/** Theme-independent geometry only; node geometry stays in the compiler. */
	public static function surface_css() {
		return '.cresco-website-builder-root{width:100%;min-width:0;max-width:none;}'
			. '.cresco-website-builder-root,.cresco-website-builder-root *{box-sizing:border-box;}'
			. '.cresco-website-builder-root [data-cresco-id]{min-width:0;}'
			. '.cresco-website-builder-root img{max-width:100%;height:auto;}';
	}

	private function session_from_payload( $post_id, $payload ) {
		if ( isset( $payload['currentSession'] ) && is_array( $payload['currentSession'] ) ) {
			return WebsiteBuilder::sanitize_session( $payload['currentSession'] );
		}
		$repository = new WordPressDocumentRepository();
		$session = $repository->load( $post_id );
		if ( is_wp_error( $session ) ) return $session;
		return is_array( $session ) ? $session : WebsiteBuilder::empty_session( $post_id );
	}

	private function load_session( $post_id ) {
		$repository = new WordPressDocumentRepository();
		$session = $repository->load( $post_id );
		return is_wp_error( $session ) ? null : $session;
	}

	private function has_builder_document( $post_id ) {
		return WebsiteBuilder::BUILDER_VERSION === (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true );
	}

	private static function frontend_page_id() {
		if ( is_admin() || ! is_singular( 'page' ) ) return 0;
		$queried_id = absint( get_queried_object_id() );
		if ( ! $queried_id || 'page' !== get_post_type( $queried_id ) ) return 0;
		$content_id = absint( get_the_ID() );
		$parent_id = 0;
		if ( $content_id && function_exists( 'wp_is_post_revision' ) ) $parent_id = absint( wp_is_post_revision( $content_id ) );
		if ( $parent_id ) $content_id = $parent_id;
		return ! $content_id || $content_id === $queried_id ? $queried_id : 0;
	}

	private function contains_widget_type( $nodes, $type ) {
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			if ( $type === (string) ( $node['type'] ?? '' ) ) return true;
			if ( $this->contains_widget_type( $node['children'] ?? array(), $type ) ) return true;
		}
		return false;
	}

	private function inspect_nodes( $nodes, $depth, &$stats ) {
		$stats['maxDepth'] = max( (int) $stats['maxDepth'], (int) $depth );
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			++$stats['nodes'];
			$type = (string) ( $node['type'] ?? '' );
			if ( 'form' === $type ) ++$stats['forms'];
			if ( 'loop-grid' === $type ) ++$stats['loops'];
			if ( 0 === strpos( $type, 'woo-' ) ) ++$stats['wooWidgets'];
			if ( ! empty( $node['meta']['hidden'] ) ) ++$stats['hiddenNodes'];
			foreach ( (array) ( $node['customCSS'] ?? array() ) as $css ) $stats['customCssBytes'] += strlen( (string) $css );
			if ( ! empty( $node['children'] ) ) $this->inspect_nodes( $node['children'], $depth + 1, $stats );
		}
	}

	/** Client-side core facade: manifest, O(1) node index and transaction helpers. */
	private static function editor_bridge_script() {
		return <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
var wp=window.wp,cfg=window.crescoBuilderCoreV2Settings||{};
if(!root||!wp||!wp.apiFetch||!cfg.postId)return;
var api=wp.apiFetch,manifest=null,index=new Map(),session=null,pending=false;
function arr(v){return Array.isArray(v)?v:[];}
function currentSession(){
 var store=window.crescoDocumentStore;
 if(store&&typeof store.getState==='function'){
  var state=store.getState()||{},candidate=state.document||state.session||null;
  if(candidate&&Array.isArray(candidate.nodes))return candidate;
 }
 var runtime=window.crescoRuntimeState||{};
 return runtime.session&&Array.isArray(runtime.session.nodes)?runtime.session:session;
}
function rebuild(next){
 session=next&&Array.isArray(next.nodes)?next:currentSession();index.clear();
 (function walk(nodes){arr(nodes).forEach(function(node){if(!node||typeof node!=='object')return;var id=String(node.id||'');if(id)index.set(id,node);walk(node.children);});})(session&&session.nodes);
 window.dispatchEvent(new CustomEvent('cresco:core-index-change',{detail:{nodeCount:index.size}}));
}
function schedule(next){if(next&&Array.isArray(next.nodes))session=next;if(pending)return;pending=true;requestAnimationFrame(function(){pending=false;rebuild(session);});}
function cascade(device){return manifest&&manifest.responsive&&manifest.responsive.cascade&&manifest.responsive.cascade[device]||[];}
function effectiveStyle(node,device){var out=Object.assign({},node&&node.style||{}),responsive=node&&node.responsive||{};cascade(device||'wide').forEach(function(bucket){if(responsive[bucket])out=Object.assign(out,responsive[bucket]);});return out;}
function previewTransaction(transaction){return api({path:cfg.transactionPreview,method:'POST',data:{currentSession:currentSession()||undefined,transaction:transaction}});}
function commitTransaction(transaction,ifMatch){return api({path:cfg.transactionCommit,method:'POST',data:{transaction:transaction,ifMatch:ifMatch||''}});}
var core={version:String(cfg.version||'2.0.0'),manifest:function(){return manifest;},getSession:currentSession,getNode:function(id){return index.get(String(id||''))||null;},nodeCount:function(){return index.size;},effectiveStyle:effectiveStyle,previewTransaction:previewTransaction,commitTransaction:commitTransaction,refreshIndex:function(){schedule(currentSession());}};
window.crescoBuilderCoreV2=core;root.setAttribute('data-cresco-core-platform','v2');
window.addEventListener('cresco:studio-session-change',function(event){var detail=event&&event.detail||{};schedule(detail.session);});
window.addEventListener('cresco:document-store-change',function(){schedule(currentSession());});
api({path:cfg.manifestPath}).then(function(data){manifest=data||null;root.setAttribute('data-cresco-core-version',String(data&&data.version||cfg.version||'2.0.0'));var badge=root.querySelector('.cc-studio-canonical-preview-status');if(badge)badge.textContent='Core v2 · Frontend renderer';schedule(currentSession());window.dispatchEvent(new CustomEvent('cresco:core-ready',{detail:{manifest:manifest}}));}).catch(function(){schedule(currentSession());});
window.setTimeout(function(){schedule(currentSession());},100);
})(window,document);
JS;
	}

	public function __construct() {}
}
