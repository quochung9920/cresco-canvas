<?php
/**
 * Harmonized editor palette for Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads narrowly scoped visual-theme layers after Studio UX Pro.
 *
 * This class intentionally owns colors and appearance defaults only. It does
 * not alter document rendering, session data, widgets, layout, or editor
 * interaction behavior.
 */
final class StudioColorHarmony {
	const HANDLE              = 'cresco-canvas-studio-color-harmony';
	const STYLE               = 'assets/css/studio-color-harmony.css';
	const LIGHT_HANDLE        = 'cresco-canvas-studio-light-first';
	const LIGHT_STYLE         = 'assets/css/studio-light-safe.css';
	const LIGHT_SCRIPT        = 'build/studio-light-first.js';
	const LIGHT_SCRIPT_HANDLE = 'cresco-canvas-studio-light-first-runtime';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1420 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::STYLE ) || ! WebsiteBuilderAsset::readable( self::LIGHT_STYLE ) || ! WebsiteBuilderAsset::readable( self::LIGHT_SCRIPT ) ) return;

		$deps = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( StudioUxPro::HANDLE, 'enqueued' ) ) {
			$deps[] = StudioUxPro::HANDLE;
		}

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);
		wp_enqueue_style(
			self::LIGHT_HANDLE,
			WebsiteBuilderAsset::url( self::LIGHT_STYLE ),
			array( self::HANDLE ),
			WebsiteBuilderAsset::version( self::LIGHT_STYLE )
		);
		wp_enqueue_script(
			self::LIGHT_SCRIPT_HANDLE,
			WebsiteBuilderAsset::url( self::LIGHT_SCRIPT ),
			array( StudioUxPro::HANDLE ),
			WebsiteBuilderAsset::version( self::LIGHT_SCRIPT ),
			true
		);
	}
}
