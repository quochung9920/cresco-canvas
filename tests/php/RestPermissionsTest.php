<?php
/**
 * REST capability regression tests across editor/admin surfaces.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\AIInterchange;
use CrescoCanvas\API\RestApi;
use CrescoCanvas\Page\PageSettings;
use CrescoCanvas\Session\HistoryManager;
use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Templates\TemplateLibrary;
use CrescoCanvas\Theme\ThemeBuilder;
use PHPUnit\Framework\TestCase;

final class RestPermissionsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_capabilities'] = array();
		$GLOBALS['cresco_test_routes'] = array();
		$GLOBALS['cresco_test_posts'][42] = (object) array(
			'ID' => 42,
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Protected page',
			'post_content' => '',
			'post_date_gmt' => '2026-08-01 00:00:00',
		);
	}

	public function test_settings_and_template_management_require_capabilities(): void {
		$GLOBALS['cresco_test_capabilities']['edit_theme_options'] = false;
		$GLOBALS['cresco_test_capabilities']['edit_pages'] = false;

		self::assertFalse( ( new RestApi() )->can_manage_settings() );
		self::assertFalse( ( new TemplateLibrary() )->can_edit_templates() );
		self::assertFalse( ( new ThemeBuilder() )->can_edit() );
	}

	public function test_page_scoped_routes_reject_user_without_edit_post(): void {
		$GLOBALS['cresco_test_capabilities']['edit_post'] = false;
		$request = new WP_REST_Request( array( 'postId' => 42 ) );

		self::assertFalse( ( new SessionManager() )->can_edit_post( $request ) );
		self::assertFalse( ( new AIInterchange() )->can_edit_post( $request ) );
		self::assertFalse( ( new HistoryManager() )->can_edit_post( $request ) );
		self::assertFalse( ( new PageSettings() )->can_edit_post( $request ) );
	}

	public function test_every_history_route_uses_the_edit_post_permission_boundary(): void {
		$manager = new HistoryManager();
		$manager->register_routes();

		$routes = array(
			'cresco-canvas/v1/history/(?P<postId>\d+)',
			'cresco-canvas/v1/history/(?P<postId>\d+)/(?P<revisionId>\d+)/restore',
			'cresco-canvas/v1/website-builder/theme-history/(?P<postId>\d+)',
			'cresco-canvas/v1/website-builder/theme-history/(?P<postId>\d+)/(?P<revisionId>\d+)/restore',
		);

		foreach ( $routes as $route ) {
			self::assertArrayHasKey( $route, $GLOBALS['cresco_test_routes'] );
			$args = $GLOBALS['cresco_test_routes'][ $route ];
			self::assertArrayHasKey( 'permission_callback', $args );
			self::assertSame( array( $manager, 'can_edit_post' ), $args['permission_callback'] );
		}

		$GLOBALS['cresco_test_capabilities']['edit_post'] = false;
		$request = new WP_REST_Request( array( 'postId' => 42 ) );
		foreach ( $routes as $route ) {
			$callback = $GLOBALS['cresco_test_routes'][ $route ]['permission_callback'];
			self::assertFalse( call_user_func( $callback, $request ) );
		}
	}
}
