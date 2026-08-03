<?php

namespace CrescoCanvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private string $hook_suffix = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'Cresco Canvas', 'cresco-canvas' ),
			__( 'Cresco Canvas', 'cresco-canvas' ),
			'edit_pages',
			'cresco-canvas',
			array( $this, 'render' ),
			'dashicons-layout',
			58
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
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
					'root'        => esc_url_raw( rest_url( 'cresco-canvas/v1/' ) ),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'adminUrl'    => admin_url(),
					'previewBase' => home_url( '/' ),
					'brand'       => 'Cresco Canvas',
					'version'     => CRESCO_CANVAS_VERSION,
				)
			) . ';',
			'before'
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to access Cresco Canvas.', 'cresco-canvas' ) );
		}
		?>
		<div class="wrap cresco-canvas-admin-wrap">
			<div id="cresco-canvas-app">
				<p><?php esc_html_e( 'Loading Cresco Canvas…', 'cresco-canvas' ); ?></p>
			</div>
		</div>
		<?php
	}
}
