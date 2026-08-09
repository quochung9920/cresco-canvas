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
}
