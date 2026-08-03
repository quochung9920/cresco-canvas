<?php

namespace CrescoCanvas;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private string $hook_suffix = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'get_edit_post_link', array( $this, 'use_canvas_as_page_editor' ), 20, 3 );
		add_filter( 'page_row_actions', array( $this, 'add_wordpress_editor_action' ), 20, 2 );
	}

	public function add_menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'edit.php?post_type=page',
			__( 'Cresco Canvas', 'cresco-canvas' ),
			__( 'Cresco Canvas', 'cresco-canvas' ),
			'edit_pages',
			'cresco-canvas',
			array( $this, 'render' )
		);
	}

	/**
	 * Make the normal Page title and Edit links open Cresco Canvas.
	 */
	public function use_canvas_as_page_editor( string $link, int $post_id, string $context ): string {
		$post = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return $link;
		}

		return $this->get_canvas_url( $post_id );
	}

	/**
	 * Keep the native WordPress editor available as a secondary row action.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @return array<string, string>
	 */
	public function add_wordpress_editor_action( array $actions, WP_Post $post ): array {
		if ( 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$native_url = $this->get_native_editor_url( $post->ID );
		$updated    = array();

		foreach ( $actions as $key => $action ) {
			$updated[ $key ] = $action;

			if ( 'edit' === $key ) {
				$updated['cresco_canvas_native_editor'] = sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $native_url ),
					esc_html__( 'WordPress Editor', 'cresco-canvas' )
				);
			}
		}

		return $updated;
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$post_id = $this->get_requested_post_id();

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style(
			'cresco-canvas-admin',
			CRESCO_CANVAS_URL . 'assets/css/admin.css',
			array( 'wp-components', 'wp-edit-blocks' ),
			CRESCO_CANVAS_VERSION
		);

		wp_enqueue_script(
			'cresco-canvas-editor',
			CRESCO_CANVAS_URL . 'assets/js/editor.js',
			array(
				'wp-api-fetch',
				'wp-block-editor',
				'wp-blocks',
				'wp-components',
				'wp-compose',
				'wp-data',
				'wp-element',
				'wp-i18n',
			),
			CRESCO_CANVAS_VERSION,
			true
		);

		wp_add_inline_script(
			'cresco-canvas-editor',
			'window.crescoCanvasSettings = ' . wp_json_encode(
				array(
					'root'              => esc_url_raw( rest_url( 'cresco-canvas/v1/' ) ),
					'nonce'             => wp_create_nonce( 'wp_rest' ),
					'adminUrl'          => admin_url(),
					'pagesUrl'          => admin_url( 'edit.php?post_type=page' ),
					'postId'            => $post_id,
					'nativeEditUrl'     => $post_id ? $this->get_native_editor_url( $post_id ) : '',
					'previewBase'       => home_url( '/' ),
					'brand'             => 'Cresco Canvas',
					'version'           => CRESCO_CANVAS_VERSION,
					'canManageSettings' => current_user_can( 'edit_theme_options' ),
				)
			) . ';',
			'before'
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to access Cresco Canvas.', 'cresco-canvas' ) );
		}

		$post_id = $this->get_requested_post_id();

		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || 'page' !== $post->post_type ) {
				wp_die( esc_html__( 'The requested Page could not be found.', 'cresco-canvas' ) );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_die( esc_html__( 'You are not allowed to edit this Page.', 'cresco-canvas' ) );
			}
		}
		?>
		<div class="wrap cresco-canvas-admin-wrap">
			<div id="cresco-canvas-app">
				<p><?php esc_html_e( 'Loading Cresco Canvas…', 'cresco-canvas' ); ?></p>
			</div>
		</div>
		<?php
	}

	private function get_requested_post_id(): int {
		return isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
	}

	private function get_canvas_url( int $post_id ): string {
		return add_query_arg(
			array(
				'post_type' => 'page',
				'page'      => 'cresco-canvas',
				'post_id'   => $post_id,
			),
			admin_url( 'edit.php' )
		);
	}

	private function get_native_editor_url( int $post_id ): string {
		return add_query_arg(
			array(
				'post'   => $post_id,
				'action' => 'edit',
			),
			admin_url( 'post.php' )
		);
	}
}
