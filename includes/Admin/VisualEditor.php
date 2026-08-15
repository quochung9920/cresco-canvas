<?php
/**
 * Standalone Cresco Studio screen shell.
 *
 * The historical standalone editor runtime has been retired. This class owns
 * only WordPress routing and the initial Studio loading/fatal shell; the
 * canonical browser application is owned by WebsiteBuilderRuntimeOwner and
 * WebsiteBuilderStudio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VisualEditor {
	const PAGE_SLUG = 'cresco-canvas-editor';

	public function register() {
		add_action( 'admin_menu', array( $this, 'register_screen' ) );
		add_filter( 'page_row_actions', array( $this, 'add_page_row_action' ), 20, 2 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_action' ), 90 );
	}

	public function register_screen() {
		add_submenu_page( null, __( 'Cresco Canvas', 'cresco-canvas' ), __( 'Cresco Canvas', 'cresco-canvas' ), 'edit_pages', self::PAGE_SLUG, array( $this, 'render_screen' ) );
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

	/** Render one canonical Studio shell; no legacy React application is mounted. */
	public function render_screen() {
		$post_id = $this->requested_post_id();
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this Page with Cresco Canvas.', 'cresco-canvas' ) );
		}

		$required = array(
			'build/website-builder-studio.js',
			'assets/css/website-builder-studio.css',
		);
		$missing = array_values(
			array_filter(
				$required,
				static function ( $relative ) {
					return ! is_readable( CRESCO_CANVAS_PATH . $relative );
				}
			)
		);

		echo '<div id="cresco-canvas-standalone-editor" class="cresco-canvas-standalone-editor" aria-live="polite">';
		if ( $missing ) {
			echo '<div class="cc-studio-fatal" role="alert"><h2>' . esc_html__( 'Cresco Studio could not start', 'cresco-canvas' ) . '</h2><p>' . esc_html( sprintf( __( 'Required Studio assets are missing: %s. Run npm run build and reload the editor.', 'cresco-canvas' ), implode( ', ', $missing ) ) ) . '</p></div>';
		} else {
			// The premium Studio stylesheet owns the single spinner via ::before.
			echo '<div class="cc-studio-loading"><strong>' . esc_html__( 'Loading Cresco Studio…', 'cresco-canvas' ) . '</strong></div>';
		}
		echo '</div>';
	}

	public function editor_url( $post_id ) {
		return add_query_arg( array( 'page' => self::PAGE_SLUG, 'post' => absint( $post_id ) ), admin_url( 'admin.php' ) );
	}

	private function requested_post_id() {
		return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
	}

	private function resolve_post_id() {
		if ( is_admin() ) return isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context.
		return (int) get_queried_object_id();
	}
}
