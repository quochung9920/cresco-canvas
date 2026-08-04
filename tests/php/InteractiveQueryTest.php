<?php
/**
 * Interactive Query regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Dynamic\InteractiveQuery;
use PHPUnit\Framework\TestCase;

final class InteractiveQueryTest extends TestCase {
	public function test_signed_payload_rejects_tampering(): void {
		$payload   = InteractiveQuery::encode_payload( array( 'query' => array( 'postType' => 'post' ) ) );
		$signature = InteractiveQuery::sign_payload( $payload );

		self::assertTrue( InteractiveQuery::verify_payload( $payload, $signature ) );
		self::assertFalse( InteractiveQuery::verify_payload( $payload . 'x', $signature ) );
		self::assertFalse( InteractiveQuery::verify_payload( $payload, str_repeat( '0', 64 ) ) );
	}

	public function test_public_filters_only_keep_declared_taxonomies(): void {
		$filters = InteractiveQuery::sanitize_public_filters(
			array(
				'search' => str_repeat( 'x', 120 ),
				'tax'    => array(
					'category' => array( 'News', 'news', '' ),
					'post_tag' => array( 'Ignored' ),
				),
			),
			array( 'category' )
		);

		self::assertSame( 100, strlen( $filters['search'] ) );
		self::assertSame( array( 'news' ), $filters['tax']['category'] );
		self::assertArrayNotHasKey( 'post_tag', $filters['tax'] );
	}

	public function test_modes_and_woocommerce_presets_are_allow_listed(): void {
		self::assertSame( 'ajax', InteractiveQuery::sanitize_mode( 'invalid' ) );
		self::assertSame( 'load_more', InteractiveQuery::sanitize_mode( 'load_more' ) );
		self::assertSame( 'none', InteractiveQuery::sanitize_woo_preset( 'arbitrary_sql' ) );
		self::assertSame( 'best_selling', InteractiveQuery::sanitize_woo_preset( 'best_selling' ) );
	}

	public function test_instance_ids_are_sanitized_and_bounded(): void {
		self::assertSame( 'productsloop', InteractiveQuery::sanitize_instance_id( 'Products Loop' ) );
		self::assertLessThanOrEqual( 32, strlen( InteractiveQuery::sanitize_instance_id( str_repeat( 'a', 80 ) ) ) );
		self::assertStringStartsWith( 'ccq_', InteractiveQuery::sanitize_instance_id( '', 'payload' ) );
	}

	public function test_templates_reject_recursive_and_executable_blocks(): void {
		self::assertTrue( InteractiveQuery::is_safe_template( '<!-- wp:paragraph --><p>Safe</p><!-- /wp:paragraph -->' ) );
		self::assertFalse( InteractiveQuery::is_safe_template( '<!-- wp:html --><script>alert(1)</script><!-- /wp:html -->' ) );
		self::assertFalse( InteractiveQuery::is_safe_template( '<!-- wp:cresco/filterable-loop --><!-- /wp:cresco/filterable-loop -->' ) );
		self::assertFalse( InteractiveQuery::is_safe_template( '<!-- wp:third-party/widget /-->' ) );
	}

	public function test_payload_keeps_only_bounded_query_configuration(): void {
		$payload = InteractiveQuery::build_payload(
			array(
				'postType'       => 'post',
				'postsPerPage'   => 999,
				'columns'        => 99,
				'wooPreset'      => 'top_rated',
				'searchFilter'   => true,
				'untrustedField' => 'ignored',
			),
			'<!-- wp:paragraph --><p>Card</p><!-- /wp:paragraph -->'
		);

		self::assertSame( 24, $payload['query']['postsPerPage'] );
		self::assertSame( 6, $payload['columns'] );
		self::assertSame( 'top_rated', $payload['wooPreset'] );
		self::assertArrayNotHasKey( 'untrustedField', $payload['query'] );
	}
}
