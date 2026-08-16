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
 * Loads a narrowly scoped visual-theme layer after Studio UX Pro.
 *
 * This class intentionally owns colors only. It does not alter document
 * rendering, session data, widgets, layout, or editor interaction behavior.
 */
final class StudioColorHarmony {
	const HANDLE = 'cresco-canvas-studio-color-harmony';
	const STYLE  = 'assets/css/studio-color-harmony.css';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1420 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$deps = array( WebsiteBuilderStudio::HANDLE );
		if ( wp_style_is( StudioUxPro::HANDLE, 'enqueued' ) ) {
			$deps[] = StudioUxPro::HANDLE;
		}

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);
	}
}
