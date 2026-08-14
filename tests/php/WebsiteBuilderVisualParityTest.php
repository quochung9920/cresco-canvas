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

	public function test_full_container_does_not_force_hundred_percent_width_as_a_flex_item(): void {
		$session = $this->responsive_session();
		$session['nodes'][0]['id'] = 'hero-copy';
		$session['nodes'][0]['style'] = array( 'maxWidth' => '820px', 'flexGrow' => '1', 'flexShrink' => '1' );
		$session['nodes'][0]['responsive'] = array();
		$session['nodes'][] = array(
			'id'         => 'survey-card',
			'type'       => 'container',
			'props'      => array( 'layout' => 'flex', 'direction' => 'column', 'wrap' => 'nowrap', 'align' => 'stretch', 'justify' => 'center', 'contentWidth' => 'full' ),
			'style'      => array( 'width' => '354px', 'maxWidth' => '100%', 'flexShrink' => '0' ),
			'responsive' => array( 'tablet' => array( 'width' => '100%', 'maxWidth' => '520px' ) ),
			'states'     => array(),
			'customCSS'  => array(),
			'meta'       => array(),
			'children'   => array(),
		);
		$css = WebsiteBuilderCssCompiler::compile( $session );
		self::assertStringContainsString( '[data-cresco-id="hero-copy"]{display:flex;width:auto;', $css );
		self::assertStringNotContainsString( '[data-cresco-id="hero-copy"]{display:flex;width:100%;', $css );
		self::assertStringContainsString( '[data-cresco-id="survey-card"]{display:flex;width:354px;', $css );
		self::assertStringContainsString( '@media (max-width:1024px){.cresco-website-builder-root [data-cresco-id="survey-card"]{width:100%;max-width:520px;}}', $css );
	}

	public function test_container_width_fallback_never_overrides_explicit_node_width(): void {
		$root = dirname( __DIR__, 2 );
		$css  = file_get_contents( $root . '/assets/css/container-width.css' );
		self::assertIsString( $css );
		self::assertStringContainsString( '[data-cresco-content-width="full"]', $css );
		self::assertStringContainsString( 'min-width: 0;', $css );
		self::assertStringNotContainsString( 'width: 100% !important', $css );
		self::assertStringNotContainsString( 'max-width: none !important', $css );
		self::assertStringNotContainsString( 'margin-left: 0 !important', $css );
	}

	public function test_legacy_frontend_compilers_are_pruned_by_core_platform(): void {
		$root = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Builder/WebsiteBuilderCorePlatform.php' );
		self::assertIsString( $source );
		self::assertStringContainsString( 'prune_legacy_frontend_hooks', $source );
		self::assertStringContainsString( 'WebsiteBuilderCompatibility::class', $source );
		self::assertStringContainsString( 'WebsiteBuilderComprehensiveV3::class', $source );
		self::assertStringContainsString( 'BuilderArchitecture::class', $source );
		self::assertStringContainsString( "STYLE_CONTRACT = 'authoritative-v5'", $source );
	}

	public function test_visual_parity_remains_canonical_editor_surface(): void {
		$css    = WebsiteBuilderVisualParity::editor_css();
		$script = WebsiteBuilderVisualParity::editor_script();
		self::assertStringContainsString( '.cc-studio-canonical-preview', $css );
		self::assertStringContainsString( '.is-cresco-canonical-preview>.cc-studio-canvas{display:none!important;}', $css );
		self::assertStringContainsString( '/website-builder/render/', $script );
		self::assertStringContainsString( 'data.currentSession=session', $script );
		self::assertStringContainsString( 'render.html', $script );
		self::assertStringContainsString( 'render.css', $script );
		self::assertStringContainsString( 'crescoCanonicalEditorPreview', $script );
	}

	public function test_part_style_compiler_uses_shared_responsive_resolver(): void {
		$root = dirname( __DIR__, 2 );
		$part = file_get_contents( $root . '/includes/Builder/WidgetPartStyleCompiler.php' );
		$resolver = file_get_contents( $root . '/includes/Core/Responsive/ResponsiveResolver.php' );
		self::assertIsString( $part );
		self::assertIsString( $resolver );
		self::assertStringContainsString( 'ResponsiveResolver::OVERRIDE_DEVICES', $part );
		self::assertStringContainsString( 'ResponsiveResolver::wrap( $device, $body )', $part );
		self::assertStringContainsString( "'desktop' => \$bp['wide'] - 1", $resolver );
		self::assertStringContainsString( "'mobile'  => \$bp['tablet'] - 1", $resolver );
		self::assertStringNotContainsString( 'max_width_for_device', $part );
	}

	public function test_render_engine_is_shared_v2_boundary_for_editor_and_frontend(): void {
		$root = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Rendering/RenderEngine.php' );
		self::assertIsString( $source );
		self::assertStringContainsString( 'WebsiteBuilderArchitectureV2::load_document', $source );
		self::assertStringContainsString( 'WebsiteRendererV2::render_document', $source );
		self::assertStringContainsString( 'WidgetPartStyleCompiler::compile', $source );
		self::assertStringContainsString( 'ComponentStyleCompiler::compile', $source );
		self::assertStringContainsString( 'WebsiteBuilderRendererParity::repair_document_html', $source );
		self::assertStringNotContainsString( 'WebsiteRenderer::render_document', $source );
	}

	public function test_core_platform_is_registered_by_plugin(): void {
		$root = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Plugin.php' );
		self::assertIsString( $source );
		self::assertStringContainsString( 'use CrescoCanvas\\Builder\\WebsiteBuilderCorePlatform;', $source );
		self::assertStringContainsString( '( new WebsiteBuilderCorePlatform() )->register();', $source );
	}
}
