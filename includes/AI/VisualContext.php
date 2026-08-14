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
 * The rest of the AI context describes a document semantically: widget tree,
 * tokens, contracts. That is the right shape for editing a design, but it says
 * nothing about how the result actually looks, so an external model cannot judge
 * overflow, contrast, alignment, or spacing consistency from it.
 *
 * This class adds the missing half by reusing the renderer and CSS compiler that
 * already produce the public page. Nothing is sent anywhere: the output is data
 * in the same envelope the editor already exports to a file or the clipboard.
 */
final class VisualContext {
	/** Cap on exported markup, mirroring the payload discipline of the rest of the envelope. */
	const MAX_HTML_BYTES = 512000;
	const MAX_CSS_BYTES  = 262144;

	/**
	 * Normalize any ScopeResolver content shape into a renderable session.
	 *
	 * `page` scope carries a whole session; narrower scopes carry a node list or
	 * a single node. Rendering the scope rather than the page keeps the payload
	 * proportional to what was asked for.
	 *
	 * @param array $content Scope content from ScopeResolver.
	 * @param array $session Full session, used as the page-scope fallback.
	 * @return array Session-shaped array with a `nodes` list.
	 */
	public static function session_from_content( $content, $session ) {
		$content = (array) $content;

		if ( isset( $content['session'] ) && is_array( $content['session'] ) ) {
			return $content['session'];
		}

		$nodes = array();
		if ( isset( $content['nodes'] ) && is_array( $content['nodes'] ) ) {
			$nodes = array_values( $content['nodes'] );
		} elseif ( isset( $content['node'] ) && is_array( $content['node'] ) ) {
			$nodes = array( $content['node'] );
		}

		$base = is_array( $session ) ? $session : array();
		$base['nodes'] = $nodes;
		return $base;
	}

	/**
	 * Build the visual payload for a scope.
	 *
	 * @param array $content Scope content from ScopeResolver.
	 * @param array $session Full session.
	 * @param int   $post_id Page being exported.
	 * @return array|null Null when the scope renders to nothing.
	 */
	public static function build( $content, $session, $post_id = 0 ) {
		$scoped = self::session_from_content( $content, $session );
		if ( empty( $scoped['nodes'] ) ) {
			return null;
		}

		$html = (string) WebsiteRenderer::render_document( $scoped, absint( $post_id ) );
		$css  = (string) WebsiteRenderer::compile_css( $scoped );
		if ( '' === trim( $html ) ) {
			return null;
		}

		$settings = GlobalStyles::get_settings();

		return array(
			'html'        => self::truncate( $html, self::MAX_HTML_BYTES ),
			'css'         => self::truncate( $css, self::MAX_CSS_BYTES ),
			'htmlTruncated' => strlen( $html ) > self::MAX_HTML_BYTES,
			'cssTruncated'  => strlen( $css ) > self::MAX_CSS_BYTES,
			// Breakpoint starts let a reader reason about which override applies
			// at a given width without re-deriving them from the media queries.
			'breakpoints' => ResponsiveResolver::breakpoints( $settings ),
			'maxWidths'   => ResponsiveResolver::max_widths( $settings ),
			'notes'       => array(
				'This is the rendered appearance of the exported scope, produced by the same renderer as the public page.',
				'CSS uses max-width media queries: a narrower breakpoint inherits from every wider one.',
				'Editing must still be expressed as cresco-session/v1 or cresco-patch/v1. Do not return HTML or CSS as the result.',
			),
		);
	}

	/**
	 * Wrap a visual payload into a standalone HTML document.
	 *
	 * The result opens in a browser with no plugin, no WordPress, and no network
	 * access, which is what makes it useful to hand to a person or to a model
	 * that can look at a page rather than read JSON.
	 *
	 * @param array  $visual Payload from build().
	 * @param string $title  Document title.
	 * @return string
	 */
	public static function document( $visual, $title = '' ) {
		$visual = (array) $visual;
		$title  = '' !== (string) $title ? (string) $title : __( 'Cresco Canvas export', 'cresco-canvas' );

		$head = "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
			. "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
			. '<title>' . esc_html( $title ) . "</title>\n"
			. "<style>\n" . self::css_text( $visual ) . "\n</style>\n</head>\n<body>\n";

		return $head . (string) ( $visual['html'] ?? '' ) . "\n</body>\n</html>\n";
	}

	/**
	 * Stylesheet text for the standalone document.
	 *
	 * @param array $visual Payload from build().
	 * @return string
	 */
	private static function css_text( $visual ) {
		// A minimal reset so the export does not inherit a browser's default
		// margins and read as broken spacing that the design did not cause.
		$reset = "*,*::before,*::after{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif}img{max-width:100%;height:auto}";
		return $reset . "\n" . (string) ( $visual['css'] ?? '' );
	}

	/**
	 * Byte-bounded truncation.
	 *
	 * @param string $value Value to bound.
	 * @param int    $limit Maximum bytes.
	 * @return string
	 */
	private static function truncate( $value, $limit ) {
		$value = (string) $value;
		return strlen( $value ) > $limit ? substr( $value, 0, $limit ) : $value;
	}
}
