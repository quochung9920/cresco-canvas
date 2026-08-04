<?php
/**
 * Scoped design settings and conditional frontend assets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

use CrescoCanvas\Admin\EditorIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GlobalStyles {
	/**
	 * Register frontend and editor style hooks.
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'add_block_editor_tokens' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'add_canvas_body_class' ) );
	}

	/**
	 * Default scoped design settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'schemaVersion'         => 2,
			'primary'               => '#635bff',
			'text'                  => '#111827',
			'muted'                 => '#6b7280',
			'background'            => '#ffffff',
			'containerMax'          => 1440,
			'contentMax'            => 1200,
			'radius'                => 12,
			'fontFamily'            => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
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
		return array(
			'schemaVersion'         => 2,
			'primary'               => sanitize_hex_color( $input['primary'] ?? '' ) ?: $defaults['primary'],
			'text'                  => sanitize_hex_color( $input['text'] ?? '' ) ?: $defaults['text'],
			'muted'                 => sanitize_hex_color( $input['muted'] ?? '' ) ?: $defaults['muted'],
			'background'            => sanitize_hex_color( $input['background'] ?? '' ) ?: $defaults['background'],
			'containerMax'          => $container_max,
			'contentMax'            => $content_max,
			'radius'                => min( 80, max( 0, absint( $input['radius'] ?? $defaults['radius'] ) ) ),
			'fontFamily'            => self::sanitize_font_family( $input['fontFamily'] ?? $defaults['fontFamily'] ),
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
	 * Build visual rules that consume the validated design variables.
	 *
	 * @param string $selector Trusted internal scope selector.
	 * @return string
	 */
	public static function visual_css( $selector ) {
		return sprintf(
			'%1$s{background:var(--cc-background);color:var(--cc-text);font-family:var(--cc-font);}%1$s .wp-block-cresco-container a:not(.wp-block-button__link){color:var(--cc-primary);}%1$s .wp-block-cresco-container .wp-block-button__link:not(.has-background){background-color:var(--cc-primary);}%1$s .wp-block-cresco-container .wp-block-button__link{border-radius:var(--cc-radius);}',
			$selector
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
		wp_add_inline_style(
			'cresco-canvas-frontend',
			self::css( 'body.cresco-canvas-page' ) . self::visual_css( 'body.cresco-canvas-page' )
		);
	}

	/**
	 * Add tokens to the editor content through WordPress's public settings API.
	 *
	 * @param array<string, mixed>     $settings Editor settings.
	 * @param \WP_Block_Editor_Context $context  Current editor context.
	 * @return array<string, mixed>
	 */
	public function add_block_editor_tokens( $settings, $context ) {
		$post = isset( $context->post ) ? $context->post : null;

		if ( ! $post || 'page' !== $post->post_type ) {
			return $settings;
		}

		$settings['styles']   = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$settings['styles'][] = array(
			'css'            => self::css( '.editor-styles-wrapper' ) . self::visual_css( '.editor-styles-wrapper.cresco-canvas-editor-scope' ),
			'__unstableType' => 'theme',
		);

		return $settings;
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
		if ( rest_sanitize_boolean( get_post_meta( $post_id, EditorIntegration::ENABLED_META, true ) ) ) {
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
