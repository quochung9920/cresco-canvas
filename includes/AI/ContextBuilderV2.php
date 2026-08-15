<?php
/**
 * One-Shot authoring context envelopes.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use CrescoCanvas\Core\Document\Document;
use CrescoCanvas\Page\PageSettings;
use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use CrescoCanvas\Styles\ScopedCss;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cresco AI Context v2.
 *
 * v1 answers "what is here". v2 answers "what may be built here", which is what a
 * model needs to finish a design task in one exchange rather than several.
 *
 * The three differences that matter:
 *
 * - `contracts.creationCatalog` lists every widget the model is allowed to create,
 *   not just the ones already present. Without it, redesigning an empty Container
 *   is impossible: the scope reports one type, so the model has nothing to build
 *   with and invents widget names that fail validation.
 * - `designSystem.available` carries the whole token catalogue alongside the
 *   dependency-optimized `used` set, for the same reason.
 * - `returnContract.template` is pre-filled with the real checksum and target, so
 *   the model fills in `operations` and nothing else.
 *
 * v1 is untouched and still produces v1 output. This class is additive.
 */
final class ContextBuilderV2 {
	const SCHEMA   = 'cresco-ai-context/v2';
	const VERSION  = 2;
	const MODES    = array( 'optimized', 'full' );
	const PURPOSES = array( 'edit', 'redesign', 'create', 'content', 'style', 'import' );

