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
	const SCHEMA_VERSION = 4;

	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_tokens' ), 20 );
		add_filter( 'block_editor_settings_all', array( $this, 'add_editor_tokens' ), 20, 2 );
	}

	public static function catalog( $settings ) {
		$colors = array(
			'primary' => (string) $settings['primary'],
			'text' => (string) $settings['text'],
			'muted' => (string) $settings['muted'],
			'background' => (string) $settings['background'],
			'surface' => (string) $settings['background'],
		);
		foreach ( (array) ( $settings['customColors'] ?? array() ) as $slug => $color ) $colors[ 'custom-' . $slug ] = $color;
		$fluid = (array) ( $settings['fluidTokens'] ?? array() );
		$button = (array) ( $settings['button'] ?? array() );
		$space = array(
			'2xs' => $fluid['space2xs'], 'xs' => $fluid['spaceXs'], 'sm' => $fluid['spaceSm'],
			'md' => $fluid['spaceMd'], 'lg' => $fluid['spaceLg'], 'xl' => $fluid['spaceXl'],
			'2xl' => $fluid['space2xl'], '3xl' => $fluid['space3xl'],
			'sectionBlock' => $fluid['sectionBlock'], 'containerGutter' => $fluid['containerGutter'],
			'gridGap' => $fluid['gridGap'],
		);
		$shadow = array(
			'sm' => '0 1px 2px rgb(15 23 42 / 0.08)',
			'md' => '0 8px 24px rgb(15 23 42 / 0.12)',
			'lg' => '0 20px 48px rgb(15 23 42 / 0.16)',
		);
		$easing = 'cubic-bezier(0.2, 0, 0, 1)';
		$containers = array(
			'sm' => sprintf( '%dpx', min( 640, (int) $settings['contentMax'] ) ),
			'md' => sprintf( '%dpx', min( 960, (int) $settings['contentMax'] ) ),
			'lg' => sprintf( '%dpx', (int) $settings['containerMax'] ),
		);

		return array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'colors' => $colors,
			'aliases' => (array) ( $settings['aliases'] ?? array() ),
			'typography' => array(
				'fontFamily' => (string) $settings['fontFamily'],
				'sizes' => array(
					'xs' => $fluid['fontXs'], 'sm' => $fluid['fontSm'], 'base' => $fluid['fontBase'],
					'lg' => $fluid['fontLg'], 'xl' => $fluid['fontXl'],
					'h1' => $fluid['h1'], 'h2' => $fluid['h2'], 'h3' => $fluid['h3'],
					'h4' => $fluid['h4'], 'h5' => $fluid['h5'], 'h6' => $fluid['h6'],
				),
				'body' => array( 'fontFamily' => (string) $settings['fontFamily'], 'fontSize' => $fluid['fontBase'], 'fontWeight' => '400', 'lineHeight' => '1.65' ),
				'heading-md' => array( 'fontFamily' => (string) $settings['fontFamily'], 'fontSize' => $fluid['h3'], 'fontWeight' => '700', 'lineHeight' => '1.15' ),
				'heading-xl' => array( 'fontFamily' => (string) $settings['fontFamily'], 'fontSize' => $fluid['h1'], 'fontWeight' => '700', 'lineHeight' => '1.08' ),
				'display' => array( 'fontFamily' => (string) $settings['fontFamily'], 'fontSize' => $fluid['h1'], 'fontWeight' => '800', 'lineHeight' => '1.02' ),
			),
			// `spacing` is retained for existing documents; `space` is the canonical semantic family.
			'spacing' => $space,
			'space' => $space,
			'layout' => array(
				'containerMax' => sprintf( '%dpx', (int) $settings['containerMax'] ),
				'contentMax' => sprintf( '%dpx', (int) $settings['contentMax'] ),
			),
			'containers' => $containers,
			// Legacy radius tokens remain resolvable for existing documents even though
			// the generic Radius / Shape editor has been removed from Global Design.
			'radius' => array(
				'base' => sprintf( '%dpx', (int) $settings['radius'] ),
				'sm' => $fluid['radiusSm'], 'md' => $fluid['radiusMd'], 'lg' => $fluid['radiusLg'], 'pill' => '9999px',
			),
			'button' => array(
				'background' => (string) ( $button['background'] ?? $settings['primary'] ),
				'text' => (string) ( $button['text'] ?? '#ffffff' ),
				'hoverBackground' => (string) ( $button['hoverBackground'] ?? $settings['primary'] ),
				'hoverText' => (string) ( $button['hoverText'] ?? '#ffffff' ),
				'activeBackground' => (string) ( $button['activeBackground'] ?? ( $button['hoverBackground'] ?? $settings['primary'] ) ),
				'activeText' => (string) ( $button['activeText'] ?? ( $button['hoverText'] ?? '#ffffff' ) ),
				'borderColor' => (string) ( $button['borderColor'] ?? 'transparent' ),
				'borderWidth' => (string) ( $button['borderWidth'] ?? '0px' ),
				'radius' => (string) ( $button['radius'] ?? $fluid['radiusMd'] ),
				'height' => (string) ( $button['height'] ?? $fluid['controlHeight'] ),
				'paddingInline' => (string) ( $button['paddingInline'] ?? $fluid['buttonPadding'] ),
				'fontWeight' => (string) ( $button['fontWeight'] ?? '600' ),
			),
			'controls' => array( 'height' => $fluid['controlHeight'], 'buttonPadding' => $fluid['buttonPadding'] ),
			'breakpoints' => (array) $settings['breakpoints'],
			// Both names resolve during the migration; new contracts should prefer singular `shadow`.
			'shadows' => $shadow,
			'shadow' => $shadow,
			'motion' => array( 'fast' => '120ms', 'normal' => '200ms', 'slow' => '360ms', 'easing' => $easing ),
			'transitions' => array(
				'fast' => '120ms ' . $easing,
				'normal' => '200ms ' . $easing,
				'slow' => '360ms ' . $easing,
			),
			'zIndex' => array( 'dropdown' => '100', 'sticky' => '200', 'overlay' => '500', 'modal' => '1000', 'toast' => '1100' ),
		);
	}

	public static function css_variables( $settings ) {
		$t = self::catalog( $settings );
		$pairs = array(
			'--cc-primary' => $t['colors']['primary'], '--cc-text' => $t['colors']['text'],
			'--cc-muted' => $t['colors']['muted'], '--cc-background' => $t['colors']['background'], '--cc-surface' => $t['colors']['surface'],
			'--cc-font' => $t['typography']['fontFamily'],
			'--cc-font-xs' => $t['typography']['sizes']['xs'], '--cc-font-sm' => $t['typography']['sizes']['sm'],
			'--cc-font-base' => $t['typography']['sizes']['base'], '--cc-font-lg' => $t['typography']['sizes']['lg'],
			'--cc-font-xl' => $t['typography']['sizes']['xl'],
			'--cc-h1' => $t['typography']['sizes']['h1'], '--cc-h2' => $t['typography']['sizes']['h2'],
			'--cc-h3' => $t['typography']['sizes']['h3'], '--cc-h4' => $t['typography']['sizes']['h4'],
			'--cc-h5' => $t['typography']['sizes']['h5'], '--cc-h6' => $t['typography']['sizes']['h6'],
			'--cc-space-2xs' => $t['space']['2xs'], '--cc-space-xs' => $t['space']['xs'],
			'--cc-space-sm' => $t['space']['sm'], '--cc-space-md' => $t['space']['md'],
			'--cc-space-lg' => $t['space']['lg'], '--cc-space-xl' => $t['space']['xl'],
			'--cc-space-2xl' => $t['space']['2xl'], '--cc-space-3xl' => $t['space']['3xl'],
			'--cc-section-padding-block' => $t['space']['sectionBlock'],
			'--cc-container-gutter' => $t['space']['containerGutter'], '--cc-grid-gap' => $t['space']['gridGap'],
			'--cc-container-max' => $t['layout']['containerMax'], '--cc-content-max' => $t['layout']['contentMax'],
			'--cc-container-sm' => $t['containers']['sm'], '--cc-container-md' => $t['containers']['md'], '--cc-container-lg' => $t['containers']['lg'],
			'--cc-radius' => $t['radius']['base'], '--cc-radius-sm' => $t['radius']['sm'],
			'--cc-radius-md' => $t['radius']['md'], '--cc-radius-lg' => $t['radius']['lg'], '--cc-radius-pill' => $t['radius']['pill'],
			'--cc-button-bg' => $t['button']['background'], '--cc-button-text' => $t['button']['text'],
			'--cc-button-hover-bg' => $t['button']['hoverBackground'], '--cc-button-hover-text' => $t['button']['hoverText'],
			'--cc-button-active-bg' => $t['button']['activeBackground'], '--cc-button-active-text' => $t['button']['activeText'],
			'--cc-button-border' => $t['button']['borderColor'], '--cc-button-border-width' => $t['button']['borderWidth'],
			'--cc-button-radius' => $t['button']['radius'], '--cc-button-height' => $t['button']['height'],
			'--cc-button-padding-x' => $t['button']['paddingInline'], '--cc-button-font-weight' => $t['button']['fontWeight'],
			'--cc-control-height' => $t['controls']['height'], '--cc-button-padding' => $t['controls']['buttonPadding'],
			'--cc-breakpoint-mobile' => $t['breakpoints']['mobile'] . 'px', '--cc-breakpoint-tablet' => $t['breakpoints']['tablet'] . 'px',
			'--cc-breakpoint-laptop' => $t['breakpoints']['laptop'] . 'px', '--cc-breakpoint-desktop' => $t['breakpoints']['desktop'] . 'px',
			'--cc-breakpoint-wide' => $t['breakpoints']['wide'] . 'px',
			'--cc-shadow-sm' => $t['shadow']['sm'], '--cc-shadow-md' => $t['shadow']['md'], '--cc-shadow-lg' => $t['shadow']['lg'],
			'--cc-motion-fast' => $t['motion']['fast'], '--cc-motion' => $t['motion']['normal'], '--cc-motion-slow' => $t['motion']['slow'], '--cc-easing' => $t['motion']['easing'],
			'--cc-transition-fast' => $t['transitions']['fast'], '--cc-transition' => $t['transitions']['normal'], '--cc-transition-slow' => $t['transitions']['slow'],
			'--cc-z-dropdown' => $t['zIndex']['dropdown'], '--cc-z-sticky' => $t['zIndex']['sticky'], '--cc-z-overlay' => $t['zIndex']['overlay'], '--cc-z-modal' => $t['zIndex']['modal'], '--cc-z-toast' => $t['zIndex']['toast'],
		);
		foreach ( $t['colors'] as $slug => $value ) if ( str_starts_with( $slug, 'custom-' ) ) $pairs[ '--cc-color-' . substr( $slug, 7 ) ] = $value;
		foreach ( $t['aliases'] as $alias => $target ) {
			$target_name = str_starts_with( $target, 'custom-' ) ? '--cc-color-' . substr( $target, 7 ) : '--cc-' . $target;
			$pairs[ '--cc-alias-' . $alias ] = 'var(' . $target_name . ')';
		}
		$css = '';
		foreach ( $pairs as $name => $value ) $css .= $name . ':' . $value . ';';
		return $css;
	}

	public function enqueue_frontend_tokens() {
		$styles = new GlobalStyles();
		if ( ! $styles->is_canvas_page() || ! wp_style_is( 'cresco-canvas-frontend', 'enqueued' ) ) return;
		wp_add_inline_style( 'cresco-canvas-frontend', 'body.cresco-canvas-page{' . self::css_variables( GlobalStyles::get_settings() ) . '}' );
	}

	public function add_editor_tokens( $settings, $context ) {
		$post = isset( $context->post ) ? $context->post : null;
		if ( ! $post || 'page' !== $post->post_type ) return $settings;
		$settings['styles'] = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$settings['styles'][] = array( 'css' => '.editor-styles-wrapper{' . self::css_variables( GlobalStyles::get_settings() ) . '}', '__unstableType' => 'theme' );
		return $settings;
	}
}