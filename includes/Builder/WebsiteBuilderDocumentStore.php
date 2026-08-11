<?php
/**
 * Registers the browser-side canonical document store before Cresco Studio.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderDocumentStore {
	const HANDLE = 'cresco-canvas-website-builder-document-store';
	const SCRIPT = 'build/website-builder-document-store.js';
	const CONSISTENCY_HANDLE = 'cresco-canvas-website-builder-consistency-guard';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 120 );
	}

	/**
	 * Register the store after RuntimeOwner has claimed Studio handles but before
	 * Studio config/support assets are attached. Updating the registered guard
	 * dependency here makes execution order explicit without a parallel runtime.
	 */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) ) return;

		wp_register_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array(),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		wp_enqueue_script( self::HANDLE );

		$scripts = wp_scripts();
		if ( ! $scripts || ! isset( $scripts->registered[ self::CONSISTENCY_HANDLE ] ) ) return;
		$guard = $scripts->registered[ self::CONSISTENCY_HANDLE ];
		$guard->deps = array_values( array_unique( array_merge( array( self::HANDLE ), (array) $guard->deps ) ) );
	}
}
