<?php
/**
 * One render boundary for Page, Theme, Loop, Component and Woo documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Rendering;

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderArchitectureV2;
use CrescoCanvas\Builder\WebsiteBuilderCssCompiler;
use CrescoCanvas\Builder\WebsiteBuilderRendererParity;
use CrescoCanvas\Builder\WebsiteRendererV2;
use CrescoCanvas\Builder\WidgetPartStyleCompiler;
use CrescoCanvas\Core\Document\Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RenderEngine {
	/**
	 * Return HTML, CSS and the normalized document envelope from one Session.
	 *
	 * This is the canonical visual boundary used by both the frontend Page filter
	 * and the Studio renderer-preview endpoint. Widget Architecture v2 is loaded
	 * against the supplied (possibly unsaved) Session so part styles, dynamic
	 * bindings, nested slots, Form v2, Loop templates and Woo context render from
	 * the same contract in both places.
	 */
	public static function render( $session, $post_id = 0, $document_type = 'page' ) {
		$session = WebsiteBuilder::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$document = Document::from_session( $session, $document_type, array( 'postId' => absint( $post_id ) ) );
		if ( is_wp_error( $document ) ) return $document;

		$architecture = $post_id > 0
			? WebsiteBuilderArchitectureV2::load_document( $post_id, $session )
			: array( 'schema' => 'cresco-widget-architecture/v2', 'version' => 2, 'nodes' => array() );
		$html = WebsiteRendererV2::render_document( $session, $post_id, $architecture );
		$html = WebsiteBuilderRendererParity::repair_document_html( $html, $session );
		$css  = WebsiteBuilderCssCompiler::compile( $session );
		$css .= WidgetPartStyleCompiler::compile( $session, $architecture );
		$css .= self::component_css( $architecture );

		return array(
			'document'     => $document,
			'architecture' => $architecture,
			'html'         => $html,
			'css'          => $css,
			'runtime'      => array( 'website-builder-frontend' ),
		);
	}

	public static function html( $session, $post_id = 0, $document_type = 'page' ) {
		$result = self::render( $session, $post_id, $document_type );
		return is_wp_error( $result ) ? $result : $result['html'];
	}

	public static function css( $session, $post_id = 0 ) {
		$session = WebsiteBuilder::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$css = WebsiteBuilderCssCompiler::compile( $session );
		if ( $post_id > 0 ) {
			$architecture = WebsiteBuilderArchitectureV2::load_document( $post_id, $session );
			$css .= WidgetPartStyleCompiler::compile( $session, $architecture );
			$css .= self::component_css( $architecture );
		}
		return $css;
	}

	/** Compile component-backed slot styles used by nested/loop templates. */
	private static function component_css( $architecture ) {
		$ids = array();
		foreach ( (array) ( $architecture['nodes'] ?? array() ) as $config ) {
			$slots = (array) ( $config['slots'] ?? array() );
			if ( ! empty( $slots['templateComponentId'] ) ) $ids[] = absint( $slots['templateComponentId'] );
			foreach ( (array) ( $slots['items'] ?? array() ) as $id ) $ids[] = absint( $id );
		}
		$css = '';
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $component_id ) {
			if ( WebsiteBuilder::COMPONENT_TYPE !== get_post_type( $component_id ) ) continue;
			$node = json_decode( (string) get_post_meta( $component_id, WebsiteBuilder::COMPONENT_META, true ), true );
			if ( is_array( $node ) ) $css .= WebsiteBuilderCssCompiler::compile( array( 'nodes' => array( $node ) ) );
		}
		return $css;
	}
}
