<?php
/**
 * Unified Cresco Canvas editor application.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorHub {
	/** Register editor assets. */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ), 100 );
	}

	/** Load registered views, one app shell, Visual Canvas, and Structure. */
	public function enqueue() {
		if ( ! wp_script_is( 'cresco-canvas-editor-foundation', 'enqueued' ) ) {
			return;
		}

		$app_asset_file = CRESCO_CANVAS_PATH . 'build/editor-app-shell.asset.php';
		if (
			is_readable( $app_asset_file ) &&
			is_readable( CRESCO_CANVAS_PATH . 'build/editor-app-shell.js' ) &&
			is_readable( CRESCO_CANVAS_PATH . 'assets/css/editor-app-shell.css' )
		) {
			$app_asset = require $app_asset_file;
			wp_enqueue_style(
				'cresco-canvas-editor-app-shell',
				CRESCO_CANVAS_URL . 'assets/css/editor-app-shell.css',
				array( 'cresco-canvas-editor', 'wp-components', 'dashicons' ),
				(string) ( $app_asset['version'] ?? CRESCO_CANVAS_VERSION )
			);
		}

		$persistent_asset_file = CRESCO_CANVAS_PATH . 'build/widget-inspector-persistent.asset.php';
		if (
			is_readable( $persistent_asset_file ) &&
			is_readable( CRESCO_CANVAS_PATH . 'build/widget-inspector-persistent.js' ) &&
			is_readable( CRESCO_CANVAS_PATH . 'assets/css/widget-inspector-persistent.css' )
		) {
			$persistent_asset = require $persistent_asset_file;
			wp_enqueue_script(
				'cresco-canvas-widget-inspector-persistent',
				CRESCO_CANVAS_URL . 'build/widget-inspector-persistent.js',
				(array) ( $persistent_asset['dependencies'] ?? array() ),
				(string) ( $persistent_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
			wp_set_script_translations( 'cresco-canvas-widget-inspector-persistent', 'cresco-canvas' );
			wp_enqueue_style(
				'cresco-canvas-widget-inspector-persistent',
				CRESCO_CANVAS_URL . 'assets/css/widget-inspector-persistent.css',
				array( 'cresco-canvas-editor-app-shell', 'wp-components' ),
				(string) ( $persistent_asset['version'] ?? CRESCO_CANVAS_VERSION )
			);
		}

		if ( isset( $app_asset ) ) {
			wp_enqueue_script(
				'cresco-canvas-editor-app-shell',
				CRESCO_CANVAS_URL . 'build/editor-app-shell.js',
				(array) ( $app_asset['dependencies'] ?? array() ),
				(string) ( $app_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
			wp_set_script_translations( 'cresco-canvas-editor-app-shell', 'cresco-canvas' );
		}

		$visual_asset_file = CRESCO_CANVAS_PATH . 'build/visual-canvas.asset.php';
		if (
			is_readable( $visual_asset_file ) &&
			is_readable( CRESCO_CANVAS_PATH . 'build/visual-canvas.js' ) &&
			is_readable( CRESCO_CANVAS_PATH . 'assets/css/visual-canvas.css' )
		) {
			$visual_asset = require $visual_asset_file;
			wp_enqueue_script(
				'cresco-canvas-visual-canvas',
				CRESCO_CANVAS_URL . 'build/visual-canvas.js',
				(array) ( $visual_asset['dependencies'] ?? array() ),
				(string) ( $visual_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
			wp_set_script_translations( 'cresco-canvas-visual-canvas', 'cresco-canvas' );
			wp_enqueue_style(
				'cresco-canvas-visual-canvas',
				CRESCO_CANVAS_URL . 'assets/css/visual-canvas.css',
				array( 'cresco-canvas-editor-app-shell', 'wp-components' ),
				(string) ( $visual_asset['version'] ?? CRESCO_CANVAS_VERSION )
			);
		}

		$structure_asset_file = CRESCO_CANVAS_PATH . 'build/structure-navigator.asset.php';
		if (
			is_readable( $structure_asset_file ) &&
			is_readable( CRESCO_CANVAS_PATH . 'build/structure-navigator.js' ) &&
			is_readable( CRESCO_CANVAS_PATH . 'assets/css/structure-navigator.css' ) &&
			is_readable( CRESCO_CANVAS_PATH . 'assets/css/structure-navigator-actions.css' )
		) {
			$structure_asset = require $structure_asset_file;
			wp_enqueue_script(
				'cresco-canvas-structure-navigator',
				CRESCO_CANVAS_URL . 'build/structure-navigator.js',
				(array) ( $structure_asset['dependencies'] ?? array() ),
				(string) ( $structure_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
			wp_set_script_translations( 'cresco-canvas-structure-navigator', 'cresco-canvas' );
			wp_enqueue_style(
				'cresco-canvas-structure-navigator',
				CRESCO_CANVAS_URL . 'assets/css/structure-navigator.css',
				array( 'dashicons', 'wp-components' ),
				(string) ( $structure_asset['version'] ?? CRESCO_CANVAS_VERSION )
			);
			wp_enqueue_style(
				'cresco-canvas-structure-navigator-actions',
				CRESCO_CANVAS_URL . 'assets/css/structure-navigator-actions.css',
				array( 'cresco-canvas-structure-navigator' ),
				(string) ( $structure_asset['version'] ?? CRESCO_CANVAS_VERSION )
			);
		}

		$preview_bridge_asset_file = CRESCO_CANVAS_PATH . 'build/preview-foundation-bridge.asset.php';
		if (
			wp_script_is( 'cresco-canvas-preview', 'enqueued' ) &&
			is_readable( $preview_bridge_asset_file ) &&
			is_readable( CRESCO_CANVAS_PATH . 'build/preview-foundation-bridge.js' )
		) {
			$preview_bridge_asset = require $preview_bridge_asset_file;
			wp_enqueue_script(
				'cresco-canvas-preview-foundation-bridge',
				CRESCO_CANVAS_URL . 'build/preview-foundation-bridge.js',
				(array) ( $preview_bridge_asset['dependencies'] ?? array() ),
				(string) ( $preview_bridge_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
		}

		$bridge_asset_file = CRESCO_CANVAS_PATH . 'build/native-gutenberg-bridge.asset.php';
		if ( is_readable( $bridge_asset_file ) && is_readable( CRESCO_CANVAS_PATH . 'build/native-gutenberg-bridge.js' ) ) {
			$bridge_asset = require $bridge_asset_file;
			wp_enqueue_script(
				'cresco-canvas-native-gutenberg-bridge',
				CRESCO_CANVAS_URL . 'build/native-gutenberg-bridge.js',
				(array) ( $bridge_asset['dependencies'] ?? array() ),
				(string) ( $bridge_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
		}
	}
}
