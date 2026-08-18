<?php
/**
 * Enforces a single React owner for the Cresco Studio DOM tree.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retires legacy DOM-enhancement runtimes after every Studio service has had a
 * chance to enqueue. Data, REST, styles, and React SDK extensions remain
 * available; only scripts that imperatively rewrite React-owned children are
 * removed from the final queue.
 */
final class StudioReactOwnershipGuard {
	const VERSION = '1.0.0';

	/** @var string[] */
	private const RETIRED_SCRIPT_HANDLES = array(
		'cresco-canvas-website-builder-responsive-properties',
		'cresco-canvas-website-builder-ui-correction',
		'cresco-canvas-website-builder-unset-styles',
		'cresco-canvas-website-builder-architecture-v2',
		'cresco-canvas-studio-dimension-controls',
		'cresco-canvas-studio-dimension-controls-sync',
		'cresco-canvas-studio-typography-popup',
		'cresco-canvas-studio-widget-state-tabs',
		'cresco-canvas-studio-ux-pro',
		'cresco-canvas-studio-ux-pro-guard',
		'cresco-canvas-studio-light-first-runtime',
	);

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enforce' ), 99999 );
	}

	/** Remove only DOM-mutating presentation runtimes from canonical Studio. */
	public function enforce() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! wp_script_is( WebsiteBuilderStudio::HANDLE, 'enqueued' ) ) return;

		foreach ( self::RETIRED_SCRIPT_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
		}

		$post_id = $context->post_id();
		$marker  = array(
			'version'          => self::VERSION,
			'owner'            => 'WebsiteBuilderStudio.React',
			'reactOwnsDom'     => true,
			'retiredScripts'   => self::RETIRED_SCRIPT_HANDLES,
			'legacyUiStateKey' => 'cresco-studio-react-owner-v1:' . $post_id,
		);

		$script = 'window.crescoStudioReactOwnership=' . wp_json_encode( $marker ) . ';' .
			'(function(w,p){try{var m="cresco-studio-react-owner-v1:"+p;if(w.localStorage.getItem(m)!=="1"){' .
			'w.localStorage.removeItem("cresco-studio-ux-pro:"+p+":widget-filter");' .
			'w.localStorage.removeItem("cresco-studio-ux-pro:"+p+":focus");' .
			'w.localStorage.setItem(m,"1");}}catch(e){}})(window,' . absint( $post_id ) . ');';
		wp_add_inline_script( WebsiteBuilderStudio::HANDLE, $script, 'after' );
	}

	/** Exposed for regression tests and diagnostics. */
	public static function retired_script_handles() {
		return self::RETIRED_SCRIPT_HANDLES;
	}
}
