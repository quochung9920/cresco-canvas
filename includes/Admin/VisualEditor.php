<?php
/**
 * Standalone Cresco visual editor screen backed by WordPress/Gutenberg data.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VisualEditor {
	const PAGE_SLUG = 'cresco-canvas-editor';

	/** @var string */
	private $hook_suffix = '';

	/** Register the standalone editor entry points. */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'page_row_actions', array( $this, 'add_page_row_action' ), 20, 2 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_action' ), 90 );
	}

	/** Register a hidden wp-admin screen dedicated to Cresco Canvas. */
	public function register_screen() {
		$this->hook_suffix = (string) add_submenu_page(
			null,
			__( 'Cresco Canvas', 'cresco-canvas' ),
			__( 'Cresco Canvas', 'cresco-canvas' ),
			'edit_pages',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/** Add "Edit with Cresco Canvas" beside the native page editor action. */
	public function add_page_row_action( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['cresco-canvas'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->editor_url( $post->ID ) ),
			esc_html__( 'Edit with Cresco Canvas', 'cresco-canvas' )
		);
		return $actions;
	}

	/** Add a quick entry while viewing/editing a Page. */
	public function add_admin_bar_action( $admin_bar ) {
		if ( ! is_admin_bar_showing() || ! is_object( $admin_bar ) ) {
			return;
		}

		$post_id = $this->resolve_post_id();
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'cresco-canvas-edit',
				'title' => __( 'Edit with Cresco Canvas', 'cresco-canvas' ),
				'href'  => $this->editor_url( $post_id ),
			)
		);
	}

	/** Render only a stable mount point. React owns the editor UI. */
	public function render_screen() {
		$post_id = $this->requested_post_id();
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this Page with Cresco Canvas.', 'cresco-canvas' ) );
		}

		echo '<div id="cresco-canvas-standalone-editor" class="cresco-canvas-standalone-editor" aria-live="polite"></div>';
	}

	/** Load the visual editor without loading Gutenberg's post-editor shell. */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! $this->is_editor_screen( $hook_suffix ) ) {
			return;
		}

		$post_id = $this->requested_post_id();
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$asset_file     = CRESCO_CANVAS_PATH . 'build/standalone-visual-editor.asset.php';
		$script_file    = CRESCO_CANVAS_PATH . 'build/standalone-visual-editor.js';
		$style_file     = CRESCO_CANVAS_PATH . 'assets/css/standalone-visual-editor.css';
		$bootstrap_file = CRESCO_CANVAS_PATH . 'build/standalone-content-bootstrap.js';
		if ( ! is_readable( $asset_file ) || ! is_readable( $script_file ) || ! is_readable( $style_file ) || ! is_readable( $bootstrap_file ) ) {
			return;
		}

		$asset = require $asset_file;
		$editor_settings = array(
			'postId'         => $post_id,
			'postType'       => 'page',
			'apiPath'        => '/wp/v2/pages/' . $post_id,
			'editUrl'        => get_edit_post_link( $post_id, 'raw' ),
			'previewUrl'     => get_preview_post_link( $post_id ),
			'adminPagesUrl'  => admin_url( 'edit.php?post_type=page' ),
			'initialContent' => (string) $post->post_content,
			'initialTitle'   => (string) $post->post_title,
			'initialStatus'  => (string) $post->post_status,
			'version'        => CRESCO_CANVAS_VERSION,
			'breakpoints'    => array(
				'wide'    => 1920,
				'desktop' => 1440,
				'laptop'  => 1366,
				'tablet'  => 768,
				'mobile'  => 390,
			),
		);

		wp_enqueue_media( array( 'post' => $post_id ) );
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style(
			'cresco-canvas-standalone-visual-editor',
			CRESCO_CANVAS_URL . 'assets/css/standalone-visual-editor.css',
			array( 'wp-components', 'wp-edit-blocks' ),
			(string) ( $asset['version'] ?? CRESCO_CANVAS_VERSION )
		);
		wp_add_inline_style(
			'cresco-canvas-standalone-visual-editor',
			'html.wp-toolbar{padding-top:0!important}body.admin_page_cresco-canvas-editor,body.toplevel_page_cresco-canvas-editor{margin:0!important}'
		);

		wp_enqueue_script(
			'cresco-canvas-standalone-content-bootstrap',
			CRESCO_CANVAS_URL . 'build/standalone-content-bootstrap.js',
			array( 'wp-api-fetch' ),
			'1.0.0-rc.1-standalone-content-bootstrap.1',
			true
		);
		wp_add_inline_script(
			'cresco-canvas-standalone-content-bootstrap',
			'window.crescoCanvasStandaloneSettings = ' . wp_json_encode( $editor_settings ) . ';',
			'before'
		);

		$dependencies = array_values( array_unique( array_merge( (array) ( $asset['dependencies'] ?? array() ), array( 'cresco-canvas-standalone-content-bootstrap' ) ) ) );
		wp_enqueue_script(
			'cresco-canvas-standalone-visual-editor',
			CRESCO_CANVAS_URL . 'build/standalone-visual-editor.js',
			$dependencies,
			(string) ( $asset['version'] ?? CRESCO_CANVAS_VERSION ),
			true
		);
		wp_set_script_translations( 'cresco-canvas-standalone-visual-editor', 'cresco-canvas' );
	}

	/** Build the standalone editor URL for a Page. */
	public function editor_url( $post_id ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'post' => absint( $post_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/** @return bool */
	private function is_editor_screen( $hook_suffix ) {
		if ( $this->hook_suffix && $hook_suffix === $this->hook_suffix ) {
			return true;
		}
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
	}

	/** @return int */
	private function requested_post_id() {
		return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
	}

	/** @return int */
	private function resolve_post_id() {
		if ( is_admin() ) {
			return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context.
		}
		return (int) get_queried_object_id();
	}
}
