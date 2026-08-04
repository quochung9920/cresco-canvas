<?php
/**
 * Structured Dynamic Data regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Dynamic\StructuredDynamicData;
use PHPUnit\Framework\TestCase;

final class StructuredDynamicDataTest extends TestCase {
	public function test_rows_are_arrays_and_bounded_to_twenty_four(): void {
		$rows = array();
		for ( $index = 0; $index < 30; $index++ ) {
			$rows[] = array( 'title' => 'Row ' . $index );
		}
		$rows[] = 'not-a-row';

		$normalized = StructuredDynamicData::normalize_rows( $rows );

		self::assertCount( 24, $normalized );
		self::assertSame( 'Row 0', $normalized[0]['title'] );
	}

	public function test_dot_path_is_sanitized_and_bounded(): void {
		self::assertSame( 'card.heading.text.extra', StructuredDynamicData::sanitize_path( 'Card.Heading.Text.Extra.Ignored' ) );
		self::assertSame( '', StructuredDynamicData::sanitize_path( '...' ) );
	}

	public function test_resolve_path_never_traverses_objects(): void {
		$row = array( 'card' => array( 'heading' => 'Hello' ), 'object' => (object) array( 'secret' => 'hidden' ) );

		self::assertSame( 'Hello', StructuredDynamicData::resolve_path( $row, 'card.heading' ) );
		self::assertNull( StructuredDynamicData::resolve_path( $row, 'object.secret' ) );
		self::assertNull( StructuredDynamicData::resolve_path( $row, 'missing' ) );
	}

	public function test_layout_templates_ignore_non_layout_blocks_and_empty_names(): void {
		$blocks = array(
			array( 'blockName' => 'core/paragraph', 'attrs' => array(), 'innerBlocks' => array() ),
			array( 'blockName' => 'cresco/acf-layout', 'attrs' => array( 'layoutName' => '' ), 'innerBlocks' => array() ),
			array( 'blockName' => 'cresco/acf-layout', 'attrs' => array( 'layoutName' => 'hero-banner' ), 'innerBlocks' => array() ),
		);

		$templates = StructuredDynamicData::layout_templates( $blocks );

		self::assertArrayHasKey( 'hero-banner', $templates );
		self::assertCount( 1, $templates );
	}
}
