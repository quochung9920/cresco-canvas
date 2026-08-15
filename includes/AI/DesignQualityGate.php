<?php
/**
 * Deterministic preflight checks for AI-authored Cresco Sessions.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This gate reports structural/design hygiene that can be proven from Session
 * data alone. It deliberately does not claim browser overflow, contrast ratios
 * or pixel-perfect responsive success without measured browser evidence.
 */
final class DesignQualityGate {
	public static function inspect( $session, $target = array() ) {
		$nodes = self::nodes_for_target( $session, $target );
		$items = array();
		$heading_levels = array();
		$stats = array(
			'nodes' => 0,
			'images' => 0,
			'buttons' => 0,
			'headings' => 0,
			'customCssWidgets' => 0,
			'responsiveWidgets' => 0,
		);
		self::walk( $nodes, $items, $heading_levels, $stats );

		$previous = null;
		foreach ( $heading_levels as $heading ) {
			$level = (int) $heading['level'];
			if ( null !== $previous && $level > $previous + 1 ) {
				$items[] = self::item(
					'heading-level-jump',
					'warning',
					$heading['nodeId'],
					'Heading level jumps from H' . $previous . ' to H' . $level . '. Review the document outline.'
				);
			}
			$previous = $level;
		}

		$warnings = count( array_filter( $items, static function ( $item ) { return 'warning' === $item['severity']; } ) );
		$infos    = count( array_filter( $items, static function ( $item ) { return 'info' === $item['severity']; } ) );
		return array(
			'status' => $warnings ? 'warning' : 'pass',
			'source' => 'session-static-preflight',
			'summary' => array(
				'warnings' => $warnings,
				'info'     => $infos,
				'nodesChecked' => $stats['nodes'],
			),
			'stats' => $stats,
			'items' => array_values( $items ),
			'notChecked' => array(
				'browserGeometry',
				'horizontalOverflow',
				'pixelContrast',
				'visualSimilarityToReferenceImage',
			),
		);
	}

	private static function walk( $nodes, &$items, &$heading_levels, &$stats ) {
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			$stats['nodes']++;
			$id   = (string) ( $node['id'] ?? '' );
			$type = (string) ( $node['type'] ?? '' );
			$props = (array) ( $node['props'] ?? array() );

			if ( ! empty( $node['customCSS'] ) ) $stats['customCssWidgets']++;
			if ( ! empty( $node['responsive'] ) ) $stats['responsiveWidgets']++;

			if ( 'heading' === $type ) {
				$stats['headings']++;
				$level = max( 1, min( 6, (int) ( $props['level'] ?? 2 ) ) );
				$heading_levels[] = array( 'nodeId' => $id, 'level' => $level );
				if ( '' === trim( wp_strip_all_tags( (string) ( $props['text'] ?? '' ) ) ) ) {
					$items[] = self::item( 'empty-heading', 'warning', $id, 'Heading text is empty.' );
				}
			}

			if ( in_array( $type, array( 'image', 'featured-image', 'site-logo' ), true ) ) {
				$stats['images']++;
				if ( 'image' === $type && '' === trim( (string) ( $props['alt'] ?? '' ) ) ) {
					$items[] = self::item( 'image-alt-empty', 'warning', $id, 'Image alt text is empty. Add meaningful alt text or explicitly mark the image decorative.' );
				}
			}

			if ( 'button' === $type ) {
				$stats['buttons']++;
				if ( '' === trim( wp_strip_all_tags( (string) ( $props['text'] ?? '' ) ) ) ) {
					$items[] = self::item( 'button-label-empty', 'warning', $id, 'Button label is empty.' );
				}
				if ( '' === trim( (string) ( $props['url'] ?? '' ) ) ) {
					$items[] = self::item( 'button-url-empty', 'info', $id, 'Button has no destination URL yet.' );
				}
			}

			self::walk( (array) ( $node['children'] ?? array() ), $items, $heading_levels, $stats );
		}
	}

	private static function nodes_for_target( $session, $target ) {
		$nodes  = (array) ( is_array( $session ) ? ( $session['nodes'] ?? array() ) : array() );
		$scope  = (string) ( $target['scope'] ?? 'page' );
		if ( 'page' === $scope ) return $nodes;

		if ( in_array( $scope, array( 'selection', 'selection-subtrees' ), true ) ) {
			$output = array();
			foreach ( (array) ( $target['nodeIds'] ?? array() ) as $id ) {
				$node = ScopeResolver::find_node( $nodes, (string) $id );
				if ( $node ) $output[] = $node;
			}
			return $output;
		}

		$node = ScopeResolver::find_node( $nodes, (string) ( $target['nodeId'] ?? '' ) );
		return $node ? array( $node ) : $nodes;
	}

	private static function item( $code, $severity, $node_id, $message ) {
		return array(
			'code'     => $code,
			'severity' => $severity,
			'nodeId'   => (string) $node_id,
			'message'  => (string) $message,
		);
	}

	private function __construct() {}
}
