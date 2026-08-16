<?php
/**
 * Professional widget expansion regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\ProfessionalWidgetExpansion;
use CrescoCanvas\Builder\WidgetCatalog;
use PHPUnit\Framework\TestCase;

final class ProfessionalWidgetExpansionTest extends TestCase {
	public function test_catalog_registers_professional_expansion_widgets(): void {
		$catalog = WidgetCatalog::all();
		$expected = array(
			'nested-card', 'clickable-container', 'cta', 'icon-list', 'badge', 'advanced-divider',
			'team-member', 'faq', 'data-table', 'table-of-contents', 'video-popup', 'stats-card',
			'advanced-icon', 'logo-grid', 'steps', 'alert', 'blockquote', 'mega-menu', 'nested-tabs',
			'tab-panel', 'nested-accordion', 'accordion-item', 'filterable-grid', 'filter-item',
		);
		foreach ( $expected as $type ) self::assertArrayHasKey( $type, $catalog, $type . ' is missing from the canonical widget catalog.' );
	}

	public function test_nested_widgets_accept_real_child_nodes(): void {
		$catalog = WidgetCatalog::all();
		foreach ( array( 'nested-card', 'clickable-container', 'cta', 'logo-grid', 'steps', 'nested-tabs', 'tab-panel', 'nested-accordion', 'accordion-item', 'filterable-grid', 'filter-item' ) as $type ) {
			self::assertTrue( $catalog[ $type ]['allowsChildren'], $type . ' must support direct child widgets.' );
		}
	}

	public function test_gallery_is_upgraded_instead_of_duplicated(): void {
		$catalog = WidgetCatalog::all();
		foreach ( array( 'layoutMode', 'tabletColumns', 'mobileColumns', 'hoverZoom', 'lightboxNavigation' ) as $prop ) {
			self::assertArrayHasKey( $prop, $catalog['gallery']['props'] );
		}
	}

	public function test_icon_list_uses_a_sanitizer_safe_item_shape(): void {
		$catalog = WidgetCatalog::all();
		self::assertSame( 'string_list', $catalog['icon-list']['props']['items']['type'] );
	}

	public function test_transform_preserves_nested_children_and_reuses_core_primitives(): void {
		$session = array(
			'nodes' => array(
				array(
					'id' => 'card-1', 'type' => 'nested-card', 'props' => array( 'url' => '/about' ),
					'children' => array( array( 'id' => 'heading-1', 'type' => 'heading', 'props' => array( 'text' => 'About', 'level' => '2' ), 'children' => array() ) ),
				),
				array( 'id' => 'faq-1', 'type' => 'faq', 'props' => array( 'items' => array( array( 'title' => 'Question', 'content' => 'Answer', 'open' => true ) ) ), 'children' => array() ),
				array( 'id' => 'menu-1', 'type' => 'mega-menu', 'props' => array( 'menu' => 7, 'depth' => 3 ), 'children' => array() ),
			),
		);
		$configs = array();
		$adapted = ProfessionalWidgetExpansion::transform_session( $session, $configs );

		self::assertSame( 'container', $adapted['nodes'][0]['type'] );
		self::assertSame( 'heading', $adapted['nodes'][0]['children'][0]['type'] );
		self::assertSame( 'accordion', $adapted['nodes'][1]['type'] );
		self::assertSame( 'nav-menu', $adapted['nodes'][2]['type'] );
		self::assertSame( 7, $adapted['nodes'][2]['props']['menu'] );
		self::assertSame( 'nested-card', $configs['card-1']['type'] );
	}
}
