<?php
/** Multisite lifecycle batching regression tests. */

use CrescoCanvas\Lifecycle\LifecycleManager;
use PHPUnit\Framework\TestCase;

final class LifecycleMultisiteTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_is_multisite'] = true;
		$GLOBALS['cresco_test_sites'] = range( 1, 205 );
		$GLOBALS['cresco_test_current_blog_id'] = 1;
		$GLOBALS['cresco_test_blog_stack'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['cresco_test_is_multisite'] = false;
		$GLOBALS['cresco_test_sites'] = array();
		$GLOBALS['cresco_test_current_blog_id'] = 1;
		$GLOBALS['cresco_test_blog_stack'] = array();
	}

	public function test_multisite_iteration_is_bounded_and_restores_blog_context(): void {
		$visited = array();
		$result = LifecycleManager::for_each_site( static function () use ( &$visited ) {
			$visited[] = get_current_blog_id();
			return true;
		} );

		self::assertTrue( $result );
		self::assertCount( 205, $visited );
		self::assertSame( 1, $visited[0] );
		self::assertSame( 205, $visited[204] );
		self::assertSame( 1, get_current_blog_id() );
		self::assertCount( 0, $GLOBALS['cresco_test_blog_stack'] );
	}
}
