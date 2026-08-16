<?php
/**
 * Cresco Studio Structure layout hardening.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StudioStructureLayout {
	const STYLE  = 'assets/css/studio-structure-layout.css';
	const HANDLE = 'cresco-canvas-studio-structure-layout';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1440 );
	}

	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;
		if ( ! wp_style_is( 'cresco-canvas-website-builder-studio', 'enqueued' ) ) return;

		$dependencies = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( 'cresco-canvas-website-builder-premium-polish', 'enqueued' ) ) {
			$dependencies[] = 'cresco-canvas-website-builder-premium-polish';
		}

		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$dependencies,
			WebsiteBuilderAsset::version( self::STYLE )
		);
	}
}
