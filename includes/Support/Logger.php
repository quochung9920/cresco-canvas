<?php
/**
 * Privacy-safe diagnostic logging.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin previously had no logging at all, so a support report of "the editor
 * froze" left nothing to read. This provides a single place to record what
 * happened, with two constraints that make it safe to ship enabled:
 *
 * - Nothing is written unless `WP_DEBUG` is on, so production sites pay nothing
 *   and no log file grows unattended.
 * - Context values are scrubbed before they are written. Log lines end up in
 *   pasted support tickets, so a secret reaching one is a real disclosure, not a
 *   hypothetical one.
 *
 * Every entry is also emitted as `cresco_canvas_log`, which is not gated by
 * `WP_DEBUG`. Monitoring and APM integrations listen there; the action fires
 * whether or not anything is written to disk.
 */
final class Logger {
	const ACTION = 'cresco_canvas_log';

	const LEVELS = array( 'debug', 'info', 'warning', 'error' );

	/** Keys whose values are replaced before a line is written. */
	const REDACT = array(
		'token', 'password', 'passwd', 'nonce', 'cookie', 'secret', 'authorization',
		'apikey', 'api_key', 'licensekey', 'license_key', 'accesstoken', 'access_token',
		'refreshtoken', 'refresh_token', 'clientsecret', 'client_secret', 'credential',
	);

	/**
	 * Record a diagnostic entry.
	 *
	 * @param string $level   One of debug, info, warning, error.
	 * @param string $message Human-readable summary. Must not embed user data.
	 * @param array  $context Structured detail; scrubbed before writing.
	 */
	public static function log( $level, $message, $context = array() ) {
		$level   = in_array( $level, self::LEVELS, true ) ? $level : 'info';
		$message = (string) $message;
		$context = self::scrub( (array) $context );

		/**
		 * Fires for every Cresco diagnostic entry, regardless of WP_DEBUG.
		 *
		 * Monitoring integrations should listen here rather than parsing the log.
		 *
		 * @param string $level   Severity.
		 * @param string $message Summary.
		 * @param array  $context Scrubbed structured detail.
		 */
		do_action( self::ACTION, $level, $message, $context );

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) return;

		$line = '[cresco-canvas] ' . strtoupper( $level ) . ': ' . $message;
		if ( $context ) {
			$encoded = wp_json_encode( $context );
			if ( is_string( $encoded ) ) $line .= ' ' . $encoded;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- WP_DEBUG-gated diagnostic channel.
		error_log( $line );
	}

	/**
	 * Replace secret-bearing values and bound the size of what gets written.
	 *
	 * @param array $context Raw context.
	 * @param int   $depth   Recursion guard.
	 * @return array
	 */
	private static function scrub( $context, $depth = 0 ) {
		if ( $depth > 4 ) return array();
		$out = array();
		foreach ( $context as $key => $value ) {
			if ( self::is_secret( $key ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			if ( is_array( $value ) ) {
				$out[ $key ] = self::scrub( $value, $depth + 1 );
				continue;
			}
			if ( is_scalar( $value ) || null === $value ) {
				$value = is_string( $value ) && strlen( $value ) > 500 ? substr( $value, 0, 500 ) . '…' : $value;
				$out[ $key ] = $value;
				continue;
			}
			$out[ $key ] = '[' . gettype( $value ) . ']';
		}
		return $out;
	}

	/**
	 * Whether a context key names something that must never be written.
	 *
	 * @param string $key Context key.
	 * @return bool
	 */
	private static function is_secret( $key ) {
		$normalized = strtolower( preg_replace( '/[^a-z0-9]+/i', '', (string) $key ) );
		if ( '' === $normalized ) return false;
		foreach ( self::REDACT as $needle ) {
			if ( false !== strpos( $normalized, str_replace( '_', '', $needle ) ) ) return true;
		}
		return false;
	}

	/**
	 * Record a debug entry.
	 *
	 * @param string $message Summary.
	 * @param array  $context Structured detail.
	 */
	public static function debug( $message, $context = array() ) { self::log( 'debug', $message, $context ); }

	/**
	 * Record an informational entry.
	 *
	 * @param string $message Summary.
	 * @param array  $context Structured detail.
	 */
	public static function info( $message, $context = array() ) { self::log( 'info', $message, $context ); }

	/**
	 * Record a warning.
	 *
	 * @param string $message Summary.
	 * @param array  $context Structured detail.
	 */
	public static function warning( $message, $context = array() ) { self::log( 'warning', $message, $context ); }

	/**
	 * Record an error.
	 *
	 * @param string $message Summary.
	 * @param array  $context Structured detail.
	 */
	public static function error( $message, $context = array() ) { self::log( 'error', $message, $context ); }
}
