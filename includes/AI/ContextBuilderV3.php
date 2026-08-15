<?php
/**
 * High-fidelity One-Shot authoring context for external AI design tools.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cresco AI Context v3.
 *
 * v2 makes the authoring contract complete. v3 makes that contract easier for a
 * model to reason about by adding a task envelope, a context ring, a compact
 * recommended contract set, semantic visual facts and concrete patch examples.
 *
 * Browser-measured geometry is intentionally not invented on the server. The
 * `visual` payload is rendered by WebsiteRenderer, while `visualFacts` explicitly
 * identifies itself as a semantic/session summary. A future browser capture may
 * add measured boxes without changing this schema.
 */
final class ContextBuilderV3 {
	const SCHEMA  = 'cresco-ai-context/v3';
	const VERSION = 3;

	/** Build a high-fidelity One-Shot package. */
	public static function build(
		$post_id,
		$session,
		$scope = 'page',
		$target = array(),
		$purpose = 'redesign',
		$mode = 'optimized',
		$resources = array(),
		$include_visual = true,
		$request = ''
	) {
		$session = WebsiteBuilderSessionSanitizer::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$request = trim( (string) $request );
		if ( '' === $request ) $request = 'Improve this design. Keep the existing copy and make it responsive.';

		// Reuse v2 for canonical scope, design-system, capability and policy data.
		$v2 = ContextBuilderV2::build(
			$post_id,
			$session,
			$scope,
			$target,
			$purpose,
			$mode,
			$resources,
			false
		);
		if ( is_wp_error( $v2 ) ) return $v2;

		$scope_package = (array) ( $v2['scopePackage'] ?? array() );
		$resolved      = (array) ( $scope_package['target'] ?? array() );
		$catalog       = ContractRegistry::all();
		$recommended   = self::recommended_types( $catalog, $scope_package, $request );
		$current       = (array) ( $scope_package['contracts']['current'] ?? array() );

		$scope_package['scene']       = self::scene( $session, $resolved, (array) ( $scope_package['content'] ?? array() ) );
		$scope_package['visualFacts'] = self::visual_facts( (array) ( $scope_package['content'] ?? array() ), $resolved );
		$scope_package['contracts']   = array(
			'current'     => $current,
			'recommended' => ContractRegistry::for_types( $recommended ),
			'catalogIndex' => self::catalog_index( $catalog ),
		);

		// Full mode remains the escape hatch for agents that explicitly need every
		// complete creation contract. Optimized mode keeps the prompt focused.
		if ( 'full' === $mode ) {
			$scope_package['contracts']['creationCatalog'] = $catalog;
		}

		if ( $include_visual ) {
			$visual = VisualContext::build( (array) ( $scope_package['content'] ?? array() ), $session, $post_id, $resolved );
			if ( null !== $visual ) $scope_package['visual'] = $visual;
		}

		$return_contract = (array) ( $v2['returnContract'] ?? array() );
		$return_contract['examples'] = self::patch_examples( $resolved );
		$return_contract['contractRule'] = 'Use only full contracts present in contracts.current, contracts.recommended, or contracts.creationCatalog when full mode is requested. catalogIndex is discovery metadata, not permission to invent props.';

		$policy = (array) ( $v2['authoringPolicy'] ?? array() );
		$policy['qualityGoals'] = array(
			'preserveExistingMeaning' => true,
			'responsiveByDefault'     => true,
			'avoidHorizontalOverflow' => true,
			'accessibleStructure'     => true,
			'preferDesignTokens'      => true,
			'minimizeCustomCSS'       => true,
		);

		$payload = array(
			'schema'          => self::SCHEMA,
			'version'         => self::VERSION,
			'purpose'         => (string) ( $v2['purpose'] ?? $purpose ),
			'mode'            => (string) ( $v2['mode'] ?? $mode ),
			'task'            => array(
				'request'                 => $request,
				'editableTarget'          => $resolved,
				'preserveExistingContent' => ! in_array( (string) $purpose, array( 'create' ), true ),
				'referenceImagePolicy'    => 'When one or more reference images are attached in the AI chat, match their visual intent closely while Cresco contracts remain the hard technical boundary.',
			),
			'scopePackage'    => $scope_package,
			'authoringPolicy' => $policy,
			'returnContract'  => $return_contract,
		);

		$payload['packageMetrics'] = self::metrics( $payload );
		return ContextSanitizer::sanitize( $payload );
	}

