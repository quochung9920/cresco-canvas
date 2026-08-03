<?php
/**
 * Development fallback autoloader.
 *
 * Release packages use Composer's optimized autoloader. This small fallback
 * keeps a source checkout recoverable before `composer install` is run.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {
	/**
	 * Register the Cresco Canvas PSR-4 fallback.
	 */
	public static function register() {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a Cresco Canvas class.
	 *
	 * @param string $class Fully qualified class name.
	 */
	private static function load( $class ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

