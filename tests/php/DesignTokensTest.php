<?php
/**
 * Design token regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Styles\DesignTokens;
use PHPUnit\Framework\TestCase;

final class DesignTokensTest extends TestCase {
	public function test_catalog_has_stable_groups_and_derived_values(): void {
		$catalog = DesignTokens::catalog(
			array(
				'primary'      => '#635bff',
				'text'         => '#111827',
				'muted'        => '#6b7280',
				'background'   => '#ffffff',
				'containerMax' => 1440,
				'contentMax'   => 1200,
				'radius'       => 12,
				'fontFamily'   => 'system-ui',
			)
		);

		self::assertSame( 1, $catalog['schemaVersion'] );
		self::assertSame( '#635bff', $catalog['colors']['primary'] );
		self::assertSame( '1rem', $catalog['typography']['sizes']['base'] );
		self::assertSame( '1.5rem', $catalog['spacing']['6'] );
		self::assertSame( '1440px', $catalog['layout']['containerMax'] );
		self::assertSame( '8px', $catalog['radius']['sm'] );
		self::assertSame( '20px', $catalog['radius']['lg'] );
	}

	public function test_css_variables_are_scoped_values_without_selectors(): void {
		$css = DesignTokens::css_variables(
			array(
				'primary'      => '#635bff',
				'text'         => '#111827',
				'muted'        => '#6b7280',
				'background'   => '#ffffff',
				'containerMax' => 1440,
				'contentMax'   => 1200,
				'radius'       => 12,
				'fontFamily'   => 'system-ui',
			)
		);

		self::assertStringContainsString( '--cc-primary:#635bff;', $css );
		self::assertStringContainsString( '--cc-space-12:3rem;', $css );
		self::assertStringContainsString( '--cc-shadow-md:', $css );
		self::assertStringNotContainsString( '{', $css );
		self::assertStringNotContainsString( '}', $css );
	}
}
