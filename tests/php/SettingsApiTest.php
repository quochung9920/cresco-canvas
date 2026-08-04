<?php
/**
 * Cresco settings REST tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\API\RestApi;
use PHPUnit\Framework\TestCase;

final class SettingsApiTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_options'] = array();
	}

	public function test_settings_save_is_sanitized_and_has_no_editor_router_option(): void {
		$api      = new RestApi();
		$response = $api->save_settings(
			new WP_REST_Request(
				array(
					'containerMax'     => 99999,
					'editorPreference' => 'canvas',
					'fontFamily'       => 'safe;font}body{display:none',
					'primary'          => '#ABCDEF',
				)
			)
		);
		$settings = $response->get_data();

		self::assertSame( 2560, $settings['containerMax'] );
		self::assertSame( '#abcdef', $settings['primary'] );
		self::assertSame( 2, $settings['schemaVersion'] );
		self::assertArrayNotHasKey( 'editorPreference', $settings );
		self::assertArrayNotHasKey( 'editorPreference', $api->settings_schema()['properties'] );
		self::assertFalse( $api->settings_schema()['additionalProperties'] );
		self::assertSame( $settings, get_option( 'cresco_canvas_settings' ) );
	}
}
