<?php
/**
 * Native Gutenberg integration tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Admin\EditorIntegration;
use PHPUnit\Framework\TestCase;

final class EditorIntegrationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_registered_meta'] = array();
		$GLOBALS['cresco_test_capabilities']    = array( 'edit_post' => true );
	}

	public function test_page_style_meta_uses_native_rest_backed_post_saving(): void {
		$integration = new EditorIntegration();
		$integration->register_meta();

		self::assertArrayHasKey( 'page:' . EditorIntegration::ENABLED_META, $GLOBALS['cresco_test_registered_meta'] );
		$args = $GLOBALS['cresco_test_registered_meta'][ 'page:' . EditorIntegration::ENABLED_META ];

		self::assertTrue( $args['show_in_rest'] );
		self::assertTrue( $args['single'] );
		self::assertTrue( $args['revisions_enabled'] );
		self::assertSame( 'boolean', $args['type'] );
		self::assertTrue( call_user_func( $args['auth_callback'], false, EditorIntegration::ENABLED_META, 42 ) );
	}
}
