<?php
/**
 * Deterministic responsive cascade/provenance tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Styles\StyleCascade;
use PHPUnit\Framework\TestCase;

final class StyleCascadeTest extends TestCase {
	private function node(): array {
		return array(
			'id' => 'hero-title',
			'type' => 'heading',
			'style' => array( 'fontSize' => '48px', 'color' => '#111111' ),
			'responsive' => array(
				'tablet' => array( 'fontSize' => '40px' ),
				'mobile' => array( 'fontSize' => '32px' ),
			),
			'states' => array(
				'hover' => array( 'color' => '#333333' ),
			),
		);
	}

	public function test_sparse_breakpoints_inherit_previous_explicit_value(): void {
		$result = StyleCascade::resolve( $this->node(), 'fontSize', 'laptop' );
		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( '48px', $result['value'] );
		self::assertSame( 'wide', $result['breakpoint'] );
		self::assertTrue( $result['inherited'] );
		self::assertSame( 'wide', $result['previousBreakpoint'] );
		self::assertFalse( $result['explicitAtCurrent'] );
	}

	public function test_explicit_breakpoint_override_reports_provenance(): void {
		$result = StyleCascade::resolve( $this->node(), 'fontSize', 'tablet' );
		self::assertSame( '40px', $result['value'] );
		self::assertSame( 'local', $result['source'] );
		self::assertSame( 'tablet', $result['breakpoint'] );
		self::assertFalse( $result['inherited'] );
		self::assertTrue( $result['explicitAtCurrent'] );
		self::assertSame( 'wide', $result['previousBreakpoint'] );
	}

	public function test_local_layer_beats_component_global_and_token_layers(): void {
		$shared = array(
			'token' => array( 'base' => array( 'fontSize' => '36px' ) ),
			'global' => array( 'base' => array( 'fontSize' => '38px' ) ),
			'component' => array( 'base' => array( 'fontSize' => '44px' ) ),
		);
		$result = StyleCascade::resolve( $this->node(), 'fontSize', 'wide', 'normal', $shared );
		self::assertSame( '48px', $result['value'] );
		self::assertSame( 'local', $result['source'] );
		self::assertCount( 4, $result['chain'] );
	}

	public function test_interaction_state_is_applied_after_breakpoint_cascade(): void {
		$result = StyleCascade::resolve( $this->node(), 'color', 'mobile', 'hover' );
		self::assertSame( '#333333', $result['value'] );
		self::assertSame( 'hover', $result['state'] );
		self::assertSame( 'local', $result['source'] );
	}

	public function test_fluid_value_builds_safe_clamp_expression(): void {
		self::assertSame( 'clamp(24px, 4vw, 48px)', StyleCascade::fluid( '24px', '4vw', '48px' ) );
		self::assertTrue( is_wp_error( StyleCascade::fluid( '24px; color:red', '4vw', '48px' ) ) );
	}
}
