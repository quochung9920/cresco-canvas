<?php
/**
 * Comprehensive Website Builder V3 integration layer.
 *
 * Keeps editor/frontend CSS parity, exposes production diagnostics, and loads
 * portable interchange / professional workflow tools without replacing core.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Session\SessionManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderComprehensiveV3 {
	const SCRIPT_HANDLE = 'cresco-canvas-website-builder-comprehensive-v3';
	const STYLE_HANDLE  = 'cresco-canvas-website-builder-comprehensive-v3';

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 33 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 1040 );
		add_action( 'wp_enqueue_scripts', array( $this, 'replace_legacy_compiled_css' ), 47 );
		add_filter( 'rest_post_dispatch', array( $this, 'normalize_runtime_capabilities' ), 20, 3 );
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/website-builder/v3/diagnostics/(?P<postId>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_diagnostics' ),
				'permission_callback' => static function ( WP_REST_Request $request ) {
					$post_id = absint( $request['postId'] ?? 0 );
					return $post_id > 0 && current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/** Keep Woo capability detection robust across plugin bootstrap variations. */
	public function normalize_runtime_capabilities( $result, $server, $request ) {
		unset( $server );
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( ! in_array( $route, array( '/cresco-canvas/v1/website-builder/options', '/cresco-canvas/v1/website-builder/context/' . absint( $request['postId'] ?? 0 ) ), true ) ) return $result;
		if ( ! $result instanceof \WP_REST_Response ) return $result;
		$data = $result->get_data();
		if ( ! is_array( $data ) ) return $result;
		if ( '/cresco-canvas/v1/website-builder/options' === $route ) $data['woocommerce'] = self::has_woocommerce();
		if ( isset( $data['capabilities'] ) && is_array( $data['capabilities'] ) ) $data['capabilities']['woocommerce'] = self::has_woocommerce();
		$result->set_data( $data );
		return $result;
	}

	public function enqueue_editor() {
		if ( ! isset( $_GET['page'] ) || VisualEditor::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
			return;
		}
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( ! wp_script_is( 'cresco-canvas-website-builder', 'enqueued' ) || ! wp_style_is( 'cresco-canvas-website-builder', 'enqueued' ) ) return;

		$script = CRESCO_CANVAS_PATH . 'build/website-builder-comprehensive-v3.js';
		$style  = CRESCO_CANVAS_PATH . 'assets/css/website-builder-comprehensive-v3.css';
		if ( ! is_readable( $script ) || ! is_readable( $style ) ) return;
		$version = $this->asset_version( $script, 'v3-js' );
		$style_version = $this->asset_version( $style, 'v3-css' );

		wp_enqueue_style( self::STYLE_HANDLE, CRESCO_CANVAS_URL . 'assets/css/website-builder-comprehensive-v3.css', array( 'cresco-canvas-website-builder' ), $style_version );
		wp_enqueue_script( self::SCRIPT_HANDLE, CRESCO_CANVAS_URL . 'build/website-builder-comprehensive-v3.js', array( 'cresco-canvas-website-builder', 'wp-api-fetch' ), $version, true );
		$settings = array(
			'postId'             => $post_id,
			'exportPath'         => '/cresco-canvas/v1/website-builder/interchange/' . $post_id . '/export',
			'previewImportPath'  => '/cresco-canvas/v1/website-builder/interchange/' . $post_id . '/preview',
			'componentSyncPath'  => '/cresco-canvas/v1/website-builder/components/sync',
			'diagnosticsPath'    => '/cresco-canvas/v1/website-builder/v3/diagnostics/' . $post_id,
			'themeTemplatesPath' => '/cresco-canvas/v1/theme-templates',
			'componentsPath'     => '/cresco-canvas/v1/website-builder/components',
			'aiContextPath'      => '/cresco-canvas/v1/ai-interchange/' . $post_id . '/context',
			'woocommerce'       => self::has_woocommerce(),
			'maxRecommendedNodes' => 600,
			'version'            => 'comprehensive-v3',
		);
		wp_add_inline_script( self::SCRIPT_HANDLE, 'window.crescoWebsiteBuilderV3Settings=' . wp_json_encode( $settings ) . ';', 'before' );
	}

	/**
	 * Replace the legacy max-width compiler output with the authoritative range
	 * compiler. The handle is dedicated to Website Builder document CSS, so only
	 * inline chunks containing node selectors are replaced.
	 */
	public function replace_legacy_compiled_css() {
		if ( ! is_singular( 'page' ) ) return;
		$post_id = get_queried_object_id();
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;
		$session = $this->load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return;
		$handle = 'cresco-canvas-website-builder-frontend';
		if ( ! wp_style_is( $handle, 'enqueued' ) ) return;

		$styles = wp_styles();
		if ( isset( $styles->registered[ $handle ]->extra['after'] ) && is_array( $styles->registered[ $handle ]->extra['after'] ) ) {
			$styles->registered[ $handle ]->extra['after'] = array_values( array_filter(
				$styles->registered[ $handle ]->extra['after'],
				static function ( $css ) {
					$css = (string) $css;
					return false === strpos( $css, '.cresco-website-builder-root [data-cresco-id=' );
				}
			) );
		}
		wp_add_inline_style( $handle, WebsiteBuilderCssCompiler::compile( $session ) );
	}

	public function rest_diagnostics( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session = $this->load_session( $post_id );
		if ( ! $session ) $session = WebsiteBuilder::empty_session( $post_id );
		$stats = array( 'nodes' => 0, 'maxDepth' => 0, 'customCssBytes' => 0, 'wooWidgets' => 0, 'forms' => 0, 'loops' => 0 );
		$this->inspect_nodes( $session['nodes'] ?? array(), 1, $stats );
		$warnings = array();
		if ( $stats['nodes'] > 600 ) $warnings[] = __( 'Large document: consider reusable components and smaller nested sections.', 'cresco-canvas' );
		if ( $stats['maxDepth'] > 12 ) $warnings[] = __( 'Deep nesting can make editing and responsive layout harder to maintain.', 'cresco-canvas' );
		if ( $stats['customCssBytes'] > 8000 ) $warnings[] = __( 'Heavy Custom CSS detected; move repeatable styling into structured controls or Global Design.', 'cresco-canvas' );
		if ( $stats['wooWidgets'] && ! self::has_woocommerce() ) $warnings[] = __( 'WooCommerce widgets exist but WooCommerce is not active.', 'cresco-canvas' );

		return new WP_REST_Response( array(
			'healthy'      => empty( $warnings ),
			'stats'        => $stats,
			'warnings'     => $warnings,
			'capabilities' => array(
				'woocommerce' => self::has_woocommerce(),
				'acf'         => function_exists( 'get_field' ),
				'forms'       => post_type_exists( 'cresco_submission' ),
				'themeBuilder'=> post_type_exists( 'cresco_template' ),
				'interchange' => true,
				'patches'     => true,
				'componentSync'=> true,
			),
		) );
	}

	public static function has_woocommerce() {
		return class_exists( '\WooCommerce' ) || defined( 'WC_VERSION' ) || function_exists( 'WC' );
	}

	private function load_session( $post_id ) {
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		if ( '' === $raw ) return null;
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) return null;
		$session = WebsiteBuilder::sanitize_session( $decoded );
		return is_wp_error( $session ) ? null : $session;
	}

	private function inspect_nodes( $nodes, $depth, &$stats ) {
		$stats['maxDepth'] = max( $stats['maxDepth'], $depth );
		foreach ( (array) $nodes as $node ) {
			++$stats['nodes'];
			$type = (string) ( $node['type'] ?? '' );
			if ( 0 === strpos( $type, 'woo-' ) ) ++$stats['wooWidgets'];
			if ( 'form' === $type ) ++$stats['forms'];
			if ( 'loop-grid' === $type ) ++$stats['loops'];
			foreach ( (array) ( $node['customCSS'] ?? array() ) as $css ) $stats['customCssBytes'] += strlen( (string) $css );
			if ( ! empty( $node['children'] ) ) $this->inspect_nodes( $node['children'], $depth + 1, $stats );
		}
	}

	private function asset_version( $path, $fallback ) {
		$contents = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local immutable plugin asset.
		return false === $contents ? CRESCO_CANVAS_VERSION . '-' . $fallback : substr( hash( 'sha256', $contents ), 0, 12 );
	}
}
