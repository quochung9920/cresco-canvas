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
		$css = GlobalStyles::css();

		self::assertStringStartsWith( '.cresco-canvas-scope{', $css );
		self::assertStringNotContainsString( ':root', $css );
		self::assertStringNotContainsString( 'body{', $css );
	}

	public function test_settings_are_bounded_and_invalid_css_font_input_is_rejected(): void {
		$settings = GlobalStyles::sanitize_settings(
			array(
				'containerMax'     => 99999,
				'contentMax'       => 99999,
				'editorPreference' => 'invalid',
				'fontFamily'       => 'safe;font}body{display:none',
				'radius'           => 999,
			)
		);

		self::assertSame( 2560, $settings['containerMax'] );
		self::assertSame( 2560, $settings['contentMax'] );
		self::assertSame( 'remember', $settings['editorPreference'] );
		self::assertSame( GlobalStyles::defaults()['fontFamily'], $settings['fontFamily'] );
		self::assertSame( 80, $settings['radius'] );
	}
}

