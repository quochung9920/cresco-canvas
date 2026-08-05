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

	/** Register Page metadata and native block-editor assets. */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );
	}

	/** Register the Page-level switch that enables Cresco global styling. */
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

	/** Load Cresco tools inside the standard Gutenberg Page editor. */
	public function enqueue_assets() {
		if ( ! $this->is_page_editor() ) {
			return;
		}

		$editor_asset = $this->editor_asset();
		if ( null === $editor_asset ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-editor',
			CRESCO_CANVAS_URL . 'build/editor.css',
			array( 'wp-components' ),
			(string) $editor_asset['version']
		);
		wp_style_add_data( 'cresco-canvas-editor', 'rtl', 'replace' );
		wp_enqueue_script(
			'cresco-canvas-editor',
			CRESCO_CANVAS_URL . 'build/editor.js',
			(array) $editor_asset['dependencies'],
			(string) $editor_asset['version'],
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

		$elements_usage_asset = $this->elements_usage_asset();
		if ( null !== $elements_usage_asset ) {
			wp_enqueue_style(
				'cresco-canvas-elements-usage',
				CRESCO_CANVAS_URL . 'assets/css/elements-usage-sort.css',
				array( 'cresco-canvas-editor' ),
				(string) $elements_usage_asset['version']
			);
			wp_enqueue_script(
				'cresco-canvas-elements-usage',
				CRESCO_CANVAS_URL . 'build/elements-usage-sort.js',
				array( 'cresco-canvas-editor' ),
				(string) $elements_usage_asset['version'],
				true
			);
		}

		$design_system_asset = $this->design_system_asset();
		if ( null !== $design_system_asset ) {
			wp_enqueue_style(
				'cresco-canvas-design-system',
				CRESCO_CANVAS_URL . 'assets/css/design-system.css',
				array( 'wp-components' ),
				(string) $design_system_asset['version']
			);
			wp_style_add_data( 'cresco-canvas-design-system', 'rtl', 'replace' );
			wp_enqueue_script(
				'cresco-canvas-design-system',
				CRESCO_CANVAS_URL . 'build/design-system.js',
				(array) $design_system_asset['dependencies'],
				(string) $design_system_asset['version'],
				true
			);
			wp_set_script_translations( 'cresco-canvas-design-system', 'cresco-canvas' );
		}

		$preview_asset = $this->preview_asset();
		if ( null === $preview_asset ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-preview',
			CRESCO_CANVAS_URL . 'assets/css/preview.css',
			array( 'wp-components' ),
			(string) $preview_asset['version']
		);
		wp_style_add_data( 'cresco-canvas-preview', 'rtl', 'replace' );
		wp_enqueue_script(
			'cresco-canvas-preview',
			CRESCO_CANVAS_URL . 'build/preview.js',
			(array) $preview_asset['dependencies'],
			(string) $preview_asset['version'],
			true
		);
		wp_add_inline_script(
			'cresco-canvas-preview',
			'window.crescoCanvasPreviewSettings = ' . wp_json_encode(
				array(
					'previewUrl' => $this->preview_url(),
					'version'    => CRESCO_CANVAS_VERSION,
				)
			) . ';',
			'before'
		);
		wp_set_script_translations( 'cresco-canvas-preview', 'cresco-canvas' );
	}

	/** Warn administrators without preventing the native editor from loading. */
	public function render_missing_build_notice() {
		if (
			! $this->is_page_editor() ||
			(
				null !== $this->editor_asset() &&
				null !== $this->elements_usage_asset() &&
				null !== $this->preview_asset() &&
				null !== $this->design_system_asset()
			) ||
			! current_user_can( 'activate_plugins' )
		) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Some Cresco Canvas tools are unavailable.', 'cresco-canvas' ),
			esc_html__( 'A compiled editor, Elements ranking, Design System, or preview asset is missing. Gutenberg remains fully usable; install a release ZIP or run the production build to restore Cresco tools.', 'cresco-canvas' )
		);
	}

	/** @return array<string, mixed>|null */
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

	/** @return array<string, mixed>|null */
	private function elements_usage_asset() {
		$script_path = CRESCO_CANVAS_PATH . 'build/elements-usage-sort.js';
		$style_path  = CRESCO_CANVAS_PATH . 'assets/css/elements-usage-sort.css';
		$asset_path  = CRESCO_CANVAS_PATH . 'build/elements-usage-sort.asset.php';
		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) || ! is_readable( $asset_path ) ) {
			return null;
		}
		$asset = require $asset_path;
		return is_array( $asset ) && isset( $asset['dependencies'], $asset['version'] ) ? $asset : null;
	}

	/** @return array<string, mixed>|null */
	private function design_system_asset() {
		$script_path = CRESCO_CANVAS_PATH . 'build/design-system.js';
		$style_path  = CRESCO_CANVAS_PATH . 'assets/css/design-system.css';
		$asset_path  = CRESCO_CANVAS_PATH . 'build/design-system.asset.php';
		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) || ! is_readable( $asset_path ) ) {
			return null;
		}
		$asset = require $asset_path;
		return is_array( $asset ) && isset( $asset['dependencies'], $asset['version'] ) ? $asset : null;
	}

	/** @return array<string, mixed>|null */
	private function preview_asset() {
		$script_path = CRESCO_CANVAS_PATH . 'build/preview.js';
		$style_path  = CRESCO_CANVAS_PATH . 'assets/css/preview.css';
		$asset_path  = CRESCO_CANVAS_PATH . 'build/preview.asset.php';
		if ( ! is_readable( $script_path ) || ! is_readable( $style_path ) || ! is_readable( $asset_path ) ) {
			return null;
		}
		$asset = require $asset_path;
		return is_array( $asset ) && isset( $asset['dependencies'], $asset['version'] ) ? $asset : null;
	}

	/** Resolve a permission-aware preview URL for the Page being edited. */
	private function preview_url() {
		$post_id = 0;
		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
			$post_id = (int) $GLOBALS['post']->ID;
		} elseif ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen context.
			$post_id = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen context.
		}
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}
		$url = get_preview_post_link( $post_id );
		return is_string( $url ) ? $url : '';
	}

	/** Detect only the standard Gutenberg Page editor screen. */
	private function is_page_editor() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && 'post' === $screen->base && 'page' === $screen->post_type && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}
}
