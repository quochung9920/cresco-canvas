<?php
/**
 * One render boundary for Page, Theme, Loop, Component and Woo documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Rendering;

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderCssCompiler;
use CrescoCanvas\Builder\WebsiteBuilderRendererParity;
use CrescoCanvas\Builder\WebsiteRenderer;
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
		$html = WebsiteRenderer::render_document( $session, $post_id );
		$html = WebsiteBuilderRendererParity::repair_document_html( $html, $session );
		return array(
			'document' => $document,
			'html'     => $html,
			'css'      => WebsiteBuilderCssCompiler::compile( $session ),
			'runtime'  => array( 'website-builder-frontend' ),
		);
	}

	public static function html( $session, $post_id = 0, $document_type = 'page' ) {
		$result = self::render( $session, $post_id, $document_type );
		return is_wp_error( $result ) ? $result : $result['html'];
	}

	public static function css( $session ) {
		$session = WebsiteBuilder::sanitize_session( $session );
		return is_wp_error( $session ) ? $session : WebsiteBuilderCssCompiler::compile( $session );
	}
}
