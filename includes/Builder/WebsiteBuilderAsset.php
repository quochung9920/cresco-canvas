<?php
/**
 * Canonical Website Builder asset path/version helpers.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderAsset {
	private function __construct() {}

	public static function absolute( $relative ) {
		return CRESCO_CANVAS_PATH . ltrim( (string) $relative, '/' );
	}

	public static function url( $relative ) {
		return CRESCO_CANVAS_URL . ltrim( (string) $relative, '/' );
	}

	public static function readable( $relative ) {
		return is_readable( self::absolute( $relative ) );
	}

	/** Content-addressed version prevents stale editor/runtime caches. */
	public static function version( $relative ) {
		$path = self::absolute( $relative );
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}

	public static function report( $relative ) {
		$path     = self::absolute( $relative );
		$readable = is_readable( $path );
		$hash     = $readable ? hash_file( 'sha256', $path ) : false;
		return array(
			'path'     => ltrim( (string) $relative, '/' ),
			'readable' => $readable,
			'bytes'    => $readable ? (int) filesize( $path ) : 0,
			'sha256'   => is_string( $hash ) ? $hash : '',
			'version'  => self::version( $relative ),
		);
	}

	public static function refresh_registered_script( $handle, $relative ) {
		$scripts = wp_scripts();
		if ( ! $scripts || ! isset( $scripts->registered[ $handle ] ) || ! self::readable( $relative ) ) return;
		$scripts->registered[ $handle ]->ver = self::version( $relative );
	}

	public static function refresh_registered_style( $handle, $relative ) {
		$styles = wp_styles();
		if ( ! $styles || ! isset( $styles->registered[ $handle ] ) || ! self::readable( $relative ) ) return;
		$styles->registered[ $handle ]->ver = self::version( $relative );
	}
}
