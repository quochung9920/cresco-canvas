<?php
/**
 * Transitional REST save safety tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\API\RestApi;
use PHPUnit\Framework\TestCase;

final class RestApiTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_posts']   = array();
		$GLOBALS['cresco_test_updates'] = array();
	}

	public function test_same_second_content_change_returns_conflict(): void {
		$post = (object) array(
			'ID'                => 42,
			'post_content'      => 'Original content',
			'post_modified_gmt' => '2026-08-03 08:00:00',
			'post_status'       => 'draft',
			'post_title'        => 'Concurrent Page',
			'post_type'         => 'page',
		);
		$GLOBALS['cresco_test_posts'][42] = $post;

		$api      = new RestApi();
		$loaded   = $api->get_page( new WP_REST_Request( array( 'id' => 42 ) ) )->get_data();
		$revision = $loaded['revision'];

		// Simulate a native-editor save whose timestamp lands in the same second.
		$GLOBALS['cresco_test_posts'][42]->post_content = 'Newer native content';

		$result = $api->save_page(
			new WP_REST_Request(
				array(
					'content'  => 'Stale Canvas content',
					'id'       => 42,
					'revision' => $revision,
					'status'   => 'draft',
					'title'    => 'Concurrent Page',
				)
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_canvas_edit_conflict', $result->get_error_code() );
		self::assertSame( 409, $result->get_error_data()['status'] );
		self::assertSame( 'Newer native content', $GLOBALS['cresco_test_posts'][42]->post_content );
	}
}
