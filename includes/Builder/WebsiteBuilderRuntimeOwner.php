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
 * Make Cresco Studio the only runtime allowed to own the public Website Builder
 * script handle. Older services may still prepare compatibility data, but they
 * are no longer allowed to replace the editor implementation or recover by
 * mounting the retired website-builder-editor.js runtime.
 */
final class WebsiteBuilderRuntimeOwner {
	const HANDLE           = 'cresco-canvas-website-builder';
	const SCRIPT           = 'build/website-builder-studio.js';
	const POINTER_HANDLE   = 'cresco-canvas-website-builder-pointer-drag';
	const STRUCTURE_HANDLE = 'cresco-canvas-website-builder-structure-row-drag';
	const AJAX_ACTION      = 'cresco_canvas_studio_runtime';

	/** Register ownership before, during, and after compatibility policy runs. */
	public function register() {
		// Serve the canonical Studio source through WordPress so generated syntax
		// defects are repaired before the browser parser sees the runtime.
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'serve_runtime' ) );

		// Claim the public handle before WebsiteBuilder::enqueue_editor() (120), so
		// its retired source can never become the registered implementation.
		add_action( 'admin_enqueue_scripts', array( $this, 'claim_runtime_handle' ), 119 );

		// Compatibility still owns useful legacy cleanup, but its footer watchdog
		// must never be allowed to inject website-builder-editor.js again.
		add_action( 'admin_enqueue_scripts', array( $this, 'remove_legacy_watchdog' ), 998 );

		// Compatibility hardening runs at 999 and historically rewrote dependencies
		// and versions from the retired runtime. Restore the canonical registration
		// immediately afterwards, before any admin scripts can be printed.
		add_action( 'admin_enqueue_scripts', array( $this, 'reassert_runtime_handle' ), 1000 );

		// RuntimeGuard applies the module policy at 1400. Verify that the required
		// drag extensions are actually registered and enqueued after that policy.
		add_action( 'admin_enqueue_scripts', array( $this, 'verify_drag_extensions' ), 1410 );
	}

	/**
	 * Serve the generated Studio runtime after repairing known malformed closure
	 * sequences. This keeps the browser on the canonical Studio implementation
	 * while the checked-in generated artifact remains content-addressed.
	 */
	public function serve_runtime() {
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only authenticated asset request.
		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			status_header( 403 );
			exit;
		}

		$path = WebsiteBuilderAsset::absolute( self::SCRIPT );
		if ( ! is_readable( $path ) ) {
			status_header( 404 );
			exit;
		}

		$source = file_get_contents( $path );
		if ( ! is_string( $source ) || '' === $source ) {
			status_header( 500 );
			exit;
		}

		// The generated Studio artifact currently contains two independent syntax
		// defects. Repair exact byte sequences only; after the artifact is rebuilt
		// correctly each replacement becomes a harmless no-op.
		$repair_rules = array(
			// Page Settings spacingControl(): close h('label') before the map callback.
			"setPageSettings(n)}})})),h('select'" => "setPageSettings(n)}}))})),h('select'",
			// Team & Extensions: close the extensions.map() callback body before its
			// closing parenthesis. Without this brace the parser reports line 83.
			"x.version||''))))))}" => "x.version||'')))})))}",
		);

		$repairs = 0;
		foreach ( $repair_rules as $malformed => $corrected ) {
			$count   = 0;
			$source  = str_replace( $malformed, $corrected, $source, $count );
			$repairs += $count;
		}

		nocache_headers();
		header( 'Content-Type: application/javascript; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Cresco-Studio-Syntax-Repair: ' . (string) $repairs );
		header( 'X-Cresco-Studio-Syntax-Repair-Version: 2' );
		echo $source; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript asset bytes.
		exit;
	}

	/** Register Cresco Studio on the public handle before any retired owner runs. */
	public function claim_runtime_handle() {
		if ( ! $this->is_editor_request() ) return;
		$this->register_studio_runtime();
	}

	/** Reassert the Studio source after compatibility cleanup/hardening. */
	public function reassert_runtime_handle() {
		if ( ! $this->is_editor_request() ) return;
		$this->register_studio_runtime();
	}

	/**
	 * Remove only the legacy browser watchdog. Frontend compatibility and the
	 * admin asset cleanup remain active; only runtime replacement is retired.
	 */
	public function remove_legacy_watchdog() {
		if ( ! $this->is_editor_request() ) return;

		global $wp_filter;
		$hook = isset( $wp_filter['admin_footer'] ) ? $wp_filter['admin_footer'] : null;
		if ( ! $hook instanceof \WP_Hook || empty( $hook->callbacks ) ) return;

		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = isset( $callback['function'] ) ? $callback['function'] : null;
				if (
					! is_array( $function )
					|| ! isset( $function[0], $function[1] )
					|| ! ( $function[0] instanceof WebsiteBuilderCompatibility )
					|| 'render_editor_bootstrap_watchdog' !== $function[1]
				) {
					continue;
				}

				remove_action( 'admin_footer', $function, (int) $priority );
			}
		}
	}

	/**
	 * Enforce the exact script source/dependency/version tuple for the public
	 * handle. Mutating an existing dependency preserves inline settings that were
	 * already attached by Studio or compatibility services.
	 */
	private function register_studio_runtime() {
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) ) return;

		$dependencies = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
		foreach ( $dependencies as $dependency ) wp_enqueue_script( $dependency );

		$scripts = wp_scripts();
		if ( ! $scripts ) return;

		$runtime_url = $this->runtime_url();
		$version     = WebsiteBuilderAsset::version( self::SCRIPT );

		if ( ! isset( $scripts->registered[ self::HANDLE ] ) ) {
			wp_register_script(
				self::HANDLE,
				$runtime_url,
				$dependencies,
				$version,
				true
			);
		} else {
			$registered       = $scripts->registered[ self::HANDLE ];
			$registered->src  = $runtime_url;
			$registered->deps = $dependencies;
			$registered->ver  = $version;
		}

		wp_enqueue_script( self::HANDLE );
	}

	/** Return the authenticated, cache-busted JavaScript transport URL. */
	private function runtime_url() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		$post_id = $context ? $context->post_id() : 0;

		return add_query_arg(
			array(
				'action'       => self::AJAX_ACTION,
				'post_id'      => $post_id,
				'studio_asset' => WebsiteBuilderAsset::version( self::SCRIPT ) . '-syntax2',
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/** Ensure Canvas and Structure movement modules are present after policy. */
	public function verify_drag_extensions() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;

		// One final ownership assertion after all policy/compatibility hooks.
		$this->register_studio_runtime();

		$module = WebsiteBuilderModuleRegistry::get( 'pointer-drag' );
		if ( ! is_array( $module ) || ! WebsiteBuilderModuleRegistry::is_enabled( 'pointer-drag', $context ) ) return;

		$scripts = wp_scripts();
		if ( ! $scripts ) return;

		foreach ( isset( $module['scripts'] ) && is_array( $module['scripts'] ) ? $module['scripts'] : array() as $asset ) {
			if ( empty( $asset['handle'] ) || empty( $asset['file'] ) || ! WebsiteBuilderAsset::readable( $asset['file'] ) ) continue;

			$handle = (string) $asset['handle'];
			$deps   = isset( $asset['deps'] ) && is_array( $asset['deps'] ) ? $asset['deps'] : array( self::HANDLE );
			if ( ! isset( $scripts->registered[ $handle ] ) ) {
				wp_register_script(
					$handle,
					WebsiteBuilderAsset::url( $asset['file'] ),
					$deps,
					WebsiteBuilderAsset::version( $asset['file'] ),
					true
				);
			} else {
				$registered       = $scripts->registered[ $handle ];
				$registered->src  = WebsiteBuilderAsset::url( $asset['file'] );
				$registered->deps = $deps;
				$registered->ver  = WebsiteBuilderAsset::version( $asset['file'] );
			}

			wp_enqueue_script( $handle );
		}

		$runtime = isset( $scripts->registered[ self::HANDLE ] ) ? $scripts->registered[ self::HANDLE ] : null;
		$payload = array(
			'expectedRuntime'  => 'studio',
			'canonicalScript'  => self::SCRIPT,
			'runtimeTransport' => 'authenticated-syntax-guard-v2',
			'registeredSrc'    => $runtime ? (string) $runtime->src : '',
			'pointerDrag'      => wp_script_is( self::POINTER_HANDLE, 'enqueued' ),
			'structureDrag'    => wp_script_is( self::STRUCTURE_HANDLE, 'enqueued' ),
			'legacyWatchdog'   => false,
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.crescoCanonicalRuntimeOwner=' . wp_json_encode( $payload ) . ';window.crescoExpectedWebsiteBuilderRuntime="studio";',
			'before'
		);
	}

	/** Limit ownership changes to the real standalone editor request. */
	private function is_editor_request() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		return $context && WebsiteBuilderModuleRegistry::is_enabled( 'core', $context );
	}
}
