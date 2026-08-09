<?php
/**
 * Page Settings tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Page\PageSettings;
use PHPUnit\Framework\TestCase;

final class PageSettingsTest extends TestCase {
	public function test_defaults_make_new_cresco_pages_full_width_without_theme_title(): void {
		$settings = PageSettings::defaults();
		self::assertSame( 'full-width', $settings['layout'] );
		self::assertSame( 'hide', $settings['pageTitle'] );
		self::assertSame( 'inherit', $settings['header'] );
		self::assertSame( 'inherit', $settings['footer'] );
		self::assertSame( 'viewport', $settings['contentRoot'] );
	}

	public function test_invalid_values_fall_back_to_safe_defaults(): void {
		$settings = PageSettings::sanitize(
			array(
				'layout'      => 'javascript:bad',
				'pageTitle'   => 'maybe',
				'header'      => 'remove-everything',
				'footer'      => 'remove-everything',
				'contentRoot' => '100vw',
			)
		);
		self::assertSame( PageSettings::defaults(), $settings );
	}

	public function test_layout_modes_apply_shell_guarantees_without_changing_container_semantics(): void {
		$full = PageSettings::effective(
			array(
				'layout'      => 'full-width',
				'pageTitle'   => 'show',
				'header'      => 'show',
				'footer'      => 'show',
				'contentRoot' => 'theme',
			)
		);
		self::assertSame( 'viewport', $full['contentRoot'] );
		self::assertSame( 'show', $full['pageTitle'] );

		$canvas = PageSettings::effective(
			array(
				'layout'      => 'canvas',
				'pageTitle'   => 'show',
				'header'      => 'show',
				'footer'      => 'show',
				'contentRoot' => 'theme',
			)
		);
		self::assertSame( 'viewport', $canvas['contentRoot'] );
		self::assertSame( 'hide', $canvas['pageTitle'] );
		self::assertSame( 'hide', $canvas['header'] );
		self::assertSame( 'hide', $canvas['footer'] );
	}
}
