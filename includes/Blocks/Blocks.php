<?php
/**
 * Cresco block registration.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Blocks {
	/**
	 * Register block initialization.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register the legacy-compatible Container block and its built editor code.
	 */
	public function register_blocks() {
		$script_handle = '';
		$script_path   = CRESCO_CANVAS_PATH . 'build/container.js';
		$asset_path    = CRESCO_CANVAS_PATH . 'build/container.asset.php';

		if ( is_readable( $script_path ) && is_readable( $asset_path ) ) {
			$asset = require $asset_path;

			if ( is_array( $asset ) && isset( $asset['dependencies'], $asset['version'] ) ) {
				$script_handle = 'cresco-canvas-container-editor';
				wp_register_script(
					$script_handle,
					CRESCO_CANVAS_URL . 'build/container.js',
					(array) $asset['dependencies'],
					(string) $asset['version'],
					true
				);
				wp_set_script_translations( $script_handle, 'cresco-canvas' );
			}
		}

		$args = array();

		if ( $script_handle ) {
			$args['editor_script'] = $script_handle;
		}

		register_block_type( CRESCO_CANVAS_PATH . 'blocks/container', $args );
	}
}
