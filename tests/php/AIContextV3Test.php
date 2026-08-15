<?php
/**
 * Cresco AI Context v3 high-fidelity One-Shot package.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\ContextBuilderV3;
use CrescoCanvas\AI\ContractRegistry;
use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use PHPUnit\Framework\TestCase;

final class AIContextV3Test extends TestCase {
	private function session(): array {
		$session = WebsiteBuilderSessionSanitizer::sanitize_session(
			array(
				'documentId' => 'v3-test',
				'nodes' => array(
					array(
						'id' => 'section-root',
						'type' => 'container',
						'props' => array( 'layout' => 'grid' ),
						'children' => array(
							array(
								'id' => 'target',
								'type' => 'container',
								'props' => array(),
								'children' => array(
									array( 'id' => 'title', 'type' => 'heading', 'props' => array( 'text' => 'Hello', 'level' => 2 ) ),
								),
							),
							array( 'id' => 'sibling', 'type' => 'text', 'props' => array( 'text' => 'Sibling context' ) ),
						),
					),
				),
			)
		);
		self::assertFalse( is_wp_error( $session ) );
		return $session;
	}

	private function build( string $mode = 'optimized' ) {
		return ContextBuilderV3::build(
			0,
			$this->session(),
			'subtree',
			array( 'nodeId' => 'target' ),
			'redesign',
			$mode,
			array(),
			true,
			'Create a premium hero with image and CTA'
		);
	}

	public function test_declares_v3_and_task(): void {
		$package = $this->build();
		self::assertSame( 'cresco-ai-context/v3', $package['schema'] );
		self::assertSame( 3, $package['version'] );
		self::assertSame( 'Create a premium hero with image and CTA', $package['task']['request'] );
		self::assertSame( 'target', $package['task']['editableTarget']['nodeId'] );
		self::assertArrayNotHasKey( 'baseChecksum', $package );
	}

	public function test_optimized_contracts_are_focused_but_discoverable(): void {
		$contracts = $this->build()['scopePackage']['contracts'];
		self::assertArrayHasKey( 'current', $contracts );
		self::assertArrayHasKey( 'recommended', $contracts );
		self::assertArrayHasKey( 'catalogIndex', $contracts );
		self::assertArrayNotHasKey( 'creationCatalog', $contracts );
		self::assertArrayHasKey( 'container', $contracts['recommended'] );
		self::assertArrayHasKey( 'heading', $contracts['recommended'] );
		self::assertArrayHasKey( 'image', $contracts['recommended'] );
		self::assertSame( array_keys( ContractRegistry::all() ), array_keys( $contracts['catalogIndex'] ) );
	}

	public function test_full_mode_retains_complete_creation_catalog(): void {
		$contracts = $this->build( 'full' )['scopePackage']['contracts'];
		self::assertSame( array_keys( ContractRegistry::all() ), array_keys( $contracts['creationCatalog'] ) );
	}

	public function test_scene_contains_sibling_but_does_not_widen_target(): void {
		$package = $this->build();
		$scene = $package['scopePackage']['scene'];
		self::assertSame( 'target', $scene['editable']['nodeId'] );
		self::assertSame( 'section-root', $scene['contextRootId'] );
		self::assertSame( 'sibling', $scene['siblings'][0]['id'] );
		self::assertSame( 'target', $package['returnContract']['template']['target']['nodeId'] );
	}

	public function test_visual_uses_context_ring_and_facts_do_not_claim_geometry(): void {
		$package = $this->build();
		self::assertSame( 'top-level-branch', $package['scopePackage']['visual']['contextMode'] );
		self::assertContains( 'section-root', $package['scopePackage']['visual']['contextRootIds'] );
		self::assertFalse( $package['scopePackage']['visualFacts']['measuredGeometry'] );
		self::assertSame( 'session-semantic-summary', $package['scopePackage']['visualFacts']['source'] );
	}

	public function test_return_contract_has_concrete_examples(): void {
		$return = $this->build()['returnContract'];
		self::assertSame( 'cresco-patch/v1', $return['preferred'] );
		self::assertNotEmpty( $return['examples'] );
		self::assertSame( 'replaceSubtree', $return['examples'][1]['op'] );
		self::assertSame( 'target', $return['examples'][1]['node']['id'] );
	}
}