	/** Select a small but useful contract set without inventing a second catalog. */
	private static function recommended_types( $catalog, $scope_package, $request ) {
		$types = array_keys( (array) ( $scope_package['contracts']['current'] ?? array() ) );
		$common = array( 'container', 'columns', 'heading', 'text', 'button', 'image', 'icon', 'list', 'divider', 'spacer' );
		foreach ( $common as $type ) if ( isset( $catalog[ $type ] ) ) $types[] = $type;

		$needle = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', (string) $request ) );
		$words  = array_values( array_filter( preg_split( '/\s+/', $needle ) ?: array(), static function ( $word ) { return strlen( $word ) >= 3; } ) );
		foreach ( (array) $catalog as $type => $contract ) {
			$haystack = strtolower( $type . ' ' . (string) ( $contract['label'] ?? '' ) . ' ' . (string) ( $contract['category'] ?? '' ) );
			foreach ( $words as $word ) {
				if ( false !== strpos( $haystack, $word ) ) {
					$types[] = $type;
					break;
				}
			}
		}

		// Fill the focused set with common visual categories so a generic
		// "match this reference" request can still choose interactive/media
		// primitives visible only in the image. The contracts still come from the
		// registry and the final set remains capped.
		foreach ( (array) $catalog as $type => $contract ) {
			if ( in_array( (string) ( $contract['category'] ?? '' ), array( 'layout', 'content', 'media', 'interactive' ), true ) ) $types[] = $type;
		}

		$types = array_values( array_unique( array_filter( $types, static function ( $type ) use ( $catalog ) { return isset( $catalog[ $type ] ); } ) ) );
		return array_slice( $types, 0, 18 );
	}

	private static function catalog_index( $catalog ) {
		$output = array();
		foreach ( (array) $catalog as $type => $contract ) {
			$output[ $type ] = array(
				'label'          => (string) ( $contract['label'] ?? $type ),
				'category'       => (string) ( $contract['category'] ?? 'content' ),
				'allowsChildren' => ! empty( $contract['allowsChildren'] ),
				'propNames'      => array_values( array_keys( (array) ( $contract['props'] ?? array() ) ) ),
				'styleCount'     => count( (array) ( $contract['structuredStyle'] ?? array() ) ),
				'responsive'     => ! empty( $contract['responsive']['allowed'] ),
				'states'         => array_values( (array) ( $contract['states'] ?? array() ) ),
			);
		}
		return $output;
	}

	/** Describe the editable target in its surrounding document without widening scope. */
	private static function scene( $session, $target, $content ) {
		$node_id = (string) ( $target['nodeId'] ?? '' );
		$scene   = array(
			'editable' => $target,
			'ancestry' => array_values( (array) ( $content['ancestry'] ?? array() ) ),
			'siblings' => array(),
			'contextRootId' => null,
			'note' => 'Scene metadata is read-only context. Patch scope remains exactly the editable target.',
		);
		if ( '' === $node_id ) return $scene;

		$info = self::node_info( (array) ( $session['nodes'] ?? array() ), $node_id );
		if ( ! $info ) return $scene;
		$scene['contextRootId'] = $info['rootId'];
		foreach ( (array) $info['siblings'] as $sibling ) {
			$scene['siblings'][] = array(
				'id'    => (string) ( $sibling['id'] ?? '' ),
				'type'  => (string) ( $sibling['type'] ?? '' ),
				'label' => (string) ( $sibling['meta']['label'] ?? '' ),
			);
		}
		return $scene;
	}

	private static function node_info( $nodes, $wanted, $root_id = null ) {
		$nodes = array_values( (array) $nodes );
		foreach ( $nodes as $node ) {
			$id   = (string) ( $node['id'] ?? '' );
			$root = null === $root_id ? $id : $root_id;
			if ( $id === $wanted ) {
				$siblings = array_values( array_filter( $nodes, static function ( $child ) use ( $wanted ) { return (string) ( $child['id'] ?? '' ) !== $wanted; } ) );
				return array( 'rootId' => $root, 'siblings' => $siblings );
			}
			$found = self::node_info( (array) ( $node['children'] ?? array() ), $wanted, $root );
			if ( $found ) return $found;
		}
		return null;
	}

