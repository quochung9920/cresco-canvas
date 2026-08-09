<?php
/**
 * Standalone Cresco visual editor screen backed by Cresco Session v1.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VisualEditor {
	const PAGE_SLUG = 'cresco-canvas-editor';

	/** @var string */
	private $hook_suffix = '';

	/** @var string */
	private $asset_error = '';

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'page_row_actions', array( $this, 'add_page_row_action' ), 20, 2 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_action' ), 90 );
	}

	public function register_screen() {
		$this->hook_suffix = (string) add_submenu_page( null, __( 'Cresco Canvas', 'cresco-canvas' ), __( 'Cresco Canvas', 'cresco-canvas' ), 'edit_pages', self::PAGE_SLUG, array( $this, 'render_screen' ) );
	}

	public function add_page_row_action( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) return $actions;
		$actions['cresco-canvas'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $this->editor_url( $post->ID ) ), esc_html__( 'Edit with Cresco Canvas', 'cresco-canvas' ) );
		return $actions;
	}

	public function add_admin_bar_action( $admin_bar ) {
		if ( ! is_admin_bar_showing() || ! is_object( $admin_bar ) ) return;
		$post_id = $this->resolve_post_id();
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;
		$admin_bar->add_node( array( 'id' => 'cresco-canvas-edit', 'title' => __( 'Edit with Cresco Canvas', 'cresco-canvas' ), 'href' => $this->editor_url( $post_id ) ) );
	}

	public function render_screen() {
		$post_id = $this->requested_post_id();
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) wp_die( esc_html__( 'You do not have permission to edit this Page with Cresco Canvas.', 'cresco-canvas' ) );

		echo '<div id="cresco-canvas-standalone-editor" class="cresco-canvas-standalone-editor" aria-live="polite">';
		if ( $this->asset_error ) {
			echo '<div class="cc-standalone-loading" role="alert"><strong>' . esc_html__( 'Cresco Canvas could not start.', 'cresco-canvas' ) . '</strong><span>' . esc_html( $this->asset_error ) . '</span></div>';
		} else {
			echo '<div class="cc-standalone-loading"><span class="spinner is-active" aria-hidden="true"></span><span>' . esc_html__( 'Loading Cresco Session…', 'cresco-canvas' ) . '</span></div>';
		}
		echo '</div>';
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( ! $this->is_editor_screen( $hook_suffix ) ) return;
		$post_id = $this->requested_post_id();
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) return;

		$required = array(
			'build/standalone-visual-editor.asset.php' => CRESCO_CANVAS_PATH . 'build/standalone-visual-editor.asset.php',
			'build/standalone-visual-editor.js' => CRESCO_CANVAS_PATH . 'build/standalone-visual-editor.js',
			'build/global-config-import.js' => CRESCO_CANVAS_PATH . 'build/global-config-import.js',
			'build/viewport-shell.js' => CRESCO_CANVAS_PATH . 'build/viewport-shell.js',
			'assets/css/standalone-visual-editor.css' => CRESCO_CANVAS_PATH . 'assets/css/standalone-visual-editor.css',
			'assets/css/global-config-import.css' => CRESCO_CANVAS_PATH . 'assets/css/global-config-import.css',
			'assets/css/viewport-shell.css' => CRESCO_CANVAS_PATH . 'assets/css/viewport-shell.css',
		);
		$missing = array();
		foreach ( $required as $relative => $absolute ) if ( ! is_readable( $absolute ) ) $missing[] = $relative;
		if ( $missing ) {
			$this->asset_error = sprintf( __( 'Required runtime assets are missing: %s. Run npm run build and reload the editor.', 'cresco-canvas' ), implode( ', ', $missing ) );
			return;
		}

		$asset = require $required['build/standalone-visual-editor.asset.php'];
		$editor_settings = array(
			'postId' => $post_id,
			'postType' => 'page',
			'sessionPath' => '/cresco-canvas/v1/session/' . $post_id,
			'validatePath' => '/cresco-canvas/v1/session/validate',
			'aiContextPath' => '/cresco-canvas/v1/ai-context/' . $post_id,
			'settingsPath' => '/cresco-canvas/v1/settings',
			'settingsImportPreviewPath' => '/cresco-canvas/v1/settings/import-preview',
			'canManageGlobal' => current_user_can( 'edit_theme_options' ),
			'previewUrl' => get_preview_post_link( $post_id ),
			'adminPagesUrl' => admin_url( 'edit.php?post_type=page' ),
			'initialTitle' => (string) $post->post_title,
			'version' => CRESCO_CANVAS_VERSION,
			'previewWidths' => array( 'wide' => 1920, 'desktop' => 1440, 'laptop' => 1366, 'tablet' => 768, 'mobile' => 390 ),
		);

		wp_enqueue_media( array( 'post' => $post_id ) );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'cresco-canvas-standalone-visual-editor', CRESCO_CANVAS_URL . 'assets/css/standalone-visual-editor.css', array( 'wp-components' ), (string) ( $asset['version'] ?? CRESCO_CANVAS_VERSION ) );
		wp_enqueue_style( 'cresco-canvas-global-config-import', CRESCO_CANVAS_URL . 'assets/css/global-config-import.css', array( 'cresco-canvas-standalone-visual-editor' ), CRESCO_CANVAS_VERSION );
		wp_enqueue_style( 'cresco-canvas-viewport-shell', CRESCO_CANVAS_URL . 'assets/css/viewport-shell.css', array( 'cresco-canvas-standalone-visual-editor' ), CRESCO_CANVAS_VERSION );
		wp_add_inline_style( 'cresco-canvas-standalone-visual-editor', 'html.wp-toolbar{padding-top:0!important}body.admin_page_cresco-canvas-editor,body.toplevel_page_cresco-canvas-editor{margin:0!important}' . GlobalStyles::css( '.cc-session-canvas' ) . GlobalStyles::visual_css( '.cc-session-canvas' ) );

		wp_enqueue_script( 'cresco-canvas-standalone-visual-editor', CRESCO_CANVAS_URL . 'build/standalone-visual-editor.js', (array) ( $asset['dependencies'] ?? array() ), (string) ( $asset['version'] ?? CRESCO_CANVAS_VERSION ), true );
		wp_add_inline_script( 'cresco-canvas-standalone-visual-editor', 'window.crescoCanvasStandaloneSettings = ' . wp_json_encode( $editor_settings ) . ';', 'before' );
		wp_set_script_translations( 'cresco-canvas-standalone-visual-editor', 'cresco-canvas' );

		wp_enqueue_script( 'cresco-canvas-viewport-shell', CRESCO_CANVAS_URL . 'build/viewport-shell.js', array( 'cresco-canvas-standalone-visual-editor' ), CRESCO_CANVAS_VERSION, true );
		wp_enqueue_script( 'cresco-canvas-global-config-import', CRESCO_CANVAS_URL . 'build/global-config-import.js', array( 'cresco-canvas-standalone-visual-editor', 'wp-api-fetch', 'wp-i18n' ), CRESCO_CANVAS_VERSION, true );
		wp_set_script_translations( 'cresco-canvas-global-config-import', 'cresco-canvas' );
	}

	public function editor_url( $post_id ) {
		return add_query_arg( array( 'page' => self::PAGE_SLUG, 'post' => absint( $post_id ) ), admin_url( 'admin.php' ) );
	}

	private function is_editor_screen( $hook_suffix ) {
		if ( $this->hook_suffix && $hook_suffix === $this->hook_suffix ) return true;
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
	}

	private function requested_post_id() {
		return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
	}

	private function resolve_post_id() {
		if ( is_admin() ) return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context.
		return (int) get_queried_object_id();
	}
}
