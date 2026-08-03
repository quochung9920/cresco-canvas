<?php
/**
 * Scoped design settings and conditional frontend assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

use CrescoCanvas\Admin\EditorPreferences;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GlobalStyles {
	/**
	 * Register frontend and editor style hooks.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_tokens' ) );
		add_filter( 'body_class', array( $this, 'add_canvas_body_class' ) );
	}

	/**
	 * Default settings, including recoverable editor behavior.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'schemaVersion'         => 1,
			'primary'               => '#635bff',
			'text'                  => '#111827',
			'muted'                 => '#6b7280',
			'background'            => '#ffffff',
			'containerMax'          => 1440,
			'contentMax'            => 1200,
			'radius'                => 12,
			'fontFamily'            => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'editorPreference'      => 'remember',
			'removeDataOnUninstall' => false,
		);
	}

	/**
	 * Get normalized settings without rewriting stored data.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		return self::sanitize_settings( (array) get_option( 'cresco_canvas_settings', array() ) );
	}

	/**
	 * Validate and normalize settings.
	 *
	 * @param array<string, mixed> $input Candidate settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ) {
		$defaults             = self::defaults();
		$container_max        = min( 2560, max( 960, absint( $input['containerMax'] ?? $defaults['containerMax'] ) ) );
		$content_max          = min( $container_max, max( 640, absint( $input['contentMax'] ?? $defaults['contentMax'] ) ) );
		$editor_preference    = sanitize_key( (string) ( $input['editorPreference'] ?? $defaults['editorPreference'] ) );
		$allowed_preferences  = array( 'canvas', 'wordpress', 'remember' );

		if ( ! in_array( $editor_preference, $allowed_preferences, true ) ) {
			$editor_preference = $defaults['editorPreference'];
		}

		return array(
			'schemaVersion'         => 1,
			'primary'               => sanitize_hex_color( $input['primary'] ?? '' ) ?: $defaults['primary'],
			'text'                  => sanitize_hex_color( $input['text'] ?? '' ) ?: $defaults['text'],
			'muted'                 => sanitize_hex_color( $input['muted'] ?? '' ) ?: $defaults['muted'],
			'background'            => sanitize_hex_color( $input['background'] ?? '' ) ?: $defaults['background'],
			'containerMax'          => $container_max,
			'contentMax'            => $content_max,
			'radius'                => min( 80, max( 0, absint( $input['radius'] ?? $defaults['radius'] ) ) ),
			'fontFamily'            => self::sanitize_font_family( $input['fontFamily'] ?? $defaults['fontFamily'] ),
			'editorPreference'      => $editor_preference,
			'removeDataOnUninstall' => rest_sanitize_boolean( $input['removeDataOnUninstall'] ?? false ),
		);
	}

	/**
	 * Build design variables scoped to a known Canvas selector.
	 *
	 * @param string $selector Trusted internal selector.
	 * @return string
	 */
	public static function css( $selector = '.cresco-canvas-scope' ) {
		$settings = self::get_settings();

		return sprintf(
			'%1$s{--cc-primary:%2$s;--cc-text:%3$s;--cc-muted:%4$s;--cc-background:%5$s;--cc-container-max:%6$dpx;--cc-content-max:%7$dpx;--cc-radius:%8$dpx;--cc-font:%9$s;}',
			$selector,
			$settings['primary'],
			$settings['text'],
			$settings['muted'],
			$settings['background'],
			(int) $settings['containerMax'],
			(int) $settings['contentMax'],
			(int) $settings['radius'],
			$settings['fontFamily']
		);
	}

	/**
	 * Load public CSS only on a Page that uses Canvas.
	 */
	public function enqueue_frontend_styles() {
		if ( ! $this->is_canvas_page() ) {
			return;
		}

		wp_enqueue_style(
			'cresco-canvas-frontend',
			CRESCO_CANVAS_URL . 'assets/css/frontend.css',
			array(),
			CRESCO_CANVAS_VERSION
		);
		wp_add_inline_style( 'cresco-canvas-frontend', self::css( 'body.cresco-canvas-page' ) );
	}

	/**
	 * Add tokens to a native block editor only for Canvas-enabled Pages.
	 */
	public function enqueue_block_editor_tokens() {
		$post_id = get_the_ID();

		if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! $this->post_uses_canvas( $post_id ) ) {
			return;
		}

		wp_register_style( 'cresco-canvas-editor-tokens', false, array(), CRESCO_CANVAS_VERSION );
		wp_enqueue_style( 'cresco-canvas-editor-tokens' );
		wp_add_inline_style( 'cresco-canvas-editor-tokens', self::css( '.editor-styles-wrapper' ) );
	}

	/**
	 * Add a stable body scope only to Canvas Pages.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function add_canvas_body_class( $classes ) {
		if ( $this->is_canvas_page() ) {
			$classes[] = 'cresco-canvas-page';
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Determine whether the queried frontend Page uses Canvas.
	 *
	 * @return bool
	 */
	public function is_canvas_page() {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}

		$post_id = get_queried_object_id();
		return $post_id > 0 && $this->post_uses_canvas( $post_id );
	}

	/**
	 * Detect both migrated metadata and legacy Canvas blocks.
	 *
	 * @param int $post_id Page ID.
	 * @return bool
	 */
	public function post_uses_canvas( $post_id ) {
		if ( rest_sanitize_boolean( get_post_meta( $post_id, EditorPreferences::ENABLED_META, true ) ) ) {
			return true;
		}

		$post = get_post( $post_id );
		return $post && has_block( 'cresco/container', $post->post_content );
	}

	/**
	 * Restrict the font stack to a safe CSS token grammar.
	 *
	 * @param mixed $value Candidate font stack.
	 * @return string
	 */
	private static function sanitize_font_family( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		return preg_match( '/^[a-zA-Z0-9 _,-.\"\'()]+$/', $value ) ? $value : self::defaults()['fontFamily'];
	}
}