	/** Compact facts derived from the Session. They are not browser measurements. */
	private static function visual_facts( $content, $target ) {
		$nodes = array();
		if ( isset( $content['session']['nodes'] ) ) $nodes = (array) $content['session']['nodes'];
		elseif ( isset( $content['nodes'] ) ) $nodes = (array) $content['nodes'];
		elseif ( isset( $content['node'] ) ) $nodes = array( $content['node'] );

		$state = array(
			'nodeCount' => 0,
			'maxDepth' => 0,
			'widgetTypes' => array(),
			'responsiveDevicesUsed' => array(),
			'customCssWidgets' => 0,
			'textWidgets' => 0,
			'imageWidgets' => 0,
			'interactiveWidgets' => 0,
		);
		self::collect_facts( $nodes, 0, $state );
		$state['widgetTypes'] = array_values( array_unique( $state['widgetTypes'] ) );
		$state['responsiveDevicesUsed'] = array_values( array_unique( $state['responsiveDevicesUsed'] ) );

		return array(
			'source' => 'session-semantic-summary',
			'measuredGeometry' => false,
			'target' => $target,
			'summary' => $state,
			'note' => 'These facts summarize authored Session data. Exact browser boxes require a client-side capture and are never guessed here.',
		);
	}

	private static function collect_facts( $nodes, $depth, &$state ) {
		foreach ( (array) $nodes as $node ) {
			$state['nodeCount']++;
			$state['maxDepth'] = max( $state['maxDepth'], $depth );
			$type = (string) ( $node['type'] ?? '' );
			if ( '' !== $type ) $state['widgetTypes'][] = $type;
			if ( in_array( $type, array( 'text', 'heading', 'testimonial' ), true ) ) $state['textWidgets']++;
			if ( in_array( $type, array( 'image', 'featured-image', 'site-logo', 'gallery' ), true ) ) $state['imageWidgets']++;
			if ( in_array( $type, array( 'button', 'form', 'accordion', 'tabs', 'video' ), true ) ) $state['interactiveWidgets']++;
			if ( ! empty( $node['customCSS'] ) ) $state['customCssWidgets']++;
			foreach ( array_keys( (array) ( $node['responsive'] ?? array() ) ) as $device ) $state['responsiveDevicesUsed'][] = (string) $device;
			self::collect_facts( (array) ( $node['children'] ?? array() ), $depth + 1, $state );
		}
	}

	private static function patch_examples( $target ) {
		$scope   = (string) ( $target['scope'] ?? 'page' );
		$node_id = (string) ( $target['nodeId'] ?? '' );
		$type    = (string) ( $target['type'] ?? 'container' );
		$examples = array();
		if ( '' !== $node_id ) {
			$examples[] = array( 'op' => 'setStyle', 'nodeId' => $node_id, 'style' => array() );
			if ( 'subtree' === $scope ) {
				$examples[] = array(
					'op'     => 'replaceSubtree',
					'nodeId' => $node_id,
					'node'   => array(
						'id'         => $node_id,
						'type'       => $type,
						'props'      => array(),
						'style'      => array(),
						'responsive' => array(),
						'states'     => array(),
						'customCSS'  => array(),
						'meta'       => array( 'label' => '', 'componentId' => 0, 'locked' => false, 'hidden' => false ),
						'children'   => array(),
					),
				);
			}
		}
		return $examples;
	}

	private static function metrics( $payload ) {
		$size = static function ( $value ) {
			$encoded = wp_json_encode( $value );
			return is_string( $encoded ) ? strlen( $encoded ) : 0;
		};
		$package = (array) ( $payload['scopePackage'] ?? array() );
		return array(
			'bytes'         => $size( $payload ),
			'contentBytes'  => $size( $package['content'] ?? array() ),
			'contractBytes' => $size( $package['contracts'] ?? array() ),
			'visualBytes'   => $size( $package['visual'] ?? array() ),
			'factsBytes'    => $size( $package['visualFacts'] ?? array() ),
		);
	}

	private function __construct() {}
}
