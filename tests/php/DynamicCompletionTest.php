<?php
/** Final Dynamic Data completion regression tests. @package CrescoCanvas */

use CrescoCanvas\Dynamic\DynamicCompletion;
use CrescoCanvas\Dynamic\InteractiveQuery;
use PHPUnit\Framework\TestCase;

final class DynamicCompletionTest extends TestCase {
	public function test_completion_block_names_are_stable(): void {
		self::assertSame( 'cresco/acf-row-image', DynamicCompletion::ROW_IMAGE );
		self::assertSame( 'cresco/acf-row-gallery', DynamicCompletion::ROW_GALLERY );
		self::assertSame( 'cresco/acf-row-relationship', DynamicCompletion::ROW_RELATIONSHIP );
	}

	public function test_facet_payload_signature_rejects_tampering(): void {
		$encoded = InteractiveQuery::encode_payload( array( 'query' => array( 'postType' => 'post' ), 'facets' => array( 'category' ) ) );
		$signature = InteractiveQuery::sign_payload( $encoded );
		self::assertTrue( InteractiveQuery::verify_payload( $encoded, $signature ) );
		self::assertFalse( InteractiveQuery::verify_payload( $encoded . 'x', $signature ) );
	}

	public function test_public_filters_remain_bounded_for_dependent_counts(): void {
		$filters = InteractiveQuery::sanitize_public_filters(
			array( 'search' => str_repeat( 'x', 150 ), 'tax' => array( 'category' => range( 1, 50 ), 'post_tag' => array( 'ignored' ) ) ),
			array( 'category' )
		);
		self::assertSame( 100, strlen( $filters['search'] ) );
		self::assertLessThanOrEqual( 24, count( $filters['tax']['category'] ) );
		self::assertArrayNotHasKey( 'post_tag', $filters['tax'] );
	}
}
