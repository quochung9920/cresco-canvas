<?php
/**
 * Builds portable Cresco AI context envelopes.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use CrescoCanvas\Page\PageSettings;
use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContextBuilder {
	const SCHEMA = 'cresco-ai-context/v1';
	const MODES  = array( 'optimized', 'full' );

	/** Build a context from the editor's current Session, including unsaved state. */
	public static function build( $post_id, $session, $scope = 'page', $target = array(), $mode = 'optimized', $resources = array() ) {
		$session = SessionManager::sanitize_session( $session );
		if ( is_wp_error( $session ) ) return $session;
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return new WP_Error( 'cresco_ai_context_mode', __( 'Unsupported AI context mode.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		$scope_data = ScopeResolver::resolve( $session, $scope, $target );
		if ( is_wp_error( $scope_data ) ) return $scope_data;

		$design_system = isset( $resources['designSystem'] ) ? (array) $resources['designSystem'] : DesignTokens::catalog( GlobalStyles::get_settings() );
		$page_settings = isset( $resources['pageSettings'] ) ? (array) $resources['pageSettings'] : PageSettings::get( $post_id );
		$page_effective = isset( $resources['pageSettingsEffective'] ) ? (array) $resources['pageSettingsEffective'] : PageSettings::effective( PageSettings::get( $post_id ) );
		$dependencies  = DependencyResolver::resolve( $scope_data['content'], $design_system );
		$contracts     = 'full' === $mode ? ContractRegistry::all() : ContractRegistry::for_types( $scope_data['requiredTypes'] );
		$design_export = 'full' === $mode ? $design_system : DependencyResolver::optimized_design_system( $design_system, $dependencies );
		$page_export   = 'full' === $mode ? array( 'settings' => $page_settings, 'effective' => $page_effective ) : array( 'effective' => $page_effective );
		$post          = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		$post_title    = array_key_exists( 'postTitle', $resources ) ? (string) $resources['postTitle'] : ( $post ? (string) get_the_title( $post ) : '' );

		$payload = array(
			'schema'       => self::SCHEMA,
			'version'      => 1,
			'scope'        => $scope_data['target']['scope'],
			'mode'         => $mode,
			'baseChecksum' => self::checksum( $session ),
			'target'       => $scope_data['target'],
			'environment'  => array(
				'crescoVersion' => defined( 'CRESCO_CANVAS_VERSION' ) ? CRESCO_CANVAS_VERSION : 'development',
				'sessionSchema' => SessionManager::SCHEMA,
				'postId'        => absint( $post_id ),
				'postTitle'     => $post_title,
			),
			'designSystem' => $design_export,
			'pageSettings' => $page_export,
			'contracts'    => $contracts,
			'content'      => $scope_data['content'],
			'dependencies' => $dependencies,
			'instructions' => self::instructions( $scope_data['target'] ),
		);
		return ContextSanitizer::sanitize( $payload );
	}

	public static function checksum( $session ) {
		$json = json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}

	private static function instructions( $target ) {
		return array(
			'Return either a complete cresco-session/v1 document or a cresco-patch/v1 object. Prefer a targeted patch for scoped edits.',
			'Use only widget types, props, structured style properties, responsive devices, and stable selector parts declared in contracts.',
			'Preserve semantic token references such as {colors.primary}, {spacing.xl}, and {radius.md} when they already express design intent.',
			'Container props.contentWidth="full" means 100% of its parent. It does not mean viewport width. Do not use 100vw to break a Container out of a boxed parent.',
			'Use structured style before customCSS. Custom CSS must remain widget-scoped with &, and must not contain @media, @import, url(), JavaScript, or global selectors.',
			'Never return JavaScript, DOM commands, PHP, credentials, nonces, cookies, authorization headers, API keys, license keys, webhook secrets, or form submission data.',
			'Patch operations must remain inside the exported target scope: ' . (string) ( $target['scope'] ?? 'page' ) . '.',
			'Image attachment IDs are site-local. Treat media dependencies as descriptors and do not assume IDs are portable between sites.',
		);
	}
}
