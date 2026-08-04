<?php
/**
 * Dynamic Data regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Dynamic\DynamicData;
use PHPUnit\Framework\TestCase;

final class DynamicDataTest extends TestCase {
	public function test_query_is_bounded_and_allow_listed(): void {
		$args = DynamicData::sanitize_query(
			array(
				'postType'     => 'not-public',
				'postsPerPage' => 999,
				'offset'       => 999,
				'order'        => 'sideways',
				'orderby'      => 'meta_value',
			)
		);

		self::assertSame( 'post', $args['post_type'] );
		self::assertSame( 24, $args['posts_per_page'] );
		self::assertSame( 200, $args['offset'] );
		self::assertSame( 'DESC', $args['order'] );
		self::assertSame( 'date', $args['orderby'] );
		self::assertSame( 'publish', $args['post_status'] );
	}

	public function test_query_accepts_supported_values(): void {
		$args = DynamicData::sanitize_query(
			array(
				'postType'     => 'page',
				'postsPerPage' => 4,
				'offset'       => 2,
				'order'        => 'ASC',
				'orderby'      => 'title',
			)
		);

		self::assertSame( 'page', $args['post_type'] );
		self::assertSame( 4, $args['posts_per_page'] );
		self::assertSame( 2, $args['offset'] );
		self::assertSame( 'ASC', $args['order'] );
		self::assertSame( 'title', $args['orderby'] );
	}
}
