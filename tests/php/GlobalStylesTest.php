<?php
/**
 * Global style tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Styles\GlobalStyles;
use PHPUnit\Framework\TestCase;

final class GlobalStylesTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_options'] = array();
	}

	public function test_defaults_are_scoped_and_do_not_target_root_or_unqualified_body(): void {
		$css = GlobalStyles::css() . GlobalStyles::visual_css( '.cresco-canvas-scope' );

		self::assertStringStartsWith( '.cresco-canvas-scope{', $css );
		self::assertStringNotContainsString( ':root', $css );
		self::assertStringNotContainsString( 'body{', $css );
		self::assertStringContainsString( '.cresco-canvas-scope{background:var(--cc-background)', $css );
		self::assertStringContainsString( '.wp-block-cresco-container', $css );
	}

	public function test_settings_are_bounded_and_invalid_css_font_input_is_rejected(): void {
		$settings = GlobalStyles::sanitize_settings(
			array(
				'containerMax'     => 99999,
				'contentMax'       => 99999,
				'fontFamily'       => 'safe;font}body{display:none',
				'radius'           => 999,
			)
		);

		self::assertSame( 2560, $settings['containerMax'] );
		self::assertSame( 2560, $settings['contentMax'] );
		self::assertSame( 4, $settings['schemaVersion'] );
		self::assertArrayNotHasKey( 'editorPreference', $settings );
		self::assertSame( GlobalStyles::defaults()['fontFamily'], $settings['fontFamily'] );
		self::assertSame( 80, $settings['radius'] );
	}

	public function test_modern_css_colors_are_preserved_after_sanitization(): void {
		$settings = GlobalStyles::sanitize_settings(
			array(
				'primary' => 'oklch(55% 0.15 235)',
				'text' => 'rgb(20 30 40)',
				'muted' => 'hsl(210 10% 45%)',
				'background' => 'oklab(98% 0.002 -0.003)',
				'customColors' => array( 'surface' => 'oklch(99% 0.002 250)' ),
			)
		);

		self::assertSame( 'oklch(55% 0.15 235)', $settings['primary'] );
		self::assertSame( 'rgb(20 30 40)', $settings['text'] );
		self::assertSame( 'hsl(210 10% 45%)', $settings['muted'] );
		self::assertSame( 'oklab(98% 0.002 -0.003)', $settings['background'] );
		self::assertSame( 'oklch(99% 0.002 250)', $settings['customColors']['surface'] );
	}

	public function test_global_button_settings_are_sanitized_and_legacy_sites_keep_their_existing_look(): void {
		$legacy = GlobalStyles::sanitize_settings(
			array(
				'primary' => '#123456',
			)
		);

		self::assertSame( '#123456', $legacy['button']['background'] );
		self::assertSame( '#123456', $legacy['button']['hoverBackground'] );
		self::assertSame( '#ffffff', $legacy['button']['text'] );
		self::assertSame( $legacy['fluidTokens']['radiusMd'], $legacy['button']['radius'] );
		self::assertSame( $legacy['fluidTokens']['controlHeight'], $legacy['button']['height'] );
		self::assertSame( $legacy['fluidTokens']['buttonPadding'], $legacy['button']['paddingInline'] );

		$settings = GlobalStyles::sanitize_settings(
			array(
				'button' => array(
					'background' => '#102030',
					'text' => '#fefefe',
					'hoverBackground' => '#203040',
					'hoverText' => '#ffffff',
					'borderColor' => '#334455',
					'borderWidth' => '2px',
					'radius' => '18px',
					'height' => '48px',
					'paddingInline' => '24px',
					'fontWeight' => '700',
				),
			)
		);

		self::assertSame( '#102030', $settings['button']['background'] );
		self::assertSame( '#203040', $settings['button']['hoverBackground'] );
		self::assertSame( '2px', $settings['button']['borderWidth'] );
		self::assertSame( '18px', $settings['button']['radius'] );
		self::assertSame( '48px', $settings['button']['height'] );
		self::assertSame( '24px', $settings['button']['paddingInline'] );
		self::assertSame( '700', $settings['button']['fontWeight'] );

		$css = GlobalStyles::visual_css( '.cresco-canvas-scope' );
		self::assertStringContainsString( 'var(--cc-button-bg)', $css );
		self::assertStringContainsString( 'var(--cc-button-radius)', $css );
		self::assertStringContainsString( 'var(--cc-button-hover-bg)', $css );
		self::assertStringContainsString( 'var(--cc-button-font-weight)', $css );
	}
}
