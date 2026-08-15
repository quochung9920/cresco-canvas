<?php
/**
 * Professional widget catalog and renderer-adapter regression coverage.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\ContractRegistry;
use CrescoCanvas\Builder\ProfessionalWidgets;
use CrescoCanvas\Builder\WidgetCatalog;
use PHPUnit\Framework\TestCase;

final class ProfessionalWidgetsTest extends TestCase {
	public function test_catalog_exposes_professional_widget_suite(): void {
		$catalog = WidgetCatalog::all();
		foreach ( array(
			'carousel', 'slides', 'loop-carousel', 'marquee', 'image-carousel',
			'testimonial-carousel', 'logo-carousel', 'media-carousel', 'before-after',
			'timeline', 'pricing-table', 'countdown', 'modal', 'off-canvas',
			'comparison-table', 'hotspot-image', 'flip-card', 'animated-headline',
			'progress-circle', 'rating', 'site-search', 'advanced-breadcrumbs', 'map',
		) as $type ) {
			self::assertArrayHasKey( $type, $catalog, $type . ' should be available to editor and AI.' );
		}
		self::assertTrue( $catalog['carousel']['allowsChildren'] );
		self::assertTrue( $catalog['marquee']['allowsChildren'] );
		self::assertFalse( $catalog['loop-carousel']['allowsChildren'] );
		self::assertArrayHasKey( 'postType', $catalog['loop-carousel']['props'] );
		self::assertArrayHasKey( 'slidesPerView', $catalog['loop-carousel']['props'] );
	}

	public function test_blueprint_documents_border_side_and_corner_order(): void {
		$blueprint = WidgetCatalog::all()['container']['blueprint'];
		self::assertSame( array( 'top', 'right', 'bottom', 'left' ), $blueprint['styleShorthands']['borderWidth']['order'] );
		self::assertSame( array( 'top', 'right', 'bottom', 'left' ), $blueprint['styleShorthands']['borderColor']['order'] );
		self::assertSame( array( 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ), $blueprint['styleShorthands']['borderRadius']['order'] );
	}

	public function test_ai_contract_exposes_border_shorthand_semantics(): void {
		$contract = ContractRegistry::all()['container'];
		self::assertSame( array( 'top', 'right', 'bottom', 'left' ), $contract['blueprint']['styleShorthands']['borderWidth']['order'] );
		self::assertSame( array( 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ), $contract['blueprint']['styleShorthands']['borderRadius']['order'] );
	}

	public function test_nested_carousel_translates_to_safe_container_without_losing_children(): void {
		$session = array(
			'schema' => 'cresco-session/v1', 'version' => 1, 'documentId' => 'professional-test',
			'nodes' => array(
				array(
					'id' => 'carousel-test', 'type' => 'carousel', 'props' => array( 'slidesPerView' => 3 ),
					'style' => array(), 'responsive' => array(), 'states' => array(), 'customCSS' => array(), 'meta' => array(),
					'children' => array(
						array( 'id' => 'slide-one', 'type' => 'text', 'props' => array( 'text' => 'One', 'tag' => 'p' ), 'children' => array() ),
					),
				),
			),
		);
		$configs = array();
		$adapted = ProfessionalWidgets::transform_session( $session, $configs );
		self::assertSame( 'container', $adapted['nodes'][0]['type'] );
		self::assertSame( 'slide-one', $adapted['nodes'][0]['children'][0]['id'] );
		self::assertSame( 'carousel', $configs['carousel-test']['type'] );
		self::assertSame( 3, $configs['carousel-test']['props']['slidesPerView'] );
	}

	public function test_loop_carousel_reuses_loop_grid_query_contract(): void {
		$session = array(
			'nodes' => array(
				array(
					'id' => 'loop-test', 'type' => 'loop-carousel',
					'props' => array( 'postType' => 'post', 'perPage' => 8, 'columns' => 4, 'order' => 'DESC', 'orderBy' => 'date', 'taxonomy' => '', 'term' => '', 'showImage' => true, 'showExcerpt' => true, 'showDate' => false, 'buttonLabel' => 'Read more', 'slidesPerView' => 3 ),
					'children' => array(),
				),
			),
		);
		$configs = array();
		$adapted = ProfessionalWidgets::transform_session( $session, $configs );
		self::assertSame( 'loop-grid', $adapted['nodes'][0]['type'] );
		self::assertSame( 'post', $adapted['nodes'][0]['props']['postType'] );
		self::assertArrayNotHasKey( 'slidesPerView', $adapted['nodes'][0]['props'] );
		self::assertSame( 3, $configs['loop-test']['props']['slidesPerView'] );
	}
}
