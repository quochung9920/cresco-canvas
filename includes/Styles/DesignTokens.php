<?php
/**
 * Structured, sanitized design-token registry.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesignTokens {
	const SCHEMA_VERSION = 2;

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_tokens' ), 20 );
		add_filter( 'block_editor_settings_all', array( $this, 'add_editor_tokens' ), 20, 2 );
	}

	public static function catalog( $settings ) {
		$colors = array(
			'primary'    => (string) $settings['primary'],
			'text'       => (string) $settings['text'],
			'muted'      => (string) $settings['muted'],
			'background' => (string) $settings['background'],
		);
		foreach ( (array) ( $settings['customColors'] ?? array() ) as $slug => $color ) {
			$colors[ 'custom-' . $slug ] = $color;
		}

		return array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'colors'        => $colors,
			'aliases'       => (array) ( $settings['aliases'] ?? array() ),
			'typography'    => array(
				'fontFamily' => (string) $settings['fontFamily'],
				'sizes'      => array(
					'xs' => '0.75rem', 'sm' => '0.875rem', 'base' => '1rem', 'lg' => '1.125rem',
					'xl' => '1.25rem', '2xl' => '1.5rem', '3xl' => '1.875rem', '4xl' => '2.25rem',
				),
			),
			'spacing'       => array(
				'0' => '0', '1' => '0.25rem', '2' => '0.5rem', '3' => '0.75rem', '4' => '1rem',
				'6' => '1.5rem', '8' => '2rem', '12' => '3rem', '16' => '4rem', '24' => '6rem',
			),
			'layout'        => array(
				'containerMax' => sprintf( '%dpx', (int) $settings['containerMax'] ),
				'contentMax'   => sprintf( '%dpx', (int) $settings['contentMax'] ),
			),
			'radius'        => array(
				'base' => sprintf( '%dpx', (int) $settings['radius'] ),
				'sm' => sprintf( '%dpx', max( 0, (int) $settings['radius'] - 4 ) ),
				'lg' => sprintf( '%dpx', min( 96, (int) $settings['radius'] + 8 ) ),
				'pill' => '9999px',
			),
			'shadows'       => array(
				'sm' => '0 1px 2px rgb(15 23 42 / 0.08)',
				'md' => '0 8px 24px rgb(15 23 42 / 0.12)',
				'lg' => '0 20px 48px rgb(15 23 42 / 0.16)',
			),
			'motion'        => array(
				'fast' => '120ms', 'normal' => '200ms', 'slow' => '360ms',
				'easing' => 'cubic-bezier(0.2, 0, 0, 1)',
			),
		);
	}

	public static function css_variables( $settings ) {
		$tokens = self::catalog( $settings );
		$pairs  = array(
			'--cc-primary' => $tokens['colors']['primary'],
			'--cc-text' => $tokens['colors']['text'],
			'--cc-muted' => $tokens['colors']['muted'],
			'--cc-background' => $tokens['colors']['background'],
			'--cc-font' => $tokens['typography']['fontFamily'],
			'--cc-font-xs' => $tokens['typography']['sizes']['xs'],
			'--cc-font-sm' => $tokens['typography']['sizes']['sm'],
			'--cc-font-base' => $tokens['typography']['sizes']['base'],
			'--cc-font-lg' => $tokens['typography']['sizes']['lg'],
			'--cc-font-xl' => $tokens['typography']['sizes']['xl'],
			'--cc-font-2xl' => $tokens['typography']['sizes']['2xl'],
			'--cc-font-3xl' => $tokens['typography']['sizes']['3xl'],
			'--cc-font-4xl' => $tokens['typography']['sizes']['4xl'],
			'--cc-space-1' => $tokens['spacing']['1'], '--cc-space-2' => $tokens['spacing']['2'],
			'--cc-space-3' => $tokens['spacing']['3'], '--cc-space-4' => $tokens['spacing']['4'],
			'--cc-space-6' => $tokens['spacing']['6'], '--cc-space-8' => $tokens['spacing']['8'],
			'--cc-space-12' => $tokens['spacing']['12'], '--cc-space-16' => $tokens['spacing']['16'],
			'--cc-space-24' => $tokens['spacing']['24'],
			'--cc-container-max' => $tokens['layout']['containerMax'],
			'--cc-content-max' => $tokens['layout']['contentMax'],
			'--cc-radius' => $tokens['radius']['base'], '--cc-radius-sm' => $tokens['radius']['sm'],
			'--cc-radius-lg' => $tokens['radius']['lg'], '--cc-radius-pill' => $tokens['radius']['pill'],
			'--cc-shadow-sm' => $tokens['shadows']['sm'], '--cc-shadow-md' => $tokens['shadows']['md'],
			'--cc-shadow-lg' => $tokens['shadows']['lg'],
			'--cc-motion-fast' => $tokens['motion']['fast'], '--cc-motion' => $tokens['motion']['normal'],
			'--cc-motion-slow' => $tokens['motion']['slow'], '--cc-easing' => $tokens['motion']['easing'],
		);

		foreach ( $tokens['colors'] as $slug => $value ) {
			if ( str_starts_with( $slug, 'custom-' ) ) {
				$pairs[ '--cc-color-' . substr( $slug, 7 ) ] = $value;
			}
		}
		foreach ( $tokens['aliases'] as $alias => $target ) {
			$target_name = str_starts_with( $target, 'custom-' ) ? '--cc-color-' . substr( $target, 7 ) : '--cc-' . $target;
			$pairs[ '--cc-alias-' . $alias ] = 'var(' . $target_name . ')';
		}

		$css = '';
		foreach ( $pairs as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}
		return $css;
	}

	public function enqueue_frontend_tokens() {
		$styles = new GlobalStyles();
		if ( ! $styles->is_canvas_page() || ! wp_style_is( 'cresco-canvas-frontend', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_style( 'cresco-canvas-frontend', 'body.cresco-canvas-page{' . self::css_variables( GlobalStyles::get_settings() ) . '}' );
	}

	public function add_editor_tokens( $settings, $context ) {
		$post = isset( $context->post ) ? $context->post : null;
		if ( ! $post || 'page' !== $post->post_type ) {
			return $settings;
		}
		$settings['styles']   = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$settings['styles'][] = array(
			'css' => '.editor-styles-wrapper{' . self::css_variables( GlobalStyles::get_settings() ) . '}',
			'__unstableType' => 'theme',
		);
		return $settings;
	}
}
