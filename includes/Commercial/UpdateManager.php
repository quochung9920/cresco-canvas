<?php
/**
 * Commercial update channel integration.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Commercial;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UpdateManager {
	const OPTION = 'cresco_canvas_update_channel';

	/** Register update hooks. */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
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
		delete_site_transient( 'update_plugins' );
		return rest_ensure_response( array( 'channel' => $channel ) );
	}

	/** @return string */
	public static function channel() {
		$channel = sanitize_key( (string) get_option( self::OPTION, 'stable' ) );
		return in_array( $channel, array( 'stable', 'beta' ), true ) ? $channel : 'stable';
	}

	/** Inject an authenticated update from the configured manifest service. */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;
		$manifest = $this->manifest();
		if ( ! $manifest || empty( $manifest['version'] ) || version_compare( CRESCO_CANVAS_VERSION, $manifest['version'], '>=' ) ) return $transient;

		$plugin = plugin_basename( CRESCO_CANVAS_FILE );
		$item = (object) array(
			'id'           => 'cresco-canvas',
			'slug'         => 'cresco-canvas',
			'plugin'       => $plugin,
			'new_version'  => sanitize_text_field( (string) $manifest['version'] ),
			'url'          => esc_url_raw( (string) ( $manifest['detailsUrl'] ?? '' ) ),
			'package'      => esc_url_raw( (string) ( $manifest['packageUrl'] ?? '' ) ),
			'tested'       => sanitize_text_field( (string) ( $manifest['tested'] ?? '' ) ),
			'requires_php' => sanitize_text_field( (string) ( $manifest['requiresPhp'] ?? CRESCO_CANVAS_MINIMUM_PHP ) ),
		);
		if ( '' !== $item->package ) $transient->response[ $plugin ] = $item;
		return $transient;
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
			'homepage'      => esc_url_raw( (string) ( $manifest['detailsUrl'] ?? '' ) ),
			'download_link' => esc_url_raw( (string) ( $manifest['packageUrl'] ?? '' ) ),
			'sections'      => array( 'changelog' => wp_kses_post( (string) ( $manifest['changelog'] ?? '' ) ) ),
		);
	}

	/** Fetch and validate the provider manifest. */
	private function manifest() {
		$url = esc_url_raw( (string) apply_filters( 'cresco_canvas_update_manifest_url', '' ) );
		if ( '' === $url ) return null;
		$state = LicenseManager::state();
		$headers = array( 'Accept' => 'application/json' );
		if ( ! empty( $state['token'] ) ) $headers['Authorization'] = 'Bearer ' . sanitize_text_field( (string) $state['token'] );
		$response = wp_safe_remote_get( add_query_arg( array( 'channel' => self::channel(), 'version' => CRESCO_CANVAS_VERSION ), $url ), array( 'timeout' => 10, 'headers' => $headers ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return null;
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['packageUrl'] ) ) return null;
		return $data;
	}
}
