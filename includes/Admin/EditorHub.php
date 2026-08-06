<?php
/**
 * Unified Cresco Canvas editor hub.
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

	/** Load the single Cresco Canvas entry point after feature modules. */
	public function enqueue() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/editor-hub.asset.php';
		if ( ! is_readable( $asset_file ) || ! is_readable( CRESCO_CANVAS_PATH . 'build/editor-hub.js' ) ) {
			return;
		}

		$asset = require $asset_file;
		wp_enqueue_script(
			'cresco-canvas-editor-hub',
			CRESCO_CANVAS_URL . 'build/editor-hub.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? CRESCO_CANVAS_VERSION ),
			true
		);
		wp_enqueue_style(
			'cresco-canvas-editor-hub',
			CRESCO_CANVAS_URL . 'assets/css/editor-hub.css',
			array(),
			CRESCO_CANVAS_VERSION
		);

		$workspace_asset_file = CRESCO_CANVAS_PATH . 'build/workspace-layout.asset.php';
		if ( is_readable( $workspace_asset_file ) && is_readable( CRESCO_CANVAS_PATH . 'build/workspace-layout.js' ) ) {
			$workspace_asset = require $workspace_asset_file;
			wp_enqueue_script(
				'cresco-canvas-workspace-layout',
				CRESCO_CANVAS_URL . 'build/workspace-layout.js',
				(array) ( $workspace_asset['dependencies'] ?? array() ),
				(string) ( $workspace_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
			wp_enqueue_style(
				'cresco-canvas-workspace-layout',
				CRESCO_CANVAS_URL . 'assets/css/workspace-layout.css',
				array( 'cresco-canvas-editor-hub' ),
				(string) ( $workspace_asset['version'] ?? CRESCO_CANVAS_VERSION )
			);
		}

		/*
		 * The former PluginSidebar-based widget inspector and compatibility router
		 * are intentionally no longer enqueued. The persistent inspector below is
		 * the single widget editing surface, avoiding sidebar activation races in
		 * different Gutenberg versions.
		 */
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
			wp_enqueue_style(
				'cresco-canvas-widget-inspector-persistent',
				CRESCO_CANVAS_URL . 'assets/css/widget-inspector-persistent.css',
				array( 'cresco-canvas-workspace-layout', 'wp-components' ),
				(string) ( $persistent_asset['version'] ?? CRESCO_CANVAS_VERSION )
			);
		}

		$structure_asset_file = CRESCO_CANVAS_PATH . 'build/structure-navigator.asset.php';
		if (
			is_readable( $structure_asset_file ) &&
			is_readable( CRESCO_CANVAS_PATH . 'build/structure-navigator.js' ) &&
			is_readable( CRESCO_CANVAS_PATH . 'assets/css/structure-navigator.css' )
		) {
			$structure_asset = require $structure_asset_file;
			wp_enqueue_script(
				'cresco-canvas-structure-navigator',
				CRESCO_CANVAS_URL . 'build/structure-navigator.js',
				(array) ( $structure_asset['dependencies'] ?? array() ),
				(string) ( $structure_asset['version'] ?? CRESCO_CANVAS_VERSION ),
				true
			);
			wp_enqueue_style(
				'cresco-canvas-structure-navigator',
				CRESCO_CANVAS_URL . 'assets/css/structure-navigator.css',
				array( 'dashicons' ),
				(string) ( $structure_asset['version'] ?? CRESCO_CANVAS_VERSION )
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
