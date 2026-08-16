<?php
/**
 * Clipboard envelope for the One-Shot authoring flow.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Vendor-neutral prompt envelope generated from the machine-readable package. */
final class OneShotPrompt {
	public static function build( $package, $request ) {
		$request = trim( (string) $request );
		if ( '' === $request ) $request = __( 'Improve this design. Keep the existing copy and make it responsive.', 'cresco-canvas' );

		// Older callers may supply an unsanitized v3 package. Enriching here keeps
		// the prompt path deterministic without changing edit scope or contracts.
		if ( class_exists( DesignIntelligence::class ) ) {
			$package = DesignIntelligence::augment_context( (array) $package );
		}

		$encoded = wp_json_encode( $package, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) $encoded = '{}';

		$lines = array_merge(
			array(
				'CRESCO ONE-SHOT DESIGN TASK',
				'',
				'USER REQUEST:',
				$request,
				'',
				'INSTRUCTIONS:',
			),
			array_map( static function ( $rule ) { return '- ' . $rule; }, self::instructions( $package ) ),
			array( '', 'CRESCO PACKAGE:', $encoded )
		);
		return implode( "\n", $lines ) . "\n";
	}

	private static function instructions( $package ) {
		$package   = (array) $package;
		$contract  = (array) ( $package['returnContract'] ?? array() );
		$scope_pkg = (array) ( $package['scopePackage'] ?? array() );
		$target    = (array) ( $scope_pkg['target'] ?? array() );
		$scope     = (string) ( $target['scope'] ?? 'page' );
		$node_id   = (string) ( $target['nodeId'] ?? '' );
		$version   = (int) ( $package['version'] ?? 1 );

		$where = 'page' === $scope
			? __( 'the whole page', 'cresco-canvas' )
			: sprintf( __( 'the %1$s "%2$s"', 'cresco-canvas' ), $scope, $node_id );

		$rules = array(
			__( 'Treat the Cresco package below as authoritative.', 'cresco-canvas' ),
			__( 'If reference images are attached to this AI message, use them as the intended visual direction.', 'cresco-canvas' ),
			__( 'Make reasonable design decisions without asking follow-up questions.', 'cresco-canvas' ),
			__( 'Prefer native widget props and structured styles; use responsive styles and states next; use scoped Custom CSS only for capabilities native controls cannot express.', 'cresco-canvas' ),
			__( 'Never invent widget types, props, structured style properties, responsive devices, states, or selector parts.', 'cresco-canvas' ),
			__( 'Use design tokens from scopePackage.designSystem.available whenever they express the requested design intent.', 'cresco-canvas' ),
			__( 'Base styles belong in node.style. Responsive override bags may use only the devices declared by scopePackage.capabilities.responsiveDevices.', 'cresco-canvas' ),
			sprintf( __( 'Stay strictly inside %s. Operations outside it are rejected.', 'cresco-canvas' ), $where ),
			sprintf( __( 'Return ONLY one %s object matching returnContract.template with operations filled in.', 'cresco-canvas' ), (string) ( $contract['preferred'] ?? 'cresco-patch/v1' ) ),
			__( 'Keep returnContract.template.target exactly as provided. Do not add a checksum.', 'cresco-canvas' ),
			__( 'Do not wrap the answer in Markdown fences and do not explain the answer.', 'cresco-canvas' ),
		);

		if ( $version >= 3 ) {
			$design_rules = array();
			if ( ! empty( $scope_pkg['designIntelligence'] ) ) {
				$design_rules = array(
					__( 'Use scopePackage.designIntelligence as the coherent recommended design direction for pattern, hierarchy, palette, typography, spacing, motion, widget choice and anti-pattern avoidance.', 'cresco-canvas' ),
					__( 'Design intelligence is guidance, not authority to widen scope or invent capabilities. Explicit user instructions, reference-image intent and Cresco contracts remain higher priority.', 'cresco-canvas' ),
				);
			}
			array_splice(
				$rules,
				4,
				0,
				array_merge(
					array(
						__( 'For optimized v3 packages, author new nodes only from full contracts in scopePackage.contracts.recommended or current. scopePackage.contracts.catalogIndex is discovery metadata, not permission to guess a contract.', 'cresco-canvas' ),
						__( 'Use scopePackage.scene and scopePackage.visual as read-only surrounding context. They help you judge the design but never widen the editable target.', 'cresco-canvas' ),
						__( 'Use scopePackage.visualFacts as semantic evidence only; measuredGeometry=false means exact browser boxes were not captured and must not be invented.', 'cresco-canvas' ),
					),
					$design_rules
				)
			);
		}
		return $rules;
	}

	private function __construct() {}
}
