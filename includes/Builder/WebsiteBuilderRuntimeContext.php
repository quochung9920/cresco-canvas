<?php
/**
 * Immutable runtime context for Website Builder editor requests.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Theme\ThemeBuilder;
use CrescoCanvas\Theme\ThemeSessionBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderRuntimeContext {
	private $screen;
	private $post_id;
	private $post_type;
	private $document_type;
	private $isolation_mode;
	private $debug;
	private $architecture_debug;

	private function __construct( $screen, $post_id, $post_type, $document_type, $isolation_mode, $debug, $architecture_debug ) {
		$this->screen             = (string) $screen;
		$this->post_id            = (int) $post_id;
		$this->post_type          = (string) $post_type;
		$this->document_type      = (string) $document_type;
		$this->isolation_mode     = (string) $isolation_mode;
		$this->debug              = (bool) $debug;
		$this->architecture_debug = (bool) $architecture_debug;
	}

	public static function from_request() {
		$screen  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor routing.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor routing.
		if ( ! in_array( $screen, array( VisualEditor::PAGE_SLUG, ThemeSessionBridge::PAGE_SLUG ), true ) ) return null;
		return self::for_document( $post_id, $screen );
	}

	public static function for_document( $post_id, $screen = '' ) {
		$post_id = absint( $post_id );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post_id ) ) return null;

		$post_type = (string) $post->post_type;
		if ( 'page' === $post_type ) {
			$screen = $screen ?: VisualEditor::PAGE_SLUG;
			if ( VisualEditor::PAGE_SLUG !== $screen ) return null;
			$document_type = 'page';
		} elseif ( ThemeBuilder::POST_TYPE === $post_type ) {
			$screen = $screen ?: ThemeSessionBridge::PAGE_SLUG;
			if ( ThemeSessionBridge::PAGE_SLUG !== $screen ) return null;
			$document_type = sanitize_key( (string) get_post_meta( $post_id, ThemeBuilder::META_TYPE, true ) );
			if ( '' === $document_type ) $document_type = 'theme';
		} else return null;

		$safe_mode = isset( $_GET['cresco-safe-mode'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cresco-safe-mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only diagnostics flag.
		$requested = isset( $_GET['cresco-module'] ) ? sanitize_key( wp_unslash( $_GET['cresco-module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only diagnostics flag.
		$privileged = current_user_can( 'manage_options' );
		$allowed = $privileged
			? array( 'core', 'controls', 'professional-ux', 'architecture', 'all' )
			: array( 'core', 'controls', 'professional-ux' );
		$mode = $safe_mode ? 'core' : ( in_array( $requested, $allowed, true ) ? $requested : 'normal' );
		$debug = $privileged && isset( $_GET['cresco-debug'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cresco-debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Privileged read-only diagnostics flag.
		$arch  = $privileged && isset( $_GET['cresco-architecture-debug'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cresco-architecture-debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Privileged read-only diagnostics flag.

		return new self( $screen, $post_id, $post_type, $document_type, $mode, $debug, $arch );
	}

	public function screen() { return $this->screen; }
	public function post_id() { return $this->post_id; }
	public function post_type() { return $this->post_type; }
	public function document_type() { return $this->document_type; }
	public function isolation_mode() { return $this->isolation_mode; }
	public function debug_enabled() { return $this->debug; }
	public function architecture_debug_enabled() { return $this->architecture_debug; }
	public function is_page_editor() { return VisualEditor::PAGE_SLUG === $this->screen; }
	public function is_theme_editor() { return ThemeSessionBridge::PAGE_SLUG === $this->screen; }

	public function to_array() {
		return array(
			'screen'            => $this->screen,
			'postId'            => $this->post_id,
			'postType'          => $this->post_type,
			'documentType'      => $this->document_type,
			'isolationMode'     => $this->isolation_mode,
			'debug'             => $this->debug,
			'architectureDebug' => $this->architecture_debug,
		);
	}
}
