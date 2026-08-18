<?php
/**
 * Compatibility boundary for the retired DOM-driven Global Design Pro layer.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global Design is owned by WebsiteBuilderStudio's React-native globalPanel().
 *
 * Historical Pro assets inserted and re-parented DOM nodes inside
 * `.cc-studio-panel`. That violated React DOM ownership and could crash the
 * complete Studio root when switching Global Design -> Edit widget. Keep this
 * service registered as a compatibility boundary, but do not enqueue any of
 * the retired DOM-mutating presentation or workflow assets.
 */
final class StudioGlobalDesignPro {
	const HANDLE                    = 'cresco-canvas-studio-global-design-pro';
	const SCRIPT                    = 'build/studio-global-design-pro.js';
	const STYLE                     = 'assets/css/studio-global-design-pro.css';
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

	/**
	 * Intentionally no-op in Studio 2.0.
	 *
	 * The canonical React runtime already owns Global Design, its settings API,
	 * and the left-panel lifecycle. Re-enabling the historical assets here would
	 * create a second DOM owner and reintroduce the removeChild crash.
	 */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! current_user_can( 'edit_theme_options' ) ) return;

		// Canonical owner: WebsiteBuilderStudio::globalPanel().
		// Do not enqueue legacy Global Design Pro DOM/workflow assets.
	}
}
