<?php
/**
 * Advanced Query regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Dynamic\AdvancedQuery;
use PHPUnit\Framework\TestCase;

final class AdvancedQueryTest extends TestCase {
	public function test_id_lists_are_unique_positive_and_bounded(): void {
		$values = range( 1, 40 );
		$values[] = 1;
		$values[] = -2;

		$ids = AdvancedQuery::sanitize_ids( $values );

		self::assertCount( 24, $ids );
		self::assertSame( range( 1, 24 ), $ids );
	}

	public function test_dates_require_valid_iso_calendar_dates(): void {
		self::assertSame( '2026-08-05', AdvancedQuery::sanitize_date( '2026-08-05' ) );
		self::assertSame( '', AdvancedQuery::sanitize_date( '2026-02-30' ) );
		self::assertSame( '', AdvancedQuery::sanitize_date( '05/08/2026' ) );
	}

	public function test_page_parameter_is_normalized_and_bounded(): void {
		self::assertSame( 'resultspage', AdvancedQuery::sanitize_page_param( 'Results Page' ) );
		self::assertSame( 'cc_advanced_page', AdvancedQuery::sanitize_page_param( '' ) );
		self::assertLessThanOrEqual( 32, strlen( AdvancedQuery::sanitize_page_param( str_repeat( 'a', 60 ) ) ) );
	}

	public function test_tax_filters_reject_invalid_shapes(): void {
		self::assertSame( array(), AdvancedQuery::sanitize_tax_filters( 'not-an-array', 'post' ) );
		self::assertSame( array(), AdvancedQuery::sanitize_tax_filters( array( 'not-an-array' ), 'post' ) );
	}
}
