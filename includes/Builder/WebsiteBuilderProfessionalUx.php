<?php
/**
 * Progressive professional UX enhancements for the unified Website Builder.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderProfessionalUx {
	const SCRIPT_HANDLE         = 'cresco-canvas-website-builder-professional-ux';
	const PREVIEW_SCRIPT_HANDLE = 'cresco-canvas-website-builder-preview-fit';

	/** Register the professional UX layer and its stabilization boundary. */
	public function register() {
		( new WebsiteBuilderStabilization() )->register();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ), 1001 );
	}

	/** Enqueue only when the central runtime policy allows this optional module. */
	public function enqueue_editor_assets() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'professional-ux', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( 'build/website-builder-professional-ux.js' ) || ! WebsiteBuilderAsset::readable( 'assets/css/website-builder-professional-ux.css' ) ) return;

		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			WebsiteBuilderAsset::url( 'assets/css/website-builder-professional-ux.css' ),
			array( 'cresco-canvas-website-builder-controls' ),
			WebsiteBuilderAsset::version( 'assets/css/website-builder-professional-ux.css' )
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WebsiteBuilderAsset::url( 'build/website-builder-professional-ux.js' ),
			array( 'cresco-canvas-website-builder-controls' ),
			WebsiteBuilderAsset::version( 'build/website-builder-professional-ux.js' ),
			true
		);

		if ( WebsiteBuilderAsset::readable( 'build/website-builder-preview-fit.js' ) ) {
			wp_enqueue_script(
				self::PREVIEW_SCRIPT_HANDLE,
				WebsiteBuilderAsset::url( 'build/website-builder-preview-fit.js' ),
				array( self::SCRIPT_HANDLE ),
				WebsiteBuilderAsset::version( 'build/website-builder-preview-fit.js' ),
				true
			);
		}
	}
}
