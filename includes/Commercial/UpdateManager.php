<?php
/**
 * Commercial update channel integration.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Commercial;

use CrescoCanvas\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-update is the highest-consequence network path a plugin owns: whatever it
 * returns as `package` is downloaded and executed on the site. This class treats
 * the manifest as untrusted input for that reason.
 *
 * Three checks gate an update:
 *
 * 1. Transport. Manifest and package URLs must be https. A plaintext URL means an
 *    on-path attacker chooses the code that gets installed.
 * 2. Origin. The package host must match the manifest host, so a compromised or
 *    misconfigured manifest cannot redirect installation to an unrelated origin.
 * 3. Integrity. The archive's SHA-256 must match the digest the manifest declared.
 *    The release pipeline already produces `SHA256SUMS`; this is where that value
 *    finally gets checked.
 *
 * Signature verification remains future work, and its absence is why
 * `cresco_canvas_update_verify_package` exists: a distributor that has signing
 * infrastructure can add that check without patching this class. Until then an
 * update is only as trustworthy as the manifest origin's TLS.
 */
final class UpdateManager {
	const OPTION           = 'cresco_canvas_update_channel';
	const MANIFEST_CACHE   = 'cresco_canvas_update_manifest';
	const MANIFEST_TTL     = 6 * HOUR_IN_SECONDS;
	const MANIFEST_FAIL_TTL = 15 * MINUTE_IN_SECONDS;

