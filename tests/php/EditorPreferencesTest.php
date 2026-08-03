<?php
/**
 * Editor preference resolution tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Admin\EditorPreferences;
use PHPUnit\Framework\TestCase;

final class EditorPreferencesTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_options']   = array();
		$GLOBALS['cresco_test_post_meta'] = array();
		$GLOBALS['cresco_test_user_meta'] = array();
	}

	public function test_remember_mode_defaults_to_native_editor(): void {
		$preferences = new EditorPreferences();
		self::assertSame( 'wordpress', $preferences->preferred_editor( 10, 7 ) );
	}

	public function test_user_choice_is_used_in_remember_mode(): void {
		$GLOBALS['cresco_test_user_meta'][7][ EditorPreferences::USER_PREFERENCE_META ] = 'canvas';
		$preferences = new EditorPreferences();
		self::assertSame( 'canvas', $preferences->preferred_editor( 10, 7 ) );
	}

	public function test_page_choice_overrides_global_choice(): void {
		$GLOBALS['cresco_test_options']['cresco_canvas_settings'] = array( 'editorPreference' => 'canvas' );
		$GLOBALS['cresco_test_post_meta'][10][ EditorPreferences::PAGE_PREFERENCE_META ] = 'wordpress';
		$preferences = new EditorPreferences();
		self::assertSame( 'wordpress', $preferences->preferred_editor( 10, 7 ) );
	}
}

