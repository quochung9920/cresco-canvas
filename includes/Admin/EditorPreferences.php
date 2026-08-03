<?php
/**
 * Configurable and recoverable editor entry behavior.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Admin;

use CrescoCanvas\Styles\GlobalStyles;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorPreferences {
	const ENABLED_META          = '_cresco_canvas_enabled';
	const PAGE_PREFERENCE_META  = '_cresco_canvas_editor_preference';
	const USER_PREFERENCE_META  = 'cresco_canvas_last_editor';
	const QUERY_EDITOR          = 'cresco_editor';
	const QUERY_SAFE_MODE       = 'cresco_safe_mode';

	/**
	 * Register metadata, link filters, and preference recording.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'admin_init', array( $this, 'record_editor_choice' ) );
		add_filter( 'get_edit_post_link', array( $this, 'filter_edit_link' ), 20, 3 );
		add_filter( 'page_row_actions', array( $this, 'add_row_actions' ), 20, 2 );
	}

	/**
	 * Register Canvas page metadata with REST-safe schemas.
	 */
	public function register_meta() {
		$auth_callback = static function ( $allowed, $meta_key, $post_id ) {
			unset( $allowed, $meta_key );
			return current_user_can( 'edit_post', (int) $post_id );
		};

		register_post_meta(
			'page',
			self::ENABLED_META,
			array(
				'auth_callback'     => $auth_callback,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
			)
		);

		register_post_meta(
			'page',
			self::PAGE_PREFERENCE_META,
			array(
				'auth_callback'     => $auth_callback,
				'default'           => '',
				'sanitize_callback' => array( self::class, 'sanitize_editor' ),
				'show_in_rest'      => array(
					'schema' => array(
						'enum' => array( '', 'canvas', 'wordpress' ),
						'type' => 'string',
					),
				),
				'single'            => true,
				'type'              => 'string',
			)
		);
	}

	/**
	 * Sanitize an editor choice.
	 *
	 * @param mixed $value Candidate value.
	 * @return string
	 */
	public static function sanitize_editor( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'canvas', 'wordpress' ), true ) ? $value : '';
	}

	/**
	 * Replace the normal edit link only when the resolved preference is Canvas.
	 *
	 * @param string $link    Existing edit link.
	 * @param int    $post_id Post ID.
	 * @param string $context Link context.
	 * @return string
	 */
	public function filter_edit_link( $link, $post_id, $context ) {
		unset( $context );

		if ( ! is_admin() || wp_doing_ajax() || $this->is_native_bypass_request() ) {
			return $link;
		}

		$post = get_post( (int) $post_id );

		if ( ! $post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return $link;
		}

		return $this->should_open_canvas( (int) $post_id ) ? $this->get_canvas_url( (int) $post_id ) : $link;
	}

	/**
	 * Add explicit, non-ambiguous links to both editors.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @param WP_Post               $post    Page object.
	 * @return array<string, string>
	 */
	public function add_row_actions( $actions, WP_Post $post ) {
		if ( 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['cresco_canvas_editor'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->get_canvas_url( $post->ID ) ),
			esc_html__( 'Edit in Canvas', 'cresco-canvas' )
		);

		$actions['cresco_canvas_wordpress_editor'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->get_native_editor_url( $post->ID ) ),
			esc_html__( 'WordPress Editor', 'cresco-canvas' )
		);

		return $actions;
	}

	/**
	 * Persist an explicit editor choice only after nonce and capability checks.
	 */
	public function record_editor_choice() {
		$choice  = isset( $_GET[ self::QUERY_EDITOR ] ) ? self::sanitize_editor( wp_unslash( $_GET[ self::QUERY_EDITOR ] ) ) : '';
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;

		if ( 'wordpress' === $choice && isset( $_GET['post'] ) ) {
			$post_id = absint( wp_unslash( $_GET['post'] ) );
		}

		if ( ! $choice || ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$action = 'canvas' === $choice ? 'cresco_canvas_open_' . $post_id : 'cresco_canvas_native_' . $post_id;

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::USER_PREFERENCE_META, $choice );

		if ( 'canvas' === $choice ) {
			update_post_meta( $post_id, self::ENABLED_META, true );
		}
	}

	/**
	 * Resolve the editor selected for a Page.
	 *
	 * @param int $post_id Page ID.
	 * @param int $user_id Optional user ID.
	 * @return string `canvas` or `wordpress`.
	 */
	public function preferred_editor( $post_id, $user_id = 0 ) {
		$page_preference = self::sanitize_editor( get_post_meta( $post_id, self::PAGE_PREFERENCE_META, true ) );

		if ( $page_preference ) {
			return $page_preference;
		}

		$settings          = GlobalStyles::get_settings();
		$global_preference = self::sanitize_editor( $settings['editorPreference'] ?? '' );

		if ( $global_preference ) {
			return $global_preference;
		}

		$user_id         = $user_id ?: get_current_user_id();
		$user_preference = self::sanitize_editor( get_user_meta( $user_id, self::USER_PREFERENCE_META, true ) );

		return $user_preference ?: 'wordpress';
	}

	/**
	 * Determine whether the primary edit link should open Canvas.
	 *
	 * @param int $post_id Page ID.
	 * @return bool
	 */
	public function should_open_canvas( $post_id ) {
		return 'canvas' === $this->preferred_editor( $post_id );
	}

	/**
	 * Build a nonce-protected Canvas URL.
	 *
	 * @param int  $post_id  Page ID.
	 * @param bool $safe_mode Whether to request Safe Mode.
	 * @return string
	 */
	public function get_canvas_url( $post_id, $safe_mode = false ) {
		$args = array(
			'post_type'        => 'page',
			'page'             => 'cresco-canvas',
			'post_id'          => $post_id,
			self::QUERY_EDITOR => 'canvas',
		);

		if ( $safe_mode ) {
			$args[ self::QUERY_SAFE_MODE ] = '1';
		}

		$url    = add_query_arg( $args, admin_url( 'edit.php' ) );
		$action = $safe_mode ? 'cresco_canvas_safe_' . $post_id : 'cresco_canvas_open_' . $post_id;

		return wp_nonce_url( $url, $action );
	}

	/**
	 * Build an explicit native-editor recovery URL.
	 *
	 * @param int $post_id Page ID.
	 * @return string
	 */
	public function get_native_editor_url( $post_id ) {
		$url = add_query_arg(
			array(
				'post'             => $post_id,
				'action'           => 'edit',
				self::QUERY_EDITOR => 'wordpress',
			),
			admin_url( 'post.php' )
		);

		return wp_nonce_url( $url, 'cresco_canvas_native_' . $post_id );
	}

	/**
	 * Verify a Safe Mode request.
	 *
	 * @param int $post_id Page ID.
	 * @return bool
	 */
	public function is_safe_mode( $post_id ) {
		$requested = isset( $_GET[ self::QUERY_SAFE_MODE ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ self::QUERY_SAFE_MODE ] ) );
		$nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		return $requested
			&& current_user_can( 'edit_post', $post_id )
			&& (bool) wp_verify_nonce( $nonce, 'cresco_canvas_safe_' . $post_id );
	}

	/**
	 * Detect the read-only native-editor bypass parameter.
	 *
	 * @return bool
	 */
	private function is_native_bypass_request() {
		$value = isset( $_GET[ self::QUERY_EDITOR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_EDITOR ] ) ) : '';
		return 'wordpress' === $value;
	}
}
