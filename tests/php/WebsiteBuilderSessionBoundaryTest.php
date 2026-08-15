<?php
/**
 * Canonical Website Builder Session boundary tests for advanced scoped Custom CSS.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use PHPUnit\Framework\TestCase;

final class WebsiteBuilderSessionBoundaryTest extends TestCase {
	private const MARQUEE = "@keyframes marquee {\n  from { transform: translate3d(0,0,0); }\n  to { transform: translate3d(-50%,0,0); }\n}\n\n& {\n  animation: marquee 32s linear infinite;\n}\n\n@media (prefers-reduced-motion: reduce) {\n  & { animation: none; transform: none; }\n}";

	private function session( string $css ): array {
		return array(
			'documentId' => 'css-boundary',
			'nodes'      => array(
				array(
					'id'        => 'marquee-root',
					'type'      => 'container',
					'props'     => array(),
					'customCSS' => array( 'base' => $css ),
					'children'  => array(),
				),
			),
		);
	}

	public function test_direct_website_builder_session_boundary_accepts_advanced_css(): void {
		$direct = WebsiteBuilder::sanitize_session( $this->session( self::MARQUEE ) );
		self::assertFalse( is_wp_error( $direct ), is_wp_error( $direct ) ? $direct->get_error_message() : '' );
		self::assertSame( self::MARQUEE, $direct['nodes'][0]['customCSS']['base'] );

		$canonical = WebsiteBuilderSessionSanitizer::sanitize_session( $this->session( self::MARQUEE ) );
		self::assertFalse( is_wp_error( $canonical ), is_wp_error( $canonical ) ? $canonical->get_error_message() : '' );
		self::assertSame( $canonical, $direct );
	}

	public function test_public_custom_css_api_uses_the_advanced_scoped_parser(): void {
		$clean = WebsiteBuilder::sanitize_custom_css( self::MARQUEE );
		self::assertIsString( $clean );
		self::assertSame( self::MARQUEE, $clean );
	}

	public function test_unsafe_constructs_remain_rejected_at_the_website_builder_boundary(): void {
		foreach ( array(
			'@import "https://example.com/theme.css";',
			'& { background-image: url(https://example.com/a.png); }',
			'body { display: none; }',
		) as $css ) {
			$result = WebsiteBuilder::sanitize_session( $this->session( $css ) );
			self::assertTrue( is_wp_error( $result ), $css );
		}
	}
}
