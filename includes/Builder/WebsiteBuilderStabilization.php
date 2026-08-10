<?php
/**
 * Runtime stabilization boundary for the consolidated Website Builder.
 *
 * Keeps compatibility layers from changing canonical dependencies/contracts and
 * bridges the remaining Page/Theme editor routes while older adapters retire.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Infrastructure\WordPress\Storage\WordPressDocumentRepository;
use CrescoCanvas\Page\PageSettings;
use CrescoCanvas\Theme\ThemeBuilder;
use CrescoCanvas\Theme\ThemeSessionBridge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderStabilization {
	const SCRIPT_HANDLE = 'cresco-canvas-website-builder-stability';

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 45 );
		add_action( 'admin_enqueue_scripts', array( $this, 'normalize_editor_runtime' ), 1090 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_theme_form_assets' ), 50 );
		add_filter( 'render_block_cresco/theme-session', array( $this, 'repair_theme_session_block' ), 20, 3 );
	}

	/** Override Page/Theme settings compatibility routes with one canonical implementation. */
	public function register_routes() {
		$page_args = array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_page_settings' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_page_settings' ) ),
		);
		$theme_args = array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_theme_settings' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'rest_page_settings' ), 'permission_callback' => array( $this, 'can_edit_theme_settings' ) ),
		);
		register_rest_route( 'cresco-canvas/v1', '/page-settings/(?P<postId>\d+)', $page_args, true );
		register_rest_route( 'cresco-canvas/v1', '/website-builder/theme-page-settings/(?P<postId>\d+)', $theme_args, true );
	}

	public function can_edit_page_settings( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function can_edit_theme_settings( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && ThemeBuilder::POST_TYPE === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	/** Read or save Page Settings for both Page and Theme document editors. */
	public function rest_page_settings( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		if ( ! $post_id ) return new WP_Error( 'cresco_page_settings_document', __( 'A valid Cresco document is required.', 'cresco-canvas' ), array( 'status' => 400 ) );

		if ( WP_REST_Server::READABLE === $request->get_method() || 'GET' === $request->get_method() ) {
			return new WP_REST_Response( $this->settings_payload( PageSettings::get( $post_id ) ) );
		}

		$payload = (array) $request->get_json_params();
		$input = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : $payload;
		// The existing React editor historically used customCss while the server
		// contract is customCSS. Accept the UI alias without storing duplicate keys.
		if ( ! array_key_exists( 'customCSS', $input ) && array_key_exists( 'customCss', $input ) ) $input['customCSS'] = $input['customCss'];
		unset( $input['customCss'] );

		// Normalize two retired UI option labels to the canonical PageSettings v2
		// values instead of silently falling back to the server defaults.
		if ( 'inherit' === ( $input['pageTitle'] ?? '' ) ) $input['pageTitle'] = 'show';
		if ( 'content' === ( $input['contentRoot'] ?? '' ) ) $input['contentRoot'] = 'theme';

		$custom_css = PageSettings::sanitize_page_custom_css( $input['customCSS'] ?? '' );
		if ( is_wp_error( $custom_css ) ) return $custom_css;
		$input['customCSS'] = $custom_css;
		$settings = PageSettings::sanitize( $input );
		$json = wp_json_encode( $settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return new WP_Error( 'cresco_page_settings_encode_failed', __( 'Page Settings could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );

		update_post_meta( $post_id, PageSettings::META_KEY, $json );
		if ( 'page' === get_post_type( $post_id ) ) update_post_meta( $post_id, EditorIntegration::ENABLED_META, true );
		$response = $this->settings_payload( $settings );
		$response['savedAt'] = gmdate( 'c' );
		return new WP_REST_Response( $response );
	}

	private function settings_payload( $settings ) {
		$settings = PageSettings::sanitize( $settings );
		$effective = PageSettings::effective( $settings );
		// Temporary UI aliases keep old editor builds functional while storage and
		// AI export remain canonical customCSS.
		$settings_ui = $settings;
		$effective_ui = $effective;
		$settings_ui['customCss'] = (string) ( $settings['customCSS'] ?? '' );
		$effective_ui['customCss'] = (string) ( $effective['customCSS'] ?? '' );
		return array( 'settings' => $settings_ui, 'effective' => $effective_ui );
	}

	/** Restore dependencies after the compatibility layer rewrites the core handle. */
	public function normalize_editor_runtime() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( ! in_array( $page, array( VisualEditor::PAGE_SLUG, ThemeSessionBridge::PAGE_SLUG ), true ) ) return;
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) return;

		$scripts = wp_scripts();
		$core_handle = 'cresco-canvas-website-builder';
		if ( isset( $scripts->registered[ $core_handle ] ) ) {
			$required = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
			$scripts->registered[ $core_handle ]->deps = array_values( array_unique( array_merge( (array) $scripts->registered[ $core_handle ]->deps, $required ) ) );
		}

		$script_path = CRESCO_CANVAS_PATH . 'build/website-builder-stability.js';
		if ( ! is_readable( $script_path ) ) return;
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CRESCO_CANVAS_URL . 'build/website-builder-stability.js',
			array( 'cresco-canvas-builder-architecture' ),
			$this->asset_version( $script_path ),
			true
		);
	}

	/** Repair native Form output inside Theme Builder session blocks. */
	public function repair_theme_session_block( $content, $block, $instance = null ) {
		unset( $instance );
		$template_id = is_array( $block ) ? absint( $block['attrs']['templateId'] ?? 0 ) : 0;
		if ( ! $template_id ) return $content;
		$session = ( new WordPressDocumentRepository() )->load( $template_id );
		return is_array( $session ) ? WebsiteBuilderRendererParity::repair_document_html( $content, $session ) : $content;
	}

	/** Enqueue Form assets before wp_head prints styles for active Theme documents. */
	public function enqueue_theme_form_assets() {
		if ( is_admin() ) return;
		$sessions = array();
		$preview_id = isset( $_GET['cresco_theme_preview'] ) ? absint( wp_unslash( $_GET['cresco_theme_preview'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset routing; preview authorization remains in ThemeSessionBridge.
		if ( $preview_id && ThemeBuilder::POST_TYPE === get_post_type( $preview_id ) ) {
			$session = ( new WordPressDocumentRepository() )->load( $preview_id );
			if ( is_array( $session ) ) $sessions[] = $session;
		} else {
			$builder = new ThemeBuilder();
			$types = array_values( array_unique( array_filter( array( 'header', 'footer', $this->frontend_document_type() ) ) ) );
			foreach ( $types as $type ) {
				$template = $builder->resolve( $type );
				if ( ! $template instanceof \WP_Post ) continue;
				$session = ( new WordPressDocumentRepository() )->load( $template->ID );
				if ( is_array( $session ) ) $sessions[] = $session;
			}
		}
		foreach ( $sessions as $session ) {
			if ( $this->session_has_form( $session['nodes'] ?? array() ) ) {
				wp_enqueue_style( 'cresco-canvas-forms' );
				wp_enqueue_script( 'cresco-canvas-forms-frontend' );
				break;
			}
		}
	}

	private function session_has_form( $nodes ) {
		foreach ( (array) $nodes as $node ) {
			if ( 'form' === ( $node['type'] ?? '' ) ) return true;
			if ( ! empty( $node['children'] ) && $this->session_has_form( $node['children'] ) ) return true;
		}
		return false;
	}

	private function frontend_document_type() {
		if ( is_404() ) return '404';
		if ( is_search() ) return 'search';
		if ( is_archive() || is_home() ) return 'archive';
		if ( is_page() ) return 'page';
		if ( is_singular() ) return 'single';
		return '';
	}

	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}
}
