<?php
/**
 * Detailed Website Builder widget-contract regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WidgetCatalog;
use PHPUnit\Framework\TestCase;

final class WidgetCatalogPropertiesTest extends TestCase {
	public function test_every_widget_has_a_detailed_editor_contract(): void {
		$catalog = WidgetCatalog::all();

		foreach ( $catalog as $type => $widget ) {
			self::assertNotEmpty( $widget['description'] ?? '', $type . ' should describe its purpose.' );
			self::assertIsArray( $widget['style'] ?? null, $type . ' should declare its style capabilities.' );
			self::assertIsArray( $widget['states'] ?? null, $type . ' should declare supported states.' );
			self::assertIsArray( $widget['parts'] ?? null, $type . ' should expose scoped style targets.' );
			self::assertArrayHasKey( 'root', $widget['parts'], $type . ' should expose a root style target.' );
		}
	}

	public function test_content_controls_use_semantic_editor_metadata(): void {
		$catalog = WidgetCatalog::all();

		self::assertSame( 'enum', $catalog['heading']['props']['level']['type'] );
		self::assertSame( 'H1', $catalog['heading']['props']['level']['valueLabels']['1'] );
		self::assertSame( 'media', $catalog['image']['props']['url']['control'] );
		self::assertSame( 'icon', $catalog['button']['props']['icon']['control'] );
		self::assertSame( 'repeater', $catalog['list']['props']['items']['control'] );
		self::assertSame( 'repeater', $catalog['gallery']['props']['images']['control'] );
		self::assertSame( 'repeater', $catalog['accordion']['props']['items']['control'] );
		self::assertSame( 'repeater', $catalog['tabs']['props']['items']['control'] );
		self::assertSame( 'repeater', $catalog['social-icons']['props']['items']['control'] );
		self::assertSame( 'repeater', $catalog['form']['props']['fields']['control'] );
		self::assertSame( 'option-select', $catalog['nav-menu']['props']['menu']['control'] );
		self::assertSame( 'menus', $catalog['nav-menu']['props']['menu']['optionsSource'] );
		self::assertSame( 'option-select', $catalog['loop-grid']['props']['postType']['control'] );
		self::assertSame( 'postTypes', $catalog['loop-grid']['props']['postType']['optionsSource'] );
	}

	public function test_widget_capabilities_remove_noop_or_irrelevant_controls(): void {
		$catalog = WidgetCatalog::all();

		self::assertArrayNotHasKey( 'controls', $catalog['video']['props'], 'oEmbed controls are provider-managed and must not expose a no-op toggle.' );
		self::assertSame( array(), $catalog['divider']['states'] );
		self::assertSame( array(), $catalog['spacer']['states'] );
		self::assertContains( 'focus', $catalog['button']['states'] );
		self::assertNotContains( 'focus', $catalog['heading']['states'] );
		self::assertContains( 'objectFit', $catalog['image']['style'] );
		self::assertNotContains( 'fontFamily', $catalog['divider']['style'] );
		self::assertContains( 'gridTemplateRows', $catalog['container']['style'] );
		self::assertContains( 'transform', $catalog['button']['style'] );
	}

	public function test_conditional_and_scoped_metadata_are_available_to_studio(): void {
		$catalog = WidgetCatalog::all();

		self::assertSame( 'layout', $catalog['container']['props']['direction']['panel'] );
		self::assertSame( 'flex', $catalog['container']['props']['direction']['condition']['equals'] );
		self::assertTrue( $catalog['form']['props']['retentionDays']['condition']['equals'] );
		self::assertSame( '& .cresco-progress__bar', $catalog['progress']['parts']['bar']['selector'] );
		self::assertSame( '& .cresco-loop-card__button', $catalog['loop-grid']['parts']['button']['selector'] );
	}
}
