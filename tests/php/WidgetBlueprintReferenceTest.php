<?php
/**
 * Reference-driven Widget Blueprint regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WidgetCatalog;
use PHPUnit\Framework\TestCase;

final class WidgetBlueprintReferenceTest extends TestCase {
	public function test_every_widget_exposes_a_normalized_blueprint(): void {
		$catalog = WidgetCatalog::all();

		self::assertCount( 35, $catalog );
		foreach ( $catalog as $type => $widget ) {
			$blueprint = $widget['blueprint'] ?? array();
			self::assertSame( 'cresco-widget-blueprint/v1', $blueprint['schema'] ?? '', $type . ' should expose the Widget Blueprint schema.' );
			self::assertIsArray( $blueprint['controlGroups'] ?? null, $type . ' should group content controls.' );
			self::assertIsArray( $blueprint['styleTargets'] ?? null, $type . ' should list style targets.' );
			self::assertContains( 'root', $blueprint['styleTargets'], $type . ' should keep the root style target.' );
			self::assertContains( 'normal', $blueprint['states'] ?? array(), $type . ' should expose its base state.' );
		}
	}

	public function test_reference_backed_widgets_record_their_catalog_source(): void {
		$catalog = WidgetCatalog::all();
		$expected = array(
			'button'    => 'e-button',
			'image'     => 'image',
			'icon-box'  => 'icon-box',
			'gallery'   => 'image-gallery',
			'accordion' => 'nested-accordion',
			'tabs'      => 'nested-tabs',
			'counter'   => 'counter',
			'progress'  => 'progress',
		);

		foreach ( $expected as $type => $reference_widget ) {
			$reference = $catalog[ $type ]['blueprint']['reference'] ?? array();
			self::assertSame( 'elementor-control-catalog', $reference['source'] ?? '', $type . ' should identify the reference catalog.' );
			self::assertContains( $reference_widget, $reference['widgets'] ?? array(), $type . ' should identify its reference widget.' );
			self::assertSame( 'capability-reference', $reference['mode'] ?? '' );
		}
	}

	public function test_tabs_accordion_gallery_and_icon_box_use_reference_driven_controls(): void {
		$catalog = WidgetCatalog::all();

		self::assertSame( array( 'top', 'bottom', 'start', 'end' ), $catalog['tabs']['props']['direction']['values'] );
		self::assertSame( array( 'start', 'center', 'end', 'stretch' ), $catalog['tabs']['props']['justify']['values'] );
		self::assertTrue( $catalog['tabs']['props']['horizontalScroll']['default'] );
		self::assertSame( array( 'start', 'end' ), $catalog['tabs']['props']['sideWidth']['condition']['in'] );
		self::assertArrayHasKey( 'activeTab', $catalog['tabs']['parts'] );

		self::assertSame( array( 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ), $catalog['accordion']['props']['titleTag']['values'] );
		self::assertSame( array( 'start', 'end' ), $catalog['accordion']['props']['iconPosition']['values'] );
		self::assertSame( 'icon', $catalog['accordion']['props']['expandIcon']['control'] );
		self::assertSame( 'icon', $catalog['accordion']['props']['collapseIcon']['control'] );

		self::assertArrayHasKey( 'gap', $catalog['gallery']['props'] );
		self::assertArrayHasKey( 'aspectRatio', $catalog['gallery']['props'] );
		self::assertArrayHasKey( 'objectFit', $catalog['gallery']['props'] );
		self::assertArrayHasKey( 'captionAlign', $catalog['gallery']['props'] );
		self::assertTrue( $catalog['gallery']['props']['showCaptions']['default'] );

		self::assertSame( array( 'top', 'start', 'end' ), $catalog['icon-box']['props']['position']['values'] );
		self::assertSame( array( 'start', 'center', 'end', 'justify' ), $catalog['icon-box']['props']['contentAlign']['values'] );
		self::assertArrayHasKey( 'iconGap', $catalog['icon-box']['props'] );
	}
}
