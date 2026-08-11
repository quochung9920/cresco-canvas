<?php
/**
 * Local accessibility and design-quality diagnostics for Cresco documents.
 *
 * Diagnostics never mutate the document. Every node issue carries a nodeId so
 * Canvas/Structure/Inspector can locate it without introducing another state owner.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Diagnostics;

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Styles\StyleCascade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DocumentDiagnostics {
	const SCHEMA = 'cresco-diagnostics/v1';
	const MAX_RECOMMENDED_DEPTH = 8;
	const MAX_RECOMMENDED_LOCAL_STYLES = 18;

	/** Analyze one canonical Session without mutating or persisting it. */
	public static function analyze( $session ) {
		$session = WebsiteBuilder::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;

		$issues = array();
		$headings = array();
		self::walk( (array) ( $session['nodes'] ?? array() ), 0, array(), $issues, $headings );
		self::heading_diagnostics( $headings, $issues );

		$summary = array( 'error' => 0, 'warning' => 0, 'info' => 0, 'total' => count( $issues ) );
		foreach ( $issues as $issue ) {
			$severity = (string) ( $issue['severity'] ?? 'info' );
			if ( isset( $summary[ $severity ] ) ) $summary[ $severity ]++;
		}

		return array(
			'schema'   => self::SCHEMA,
			'summary'  => $summary,
			'issues'   => array_values( $issues ),
		);
	}

	private static function walk( $nodes, $depth, $ancestors, &$issues, &$headings ) {
		foreach ( array_values( (array) $nodes ) as $index => $node ) {
			if ( ! is_array( $node ) ) continue;
			$node_id = (string) ( $node['id'] ?? '' );
			$type = (string) ( $node['type'] ?? '' );
			$path = array_merge( $ancestors, array( $node_id ) );
			$props = (array) ( $node['props'] ?? array() );

			if ( $depth > self::MAX_RECOMMENDED_DEPTH ) {
				$issues[] = self::issue( 'warning', 'structure.deep-nesting', 'structure', $node_id, $path, __( 'This node is deeply nested. Consider simplifying the layout hierarchy.', 'cresco-canvas' ), array( 'depth' => $depth ) );
			}

			$local_style_count = count( (array) ( $node['style'] ?? array() ) );
			if ( $local_style_count > self::MAX_RECOMMENDED_LOCAL_STYLES ) {
				$issues[] = self::issue( 'info', 'style.excessive-local-overrides', 'design', $node_id, $path, __( 'This node has many local style overrides. Consider using shared design tokens or reusable styles.', 'cresco-canvas' ), array( 'count' => $local_style_count ) );
			}

			self::responsive_diagnostics( $node, $node_id, $path, $issues );

			if ( 'heading' === $type ) {
				$headings[] = array(
					'nodeId' => $node_id,
					'path'   => $path,
					'level'  => min( 6, max( 1, absint( $props['level'] ?? 2 ) ) ),
					'text'   => trim( wp_strip_all_tags( (string) ( $props['text'] ?? '' ) ) ),
				);
			}

			if ( 'image' === $type ) {
				$decorative = ! empty( $props['decorative'] );
				$url = trim( (string) ( $props['url'] ?? '' ) );
				$alt = trim( wp_strip_all_tags( (string) ( $props['alt'] ?? '' ) ) );
				if ( '' !== $url && ! $decorative && '' === $alt ) {
					$issues[] = self::issue( 'warning', 'image.missing-alt', 'accessibility', $node_id, $path, __( 'Image alternative text is missing. Add useful alt text or mark the image as decorative.', 'cresco-canvas' ) );
				}
			}

			if ( 'button' === $type ) {
				$label = trim( wp_strip_all_tags( (string) ( $props['accessibleLabel'] ?? $props['ariaLabel'] ?? '' ) ) );
				$text = trim( wp_strip_all_tags( (string) ( $props['text'] ?? '' ) ) );
				if ( '' === $label && '' === $text ) {
					$issues[] = self::issue( 'error', 'button.missing-accessible-name', 'accessibility', $node_id, $path, __( 'Button has no accessible name.', 'cresco-canvas' ) );
				}
				if ( '_blank' === (string) ( $props['target'] ?? '' ) ) {
					$rel = preg_split( '/\s+/', strtolower( trim( (string) ( $props['rel'] ?? '' ) ) ) );
					$rel = array_values( array_filter( (array) $rel ) );
					if ( ! in_array( 'noopener', $rel, true ) ) {
						$issues[] = self::issue( 'info', 'button.external-rel-normalized', 'security', $node_id, $path, __( 'This new-tab link will be rendered with rel="noopener noreferrer" for safety.', 'cresco-canvas' ) );
					}
				}
			}

			self::walk( (array) ( $node['children'] ?? array() ), $depth + 1, $path, $issues, $headings );
		}
	}

	private static function heading_diagnostics( $headings, &$issues ) {
		$h1 = array_values( array_filter( $headings, static function ( $heading ) { return 1 === (int) $heading['level']; } ) );
		if ( count( $h1 ) > 1 ) {
			foreach ( $h1 as $heading ) {
				$issues[] = self::issue( 'warning', 'heading.multiple-h1', 'accessibility', $heading['nodeId'], $heading['path'], __( 'Multiple H1 headings were detected. Verify that the page hierarchy is intentional.', 'cresco-canvas' ), array( 'h1Count' => count( $h1 ) ) );
			}
		}

		$previous = null;
		foreach ( $headings as $heading ) {
			$level = (int) $heading['level'];
			if ( null !== $previous && $level > $previous + 1 ) {
				$issues[] = self::issue( 'warning', 'heading.level-skip', 'accessibility', $heading['nodeId'], $heading['path'], __( 'Heading level skips over an intermediate level.', 'cresco-canvas' ), array( 'previousLevel' => $previous, 'level' => $level ) );
			}
			$previous = $level;
		}
	}

	private static function responsive_diagnostics( $node, $node_id, $path, &$issues ) {
		$order = StyleCascade::BREAKPOINTS;
		$responsive = (array) ( $node['responsive'] ?? array() );
		foreach ( $responsive as $breakpoint => $properties ) {
			$position = array_search( $breakpoint, $order, true );
			if ( false === $position || 0 === $position || ! is_array( $properties ) ) continue;
			$previous_breakpoint = $order[ $position - 1 ];
			foreach ( $properties as $property => $value ) {
				$previous = StyleCascade::resolve( $node, (string) $property, $previous_breakpoint );
				if ( is_wp_error( $previous ) ) continue;
				if ( self::same_value( $value, $previous['value'] ?? null ) ) {
					$issues[] = self::issue( 'info', 'responsive.redundant-override', 'responsive', $node_id, $path, __( 'Responsive override matches its inherited value and can be reset.', 'cresco-canvas' ), array( 'breakpoint' => $breakpoint, 'property' => (string) $property ) );
				}
			}
		}
	}

	private static function same_value( $left, $right ) {
		if ( is_scalar( $left ) && is_scalar( $right ) ) return trim( (string) $left ) === trim( (string) $right );
		return wp_json_encode( $left ) === wp_json_encode( $right );
	}

	private static function issue( $severity, $code, $category, $node_id, $path, $message, $details = array() ) {
		return array(
			'id'       => substr( hash( 'sha256', $code . '|' . $node_id . '|' . wp_json_encode( $details ) ), 0, 20 ),
			'severity' => $severity,
			'code'     => $code,
			'category' => $category,
			'nodeId'   => $node_id,
			'path'     => array_values( array_filter( array_map( 'strval', (array) $path ) ) ),
			'message'  => $message,
			'details'  => (array) $details,
		);
	}

	private function __construct() {}
}
