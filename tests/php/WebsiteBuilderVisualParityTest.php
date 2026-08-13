<?php
/**
 * Studio/frontend visual parity regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilderCssCompiler;
use CrescoCanvas\Builder\WebsiteBuilderVisualParity;
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

	public function test_compiler_matches_studio_desktop_first_breakpoint_inheritance(): void {
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

	public function test_frontend_compatibility_uses_the_same_canonical_compiler(): void {
		$root   = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Builder/WebsiteBuilderCompatibility.php' );

		self::assertIsString( $source );
		self::assertStringContainsString( 'replace_frontend_compiled_styles', $source );
		self::assertStringContainsString( 'WebsiteBuilderCssCompiler::compile( $session )', $source );
	}

	public function test_editor_normalization_exposes_frontend_widget_semantics_without_replacing_react_dom(): void {
		$css    = WebsiteBuilderVisualParity::editor_css();
		$script = WebsiteBuilderVisualParity::editor_script();

		self::assertStringContainsString( '[data-cresco-widget="heading"]>h1', $css );
		self::assertStringContainsString( '[data-cresco-widget="button"]>a', $css );
		self::assertStringContainsString( '[data-cresco-field-type="textarea"] input', $css );
		self::assertStringContainsString( '.is-cresco-decoration>.cc-studio-container-empty', $css );
		self::assertStringContainsString( 'window.crescoDocumentStore', $script );
		self::assertStringContainsString( "el.setAttribute('data-cresco-widget'", $script );
		self::assertStringContainsString( "label.setAttribute('data-cresco-field-type'", $script );
		self::assertStringContainsString( "button.setAttribute('type','submit')", $script );
		self::assertStringContainsString( 'MutationObserver', $script );
	}

	public function test_visual_parity_service_is_registered_by_plugin(): void {
		$root   = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Plugin.php' );

		self::assertIsString( $source );
		self::assertStringContainsString( 'use CrescoCanvas\\Builder\\WebsiteBuilderVisualParity;', $source );
		self::assertStringContainsString( '( new WebsiteBuilderVisualParity() )->register();', $source );
	}
}
