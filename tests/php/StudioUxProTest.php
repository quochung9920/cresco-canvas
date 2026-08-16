<?php
/**
 * Studio UX Pro regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\StudioUxPro;
use PHPUnit\Framework\TestCase;

final class StudioUxProTest extends TestCase {
	public function test_project_brief_sanitizer_drops_unknown_fields_and_markup(): void {
		$brief = StudioUxPro::sanitize_brief(
			array(
				'business' => '<strong>North West Damp</strong>',
				'industry' => 'Home Services',
				'goal'     => "Free survey leads\n<script>alert(1)</script>",
				'secret'   => 'must not survive',
			)
		);

		self::assertSame( 'North West Damp', $brief['business'] );
		self::assertSame( 'Home Services', $brief['industry'] );
		self::assertStringNotContainsString( '<script>', $brief['goal'] );
		self::assertArrayNotHasKey( 'secret', $brief );
		self::assertSame(
			array( 'business', 'industry', 'location', 'goal', 'audience', 'personality', 'notes' ),
			array_keys( $brief )
		);
	}

	public function test_project_brief_has_bounded_field_lengths(): void {
		$brief = StudioUxPro::sanitize_brief( array( 'notes' => str_repeat( 'a', 1800 ) ) );
		self::assertLessThanOrEqual( 1200, strlen( $brief['notes'] ) );
	}

	public function test_ai_payload_enrichment_preserves_scope_and_target(): void {
		$data = array(
			'package' => array(
				'schema' => 'cresco-interchange/v1',
				'scope'  => 'subtree',
				'target' => array( 'scope' => 'subtree', 'nodeId' => 'section-1' ),
				'aiContext' => array(
					'schema' => 'cresco-ai-context/v3',
					'task' => array( 'request' => 'Improve this section.' ),
					'scopePackage' => array( 'target' => array( 'scope' => 'subtree', 'nodeId' => 'section-1' ) ),
				),
			),
		);

		$result = StudioUxPro::enrich_payload_with_brief(
			$data,
			array(
				'business' => 'North West Damp',
				'industry' => 'Home Services',
				'goal' => 'Free survey leads',
			)
		);

		self::assertSame( 'subtree', $result['package']['scope'] );
		self::assertSame( 'section-1', $result['package']['target']['nodeId'] );
		self::assertSame( 'subtree', $result['package']['aiContext']['scopePackage']['target']['scope'] );
		self::assertSame( 'Home Services', $result['package']['aiContext']['task']['projectBrief']['industry'] );
		self::assertTrue( $result['package']['aiContext']['authoringPolicy']['projectBrief']['neverWidensScope'] );
	}

	public function test_empty_brief_does_not_change_payload(): void {
		$data = array( 'package' => array( 'schema' => 'cresco-interchange/v1', 'scope' => 'page' ) );
		self::assertSame( $data, StudioUxPro::enrich_payload_with_brief( $data, array() ) );
	}

	public function test_brief_text_is_compact_and_labeled(): void {
		$text = StudioUxPro::brief_text(
			array(
				'business' => 'North West Damp',
				'industry' => 'Home Services',
				'audience' => 'Homeowners and landlords',
			)
		);
		self::assertStringContainsString( 'Business: North West Damp.', $text );
		self::assertStringContainsString( 'Industry: Home Services.', $text );
		self::assertStringContainsString( 'Audience: Homeowners and landlords.', $text );
	}
}
