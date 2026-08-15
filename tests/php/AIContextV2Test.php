<?php
/**
 * Cresco AI Context v2 One-Shot authoring package.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\ContextBuilder;
use CrescoCanvas\AI\ContextBuilderV2;
use CrescoCanvas\AI\ContractRegistry;
use CrescoCanvas\AI\PatchValidator;
use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use PHPUnit\Framework\TestCase;

final class AIContextV2Test extends TestCase {
	/** Session with a populated section and an empty Container beside it. */
	private function session(): array {
		$session = WebsiteBuilderSessionSanitizer::sanitize_session(
			array(
				'documentId' => 'v2-test',
				'nodes'      => array(
					array(
						'id'       => 'hero',
						'type'     => 'container',
						'props'    => array( 'contentWidth' => 'boxed' ),
						'style'    => array( 'paddingTop' => '{spacing.xl}' ),
						'children' => array(
							array( 'id' => 'hero-title', 'type' => 'heading', 'props' => array( 'text' => 'Hello' ) ),
						),
					),
					array( 'id' => 'one-shot-root', 'type' => 'container', 'props' => array(), 'children' => array() ),
				),
			)
		);
		self::assertFalse( is_wp_error( $session ) );
		return $session;
	}

	private function build( array $overrides = array() ) {
		$session = $this->session();
		return ContextBuilderV2::build(
			0,
			$session,
			$overrides['scope'] ?? 'subtree',
			$overrides['target'] ?? array( 'nodeId' => 'one-shot-root' ),
			$overrides['purpose'] ?? 'redesign',
			$overrides['mode'] ?? 'optimized',
			$overrides['resources'] ?? array(),
			$overrides['includeVisual'] ?? true
		);
	}

	// A. V2 schema

	public function test_envelope_declares_v2(): void {
		$package = $this->build();

		self::assertSame( 'cresco-ai-context/v2', $package['schema'] );
		self::assertSame( 2, $package['version'] );
		self::assertSame( 'redesign', $package['purpose'] );
		self::assertSame( 'optimized', $package['mode'] );
	}

	public function test_package_does_not_export_a_checksum(): void {
		$package = $this->build();
		self::assertArrayNotHasKey( 'baseChecksum', $package );
		self::assertArrayNotHasKey( 'baseChecksum', $package['returnContract']['template'] );
	}

	public function test_unsupported_purpose_and_mode_are_rejected(): void {
		self::assertTrue( is_wp_error( $this->build( array( 'purpose' => 'sabotage' ) ) ) );
		self::assertTrue( is_wp_error( $this->build( array( 'mode' => 'gigantic' ) ) ) );
	}

	// B. Scope

	public function test_subtree_scope_carries_the_target_and_omits_outside_nodes(): void {
		$package = $this->build( array( 'target' => array( 'nodeId' => 'hero' ) ) );
		$target  = $package['scopePackage']['target'];

		self::assertSame( 'subtree', $target['scope'] );
		self::assertSame( 'hero', $target['nodeId'] );

		$encoded = wp_json_encode( $package['scopePackage']['content'] );
		self::assertStringContainsString( 'hero-title', $encoded );
		self::assertStringNotContainsString( 'one-shot-root', $encoded );
	}

	public function test_scope_content_retains_ancestry(): void {
		$package = $this->build( array( 'target' => array( 'nodeId' => 'hero-title' ), 'scope' => 'widget' ) );
		self::assertArrayHasKey( 'ancestry', $package['scopePackage']['content'] );
	}

	// C. Creation catalog — the reason v2 exists

	public function test_empty_container_still_receives_the_whole_creation_catalog(): void {
		$package   = $this->build();
		$contracts = $package['scopePackage']['contracts'];

		self::assertArrayHasKey( 'container', $contracts['current'] );

		$expected = array_keys( ContractRegistry::all() );
		self::assertSame( $expected, array_keys( $contracts['creationCatalog'] ) );
		self::assertGreaterThan( count( $contracts['current'] ), count( $contracts['creationCatalog'] ) );
	}

	// D. Design System

	public function test_available_tokens_survive_an_empty_scope(): void {
		$package = $this->build();
		$design  = $package['scopePackage']['designSystem'];

		self::assertArrayHasKey( 'available', $design );
		self::assertArrayHasKey( 'used', $design );
		self::assertNotEmpty( $design['available'] );
	}

	// E. Visual

	public function test_visual_is_attached_by_default_when_the_scope_renders(): void {
		$package = $this->build( array( 'target' => array( 'nodeId' => 'hero' ) ) );
		self::assertArrayHasKey( 'visual', $package['scopePackage'] );
		self::assertArrayHasKey( 'html', $package['scopePackage']['visual'] );
	}

	public function test_visual_can_be_switched_off(): void {
		$package = $this->build( array( 'target' => array( 'nodeId' => 'hero' ), 'includeVisual' => false ) );
		self::assertArrayNotHasKey( 'visual', $package['scopePackage'] );
	}

	// F. Return contract

	public function test_return_template_is_prefilled_with_real_values(): void {
		$package  = $this->build();
		$contract = $package['returnContract'];

		self::assertSame( PatchValidator::SCHEMA, $contract['preferred'] );
		self::assertTrue( $contract['scopeEnforced'] );
		self::assertTrue( $contract['preserveTargetRootId'] );

		$template = $contract['template'];
		self::assertSame( 'cresco-patch/v1', $template['schema'] );
		self::assertArrayNotHasKey( 'baseChecksum', $template );
		self::assertSame( $package['scopePackage']['target'], $template['target'] );
		self::assertSame( array(), $template['operations'] );
	}

	public function test_subtree_redesign_prefers_replace_subtree(): void {
		$package = $this->build();
		self::assertSame( 'replaceSubtree', $package['returnContract']['preferredOperationForRedesign'] );
	}

	public function test_page_scope_does_not_force_replace_subtree(): void {
		$package = $this->build( array( 'scope' => 'page', 'target' => array() ) );
		self::assertNotSame( 'replaceSubtree', $package['returnContract']['preferredOperationForRedesign'] );
	}

	// Capabilities must be sourced, not written out

	public function test_capabilities_mirror_the_enforcing_registries(): void {
		$capabilities = $this->build()['scopePackage']['capabilities'];

		self::assertSame( array_values( PatchValidator::OPERATIONS ), $capabilities['patchOperations'] );
		self::assertSame( array_values( ContractRegistry::RESPONSIVE_DEVICES ), $capabilities['responsiveDevices'] );
		self::assertSame( array_values( ContractRegistry::STATES ), $capabilities['states'] );
		self::assertSame( array_values( ContractRegistry::CUSTOM_CSS_BUCKETS ), $capabilities['customCssBuckets'] );
	}

	public function test_wide_is_described_as_the_base_not_a_responsive_bucket(): void {
		$capabilities = $this->build()['scopePackage']['capabilities'];

		self::assertNotContains( 'wide', $capabilities['responsiveDevices'] );
		self::assertSame( 'wide', $capabilities['responsiveModel']['baseDevice'] );
		self::assertSame( 'style', $capabilities['responsiveModel']['baseBucket'] );
	}

	// G. Security

	public function test_secret_bearing_values_never_reach_the_package(): void {
		$session = $this->session();
		$session['nodes'][0]['props']['apiKey']        = 'sk-live-should-not-travel';
		$session['nodes'][0]['props']['nonce']         = 'nonce-should-not-travel';
		$session['nodes'][0]['props']['authorization'] = 'Bearer should-not-travel';
		$session['nodes'][0]['props']['cookie']        = 'cookie-should-not-travel';
		$session['nodes'][0]['props']['licenseKey']    = 'license-should-not-travel';

		$package = ContextBuilderV2::build( 0, $session, 'page', array(), 'redesign', 'optimized', array(), false );
		$encoded = wp_json_encode( $package );

		foreach ( array( 'sk-live-should-not-travel', 'nonce-should-not-travel', 'Bearer should-not-travel', 'cookie-should-not-travel', 'license-should-not-travel' ) as $secret ) {
			self::assertStringNotContainsString( $secret, (string) $encoded );
		}
	}

	// H. Backward-compatible profile

	public function test_v1_remains_available_without_checksum_locking(): void {
		$session = $this->session();
		$v1      = ContextBuilder::build( 0, $session, 'subtree', array( 'nodeId' => 'one-shot-root' ) );

		self::assertSame( 'cresco-ai-context/v1', $v1['schema'] );
		self::assertSame( 1, $v1['version'] );
		self::assertArrayNotHasKey( 'baseChecksum', $v1 );
		self::assertArrayNotHasKey( 'scopePackage', $v1 );
		self::assertArrayNotHasKey( 'visual', $v1 );
		self::assertSame( array( 'container' ), array_keys( $v1['contracts'] ) );
	}

	// Diagnostics

	public function test_package_metrics_are_reported(): void {
		$metrics = $this->build()['packageMetrics'];
		foreach ( array( 'bytes', 'contentBytes', 'contractBytes', 'visualBytes' ) as $key ) {
			self::assertArrayHasKey( $key, $metrics );
		}
		self::assertGreaterThan( 0, $metrics['bytes'] );
	}
}
