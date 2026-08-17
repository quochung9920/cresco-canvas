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
					'button'           => array(
						'background' => '#123456',
						'text' => '#ffffff',
						'hoverBackground' => '#234567',
						'hoverText' => '#ffffff',
						'activeBackground' => '#345678',
						'activeText' => '#f4f4f4',
						'borderColor' => '#456789',
						'borderWidth' => '2px',
						'radius' => '14px',
						'height' => '48px',
						'paddingInline' => '22px',
						'fontWeight' => '700',
					),
				)
			)
		);
		$settings = $response->get_data();

		self::assertSame( 2560, $settings['containerMax'] );
		self::assertSame( '#abcdef', $settings['primary'] );
		self::assertSame( '#123456', $settings['button']['background'] );
		self::assertSame( '#345678', $settings['button']['activeBackground'] );
		self::assertSame( '#f4f4f4', $settings['button']['activeText'] );
		self::assertSame( '14px', $settings['button']['radius'] );
		self::assertSame( '700', $settings['button']['fontWeight'] );
		self::assertSame( 4, $settings['schemaVersion'] );
		self::assertArrayNotHasKey( 'editorPreference', $settings );
		self::assertArrayNotHasKey( 'editorPreference', $api->settings_schema()['properties'] );
		self::assertArrayHasKey( 'button', $api->settings_schema()['properties'] );
		self::assertArrayHasKey( 'activeBackground', $api->settings_schema()['properties']['button']['properties'] );
		self::assertArrayHasKey( 'activeText', $api->settings_schema()['properties']['button']['properties'] );
		self::assertFalse( $api->settings_schema()['properties']['button']['additionalProperties'] );
		self::assertFalse( $api->settings_schema()['additionalProperties'] );
		self::assertSame( $settings, get_option( 'cresco_canvas_settings' ) );
	}

	public function test_import_preview_does_not_save_until_settings_are_applied(): void {
		$api = new RestApi();
		$response = $api->preview_settings_import(
			new WP_REST_Request(
				array(
					'input' => '--bg: oklch(98% 0.005 250); --blue: oklch(55% 0.15 235); font-family: Poppins, sans-serif;',
				)
			)
		);
		$result = $response->get_data();

		self::assertTrue( $result['valid'] );
		self::assertSame( 'oklch(98% 0.005 250)', $result['settings']['background'] );
		self::assertSame( 'oklch(55% 0.15 235)', $result['settings']['primary'] );
		self::assertSame( 'Poppins, sans-serif', $result['settings']['fontFamily'] );
		self::assertSame( array(), get_option( 'cresco_canvas_settings', array() ) );
	}
}