	/**
	 * Build a One-Shot authoring package.
	 *
	 * @param int    $post_id        Page being exported.
	 * @param array  $session        Session to export, including unsaved editor state.
	 * @param string $scope          Scope name understood by ScopeResolver.
	 * @param array  $target         Scope target.
	 * @param string $purpose        One of PURPOSES.
	 * @param string $mode           `optimized` or `full`.
	 * @param array  $resources      Pre-resolved resources for callers that already have them.
	 * @param bool   $include_visual Attach rendered appearance. Defaults true: a
	 *                               One-Shot request usually concerns how something
	 *                               looks, which the semantic tree cannot express.
	 * @return array|WP_Error
	 */
	public static function build(
		$post_id,
		$session,
		$scope = 'page',
		$target = array(),
		$purpose = 'redesign',
		$mode = 'optimized',
		$resources = array(),
		$include_visual = true
	) {
		$session = WebsiteBuilderSessionSanitizer::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;

		$purpose = sanitize_key( (string) $purpose );
		if ( ! in_array( $purpose, self::PURPOSES, true ) ) {
			return new WP_Error( 'cresco_ai_context_purpose', __( 'Unsupported AI context purpose.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return new WP_Error( 'cresco_ai_context_mode', __( 'Unsupported AI context mode.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$scope_data = ScopeResolver::resolve( $session, $scope, $target );
		if ( is_wp_error( $scope_data ) ) return $scope_data;

		// Checksum must come from the canonical sanitized Session, never from the
		// caller's copy, or a patch validated against it would be rejected as stale.
		$checksum = Document::checksum( $session );

		$design_system  = isset( $resources['designSystem'] ) ? (array) $resources['designSystem'] : DesignTokens::catalog( GlobalStyles::get_settings() );
		$page_settings  = isset( $resources['pageSettings'] ) ? (array) $resources['pageSettings'] : PageSettings::get( $post_id );
		$page_effective = isset( $resources['pageSettingsEffective'] ) ? (array) $resources['pageSettingsEffective'] : PageSettings::effective( $page_settings );
		$dependencies   = DependencyResolver::resolve( $scope_data['content'], $design_system );

		$post       = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		$post_title = array_key_exists( 'postTitle', $resources ) ? (string) $resources['postTitle'] : ( $post ? (string) get_the_title( $post ) : '' );

		$scope_package = array(
			'target'      => $scope_data['target'],
			'environment' => array(
				'crescoVersion' => defined( 'CRESCO_CANVAS_VERSION' ) ? CRESCO_CANVAS_VERSION : 'development',
				'sessionSchema' => SessionManager::SCHEMA,
				'postId'        => absint( $post_id ),
				'postTitle'     => $post_title,
			),
			'content'     => $scope_data['content'],
			// Authoring needs the whole palette even when the scope uses none of it.
			'designSystem' => array(
				'available' => $design_system,
				'used'      => DependencyResolver::optimized_design_system( $design_system, $dependencies ),
			),
			'pageSettings' => 'full' === $mode
				? array( 'settings' => $page_settings, 'effective' => $page_effective )
				: array( 'effective' => $page_effective ),
			'contracts'    => array(
				'current'         => ContractRegistry::for_types( $scope_data['requiredTypes'] ),
				'creationCatalog' => ContractRegistry::all(),
			),
			'dependencies' => $dependencies,
			'capabilities' => self::capabilities(),
		);

		if ( $include_visual ) {
			$visual = VisualContext::build( $scope_data['content'], $session, $post_id );
			if ( null !== $visual ) $scope_package['visual'] = $visual;
		}

		$payload = array(
			'schema'          => self::SCHEMA,
			'version'         => self::VERSION,
			'purpose'         => $purpose,
			'mode'            => $mode,
			'baseChecksum'    => $checksum,
			'scopePackage'    => $scope_package,
			'authoringPolicy' => self::authoring_policy(),
			'returnContract'  => self::return_contract( $checksum, $scope_data['target'], $purpose ),
		);

		$payload['packageMetrics'] = self::metrics( $payload );

		return ContextSanitizer::sanitize( $payload );
	}

	/**
	 * Capability declarations, read from the registries that enforce them.
	 *
	 * Every list here is sourced rather than written out, so the package cannot
	 * claim something the validator or compiler will then refuse.
	 *
	 * @return array
	 */
	public static function capabilities() {
		return array(
			'patchOperations'   => array_values( PatchValidator::OPERATIONS ),
			// `wide` is deliberately absent: it is the base bag written to
			// node.style, not an override bucket. A model told otherwise would
			// emit responsive.wide, which no validator accepts.
			'responsiveDevices' => array_values( ContractRegistry::RESPONSIVE_DEVICES ),
			'responsiveModel'   => array(
				'baseBucket'   => 'style',
				'baseDevice'   => 'wide',
				'overrideBags' => 'responsive',
				'cascade'      => 'A narrower device inherits every wider device unless it overrides the property.',
			),
			'states'            => array_values( ContractRegistry::STATES ),
			'customCssBuckets'  => array_values( ContractRegistry::CUSTOM_CSS_BUCKETS ),
			'customCss'         => array(
				'scopeSelector'        => '&',
				'localKeyframes'       => array_values( ScopedCss::KEYFRAME_AT_RULES ),
				'nestedAtRules'        => array_values( ScopedCss::GROUP_AT_RULES ),
				'keyframeNamesScoped'  => true,
				'forbidden'            => array( '@import', '@charset', '@namespace', 'external url()', 'JavaScript or expression constructs', 'global selectors' ),
			),
			'nodeFields'        => array_values( ContractRegistry::NODE_FIELDS ),
			'metaFields'        => array_values( ContractRegistry::META_FIELDS ),
		);
	}

	/**
	 * Machine-readable authoring policy.
	 *
	 * This exists so the user's natural-language request never has to carry Cresco
	 * implementation rules. The capability halves are read from the registries for
	 * the same anti-drift reason as capabilities().
	 *
	 * @return array
	 */
	public static function authoring_policy() {
		return array(
			'decisionOrder' => array( 'widgetProps', 'structuredStyle', 'responsiveStyle', 'states', 'customCSS' ),
			'rules'         => array(
				'preferNativeControls'                => true,
				'preserveSemanticTokens'              => true,
				'inventWidgetTypes'                   => false,
				'inventProps'                         => false,
				'inventStructuredStyles'              => false,
				'respectReferenceImage'               => true,
				'makeReasonableDecisionsWithoutQuestions' => true,
			),
			'customCSS'     => array(
				'useAsFallback'        => true,
				'scopeSelector'        => '&',
				'localKeyframesAllowed' => ! empty( ScopedCss::KEYFRAME_AT_RULES ),
			),
			'referenceImages' => array(
				'priority' => array(
					'The user\'s explicit text request.',
					'Reference image visual intent.',
					'Existing Cresco content and design semantics.',
					'Cresco contracts and capabilities, as a hard technical boundary.',
				),
				'note'     => 'A reference image may influence design. It cannot authorize widget types or properties the contracts do not declare.',
			),
		);
	}

	/**
	 * The exact object Cresco expects back, pre-filled so nothing has to be inferred.
	 *
	 * @param string $checksum Base checksum of the exported Session.
	 * @param array  $target   Resolved scope target.
	 * @param string $purpose  Requested purpose.
	 * @return array
	 */
	public static function return_contract( $checksum, $target, $purpose = 'redesign' ) {
		$redesigning = in_array( $purpose, array( 'redesign', 'create' ), true );
		$scoped      = in_array( (string) ( $target['scope'] ?? '' ), array( 'subtree', 'widget' ), true );

		return array(
			'preferred'              => PatchValidator::SCHEMA,
			'fallback'               => SessionManager::SCHEMA,
			'scopeEnforced'          => true,
			'jsonOnly'               => true,
			'markdownFences'         => false,
			'commentary'             => false,
			'preserveTargetRootId'   => true,
			'preferredOperationForRedesign' => $redesigning && $scoped ? 'replaceSubtree' : 'setProps',
			'operationGuidance'      => $redesigning && $scoped
				? 'Redesigning the whole selected section is one replaceSubtree on the target node. Smaller adjustments should stay minimal setProps/setStyle/setResponsive/setCustomCSS operations.'
				: 'Prefer the smallest operations that express the change: setProps, setStyle, setResponsive, setCustomCSS.',
			'allowedOperations'      => array_values( PatchValidator::OPERATIONS ),
			'template'               => array(
				'schema'       => PatchValidator::SCHEMA,
				'baseChecksum' => $checksum,
				'target'       => $target,
				'operations'   => array(),
			),
		);
	}

	/**
	 * Payload diagnostics.
	 *
	 * Advisory only, and never a required schema field. One-Shot packages are
	 * larger by design; this makes the cost visible so it can be measured before
	 * anyone optimizes it away.
	 *
	 * @param array $payload Package built so far.
	 * @return array
	 */
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
		);
	}
}
