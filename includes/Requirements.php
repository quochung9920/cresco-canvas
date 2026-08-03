<?php
/**
 * Runtime compatibility checks.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Requirements {
	/**
	 * Return compatibility errors for the current runtime.
	 *
	 * @return string[]
	 */
	public function errors() {
		$errors = array();

		if ( version_compare( PHP_VERSION, CRESCO_CANVAS_MINIMUM_PHP, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Cresco Canvas requires PHP %1$s or newer. This site is running PHP %2$s.', 'cresco-canvas' ),
				CRESCO_CANVAS_MINIMUM_PHP,
				PHP_VERSION
			);
		}

		$wordpress_version = get_bloginfo( 'version' );

		if ( version_compare( $wordpress_version, CRESCO_CANVAS_MINIMUM_WORDPRESS, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'Cresco Canvas requires WordPress %1$s or newer. This site is running WordPress %2$s.', 'cresco-canvas' ),
				CRESCO_CANVAS_MINIMUM_WORDPRESS,
				$wordpress_version
			);
		}

		return $errors;
	}

	/**
	 * Determine whether the runtime is compatible.
	 *
	 * @return bool
	 */
	public function is_compatible() {
		return array() === $this->errors();
	}

	/**
	 * Stop activation on an unsupported runtime.
	 */
	public function assert_compatible() {
		$errors = $this->errors();

		if ( array() === $errors ) {
			return;
		}

		deactivate_plugins( plugin_basename( CRESCO_CANVAS_FILE ) );

		wp_die(
			wp_kses_post( implode( '<br>', array_map( 'esc_html', $errors ) ) ),
			esc_html__( 'Cresco Canvas could not be activated', 'cresco-canvas' ),
			array( 'back_link' => true )
		);
	}

	/**
	 * Render a recoverable admin notice when requirements are unmet.
	 */
	public function render_admin_notice() {
		foreach ( $this->errors() as $error ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $error )
			);
		}
	}
}

