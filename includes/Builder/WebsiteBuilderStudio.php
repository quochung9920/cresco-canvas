<?php
/**
 * Cresco Studio next-generation Website Builder runtime.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderStudio {
	const SCRIPT = 'build/website-builder-studio.js';
	const STYLE  = 'assets/css/website-builder-studio.css';

	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 121 );
	}

	/** Replace only the core editor implementation while preserving its public handle. */
	public function enqueue() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'core', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( self::SCRIPT ) || ! WebsiteBuilderAsset::readable( self::STYLE ) ) return;

		$config = WebsiteBuilderEditorConfig::for_context( $context );
		if ( ! $config ) return;

		$config['studio'] = array(
			'version'            => '2.0.0',
			'platformPath'       => '/cresco-canvas/v1/website-builder/platform/' . $context->post_id(),
			'presencePath'       => '/cresco-canvas/v1/website-builder/platform/' . $context->post_id() . '/presence',
			'commentsPath'       => '/cresco-canvas/v1/website-builder/platform/' . $context->post_id() . '/comments',
			'interchangeExport'  => '/cresco-canvas/v1/website-builder/interchange/' . $context->post_id() . '/export',
			'interchangePreview' => '/cresco-canvas/v1/website-builder/interchange/' . $context->post_id() . '/preview',
			'diagnosticsUrl'     => add_query_arg(
				array( 'page' => 'cresco-canvas-diagnostics', 'post' => $context->post_id() ),
				admin_url( 'tools.php' )
			),
		);

		wp_dequeue_script( 'cresco-canvas-website-builder' );
		wp_deregister_script( 'cresco-canvas-website-builder' );
		wp_register_script(
			'cresco-canvas-website-builder',
			WebsiteBuilderAsset::url( self::SCRIPT ),
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			WebsiteBuilderAsset::version( self::SCRIPT ),
			true
		);
		wp_enqueue_script( 'cresco-canvas-website-builder' );
		wp_add_inline_script( 'cresco-canvas-website-builder', 'window.crescoWebsiteBuilderSettings=' . wp_json_encode( $config ) . ';', 'before' );
		wp_set_script_translations( 'cresco-canvas-website-builder', 'cresco-canvas' );

		wp_enqueue_style(
			'cresco-canvas-website-builder-studio',
			WebsiteBuilderAsset::url( self::STYLE ),
			array( 'cresco-canvas-website-builder', 'wp-components' ),
			WebsiteBuilderAsset::version( self::STYLE )
		);
	}
}
