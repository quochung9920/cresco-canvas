<?php
/** Cresco Design Intelligence regression tests. */

use CrescoCanvas\AI\ContractRegistry;
use CrescoCanvas\AI\DesignIntelligence;
use CrescoCanvas\AI\DesignIntelligenceCatalog;
use CrescoCanvas\AI\DesignQualityGate;
use PHPUnit\Framework\TestCase;

final class DesignIntelligenceTest extends TestCase {
	public function test_home_service_request_produces_lead_generation_recipe(): void {
		$recipe = DesignIntelligence::recommend( 'Build a damp and mould remediation website in London with free survey quote.' );
		self::assertSame( 'cresco-design-recipe/v1', $recipe['schema'] );
		self::assertSame( 'home-services', $recipe['classification']['industry']['id'] );
		self::assertSame( 'lead-generation', $recipe['classification']['goal']['id'] );
		self::assertSame( 'lead-trust', $recipe['pattern']['id'] );
		self::assertContains( 'services', $recipe['pattern']['sections'] );
		self::assertTrue( $recipe['rules']['minimizeCustomCSS'] );
	}

	public function test_dark_saas_request_resolves_mode_and_dials(): void {
		$recipe = DesignIntelligence::recommend( 'Dark mode SaaS analytics dashboard with an animated product demo and free trial.' );
		self::assertSame( 'saas', $recipe['classification']['industry']['id'] );
		self::assertSame( 'signup', $recipe['classification']['goal']['id'] );
		self::assertSame( 'dark', $recipe['style']['mode'] );
		self::assertGreaterThanOrEqual( 5, $recipe['dials']['density'] );
		self::assertGreaterThanOrEqual( 4, $recipe['dials']['motion'] );
		self::assertSame( '#0B1120', $recipe['colors']['background'] );
	}

	public function test_explicit_dials_are_clamped_and_deterministic(): void {
		$first = DesignIntelligence::recommend( 'Creative portfolio', array( 'variance' => 20, 'density' => 0, 'motion' => 7 ) );
		$second = DesignIntelligence::recommend( 'Creative portfolio', array( 'variance' => 20, 'density' => 0, 'motion' => 7 ) );
		self::assertSame( 10, $first['dials']['variance'] );
		self::assertSame( 1, $first['dials']['density'] );
		self::assertSame( 7, $first['dials']['motion'] );
		self::assertSame( $first, $second );
	}

	public function test_recipe_recommends_only_registered_contracts(): void {
		$recipe = DesignIntelligence::recommend( 'SaaS landing page with pricing and FAQ' );
		$catalog = ContractRegistry::all();
		foreach ( $recipe['recommendedWidgets'] as $types ) {
			foreach ( $types as $type ) self::assertArrayHasKey( $type, $catalog, $type . ' must exist in ContractRegistry.' );
		}
	}

	public function test_context_augmentation_adds_recipe_without_changing_target(): void {
		$package = array(
			'schema' => 'cresco-ai-context/v3',
			'version' => 3,
			'task' => array( 'request' => 'Create a premium SaaS hero with demo CTA' ),
			'scopePackage' => array(
				'target' => array( 'scope' => 'subtree', 'nodeId' => 'target' ),
				'contracts' => array( 'current' => array(), 'recommended' => ContractRegistry::for_types( array( 'container', 'heading', 'text', 'button' ) ) ),
			),
			'authoringPolicy' => array(),
		);
		$augmented = DesignIntelligence::augment_context( $package );
		self::assertSame( 'target', $augmented['scopePackage']['target']['nodeId'] );
		self::assertArrayHasKey( 'designIntelligence', $augmented['scopePackage'] );
		self::assertSame( 'recommended-design-direction', $augmented['authoringPolicy']['designIntelligence']['role'] );
		self::assertGreaterThan( 4, count( $augmented['scopePackage']['contracts']['recommended'] ) );
	}

	public function test_quality_gate_reports_design_system_hygiene_from_session_only(): void {
		$children = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$children[] = array(
				'id' => 'box-' . $i,
				'type' => 'container',
				'props' => array(),
				'style' => array( 'backgroundColor' => sprintf( '#%06X', 0x111111 + ( $i * 0x10101 ) ), 'paddingTop' => ( 4 + $i ) . 'px' ),
				'customCSS' => $i < 4 ? array( 'base' => '& { opacity: .99; }' ) : array(),
				'children' => array(),
			);
		}
		$session = array( 'nodes' => array( array( 'id' => 'root', 'type' => 'container', 'props' => array(), 'children' => $children ) ) );
		$result = DesignQualityGate::inspect( $session );
		$codes = array_column( $result['items'], 'code' );
		self::assertContains( 'raw-color-fragmentation', $codes );
		self::assertContains( 'custom-css-overuse', $codes );
		self::assertSame( 10, $result['stats']['rawColorValues'] );
	}

	public function test_catalog_is_cresco_owned_and_machine_readable(): void {
		$summary = DesignIntelligenceCatalog::summary();
		self::assertSame( 'cresco-design-intelligence-catalog/v1', $summary['schema'] );
		self::assertArrayHasKey( 'home-services', $summary['industries'] );
		self::assertArrayHasKey( 'saas-conversion', $summary['patterns'] );
	}
}
