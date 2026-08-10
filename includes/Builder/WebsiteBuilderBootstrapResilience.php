<?php
/**
 * Resilient bootstrap for the standalone Website Builder.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderBootstrapResilience {
	const HANDLE = 'cresco-canvas-website-builder-bootstrap';

	/** Register the bootstrap immediately after the main Website Builder enqueue pass. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 121 );
	}

	/**
	 * Load a small pre-editor recovery layer and make the editor depend on it.
	 *
	 * The main editor currently performs several REST reads in parallel. This
	 * bootstrap keeps optional modules from blocking Canvas startup forever and
	 * turns a critical Session timeout/runtime failure into an actionable error
	 * screen instead of an infinite spinner.
	 */
	public function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor routing.
		if ( VisualEditor::PAGE_SLUG !== $page ) return;

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor routing.
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) return;

		$script = CRESCO_CANVAS_PATH . 'build/website-builder-bootstrap.js';
		if ( ! is_readable( $script ) ) return;

		wp_enqueue_script(
			self::HANDLE,
			CRESCO_CANVAS_URL . 'build/website-builder-bootstrap.js',
			array( 'wp-api-fetch' ),
			$this->asset_version( $script ),
			true
		);

		$settings = array(
			'postId'            => $post_id,
			'postTitle'         => (string) $post->post_title,
			'builderVersion'    => WebsiteBuilder::BUILDER_VERSION,
			'optionalTimeoutMs' => 6500,
			'criticalTimeoutMs' => 10000,
			'watchdogMs'        => 13000,
			'paths'             => array(
				'session'        => '/cresco-canvas/v1/website-builder/session/' . $post_id,
				'context'        => '/cresco-canvas/v1/website-builder/context/' . $post_id,
				'options'        => '/cresco-canvas/v1/website-builder/options',
				'components'     => '/cresco-canvas/v1/website-builder/components',
				'pageSettings'   => '/cresco-canvas/v1/page-settings/' . $post_id,
			
'themeTemplates' => '/cresco-canvas/v1/theme-templates',
				'globalSettings' => '/cresco-canvas/v1/settings',
			),
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.crescoWebsiteBuilderBootstrapSettings=' . wp_json_encode( $settings ) . ';',
			'before'
		);

		// WebsiteBuilder enqueues its editor at priority 120. Add this bootstrap as
		// a hard dependency at 121 so middleware/watchdog are installed first.
		$scripts = wp_scripts();
		if ( isset( $scripts->registered['cresco-canvas-website-builder'] ) ) {
			$deps = (array) $scripts->registered['cresco-canvas-website-builder']->deps;
			if ( ! in_array( self::HANDLE, $deps, true ) ) {
				$deps[] = self::HANDLE;
				$scripts->registered['cresco-canvas-website-builder']->deps = $deps;
			}
		}
	}

	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}
}
