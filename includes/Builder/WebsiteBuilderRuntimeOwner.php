<?php
/**
 * Canonical browser-runtime ownership for the Website Builder editor.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Own the public Website Builder handle with one direct, content-addressed
 * Cresco Studio runtime. Legacy runtimes are never registered as fallbacks.
 */
final class WebsiteBuilderRuntimeOwner {
	const HANDLE              = 'cresco-canvas-website-builder';
	const SCRIPT              = 'build/website-builder-studio.js';
	const CONSISTENCY_HANDLE  = 'cresco-canvas-website-builder-consistency-guard';
	const CONSISTENCY_SCRIPT  = 'build/website-builder-consistency-guard.js';
	const POINTER_HANDLE      = 'cresco-canvas-website-builder-pointer-drag';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'claim_runtime_handle' ), 119 );
		add_action( 'admin_enqueue_scripts', array( $this, 'verify_core_extensions' ), 1410 );
	}

	/** Claim the public runtime handle before presentation services run. */
	public function claim_runtime_handle() {
		if ( ! $this->is_editor_request() ) return;
		$this->register_studio_runtime();
	}

	/**
	 * Register exactly one Studio source and a pre-runtime consistency guard.
	 * Mutating an existing dependency preserves inline configuration attached by
	 * WebsiteBuilderStudio while preventing any retired implementation from
	 * becoming the rendered owner.
	 */
	private function register_studio_runtime() {
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) ) return;

		$dependencies = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
		if ( WebsiteBuilderAsset::readable( self::CONSISTENCY_SCRIPT ) ) {
			wp_register_script(
				self::CONSISTENCY_HANDLE,
				WebsiteBuilderAsset::url( self::CONSISTENCY_SCRIPT ),
				array( 'wp-api-fetch' ),
				WebsiteBuilderAsset::version( self::CONSISTENCY_SCRIPT ),
				true
			);
			wp_enqueue_script( self::CONSISTENCY_HANDLE );
			$dependencies[] = self::CONSISTENCY_HANDLE;
		}

		foreach ( array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ) as $dependency ) {
			wp_enqueue_script( $dependency );
		}

		$scripts = wp_scripts();
		if ( ! $scripts ) return;

		if ( ! isset( $scripts->registered[ self::HANDLE ] ) ) {
			wp_register_script(
				self::HANDLE,
				WebsiteBuilderAsset::url( self::SCRIPT ),
				$dependencies,
				WebsiteBuilderAsset::version( self::SCRIPT ),
				true
			);
		} else {
			$registered       = $scripts->registered[ self::HANDLE ];
			$registered->src  = WebsiteBuilderAsset::url( self::SCRIPT );
			$registered->deps = $dependencies;
			$registered->ver  = WebsiteBuilderAsset::version( self::SCRIPT );
		}

		wp_enqueue_script( self::HANDLE );
	}

	/** Ensure required core extensions are present after module policy runs. */
	public function verify_core_extensions() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;

		$this->register_studio_runtime();

		$module = WebsiteBuilderModuleRegistry::get( 'pointer-drag' );
		if ( is_array( $module ) && WebsiteBuilderModuleRegistry::is_enabled( 'pointer-drag', $context ) ) {
			$scripts = wp_scripts();
			if ( $scripts ) {
				foreach ( (array) ( $module['scripts'] ?? array() ) as $asset ) {
					if ( empty( $asset['handle'] ) || empty( $asset['file'] ) || ! WebsiteBuilderAsset::readable( $asset['file'] ) ) continue;
					$handle = (string) $asset['handle'];
					$deps   = isset( $asset['deps'] ) && is_array( $asset['deps'] ) ? $asset['deps'] : array( self::HANDLE );
					if ( ! isset( $scripts->registered[ $handle ] ) ) {
						wp_register_script( $handle, WebsiteBuilderAsset::url( $asset['file'] ), $deps, WebsiteBuilderAsset::version( $asset['file'] ), true );
					} else {
						$registered       = $scripts->registered[ $handle ];
						$registered->src  = WebsiteBuilderAsset::url( $asset['file'] );
						$registered->deps = $deps;
						$registered->ver  = WebsiteBuilderAsset::version( $asset['file'] );
					}
					wp_enqueue_script( $handle );
				}
			}
		}

		$scripts = wp_scripts();
		$runtime = $scripts && isset( $scripts->registered[ self::HANDLE ] ) ? $scripts->registered[ self::HANDLE ] : null;
		$payload = array(
			'expectedRuntime' => 'studio',
			'canonicalScript' => self::SCRIPT,
			'runtimeTransport'=> 'direct-content-addressed-asset',
			'registeredSrc'   => $runtime ? (string) $runtime->src : '',
			'consistencyGuard'=> wp_script_is( self::CONSISTENCY_HANDLE, 'enqueued' ),
			'pointerDrag'     => wp_script_is( self::POINTER_HANDLE, 'enqueued' ),
			'legacyWatchdog'  => false,
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.crescoCanonicalRuntimeOwner=' . wp_json_encode( $payload ) . ';window.crescoExpectedWebsiteBuilderRuntime="studio";',
			'before'
		);
	}

	private function is_editor_request() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		return $context && WebsiteBuilderModuleRegistry::is_enabled( 'core', $context );
	}
}
