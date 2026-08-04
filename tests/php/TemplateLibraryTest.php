<?php
/**
 * Template Library regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Templates\TemplateLibrary;
use PHPUnit\Framework\TestCase;

final class TemplateLibraryTest extends TestCase {
	public function test_catalog_has_stable_unique_native_templates(): void {
		$catalog = TemplateLibrary::catalog();
		self::assertGreaterThanOrEqual( 7, count( $catalog ) );
		self::assertSame( array_keys( $catalog ), array_values( array_unique( array_keys( $catalog ) ) ) );

		foreach ( $catalog as $id => $template ) {
			self::assertMatchesRegularExpression( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id );
			self::assertNotSame( '', trim( $template['title'] ) );
			self::assertNotSame( '', trim( $template['description'] ) );
			self::assertArrayHasKey( $template['category'], TemplateLibrary::categories() );
			self::assertStringContainsString( '<!-- wp:', $template['content'] );
			self::assertStringNotContainsString( '<?php', $template['content'] );
			self::assertStringNotContainsString( '<script', strtolower( $template['content'] ) );
		}
	}

	public function test_catalog_includes_complete_page_and_section_types(): void {
		$catalog = TemplateLibrary::catalog();
		self::assertArrayHasKey( 'landing-page', $catalog );
		self::assertArrayHasKey( 'hero-centered', $catalog );
		self::assertArrayHasKey( 'feature-grid', $catalog );
		self::assertArrayHasKey( 'cta-band', $catalog );
		self::assertArrayHasKey( 'testimonial-card', $catalog );
		self::assertArrayHasKey( 'pricing-three', $catalog );
		self::assertArrayHasKey( 'contact-split', $catalog );
	}
}
