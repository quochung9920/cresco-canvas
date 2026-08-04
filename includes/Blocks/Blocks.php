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
		add_filter( 'block_categories_all', array( $this, 'register_category' ), 10, 2 );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Add a dedicated Cresco category to Gutenberg's native inserter.
	 *
	 * @param array<int, array<string, string>> $categories Existing categories.
	 * @return array<int, array<string, string>>
	 */
	public function register_category( $categories ) {
		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && 'cresco-canvas' === $category['slug'] ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => 'cresco-canvas',
				'title' => __( 'Cresco Canvas', 'cresco-canvas' ),
				'icon'  => 'layout',
			)
		);

		return $categories;
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
