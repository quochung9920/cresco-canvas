<?php
/**
 * Widget Architecture v2 regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilderRendererParity;
use CrescoCanvas\Builder\WidgetArchitectureV2;
use CrescoCanvas\Builder\WidgetCatalog;
use PHPUnit\Framework\TestCase;

final class WebsiteBuilderArchitectureV2Test extends TestCase {
	public function test_all_existing_widgets_receive_v2_capabilities_without_expanding_the_catalog(): void {
		$legacy = WidgetCatalog::all();
		$catalog = WidgetArchitectureV2::catalog();
		self::assertCount( 35, $legacy );
		self::assertCount( 35, $catalog );
		foreach ( $catalog as $type => $widget ) {
			$blueprint = $widget['blueprint'] ?? array();
			self::assertSame( '2.0.0', $blueprint['architectureVersion'] ?? '', $type );
			self::assertIsArray( $blueprint['dynamicProperties'] ?? null, $type );
			self::assertIsArray( $blueprint['partStyleSchema'] ?? null, $type );
			self::assertContains( 'wide', $blueprint['partStyleSchema']['breakpoints'] ?? array(), $type );
			self::assertContains( 'post', $blueprint['dynamicSources'] ?? array(), $type );
		}
	}

	public function test_nested_query_form_and_effect_capabilities_are_exposed(): void {
		$catalog = WidgetArchitectureV2::catalog();
		self::assertSame( 'component-per-item', $catalog['tabs']['blueprint']['nestedSlots']['mode'] );
		self::assertSame( 'component-per-item', $catalog['accordion']['blueprint']['nestedSlots']['mode'] );
		self::assertSame( 'component-template', $catalog['loop-grid']['blueprint']['nestedSlots']['mode'] );
		self::assertSame( 'component-template', $catalog['woo-products']['blueprint']['nestedSlots']['mode'] );
		self::assertSame( 'cresco-query-builder/v2', $catalog['loop-grid']['blueprint']['queryBuilder']['schema'] );
		self::assertSame( 'cresco-form-engine/v2', $catalog['form']['blueprint']['formEngine']['schema'] );
		self::assertTrue( $catalog['form']['blueprint']['formEngine']['multiStep'] );
		self::assertContains( 'backdropFilter', WidgetArchitectureV2::advanced_style_properties() );
		self::assertContains( 'clipPath', WidgetArchitectureV2::advanced_style_properties() );
	}

	public function test_architecture_document_preserves_camel_case_properties_and_scoped_styles(): void {
		$session = array(
			'schema' => 'cresco-session/v1',
			'version' => 1,
			'documentId' => 'test',
			'nodes' => array(
				array( 'id' => 'button-one', 'type' => 'button', 'props' => array(), 'children' => array() ),
				array( 'id' => 'loop-one', 'type' => 'loop-grid', 'props' => array(), 'children' => array() ),
				array( 'id' => 'tabs-one', 'type' => 'tabs', 'props' => array(), 'children' => array() ),
				array( 'id' => 'form-one', 'type' => 'form', 'props' => array(), 'children' => array() ),
			),
		);
		$input = array(
			'nodes' => array(
				'button-one' => array(
					'partStyles' => array( 'icon' => array( 'base' => array( 'mixBlendMode' => 'multiply' ), 'responsive' => array( 'tablet' => array( 'fontSize' => '18px' ) ) ) ),
				),
				'loop-one' => array( 'bindings' => array( 'buttonLabel' => array( 'source' => 'meta', 'key' => 'cta_label', 'fallback' => 'Read more' ) ) ),
				'tabs-one' => array( 'slots' => array( 'items' => array( '0' => 44, '1' => 45 ) ) ),
				'form-one' => array( 'form' => array( 'replyToField' => 'email', 'captcha' => array( 'enabled' => true, 'provider' => 'turnstile', 'siteKey' => 'site-key', 'action' => 'contact' ) ) ),
			),
		);
		$clean = WidgetArchitectureV2::sanitize_document( $input, $session );
		self::assertArrayHasKey( 'buttonLabel', $clean['nodes']['loop-one']['bindings'] );
		self::assertSame( 'multiply', $clean['nodes']['button-one']['partStyles']['icon']['base']['mixBlendMode'] );
		self::assertSame( '18px', $clean['nodes']['button-one']['partStyles']['icon']['responsive']['tablet']['fontSize'] );
		self::assertSame( 44, $clean['nodes']['tabs-one']['slots']['items']['0'] );
		self::assertSame( 'email', $clean['nodes']['form-one']['form']['replyToField'] );
		self::assertSame( 'turnstile', $clean['nodes']['form-one']['form']['captcha']['provider'] );
	}

	public function test_frontend_parity_accepts_preview_revisions_but_rejects_nested_posts(): void {
		self::assertTrue( WebsiteBuilderRendererParity::matches_frontend_page( 42, 42 ) );
		self::assertTrue( WebsiteBuilderRendererParity::matches_frontend_page( 42, 84, 42 ) );
		self::assertTrue( WebsiteBuilderRendererParity::matches_frontend_page( 42, 0 ) );
		self::assertFalse( WebsiteBuilderRendererParity::matches_frontend_page( 42, 99 ) );
		self::assertFalse( WebsiteBuilderRendererParity::matches_frontend_page( 0, 0 ) );
	}
}
