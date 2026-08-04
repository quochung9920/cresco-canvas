<?php
/**
 * Native Gutenberg integration.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorIntegration {
	const ENABLED_META = '_cresco_canvas_enabled';

	/**
	 * Register Page metadata and native block-editor assets.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );
	}

	/**
	 * Register the Page-level switch that enables Cresco global styling.
	 */
	public function register_meta() {
		register_post_meta(
			'page',
			self::ENABLED_META,
			array(
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'default'           => false,
				'description'       => __( 'Whether Cresco Canvas global design tokens apply to this Page.', 'cresco-canvas' ),
				'label'             => __( 'Cresco Canvas Page styles', 'cresco-canvas' ),
				'revisions_enabled' => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
			)
		);
	}

	/**
	 * Load the Cresco sidebar inside the standard Gutenberg Page editor.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_page_editor() ) {
			return;
		}

		$asset = $this->editor_asset();

		if ( null === $asset ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-editor',
			CRESCO_CANVAS_URL . 'build/editor.css',
			array( 'wp-components' ),
			(string) $asset['version']
		);
		wp_style_add_data( 'cresco-canvas-editor', 'rtl', 'replace' );
		wp_enqueue_script(
			'cresco-canvas-editor',
			CRESCO_CANVAS_URL . 'build/editor.js',
			(array) $asset['dependencies'],
			(string) $asset['version'],
			true
		);
		wp_add_inline_script(
			'cresco-canvas-editor',
			'window.crescoCanvasEditorSettings = ' . wp_json_encode(
				array(
					'canManageSettings' => current_user_can( 'edit_theme_options' ),
					'restPath'          => '/cresco-canvas/v1/',
					'version'           => CRESCO_CANVAS_VERSION,
				)
			) . ';',
			'before'
		);
		wp_set_script_translations( 'cresco-canvas-editor', 'cresco-canvas' );
	}

	/**
	 * Warn administrators without preventing the native editor from loading.
	 */
	public function render_missing_build_notice() {
		if ( ! $this->is_page_editor() || null !== $this->editor_asset() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Cresco Canvas tools are unavailable.', 'cresco-canvas' ),
			esc_html__( 'The compiled editor extension is missing. Gutenberg remains fully usable; install a release ZIP or run the production build to restore Cresco tools.', 'cresco-canvas' )
		);
	}

	/**
	 * Return a validated editor asset manifest.
	 *
	 * @return array<string, mixed>|null
	 */
	private function editor_asset() {
		$script_path = CRESCO_CANVAS_PATH . 'build/editor.js';
		$style_path  = CRESCO_CANVAS_PATH . 'build/editor.css';
		$asset_path  = CRESCO_CANVAS_PATH . 'build/editor.asset.php';

		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) || ! is_readable( $asset_path ) ) {
			return null;
		}

		$asset = require $asset_path;

		return is_array( $asset ) && isset( $asset['dependencies'], $asset['version'] ) ? $asset : null;
	}

	/**
	 * Detect only the standard Gutenberg Page editor screen.
	 *
	 * @return bool
	 */
	private function is_page_editor() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		return $screen &&
			'post' === $screen->base &&
			'page' === $screen->post_type &&
			method_exists( $screen, 'is_block_editor' ) &&
			$screen->is_block_editor();
	}
}
