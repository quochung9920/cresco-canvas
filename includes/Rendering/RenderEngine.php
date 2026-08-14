<?php
/**
 * One render boundary for Page, Theme, Loop, Component and Woo documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Rendering;

use CrescoCanvas\Builder\ComponentStyleCompiler;
use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderArchitectureV2;
use CrescoCanvas\Builder\WebsiteBuilderCssCompiler;
use CrescoCanvas\Builder\WebsiteBuilderRendererParity;
use CrescoCanvas\Builder\WebsiteRendererV2;
use CrescoCanvas\Builder\WidgetArchitectureV2;
use CrescoCanvas\Builder\WidgetPartStyleCompiler;
use CrescoCanvas\Core\Document\Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RenderEngine {
	/** Return HTML, CSS and the normalized document envelope from one Session. */
	public static function render( $session, $post_id = 0, $document_type = 'page' ) {
		$session = WebsiteBuilder::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$document = Document::from_session( $session, $document_type, array( 'postId' => absint( $post_id ) ) );
		if ( is_wp_error( $document ) ) return $document;

		$architecture = absint( $post_id )
			? WebsiteBuilderArchitectureV2::load_document( absint( $post_id ), $session )
			: WidgetArchitectureV2::empty_document();
		$html = WebsiteRendererV2::render_document( $session, absint( $post_id ), $architecture );
		$html = WebsiteBuilderRendererParity::repair_document_html( $html, $session );
		$css = self::compile_css( $session, $architecture );

		return array(
			'document'     => $document,
			'architecture' => $architecture,
			'html'         => $html,
			'css'          => $css,
			'runtime'      => array( 'website-builder-frontend' ),
			'renderOwner'  => 'RenderEngine/v2',
		);
	}

	public static function html( $session, $post_id = 0, $document_type = 'page' ) {
		$result = self::render( $session, $post_id, $document_type );
		return is_wp_error( $result ) ? $result : $result['html'];
	}

	public static function css( $session, $post_id = 0, $document_type = 'page' ) {
		unset( $document_type );
		$session = WebsiteBuilder::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$architecture = absint( $post_id )
			? WebsiteBuilderArchitectureV2::load_document( absint( $post_id ), $session )
			: WidgetArchitectureV2::empty_document();
		return self::compile_css( $session, $architecture );
	}

	private static function compile_css( $session, $architecture ) {
		return self::surface_css()
			. WebsiteBuilderCssCompiler::compile( $session )
			. WidgetPartStyleCompiler::compile( $session, $architecture )
			. ComponentStyleCompiler::compile( $architecture );
	}

	/** Theme-independent geometry shared by Studio iframe and frontend. */
	private static function surface_css() {
		return '.cresco-website-builder-root{width:100%;min-width:0;max-width:none;}'
			. '.cresco-website-builder-root,.cresco-website-builder-root *{box-sizing:border-box;}'
			. '.cresco-website-builder-root [data-cresco-id]{min-width:0;}'
			. '.cresco-website-builder-root img{max-width:100%;height:auto;}';
	}

	private function __construct() {}
}
