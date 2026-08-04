<?php
/**
 * Design token regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Styles\DesignTokens;
use PHPUnit\Framework\TestCase;

final class DesignTokensTest extends TestCase {
	private function settings(): array {
		return array(
			'primary'      => '#635bff',
			'text'         => '#111827',
			'muted'        => '#6b7280',
			'background'   => '#ffffff',
			'containerMax' => 1440,
			'contentMax'   => 1200,
			'radius'       => 12,
			'fontFamily'   => 'system-ui',
			'customColors' => array( 'brand-alt' => '#ff3366' ),
			'aliases'      => array( 'accent' => 'custom-brand-alt', 'body' => 'text' ),
		);
	}

	public function test_catalog_has_stable_groups_and_derived_values(): void {
		$catalog = DesignTokens::catalog( $this->settings() );
		self::assertSame( 2, $catalog['schemaVersion'] );
		self::assertSame( '#635bff', $catalog['colors']['primary'] );
		self::assertSame( '#ff3366', $catalog['colors']['custom-brand-alt'] );
		self::assertSame( 'custom-brand-alt', $catalog['aliases']['accent'] );
		self::assertSame( '1rem', $catalog['typography']['sizes']['base'] );
		self::assertSame( '1.5rem', $catalog['spacing']['6'] );
		self::assertSame( '1440px', $catalog['layout']['containerMax'] );
		self::assertSame( '8px', $catalog['radius']['sm'] );
		self::assertSame( '20px', $catalog['radius']['lg'] );
	}

	public function test_css_variables_include_custom_colors_and_aliases(): void {
		$css = DesignTokens::css_variables( $this->settings() );
		self::assertStringContainsString( '--cc-primary:#635bff;', $css );
		self::assertStringContainsString( '--cc-color-brand-alt:#ff3366;', $css );
		self::assertStringContainsString( '--cc-alias-accent:var(--cc-color-brand-alt);', $css );
		self::assertStringContainsString( '--cc-alias-body:var(--cc-text);', $css );
		self::assertStringContainsString( '--cc-space-12:3rem;', $css );
		self::assertStringContainsString( '--cc-shadow-md:', $css );
		self::assertStringNotContainsString( '{', $css );
		self::assertStringNotContainsString( '}', $css );
	}
}
