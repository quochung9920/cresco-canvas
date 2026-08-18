<?php
/**
 * React-native professional Global Design workspace for Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps WebsiteBuilderStudio.React as the single Studio DOM owner while
 * restoring the professional Global Design experience through the Studio SDK.
 *
 * Historical Global Design assets imperatively inserted/re-parented nodes inside
 * `.cc-studio-panel`. Those runtimes stay retired. This service only enqueues a
 * React SDK panel plus scoped CSS; it never mutates React-owned children.
 */
final class StudioGlobalDesignPro {
	const HANDLE              = 'cresco-canvas-studio-global-design-pro';
	const SCRIPT              = 'build/studio-global-design-pro.js';
	const STYLE               = 'assets/css/studio-global-design-pro.css';
	const REACT_STYLE_HANDLE = 'cresco-canvas-studio-global-design-react';
	const REACT_STYLE        = 'assets/css/studio-global-design-react.css';

	// Historical handles remain declared for compatibility/tests. They are not enqueued.
	const AUTHORITY_HANDLE          = 'cresco-canvas-studio-global-design-authority';
	const AUTHORITY_SCRIPT          = 'build/studio-global-design-authority.js';
	const AUTHORITY_STYLE           = 'assets/css/studio-global-design-authority.css';
	const WORKFLOW_GUARD_HANDLE     = 'cresco-canvas-studio-global-design-workflows-guard';
	const WORKFLOW_GUARD_SCRIPT     = 'build/studio-global-design-workflows-guard.js';
	const WORKFLOW_HANDLE           = 'cresco-canvas-studio-global-design-workflows';
	const WORKFLOW_SCRIPT           = 'build/studio-global-design-workflows.js';
	const WORKFLOW_STYLE            = 'assets/css/studio-global-design-workflows.css';
	const COMPACT_HANDLE            = 'cresco-canvas-studio-global-design-compact';
	const COMPACT_SCRIPT            = 'build/studio-global-design-compact.js';
	const COMPACT_STYLE             = 'assets/css/studio-global-design-compact.css';
	const FONT_SEARCH_FIX_HANDLE    = 'cresco-canvas-studio-global-design-font-search-fix';
	const FONT_SEARCH_FIX_STYLE     = 'assets/css/studio-global-design-font-search-fix.css';
	const SHARED_STYLE_HANDLE       = 'cresco-canvas-studio-global-design-shared-controls';
	const SHARED_STYLE              = 'assets/css/studio-global-design-shared-controls.css';
	const SHARED_SCRIPT_HANDLE      = 'cresco-canvas-studio-global-design-shared-controls-runtime';
	const SHARED_SCRIPT             = 'build/studio-global-design-shared-controls.js';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 1430 );
	}

	/** Enqueue only the React-native Global Design workspace and scoped styles. */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! current_user_can( 'edit_theme_options' ) ) return;
		if ( ! wp_script_is( WebsiteBuilderStudio::HANDLE, 'enqueued' ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) || ! WebsiteBuilderAsset::readable( self::REACT_STYLE ) ) return;

		$style_deps = array( 'cresco-canvas-website-builder-studio' );
		if ( wp_style_is( StudioColorHarmony::LIGHT_HANDLE, 'enqueued' ) ) $style_deps[] = StudioColorHarmony::LIGHT_HANDLE;
		wp_enqueue_style(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::STYLE ),
			$style_deps,
			WebsiteBuilderAsset::version( self::STYLE )
		);
		wp_enqueue_style(
			self::REACT_STYLE_HANDLE,
			WebsiteBuilderAsset::url( self::REACT_STYLE ),
			array( self::HANDLE ),
			WebsiteBuilderAsset::version( self::REACT_STYLE )
		);

		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array( WebsiteBuilderStudio::HANDLE, 'wp-element', 'wp-api-fetch' ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);

		$config = array(
			'schema'       => 'cresco-global-design-pro/v2',
			'settingsPath' => '/cresco-canvas/v1/settings',
			'tokensPath'   => '/cresco-canvas/v1/design-tokens',
			'resetPath'    => '/cresco-canvas/v1/settings/reset',
			'postId'       => $context->post_id(),
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.crescoGlobalDesignProSettings=' . wp_json_encode( $config ) . ';',
			'before'
		);
	}
}
