<?php
/**
 * Design-token usage analysis for Cresco documents.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Core\Design;

use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesignSystemAnalyzer {
	/** Return the public Design System contract with usage counts. */
	public static function manifest( $session = array(), $architecture = array() ) {
		return array(
			'schema' => 'cresco-design-system/v2',
			'tokens' => DesignTokens::catalog( GlobalStyles::get_settings() ),
			'usage'  => self::usage( $session, $architecture ),
			'precedence' => array( 'token', 'global', 'component', 'local', 'state', 'responsive' ),
		);
	}

	/** Count token references such as {colors.primary} across document-owned data. */
	public static function usage( $session, $architecture = array() ) {
		$counts = array();
		self::scan( is_array( $session ) ? $session : array(), $counts );
		self::scan( is_array( $architecture ) ? $architecture : array(), $counts );
		ksort( $counts );
		return $counts;
	}

	private static function scan( $value, &$counts ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) self::scan( $item, $counts );
			return;
		}
		if ( ! is_string( $value ) || false === strpos( $value, '{' ) ) return;
		if ( preg_match_all( '/\{([a-zA-Z0-9._-]+)\}/', $value, $matches ) ) {
			foreach ( $matches[1] as $token ) {
				$canonical = strtolower( (string) $token );
				if ( '' === $canonical || ! preg_match( '/^[a-z0-9._-]+$/', $canonical ) ) continue;
				$counts[ $canonical ] = (int) ( $counts[ $canonical ] ?? 0 ) + 1;
			}
		}
	}

	private function __construct() {}
}
