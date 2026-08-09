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
}
