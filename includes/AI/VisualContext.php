<?php
/**
 * Rendered appearance for AI context envelopes.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Builder\WebsiteRenderer;
use CrescoCanvas\Core\Responsive\ResponsiveResolver;
use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the rendered half of an AI package by reusing the public WebsiteRenderer.
 *
 * For a narrow editable target, v3 may render the complete top-level document
 * branch that contains that target. This preserves parent width, grid/flex and
 * sibling context while the machine-readable patch scope remains unchanged.
 */
final class VisualContext {
	const MAX_HTML_BYTES = 512000;
	const MAX_CSS_BYTES  = 262144;

	/** Normalize ScopeResolver content into a renderable Session. */
	public static function session_from_content( $content, $session ) {
		$content = (array) $content;
		if ( isset( $content['session'] ) && is_array( $content['session'] ) ) return $content['session'];

		$nodes = array();
		if ( isset( $content['nodes'] ) && is_array( $content['nodes'] ) ) $nodes = array_values( $content['nodes'] );
		elseif ( isset( $content['node'] ) && is_array( $content['node'] ) ) $nodes = array( $content['node'] );

		$base = is_array( $session ) ? $session : array();
		$base['nodes'] = $nodes;
		return $base;
	}

	/**
	 * Build the visual payload.
	 *
	 * @param array $content Scope content from ScopeResolver.
	 * @param array $session Full Session.
	 * @param int   $post_id Page being exported.
	 * @param array $target  Optional resolved target. Narrow targets render with
	 *                       their actual top-level branch as read-only context.
	 * @return array|null
	 */
	public static function build( $content, $session, $post_id = 0, $target = array() ) {
		$scoped       = self::session_from_content( $content, $session );
		$context_mode = 'scope-only';
		$context_roots = array();
		$target       = (array) $target;

		if ( 'page' === (string) ( $target['scope'] ?? '' ) ) {
			$context_mode = 'page';
		} else {
			$context = self::session_with_context_ring( $session, $target );
			if ( ! empty( $context['nodes'] ) ) {
				$scoped        = $context;
				$context_mode  = 'top-level-branch';
				$context_roots = array_values( array_filter( array_map( static function ( $node ) { return (string) ( $node['id'] ?? '' ); }, (array) $context['nodes'] ) ) );
			}
		}

		if ( empty( $scoped['nodes'] ) ) return null;

		$html = (string) WebsiteRenderer::render_document( $scoped, absint( $post_id ) );
		$css  = (string) WebsiteRenderer::compile_css( $scoped );
		if ( '' === trim( $html ) ) return null;

		$settings = GlobalStyles::get_settings();
		return array(
			'html'             => self::truncate( $html, self::MAX_HTML_BYTES ),
			'css'              => self::truncate( $css, self::MAX_CSS_BYTES ),
			'htmlTruncated'    => strlen( $html ) > self::MAX_HTML_BYTES,
			'cssTruncated'     => strlen( $css ) > self::MAX_CSS_BYTES,
			'breakpoints'      => ResponsiveResolver::breakpoints( $settings ),
			'maxWidths'        => ResponsiveResolver::max_widths( $settings ),
			'contextMode'      => $context_mode,
			'contextRootIds'   => $context_roots,
			'editableTarget'   => $target,
			'notes'            => array(
				'This appearance is produced by the same WebsiteRenderer as the public page.',
				'Top-level branch context is visual/read-only context only; editing remains restricted to editableTarget.',
				'CSS uses max-width media queries: a narrower breakpoint inherits from every wider one.',
				'Editing must still be returned as cresco-session/v1 or cresco-patch/v1, never as HTML or CSS.',
			),
		);
	}

	/** Build a standalone HTML document suitable for iframe review or file export. */
	public static function document( $visual, $title = '' ) {
		$visual = (array) $visual;
		$title  = '' !== (string) $title ? (string) $title : __( 'Cresco Canvas export', 'cresco-canvas' );
		$head = "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
			. "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
			. '<title>' . esc_html( $title ) . "</title>\n"
			. "<style>\n" . self::css_text( $visual ) . "\n</style>\n</head>\n<body>\n";
		return $head . (string) ( $visual['html'] ?? '' ) . "\n</body>\n</html>\n";
	}

	/** Render the top-level root(s) containing the requested target IDs. */
	private static function session_with_context_ring( $session, $target ) {
		$session = is_array( $session ) ? $session : array();
		$ids = array();
		if ( ! empty( $target['nodeId'] ) ) $ids[] = (string) $target['nodeId'];
		foreach ( (array) ( $target['nodeIds'] ?? array() ) as $id ) if ( '' !== (string) $id ) $ids[] = (string) $id;
		$ids = array_values( array_unique( $ids ) );
		if ( ! $ids ) return array();

		$roots = array();
		foreach ( (array) ( $session['nodes'] ?? array() ) as $node ) {
			foreach ( $ids as $id ) {
				if ( self::contains_id( $node, $id ) ) {
					$roots[ (string) ( $node['id'] ?? count( $roots ) ) ] = $node;
					break;
				}
			}
		}
		if ( ! $roots ) return array();
		$context = $session;
		$context['nodes'] = array_values( $roots );
		return $context;
	}

	private static function contains_id( $node, $wanted ) {
		if ( ! is_array( $node ) ) return false;
		if ( (string) ( $node['id'] ?? '' ) === (string) $wanted ) return true;
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) if ( self::contains_id( $child, $wanted ) ) return true;
		return false;
	}

	private static function css_text( $visual ) {
		$reset = "*,*::before,*::after{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif}img{max-width:100%;height:auto}";
		return $reset . "\n" . (string) ( $visual['css'] ?? '' );
	}

	private static function truncate( $value, $limit ) {
		$value = (string) $value;
		return strlen( $value ) > $limit ? substr( $value, 0, $limit ) : $value;
	}

	private function __construct() {}
}
