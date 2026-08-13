<?php
/**
 * Studio/frontend visual parity regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilderCssCompiler;
use PHPUnit\Framework\TestCase;

final class WebsiteBuilderVisualParityTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['cresco_test_options'] = array();
	}

	private function responsive_session(): array {
		return array(
			'schema'     => 'cresco-session/v1',
			'version'    => 1,
			'documentId' => 'parity-test',
			'nodes'      => array(
				array(
					'id'         => 'hero-inner',
					'type'       => 'container',
					'props'      => array(
						'layout'       => 'flex',
						'direction'    => 'row',
						'wrap'         => 'nowrap',
						'align'        => 'center',
						'justify'      => 'space-between',
						'contentWidth' => 'full',
					),
					'style'      => array( 'width' => '86%', 'gap' => '80px' ),
					'responsive' => array(
						'desktop' => array( 'width' => '92%' ),
						'laptop'  => array( 'gap' => '52px' ),
						'tablet'  => array( 'flexDirection' => 'column' ),
						'mobile'  => array( 'gap' => '32px' ),
					),
					'states'     => array(),
					'customCSS'  => array(
						'desktop' => '&{opacity:.98}',
						'mobile'  => '&{opacity:1}',
					),
					'meta'       => array(),
					'children'   => array(),
				),
			),
		);
	}

	public function test_compiler_uses_studio_desktop_first_breakpoint_cascade(): void {
		$css = WebsiteBuilderCssCompiler::compile( $this->responsive_session() );

		self::assertStringContainsString( '@media (max-width:1919px)', $css );
		self::assertStringContainsString( '@media (max-width:1439px)', $css );
		self::assertStringContainsString( '@media (max-width:1024px)', $css );
		self::assertStringContainsString( '@media (max-width:767px)', $css );
		self::assertStringNotContainsString( '@media (min-width:', $css );

		$desktop = strpos( $css, '@media (max-width:1919px)' );
		$laptop  = strpos( $css, '@media (max-width:1439px)' );
		$tablet  = strpos( $css, '@media (max-width:1024px)' );
		$mobile  = strpos( $css, '@media (max-width:767px)' );
		self::assertIsInt( $desktop );
		self::assertIsInt( $laptop );
		self::assertIsInt( $tablet );
		self::assertIsInt( $mobile );
		self::assertLessThan( $laptop, $desktop );
		self::assertLessThan( $tablet, $laptop );
		self::assertLessThan( $mobile, $tablet );
	}

	public function test_parity_service_uses_canonical_compiler_on_editor_and_frontend(): void {
		$root   = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Builder/WebsiteBuilderRendererParity.php' );

		self::assertIsString( $source );
		self::assertStringContainsString( 'FRONTEND_STYLE_HANDLE', $source );
		self::assertStringContainsString( "enqueue_frontend_parity_styles", $source );
		self::assertGreaterThanOrEqual( 2, substr_count( $source, 'WebsiteBuilderCssCompiler::compile( $session )' ) );
		self::assertStringContainsString( "wp_add_inline_style( self::FRONTEND_STYLE_HANDLE, \$compiled )", $source );
	}

	public function test_editor_parity_hides_empty_decoration_dropzones_and_normalizes_heading_content(): void {
		$root   = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Builder/WebsiteBuilderRendererParity.php' );

		self::assertIsString( $source );
		self::assertStringContainsString( '.cc-studio-container-empty{display:none!important;}', $source );
		self::assertStringContainsString( '.cc-studio-canvas-node>h1', $source );
		self::assertStringContainsString( 'font-size:inherit', $source );
		self::assertStringContainsString( '.cc-studio-canvas-node>a{color:inherit', $source );
	}
}
