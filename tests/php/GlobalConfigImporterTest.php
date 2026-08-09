<?php
/**
 * Global Config import regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalConfigImporter;
use CrescoCanvas\Styles\GlobalStyles;
use PHPUnit\Framework\TestCase;

final class GlobalConfigImporterTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_options'] = array();
	}

	public function test_css_variables_map_to_global_and_custom_tokens(): void {
		$input = <<<'CSS'
--bg: oklch(98% 0.005 250);
--surface: oklch(99% 0.002 250);
--surface-alt: oklch(95% 0.012 250);
--ink: oklch(22% 0.02 250);
--ink-muted: oklch(46% 0.015 250);
--blue-dark: oklch(38% 0.13 255);
--blue: oklch(55% 0.15 235);
--blue-light: oklch(90% 0.035 235);
--blue-mid: oklch(66% 0.075 235);
--green: oklch(55% 0.13 145);
--green-light: oklch(93% 0.04 145);
--border: oklch(88% 0.012 250);
font-family: Poppins, sans-serif;
color: var(--ink);
CSS;

		$result = GlobalConfigImporter::preview( $input );
		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( 'css', $result['format'] );
		self::assertSame( 'oklch(98% 0.005 250)', $result['settings']['background'] );
		self::assertSame( 'oklch(22% 0.02 250)', $result['settings']['text'] );
		self::assertSame( 'oklch(46% 0.015 250)', $result['settings']['muted'] );
		self::assertSame( 'oklch(55% 0.15 235)', $result['settings']['primary'] );
		self::assertSame( 'Poppins, sans-serif', $result['settings']['fontFamily'] );
		self::assertSame( 'oklch(99% 0.002 250)', $result['settings']['customColors']['surface'] );
		self::assertSame( 'oklch(88% 0.012 250)', $result['settings']['customColors']['border'] );
		self::assertSame( 'background', $result['settings']['aliases']['bg'] );
		self::assertSame( 'text', $result['settings']['aliases']['ink'] );
		self::assertSame( 'custom-surface', $result['settings']['aliases']['surface'] );
		self::assertSame( 'oklch(99% 0.002 250)', $result['tokens']['colors']['custom-surface'] );
	}

	public function test_structured_json_merges_into_current_global_settings(): void {
		$result = GlobalConfigImporter::preview(
			array(
				'background' => 'hsl(210 20% 98%)',
				'primary' => 'rgb(12 95 180)',
				'fontFamily' => 'Poppins, sans-serif',
				'customColors' => array( 'panel' => 'oklab(95% 0.01 -0.02)' ),
			)
		);

		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( 'json', $result['format'] );
		self::assertSame( 'hsl(210 20% 98%)', $result['settings']['background'] );
		self::assertSame( 'rgb(12 95 180)', $result['settings']['primary'] );
		self::assertSame( 'oklab(95% 0.01 -0.02)', $result['settings']['customColors']['panel'] );
		self::assertSame( 1440, $result['settings']['containerMax'] );
	}

	public function test_copy_global_config_catalog_can_be_imported_directly(): void {
		$settings = GlobalStyles::sanitize_settings(
			array(
				'primary' => 'oklch(55% 0.15 235)',
				'background' => 'oklch(98% 0.005 250)',
				'fontFamily' => 'Poppins, sans-serif',
				'customColors' => array( 'surface' => 'oklch(99% 0.002 250)' ),
			)
		);
		$catalog = DesignTokens::catalog( $settings );
		$result = GlobalConfigImporter::preview( $catalog );

		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( 'oklch(55% 0.15 235)', $result['settings']['primary'] );
		self::assertSame( 'oklch(98% 0.005 250)', $result['settings']['background'] );
		self::assertSame( 'Poppins, sans-serif', $result['settings']['fontFamily'] );
		self::assertSame( 'oklch(99% 0.002 250)', $result['settings']['customColors']['surface'] );
		self::assertSame( $settings['containerMax'], $result['settings']['containerMax'] );
		self::assertSame( $settings['breakpoints'], $result['settings']['breakpoints'] );
	}

	public function test_forbidden_or_unrecognized_import_is_rejected(): void {
		$external = GlobalConfigImporter::preview( '--hero: url(https://example.test/image.png);' );
		self::assertTrue( is_wp_error( $external ) );
		self::assertSame( 'cresco_global_import_forbidden', $external->get_error_code() );

		$unknown = GlobalConfigImporter::preview( 'display: grid;' );
		self::assertTrue( is_wp_error( $unknown ) );
		self::assertSame( 'cresco_global_import_unrecognized', $unknown->get_error_code() );
	}

	public function test_modern_color_sanitizer_blocks_arbitrary_css(): void {
		self::assertSame( 'oklch(55% 0.15 235)', GlobalStyles::sanitize_color_value( 'oklch(55% 0.15 235)' ) );
		self::assertSame( 'rgb(12 95 180 / 80%)', GlobalStyles::sanitize_color_value( 'rgb(12 95 180 / 80%)' ) );
		self::assertSame( '', GlobalStyles::sanitize_color_value( 'var(--evil)' ) );
		self::assertSame( '', GlobalStyles::sanitize_color_value( 'red;display:none' ) );
	}
}
