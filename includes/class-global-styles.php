<?php

namespace CrescoCanvas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Global_Styles {
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ) );
		add_action( 'enqueue_block_assets', array( $this, 'block_styles' ) );
	}

	public static function defaults(): array {
		return array(
			'primary'       => '#635bff',
			'text'          => '#111827',
			'muted'         => '#6b7280',
			'background'    => '#ffffff',
			'containerMax'  => 1440,
			'contentMax'    => 1200,
			'radius'        => 12,
			'fontFamily'    => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
		);
	}

	public static function get_settings(): array {
		return wp_parse_args( (array) get_option( 'cresco_canvas_settings', array() ), self::defaults() );
	}

	public static function sanitize_settings( array $input ): array {
		$defaults = self::defaults();
		return array(
			'primary'      => sanitize_hex_color( $input['primary'] ?? '' ) ?: $defaults['primary'],
			'text'         => sanitize_hex_color( $input['text'] ?? '' ) ?: $defaults['text'],
			'muted'        => sanitize_hex_color( $input['muted'] ?? '' ) ?: $defaults['muted'],
			'background'   => sanitize_hex_color( $input['background'] ?? '' ) ?: $defaults['background'],
			'containerMax' => min( 2560, max( 960, absint( $input['containerMax'] ?? $defaults['containerMax'] ) ) ),
			'contentMax'   => min( 1920, max( 640, absint( $input['contentMax'] ?? $defaults['contentMax'] ) ) ),
			'radius'       => min( 80, max( 0, absint( $input['radius'] ?? $defaults['radius'] ) ) ),
			'fontFamily'   => sanitize_text_field( (string) ( $input['fontFamily'] ?? $defaults['fontFamily'] ) ),
		);
	}

	public static function css(): string {
		$s = self::get_settings();
		return sprintf(
			':root{--cc-primary:%1$s;--cc-text:%2$s;--cc-muted:%3$s;--cc-background:%4$s;--cc-container-max:%5$dpx;--cc-content-max:%6$dpx;--cc-radius:%7$dpx;--cc-font:%8$s;}',
			esc_attr( $s['primary'] ),
			esc_attr( $s['text'] ),
			esc_attr( $s['muted'] ),
			esc_attr( $s['background'] ),
			(int) $s['containerMax'],
			(int) $s['contentMax'],
			(int) $s['radius'],
			esc_attr( $s['fontFamily'] )
		);
	}

	public function frontend_styles(): void {
		wp_enqueue_style( 'cresco-canvas-frontend', CRESCO_CANVAS_URL . 'assets/css/frontend.css', array(), CRESCO_CANVAS_VERSION );
		wp_add_inline_style( 'cresco-canvas-frontend', self::css() );
	}

	public function block_styles(): void {
		wp_add_inline_style( 'wp-block-library', self::css() );
	}
}
