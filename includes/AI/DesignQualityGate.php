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
			'interactiveWidgets' => 0,
			'customCssWidgets' => 0,
			'responsiveWidgets' => 0,
		);
		$hygiene = array( 'rawColors' => array(), 'tokenColors' => array(), 'spacingValues' => array() );
		self::walk( $nodes, $items, $heading_levels, $stats, $hygiene );

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

		$stats['rawColorValues'] = count( $hygiene['rawColors'] );
		$stats['tokenColorValues'] = count( $hygiene['tokenColors'] );
		$stats['uniqueSpacingValues'] = count( $hygiene['spacingValues'] );

		if ( $stats['nodes'] >= 12 && 0 === $stats['responsiveWidgets'] ) {
			$items[] = self::item( 'responsive-overrides-none', 'info', '', 'No explicit responsive overrides are present in this scope. Fluid layouts may still be valid; review narrow breakpoints before delivery.' );
		}
		if ( $stats['rawColorValues'] >= 8 ) {
			$items[] = self::item( 'raw-color-fragmentation', 'warning', '', 'This scope uses ' . $stats['rawColorValues'] . ' distinct literal color values. Consolidate recurring colors into design tokens where possible.' );
		} elseif ( $stats['rawColorValues'] >= 5 && 0 === $stats['tokenColorValues'] ) {
			$items[] = self::item( 'design-tokens-unused-for-colors', 'info', '', 'Several literal colors are used without semantic color tokens. Consider tokenizing the reusable palette.' );
		}
		if ( $stats['uniqueSpacingValues'] >= 12 ) {
			$items[] = self::item( 'spacing-scale-fragmented', 'info', '', 'This scope uses many distinct spacing values. Review whether a smaller spacing scale would improve consistency.' );
		}
		if ( $stats['customCssWidgets'] >= 3 && $stats['nodes'] > 0 && ( $stats['customCssWidgets'] / $stats['nodes'] ) >= 0.15 ) {
			$items[] = self::item( 'custom-css-overuse', 'warning', '', 'Custom CSS is used on a significant share of widgets. Prefer native controls, structured styles and shared tokens when they can express the same design.' );
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

	private static function walk( $nodes, &$items, &$heading_levels, &$stats, &$hygiene ) {
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			$stats['nodes']++;
			$id   = (string) ( $node['id'] ?? '' );
			$type = (string) ( $node['type'] ?? '' );
			$props = (array) ( $node['props'] ?? array() );

			if ( ! empty( $node['customCSS'] ) ) $stats['customCssWidgets']++;
			if ( ! empty( $node['responsive'] ) ) $stats['responsiveWidgets']++;
			if ( in_array( $type, array( 'button', 'form', 'accordion', 'tabs', 'nested-tabs', 'nested-accordion', 'clickable-container', 'video-popup', 'mega-menu', 'filterable-grid' ), true ) ) $stats['interactiveWidgets']++;

			self::inspect_style_bag( (array) ( $node['style'] ?? array() ), $hygiene );
			foreach ( (array) ( $node['responsive'] ?? array() ) as $style ) self::inspect_style_bag( (array) $style, $hygiene );
			foreach ( (array) ( $node['states'] ?? array() ) as $style ) self::inspect_style_bag( (array) $style, $hygiene );

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

			self::walk( (array) ( $node['children'] ?? array() ), $items, $heading_levels, $stats, $hygiene );
		}
	}

	private static function inspect_style_bag( $style, &$hygiene ) {
		foreach ( (array) $style as $key => $value ) {
			if ( ! is_scalar( $value ) ) continue;
			$value = trim( (string) $value );
			if ( '' === $value ) continue;
			if ( false !== stripos( (string) $key, 'color' ) ) {
				if ( preg_match( '/^\{[a-zA-Z0-9._-]+\}$/', $value ) ) $hygiene['tokenColors'][ $value ] = true;
				elseif ( preg_match( '/^(?:#[0-9a-f]{3,8}|rgba?\(|hsla?\()/i', $value ) ) $hygiene['rawColors'][ strtolower( $value ) ] = true;
			}
			if ( preg_match( '/^(?:margin|padding|gap|rowGap|columnGap)/i', (string) $key ) && preg_match( '/^-?[0-9.]+(?:px|rem|em|%|vw|vh|vmin|vmax|ch)$/i', $value ) ) {
				$hygiene['spacingValues'][ strtolower( $value ) ] = true;
			}
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
