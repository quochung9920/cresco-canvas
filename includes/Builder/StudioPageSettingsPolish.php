<?php
/**
 * Presentation-only polish for the canonical React Page Settings 2.0 panel.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds only scoped CSS. Page Settings markup, state and persistence remain
 * owned by WebsiteBuilderStudio.React.
 */
final class StudioPageSettingsPolish {
	const HANDLE = 'cresco-canvas-studio-page-settings-polish';
	const STYLE  = 'assets/css/studio-page-settings-polish.css';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1470 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! wp_style_is( 'cresco-canvas-website-builder-studio', 'enqueued' ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$deps = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( 'cresco-canvas-website-builder-premium-polish', 'enqueued' ) ) {
			$deps[] = 'cresco-canvas-website-builder-premium-polish';
		}

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);
	}
}
