<?php
/**
 * Cache access that prefers a persistent object cache.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin previously reached for transients everywhere and never called
 * `wp_cache_*`. On a site running Redis or Memcached, transients are backed by
 * that cache anyway, so the difference is small. On a site without one, every
 * transient write is a row in `wp_options`, and a dynamic query endpoint under
 * traffic turns that table into the bottleneck it was never meant to be.
 *
 * This routes through the object cache when the site has a persistent one and
 * falls back to transients otherwise, so both deployments get the behaviour that
 * suits them without callers having to know which is in play.
 */
final class ObjectCache {
	const GROUP = 'cresco_canvas';

	/** @var array<string,mixed> Request-scoped memo, in front of both backends. */
	private static $memo = array();

	/**
	 * Whether a persistent object cache is available.
	 *
	 * @return bool
	 */
	public static function persistent() {
		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}

	/**
	 * Read a value.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Returned when the key is absent.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$key = (string) $key;
		if ( array_key_exists( $key, self::$memo ) ) return self::$memo[ $key ];

		if ( self::persistent() ) {
			$found = false;
			$value = wp_cache_get( $key, self::GROUP, false, $found );
			$value = $found ? $value : $default;
		} else {
			$value = get_transient( self::prefixed( $key ) );
			if ( false === $value ) $value = $default;
		}

		self::$memo[ $key ] = $value;
		return $value;
	}

	/**
	 * Write a value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Lifetime in seconds.
	 * @return bool
	 */
	public static function set( $key, $value, $ttl = 60 ) {
		$key = (string) $key;
		$ttl = max( 1, absint( $ttl ) );
		self::$memo[ $key ] = $value;

		return self::persistent()
			? (bool) wp_cache_set( $key, $value, self::GROUP, $ttl )
			: (bool) set_transient( self::prefixed( $key ), $value, $ttl );
	}

	/**
	 * Remove a value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public static function delete( $key ) {
		$key = (string) $key;
		unset( self::$memo[ $key ] );

		return self::persistent()
			? (bool) wp_cache_delete( $key, self::GROUP )
			: (bool) delete_transient( self::prefixed( $key ) );
	}

	/**
	 * Drop the request-scoped memo.
	 *
	 * Only useful in tests and long-running CLI processes, where one request's
	 * assumption that data cannot change underneath it stops holding.
	 */
	public static function flush_memo() {
		self::$memo = array();
	}

	/**
	 * Transient keys are capped at 172 characters and share a global namespace.
	 *
	 * @param string $key Cache key.
	 * @return string
	 */
	private static function prefixed( $key ) {
		$key = 'cc_' . $key;
		return strlen( $key ) > 160 ? 'cc_' . hash( 'sha256', $key ) : $key;
	}
}
