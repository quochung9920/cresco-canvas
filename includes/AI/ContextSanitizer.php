<?php
/**
 * Defense-in-depth sanitizer for data exported to external AI systems.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContextSanitizer {
	/** Remove secret-bearing keys recursively, then enrich supported top-level contexts. */
	public static function sanitize( $value ) {
		$output = self::sanitize_recursive( $value );
		if ( is_array( $output ) && class_exists( DesignIntelligence::class ) ) {
			$output = DesignIntelligence::augment_context( $output );
		}
		return $output;
	}

	private static function sanitize_recursive( $value ) {
		if ( ! is_array( $value ) ) return $value;
		$output = array();
		foreach ( $value as $key => $child ) {
			if ( self::is_sensitive_key( $key ) ) continue;
			$output[ $key ] = is_array( $child ) ? self::sanitize_recursive( $child ) : $child;
		}
		return $output;
	}

	private static function is_sensitive_key( $key ) {
		$key = strtolower( preg_replace( '/[^a-z0-9]+/i', '', (string) $key ) );
		if ( '' === $key ) return false;
		$exact = array(
			'nonce', 'password', 'passwd', 'cookie', 'cookies', 'authorization', 'authorizationheader',
			'apikey', 'licensekey', 'webhooksecret', 'clientsecret', 'dbpassword', 'databasepassword',
			'accesskey', 'secretkey', 'refreshtoken', 'accesstoken', 'sessioncookie', 'usersession',
			'formsubmission', 'formsubmissions', 'privateformsubmission', 'databasecredentials',
		);
		if ( in_array( $key, $exact, true ) ) return true;
		return (bool) preg_match( '/(?:password|authorization|apikey|licensekey|webhooksecret|clientsecret|dbcredential|databasecredential)/', $key );
	}
}
