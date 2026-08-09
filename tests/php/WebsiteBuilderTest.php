<?php
/**
 * Website Builder Core regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WidgetCatalog;
use PHPUnit\Framework\TestCase;

final class WebsiteBuilderTest extends TestCase {
	private function session( array $nodes ): array {
		return array(
			'schema'      => 'cresco-session/v1',
			'version'     => 1,
			'documentId'  => 'website-builder-test',
			'nodes'       => $nodes,
		);
	}

	public function test_catalog_covers_real_website_builder_groups(): void {
		$catalog = WidgetCatalog::all();
		self::assertGreaterThanOrEqual( 30, count( $catalog ) );
		foreach ( array( 'container', 'gallery', 'accordion', 'tabs', 'nav-menu', 'breadcrumbs', 'dynamic-field', 'loop-grid', 'form', 'woo-products' ) as $type ) {
			self::assertArrayHasKey( $type, $catalog );
		}
		self::assertTrue( $catalog['container']['allowsChildren'] );
		self::assertSame( 'dynamic', $catalog['loop-grid']['category'] );
		self::assertContains( 'gridTemplateRows', $catalog['container']['style'] );
		self::assertContains( 'transform', $catalog['button']['style'] );
	}

	public function test_sanitizer_preserves_extended_layout_responsive_states_and_meta(): void {
		$result = WebsiteBuilder::sanitize_session( $this->session( array(
			array(
				'id'         => 'hero',
				'type'       => 'container',
				'props'      => array( 'layout' => 'grid', 'columns' => 3, 'gridTemplate' => 'repeat(3, minmax(0, 1fr))', 'tag' => 'section', 'ariaLabel' => 'Hero' ),
				'style'      => array( 'gap' => '{spacing.lg}', 'minHeight' => '70vh', 'transform' => 'translateY(0)' ),
				'responsive' => array( 'mobile' => array( 'gridTemplateColumns' => '1fr', 'paddingLeft' => '20px' ) ),
				'states'     => array( 'hover' => array( 'transform' => 'translateY(-2px)', 'opacity' => '0.98' ) ),
				'customCSS'  => array( 'base' => '& .feature { opacity: .9; }' ),
				'meta'       => array( 'label' => 'Hero section', 'locked' => true, 'hidden' => false, 'componentId' => 9 ),
				'children'   => array(),
			),
		) ) );
		self::assertFalse( is_wp_error( $result ) );
		$node = $result['nodes'][0];
		self::assertSame( 'grid', $node['props']['layout'] );
		self::assertSame( '1fr', $node['responsive']['mobile']['gridTemplateColumns'] );
		self::assertSame( 'translateY(-2px)', $node['states']['hover']['transform'] );
		self::assertSame( 'Hero section', $node['meta']['label'] );
		self::assertTrue( $node['meta']['locked'] );
		self::assertSame( 9, $node['meta']['componentId'] );
	}

	public function test_sanitizer_rejects_unknown_widgets_duplicate_ids_and_css_escape(): void {
		$unknown = WebsiteBuilder::sanitize_session( $this->session( array( array( 'id' => 'bad', 'type' => 'script-widget' ) ) ) );
		self::assertTrue( is_wp_error( $unknown ) );
		self::assertSame( 'cresco_builder_widget', $unknown->get_error_code() );

		$duplicate = WebsiteBuilder::sanitize_session( $this->session( array(
			array( 'id' => 'same', 'type' => 'divider' ),
			array( 'id' => 'same', 'type' => 'divider' ),
		) ) );
		self::assertTrue( is_wp_error( $duplicate ) );
		self::assertSame( 'cresco_builder_duplicate_id', $duplicate->get_error_code() );

		$css = WebsiteBuilder::sanitize_session( $this->session( array(
			array( 'id' => 'unsafe', 'type' => 'divider', 'customCSS' => array( 'base' => 'body { display:none; }' ) ),
		) ) );
		self::assertTrue( is_wp_error( $css ) );
		self::assertSame( 'cresco_builder_css_scope', $css->get_error_code() );
	}

	public function test_style_values_reject_executable_or_unscoped_constructs(): void {
		self::assertSame( '', WebsiteBuilder::sanitize_css_value( 'url(https://example.test/a.png)' ) );
		self::assertSame( '', WebsiteBuilder::sanitize_css_value( 'red;position:fixed' ) );
		self::assertSame( '{colors.primary}', WebsiteBuilder::sanitize_css_value( '{colors.primary}' ) );
		self::assertSame( 'clamp(1rem, 2vw, 3rem)', WebsiteBuilder::sanitize_css_value( 'clamp(1rem, 2vw, 3rem)' ) );
	}
}