	/** Register update hooks. */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_download' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register update settings route. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/commercial/update-channel',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function () { return rest_ensure_response( array( 'channel' => self::channel() ) ); },
					'permission_callback' => function () { return current_user_can( 'manage_options' ); },
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_channel' ),
					'permission_callback' => function () { return current_user_can( 'manage_options' ); },
					'args'                => array( 'channel' => array( 'type' => 'string', 'required' => true ) ),
				),
			)
		);
	}

	/** Save stable/beta channel. */
	public function save_channel( $request ) {
		$channel = sanitize_key( (string) $request->get_param( 'channel' ) );
		if ( ! in_array( $channel, array( 'stable', 'beta' ), true ) ) {
			return new \WP_Error( 'cresco_invalid_update_channel', __( 'Update channel must be stable or beta.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		update_option( self::OPTION, $channel, false );
		self::flush_manifest_cache();
		delete_site_transient( 'update_plugins' );
		return rest_ensure_response( array( 'channel' => $channel ) );
	}

	/** @return string */
	public static function channel() {
		$channel = sanitize_key( (string) get_option( self::OPTION, 'stable' ) );
		return in_array( $channel, array( 'stable', 'beta' ), true ) ? $channel : 'stable';
	}

	/** Drop the cached manifest so the next check refetches. */
	public static function flush_manifest_cache() {
		delete_site_transient( self::MANIFEST_CACHE );
	}

	/**
	 * Require https and return the normalized URL, or '' when unusable.
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	public static function secure_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( '' === $url ) return '';
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return 'https' === $scheme ? $url : '';
	}

	/**
	 * Whether two URLs share a host, compared case-insensitively.
	 *
	 * @param string $a First URL.
	 * @param string $b Second URL.
	 * @return bool
	 */
	public static function same_host( $a, $b ) {
		$host_a = strtolower( (string) wp_parse_url( (string) $a, PHP_URL_HOST ) );
		$host_b = strtolower( (string) wp_parse_url( (string) $b, PHP_URL_HOST ) );
		return '' !== $host_a && $host_a === $host_b;
	}

	/**
	 * Normalize a declared digest to lowercase hex, or '' when not a SHA-256.
	 *
	 * @param mixed $value Declared digest.
	 * @return string
	 */
	public static function normalize_digest( $value ) {
		$value = strtolower( trim( (string) $value ) );
		// Accept the `sha256:<hex>` form some manifests use.
		if ( 0 === strpos( $value, 'sha256:' ) ) $value = substr( $value, 7 );
		return preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	/** Inject an authenticated update from the configured manifest service. */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;
		$manifest = $this->manifest();
		if ( ! $manifest || empty( $manifest['version'] ) || version_compare( CRESCO_CANVAS_VERSION, $manifest['version'], '>=' ) ) return $transient;

		$package = self::secure_url( $manifest['packageUrl'] ?? '' );
		if ( '' === $package ) return $transient;

		$plugin = plugin_basename( CRESCO_CANVAS_FILE );
		$item = (object) array(
			'id'           => 'cresco-canvas',
			'slug'         => 'cresco-canvas',
			'plugin'       => $plugin,
			'new_version'  => sanitize_text_field( (string) $manifest['version'] ),
			'url'          => self::secure_url( $manifest['detailsUrl'] ?? '' ),
			'package'      => $package,
			'tested'       => sanitize_text_field( (string) ( $manifest['tested'] ?? '' ) ),
			'requires_php' => sanitize_text_field( (string) ( $manifest['requiresPhp'] ?? CRESCO_CANVAS_MINIMUM_PHP ) ),
		);
		$transient->response[ $plugin ] = $item;
		return $transient;
	}

	/**
	 * Download our own package and refuse to install one that fails integrity.
	 *
	 * WordPress calls this before fetching an update archive. Returning a path
	 * supplies the file; returning a WP_Error aborts the install with that message.
	 * Any other package passes straight through untouched.
	 *
	 * @param bool|string|\WP_Error $reply    Short-circuit value from an earlier filter.
	 * @param string                $package  Package URL core is about to download.
	 * @param object                $upgrader Upgrader instance.
	 * @return bool|string|\WP_Error
	 */
	public function verify_download( $reply, $package, $upgrader = null ) {
		unset( $upgrader );
		if ( false !== $reply ) return $reply;

		$manifest = $this->manifest();
		if ( ! $manifest ) return $reply;

		$expected_package = self::secure_url( $manifest['packageUrl'] ?? '' );
		if ( '' === $expected_package || (string) $package !== $expected_package ) return $reply;

		$digest = self::normalize_digest( $manifest['sha256'] ?? ( $manifest['checksum'] ?? '' ) );
		if ( '' === $digest ) {
			Logger::warning( 'Update refused: manifest declared no SHA-256 for the package.' );
			return new \WP_Error(
				'cresco_update_no_digest',
				__( 'Cresco Canvas refused this update because the update manifest did not declare a SHA-256 checksum for the package.', 'cresco-canvas' )
			);
		}

		if ( ! function_exists( 'download_url' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = download_url( $package );
		if ( is_wp_error( $file ) ) return $file;

		$actual = hash_file( 'sha256', $file );
		if ( ! hash_equals( $digest, (string) $actual ) ) {
			wp_delete_file( $file );
			Logger::error( 'Update refused: package checksum mismatch.', array( 'expected' => $digest, 'actual' => (string) $actual ) );
			return new \WP_Error(
				'cresco_update_checksum',
				__( 'Cresco Canvas refused this update because the downloaded package did not match the checksum published for it. The download may be corrupt or tampered with.', 'cresco-canvas' )
			);
		}

		/**
		 * Filter the verdict on a package that already passed checksum verification.
		 *
		 * Return a WP_Error to refuse the install. This is where a distributor with
		 * signing infrastructure adds signature verification without patching core
		 * plugin code.
		 *
		 * @param true|\WP_Error $verdict  True when the package is acceptable.
		 * @param string         $file     Local path to the downloaded archive.
		 * @param array          $manifest Manifest the package was described by.
		 */
		$verdict = apply_filters( 'cresco_canvas_update_verify_package', true, $file, $manifest );
		if ( is_wp_error( $verdict ) ) {
			wp_delete_file( $file );
			return $verdict;
		}

		Logger::info( 'Update package verified.', array( 'version' => (string) ( $manifest['version'] ?? '' ) ) );
		return $file;
	}

	/** Provide plugin-information modal data from the same manifest. */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'cresco-canvas' !== $args->slug ) return $result;
		$manifest = $this->manifest();
		if ( ! $manifest ) return $result;
		return (object) array(
			'name'          => 'Cresco Canvas',
			'slug'          => 'cresco-canvas',
			'version'       => sanitize_text_field( (string) ( $manifest['version'] ?? CRESCO_CANVAS_VERSION ) ),
			'homepage'      => self::secure_url( $manifest['detailsUrl'] ?? '' ),
			'download_link' => self::secure_url( $manifest['packageUrl'] ?? '' ),
			'sections'      => array( 'changelog' => wp_kses_post( (string) ( $manifest['changelog'] ?? '' ) ) ),
		);
	}

	/**
	 * Validate a decoded manifest, or return null.
	 *
	 * Kept separate from transport so it can be exercised directly.
	 *
	 * @param mixed  $data         Decoded manifest body.
	 * @param string $manifest_url URL the manifest came from, for origin pinning.
	 * @return array|null
	 */
	public static function validate_manifest( $data, $manifest_url ) {
		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['packageUrl'] ) ) return null;

		$package = self::secure_url( $data['packageUrl'] );
		if ( '' === $package ) {
			Logger::warning( 'Update manifest rejected: package URL is not https.' );
			return null;
		}
		if ( ! self::same_host( $package, $manifest_url ) ) {
			Logger::warning( 'Update manifest rejected: package host does not match the manifest host.' );
			return null;
		}

		$data['packageUrl'] = $package;
		return $data;
	}

	/**
	 * Fetch and validate the provider manifest.
	 *
	 * Cached, because this runs on `pre_set_site_transient_update_plugins`, which
	 * fires often. Without a cache every admin page load could block on a remote
	 * request, and an unreachable endpoint would stall the admin for the full
	 * timeout on each one. Failures are cached briefly too, for the same reason.
	 *
	 * @return array|null
	 */
	private function manifest() {
		$cached = get_site_transient( self::MANIFEST_CACHE );
		if ( is_array( $cached ) ) return $cached;
		if ( 'none' === $cached ) return null;

		$url = self::secure_url( apply_filters( 'cresco_canvas_update_manifest_url', '' ) );
		if ( '' === $url ) return null;

		$state   = LicenseManager::state();
		$headers = array( 'Accept' => 'application/json' );
		if ( ! empty( $state['token'] ) ) $headers['Authorization'] = 'Bearer ' . sanitize_text_field( (string) $state['token'] );

		$response = wp_safe_remote_get(
			add_query_arg( array( 'channel' => self::channel(), 'version' => CRESCO_CANVAS_VERSION ), $url ),
			array( 'timeout' => 10, 'headers' => $headers )
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::MANIFEST_CACHE, 'none', self::MANIFEST_FAIL_TTL );
			return null;
		}

		$manifest = self::validate_manifest( json_decode( wp_remote_retrieve_body( $response ), true ), $url );
		if ( null === $manifest ) {
			set_site_transient( self::MANIFEST_CACHE, 'none', self::MANIFEST_FAIL_TTL );
			return null;
		}

		set_site_transient( self::MANIFEST_CACHE, $manifest, self::MANIFEST_TTL );
		return $manifest;
	}
}
