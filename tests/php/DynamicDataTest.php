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
				'paged'        => 9999,
				'order'        => 'sideways',
				'orderby'      => 'meta_value',
			)
		);

		self::assertSame( 'post', $args['post_type'] );
		self::assertSame( 24, $args['posts_per_page'] );
		self::assertSame( 2000, $args['offset'] );
		self::assertSame( 999, $args['paged'] );
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
				'paged'        => 3,
				'order'        => 'ASC',
				'orderby'      => 'title',
			)
		);

		self::assertSame( 'page', $args['post_type'] );
		self::assertSame( 4, $args['posts_per_page'] );
		self::assertSame( 10, $args['offset'] );
		self::assertSame( 3, $args['paged'] );
		self::assertSame( 'ASC', $args['order'] );
		self::assertSame( 'title', $args['orderby'] );
	}

	/**
	 * @dataProvider preset_provider
	 */
	public function test_query_presets_are_deterministic( string $preset, string $orderby, string $order ): void {
		$args = DynamicData::sanitize_query( array( 'preset' => $preset ) );
		self::assertSame( $orderby, $args['orderby'] );
		self::assertSame( $order, $args['order'] );
	}

	public static function preset_provider(): array {
		return array(
			'recent'       => array( 'recent', 'date', 'DESC' ),
			'oldest'       => array( 'oldest', 'date', 'ASC' ),
			'alphabetical' => array( 'alphabetical', 'title', 'ASC' ),
			'random'       => array( 'random', 'rand', 'DESC' ),
		);
	}

	public function test_page_parameter_is_sanitized_and_bounded(): void {
		self::assertSame( 'loop_page', DynamicData::sanitize_page_param( 'Loop Page!' ) );
		self::assertSame( 'cc_page', DynamicData::sanitize_page_param( '' ) );
		self::assertSame( 32, strlen( DynamicData::sanitize_page_param( str_repeat( 'a', 80 ) ) ) );
	}

	public function test_image_resolver_returns_empty_shape_without_post(): void {
		self::assertSame(
			array( 'id' => 0, 'url' => '', 'alt' => '' ),
			DynamicData::resolve_image( array( 'source' => 'featured' ), 0 )
		);
	}
}
