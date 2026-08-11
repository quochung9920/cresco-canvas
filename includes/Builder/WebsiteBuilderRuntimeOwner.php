<?php
/**
 * Canonical browser-runtime ownership for the Website Builder editor.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Styles\GlobalStyles;
use CrescoCanvas\Theme\ThemeSessionBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderRuntimeOwner {
	const HANDLE             = 'cresco-canvas-website-builder';
	const SCRIPT             = 'build/website-builder-studio.js';
	const STYLE              = 'assets/css/website-builder.css';
	const CONSISTENCY_HANDLE = 'cresco-canvas-website-builder-consistency-guard';
	const CONSISTENCY_SCRIPT = 'build/website-builder-consistency-guard.js';
	const POINTER_HANDLE     = 'cresco-canvas-website-builder-pointer-drag';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'claim_runtime_handle' ), 119 );
		add_action( 'admin_enqueue_scripts', array( $this, 'retire_legacy_admin_runtime' ), 998 );
		add_action( 'admin_enqueue_scripts', array( $this, 'retire_observer_monkeypatch' ), 1198 );
		add_action( 'admin_enqueue_scripts', array( $this, 'verify_core_extensions' ), 1410 );
	}

	/**
	 * Bootstrap all presentation prerequisites before any historical editor
	 * callback can execute, then remove those callbacks from the request.
	 */
	public function claim_runtime_handle() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		$this->retire_historical_editor_enqueue_callbacks();
		$this->register_base_presentation( $context );
		$this->register_studio_runtime();
	}

	/** Keep frontend compatibility, but remove retired admin bootstrap paths. */
	public function retire_legacy_admin_runtime() {
		if ( ! $this->is_editor_request() ) return;
		$this->remove_object_method( 'admin_enqueue_scripts', WebsiteBuilderCompatibility::class, 'remove_legacy_editor_assets' );
		$this->remove_object_method( 'admin_footer', WebsiteBuilderCompatibility::class, 'render_editor_bootstrap_watchdog' );
	}

	/** Optional modules use their own observers; never monkey-patch the browser global. */
	public function retire_observer_monkeypatch() {
		if ( ! $this->is_editor_request() ) return;
		$this->remove_object_method( 'admin_enqueue_scripts', WebsiteBuilderBootstrapResilience::class, 'attach_observer_guards' );
	}

	/**
	 * The old WebsiteBuilder and ThemeSessionBridge enqueue methods both point at
	 * website-builder-editor.js. Studio now owns their media/base-style duties,
	 * so the historical runtime callbacks can be removed entirely.
	 */
	private function retire_historical_editor_enqueue_callbacks() {
		$this->remove_object_method( 'admin_enqueue_scripts', WebsiteBuilder::class, 'enqueue_editor' );
		$this->remove_object_method( 'admin_enqueue_scripts', ThemeSessionBridge::class, 'enqueue_editor' );
	}

	private function remove_object_method( $hook_name, $class_name, $method_name ) {
		global $wp_filter;
		$hook = $wp_filter[ $hook_name ] ?? null;
		if ( ! $hook instanceof \WP_Hook || empty( $hook->callbacks ) ) return;
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'] ?? null;
				if ( ! is_array( $function ) || ! isset( $function[0], $function[1] ) ) continue;
				if ( ! is_object( $function[0] ) || ! is_a( $function[0], $class_name ) || $method_name !== $function[1] ) continue;
				remove_action( $hook_name, $function, (int) $priority );
			}
		}
	}

	/** Re-home media and base CSS responsibilities formerly owned by legacy bootstraps. */
	private function register_base_presentation( WebsiteBuilderRuntimeContext $context ) {
		wp_enqueue_media( array( 'post' => $context->post_id() ) );
		wp_enqueue_style( 'wp-components' );
		if ( ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$styles = wp_styles();
		if ( ! $styles ) return;
		if ( ! isset( $styles->registered[ self::HANDLE ] ) ) {
			wp_register_style(
				self::HANDLE,
				WebsiteBuilderAsset::url( self::STYLE ),
				array( 'wp-components' ),
				WebsiteBuilderAsset::version( self::STYLE )
			);
		} else {
			$registered       = $styles->registered[ self::HANDLE ];
			$registered->src  = WebsiteBuilderAsset::url( self::STYLE );
			$registered->deps = array( 'wp-components' );
			$registered->ver  = WebsiteBuilderAsset::version( self::STYLE );
		}
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, GlobalStyles::css( '.cc-builder-canvas' ) . GlobalStyles::visual_css( '.cc-builder-canvas' ) );

		if ( $context->is_theme_editor() ) {
			$screen = sanitize_html_class( ThemeSessionBridge::PAGE_SLUG );
			wp_add_inline_style(
				self::HANDLE,
				'html.wp-toolbar{padding-top:0!important}body.admin_page_' . $screen . '{overflow:hidden;margin:0!important;background:#f3f5f8}body.admin_page_' . $screen . ' #wpadminbar,body.admin_page_' . $screen . ' #adminmenumain,body.admin_page_' . $screen . ' #wpfooter{display:none!important}body.admin_page_' . $screen . ' #wpcontent,body.admin_page_' . $screen . ' #wpbody-content{margin:0!important;padding:0!important}'
			);
		}
	}

	private function register_studio_runtime() {
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) ) return;
		$dependencies = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
		if ( WebsiteBuilderAsset::readable( self::CONSISTENCY_SCRIPT ) ) {
			wp_register_script( self::CONSISTENCY_HANDLE, WebsiteBuilderAsset::url( self::CONSISTENCY_SCRIPT ), array( 'wp-api-fetch' ), WebsiteBuilderAsset::version( self::CONSISTENCY_SCRIPT ), true );
			wp_enqueue_script( self::CONSISTENCY_HANDLE );
			$dependencies[] = self::CONSISTENCY_HANDLE;
		}
		foreach ( array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ) as $dependency ) wp_enqueue_script( $dependency );
		$scripts = wp_scripts();
		if ( ! $scripts ) return;
		if ( ! isset( $scripts->registered[ self::HANDLE ] ) ) wp_register_script( self::HANDLE, WebsiteBuilderAsset::url( self::SCRIPT ), $dependencies, WebsiteBuilderAsset::version( self::SCRIPT ), true );
		else {
			$registered = $scripts->registered[ self::HANDLE ];
			$registered->src = WebsiteBuilderAsset::url( self::SCRIPT );
			$registered->deps = $dependencies;
			$registered->ver = WebsiteBuilderAsset::version( self::SCRIPT );
		}
		wp_enqueue_script( self::HANDLE );
	}

	public function verify_core_extensions() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		$this->register_studio_runtime();
		$module = WebsiteBuilderModuleRegistry::get( 'pointer-drag' );
		if ( is_array( $module ) && WebsiteBuilderModuleRegistry::is_enabled( 'pointer-drag', $context ) ) {
			$scripts = wp_scripts();
			if ( $scripts ) foreach ( (array) ( $module['scripts'] ?? array() ) as $asset ) {
				if ( empty( $asset['handle'] ) || empty( $asset['file'] ) || ! WebsiteBuilderAsset::readable( $asset['file'] ) ) continue;
				$handle = (string) $asset['handle'];
				$deps = isset( $asset['deps'] ) && is_array( $asset['deps'] ) ? $asset['deps'] : array( self::HANDLE );
				if ( ! isset( $scripts->registered[ $handle ] ) ) wp_register_script( $handle, WebsiteBuilderAsset::url( $asset['file'] ), $deps, WebsiteBuilderAsset::version( $asset['file'] ), true );
				else {
					$registered = $scripts->registered[ $handle ];
					$registered->src = WebsiteBuilderAsset::url( $asset['file'] );
					$registered->deps = $deps;
					$registered->ver = WebsiteBuilderAsset::version( $asset['file'] );
				}
				wp_enqueue_script( $handle );
			}
		}
		$scripts = wp_scripts();
		$runtime = $scripts && isset( $scripts->registered[ self::HANDLE ] ) ? $scripts->registered[ self::HANDLE ] : null;
		$payload = array(
			'expectedRuntime' => 'studio',
			'canonicalScript' => self::SCRIPT,
			'runtimeTransport' => 'direct-content-addressed-asset',
			'registeredSrc' => $runtime ? (string) $runtime->src : '',
			'consistencyGuard' => wp_script_is( self::CONSISTENCY_HANDLE, 'enqueued' ),
			'pointerDrag' => wp_script_is( self::POINTER_HANDLE, 'enqueued' ),
			'legacyWatchdog' => false,
			'legacyEditorEnqueue' => false,
			'observerMonkeypatch' => false,
		);
		wp_add_inline_script( self::HANDLE, 'window.crescoCanonicalRuntimeOwner=' . wp_json_encode( $payload ) . ';window.crescoExpectedWebsiteBuilderRuntime="studio";', 'before' );
	}

	private function is_editor_request() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		return $context && WebsiteBuilderModuleRegistry::is_enabled( 'core', $context );
	}
}
