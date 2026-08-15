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

/**
 * Turns a v2 package plus a natural-language request into one string to paste.
 *
 * The instructions live here, on the server, rather than in the editor bundle.
 * Normative rules duplicated in JavaScript drift from the validator that enforces
 * them, and a user who has to append schema rules by hand after copying has not
 * really been given a one-shot workflow.
 *
 * Deliberately vendor-neutral: it names no model and assumes no chat features
 * beyond pasting text and optionally attaching an image.
 */
final class OneShotPrompt {
	/**
	 * Build the clipboard string.
	 *
	 * @param array  $package Sanitized cresco-ai-context/v2 package.
	 * @param string $request The user's natural-language request.
	 * @return string
	 */
	public static function build( $package, $request ) {
		$request = trim( (string) $request );
		if ( '' === $request ) {
			$request = __( 'Improve this design. Keep the existing copy.', 'cresco-canvas' );
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
			array_map(
				static function ( $rule ) {
					return '- ' . $rule;
				},
				self::instructions( $package )
			),
			array(
				'',
				'CRESCO PACKAGE:',
				$encoded,
			)
		);

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Instruction lines.
	 *
	 * Scope and return shape are read from the package so the prose cannot
	 * contradict the machine-readable contract sitting beside it.
	 *
	 * @param array $package Sanitized v2 package.
	 * @return string[]
	 */
	private static function instructions( $package ) {
		$package  = (array) $package;
		$contract = (array) ( $package['returnContract'] ?? array() );
		$target   = (array) ( $package['scopePackage']['target'] ?? array() );
		$scope    = (string) ( $target['scope'] ?? 'page' );
		$node_id  = (string) ( $target['nodeId'] ?? '' );

		$where = 'page' === $scope
			? __( 'the whole page', 'cresco-canvas' )
			: sprintf( /* translators: 1: scope name, 2: node id. */ __( 'the %1$s "%2$s"', 'cresco-canvas' ), $scope, $node_id );

		return array(
			__( 'Treat the Cresco package below as authoritative.', 'cresco-canvas' ),
			__( 'If reference images are attached to this message, use them as the intended visual direction.', 'cresco-canvas' ),
			__( 'Make reasonable design decisions. Do not ask follow-up questions.', 'cresco-canvas' ),
			__( 'Use only widget types listed in scopePackage.contracts.creationCatalog.', 'cresco-canvas' ),
			__( 'Use only props, structured style properties, responsive devices, states and selector parts those contracts declare.', 'cresco-canvas' ),
			__( 'Prefer native props and structured styles. Use scoped Custom CSS only where native controls cannot express the result.', 'cresco-canvas' ),
			__( 'Design tokens available for authoring are in scopePackage.designSystem.available. Preserve token references such as {colors.primary} where they already express intent.', 'cresco-canvas' ),
			// The responsive model is the single most common source of invalid
			// output, so it is stated in prose as well as in capabilities.
			__( 'Base styles go in node.style. Only the devices in capabilities.responsiveDevices may appear under node.responsive; a narrower device inherits every wider one.', 'cresco-canvas' ),
			sprintf( /* translators: %s: human description of the editable scope. */ __( 'Stay strictly inside %s. Operations outside it are rejected.', 'cresco-canvas' ), $where ),
			sprintf( /* translators: %s: schema identifier. */ __( 'Return ONLY one %s object, matching returnContract.template with operations filled in.', 'cresco-canvas' ), (string) ( $contract['preferred'] ?? 'cresco-patch/v1' ) ),
			__( 'Keep baseChecksum and target exactly as given.', 'cresco-canvas' ),
			__( 'Do not wrap the answer in Markdown fences. Do not explain the answer.', 'cresco-canvas' ),
		);
	}
}
