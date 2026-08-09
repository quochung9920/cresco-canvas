<?php
/**
 * Commercial readiness services.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Commercial;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CommercialManager {
	const ONBOARDING_OPTION = 'cresco_canvas_onboarding_complete';
	const PREVIOUS_VERSION_OPTION = 'cresco_canvas_previous_version';

	/** Register commercial services. */
	public function register() {
		( new LicenseManager() )->register();
		( new UpdateManager() )->register();
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
		add_action( 'admin_notices', array( $this, 'onboarding_notice' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'upgrader_process_complete', array( $this, 'record_previous_version' ), 10, 2 );
	}

	/** Register system-status and onboarding endpoints. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/commercial/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => function () { return current_user_can( 'manage_options' ); },
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/commercial/onboarding',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete_onboarding' ),
				'permission_callback' => function () { return current_user_can( 'manage_options' ); },
			)
		);
	}

	/** Copy-safe diagnostic payload with no secrets. */
	public function status() {
		global $wp_version;
		return rest_ensure_response(
			array(
				'version'         => CRESCO_CANVAS_VERSION,
				'schemaVersion'   => CRESCO_CANVAS_SCHEMA_VERSION,
				'wordpress'       => (string) $wp_version,
				'php'             => PHP_VERSION,
				'updateChannel'   => UpdateManager::channel(),
				'license'         => LicenseManager::public_state(),
				'onboarding'      => (bool) get_option( self::ONBOARDING_OPTION, false ),
				'previousVersion' => sanitize_text_field( (string) get_option( self::PREVIOUS_VERSION_OPTION, '' ) ),
				'multisite'       => is_multisite(),
			)
		);
	}

	/** Add Cresco to WordPress Site Health Info. */
	public function debug_information( $info ) {
		$license = LicenseManager::public_state();
		$info['cresco-canvas'] = array(
			'label'  => __( 'Cresco Canvas', 'cresco-canvas' ),
			'fields' => array(
				'version' => array( 'label' => __( 'Version', 'cresco-canvas' ), 'value' => CRESCO_CANVAS_VERSION ),
				'schema' => array( 'label' => __( 'Schema', 'cresco-canvas' ), 'value' => (string) CRESCO_CANVAS_SCHEMA_VERSION ),
				'channel' => array( 'label' => __( 'Update channel', 'cresco-canvas' ), 'value' => UpdateManager::channel() ),
				'license' => array( 'label' => __( 'License status', 'cresco-canvas' ), 'value' => $license['status'] ),
				'previous' => array( 'label' => __( 'Previous version', 'cresco-canvas' ), 'value' => sanitize_text_field( (string) get_option( self::PREVIOUS_VERSION_OPTION, '' ) ) ),
			),
		);
		return $info;
	}

	/** Mark onboarding complete. */
	public function complete_onboarding() {
		update_option( self::ONBOARDING_OPTION, true, false );
		return rest_ensure_response( array( 'complete' => true ) );
	}

	/** Lightweight onboarding notice for administrators. */
	public function onboarding_notice() {
		if ( ! current_user_can( 'manage_options' ) || get_option( self::ONBOARDING_OPTION, false ) ) return;
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Cresco Canvas is ready.', 'cresco-canvas' ) . '</strong> ' . esc_html__( 'Open the Cresco editor, configure your design system, then review Site Health > Info for the copy-safe system status.', 'cresco-canvas' ) . '</p></div>';
	}

	/** Record the version being replaced so support can guide rollback. */
	public function record_previous_version( $upgrader, $options ) {
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] || empty( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) return;
		if ( ! in_array( plugin_basename( CRESCO_CANVAS_FILE ), $options['plugins'], true ) ) return;
		$stored = sanitize_text_field( (string) get_option( 'cresco_canvas_installed_version', '' ) );
		if ( '' !== $stored && CRESCO_CANVAS_VERSION !== $stored ) update_option( self::PREVIOUS_VERSION_OPTION, $stored, false );
		update_option( 'cresco_canvas_installed_version', CRESCO_CANVAS_VERSION, false );
	}
}
