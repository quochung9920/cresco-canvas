<?php
use CrescoCanvas\Migration\Migrator;
use CrescoCanvas\Session\SessionManager;
use PHPUnit\Framework\TestCase;

final class MigrationHistoricalFixtureTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_options'] = array();
		$GLOBALS['cresco_test_actions'] = array();
		$GLOBALS['cresco_test_post_meta'] = array();
		$GLOBALS['cresco_test_user_meta'] = array();
	}
	private function fixture( $name ) { return json_decode( (string) file_get_contents( __DIR__ . '/fixtures/migration/' . $name ), true ); }

	public function test_historical_settings_are_migrated_with_backup_and_retired_preference_removed(): void {
		$legacy = $this->fixture( 'settings-v0.json' );
		$GLOBALS['cresco_test_options']['cresco_canvas_settings'] = $legacy;
		self::assertTrue( Migrator::run() );
		self::assertSame( CRESCO_CANVAS_SCHEMA_VERSION, get_option( Migrator::VERSION_OPTION ) );
		self::assertSame( $legacy, get_option( Migrator::SNAPSHOT_OPTION )['settings'] );
		self::assertArrayNotHasKey( 'editorPreference', get_option( 'cresco_canvas_settings' ) );
	}

	public function test_malformed_historical_settings_are_sanitized_without_exception(): void {
		$GLOBALS['cresco_test_options']['cresco_canvas_settings'] = $this->fixture( 'settings-malformed.json' );
		self::assertTrue( Migrator::run() );
		self::assertSame( CRESCO_CANVAS_SCHEMA_VERSION, get_option( Migrator::VERSION_OPTION ) );
		$settings = get_option( 'cresco_canvas_settings' );
		self::assertSame( '', $settings['customCss'] );
		self::assertStringNotContainsString( 'javascript:', $settings['primary'] );
	}

	public function test_historical_session_fixture_is_accepted_but_malformed_session_is_rejected(): void {
		$valid = SessionManager::sanitize_session( $this->fixture( 'session-v1.json' ) );
		self::assertFalse( is_wp_error( $valid ) );
		$invalid = SessionManager::sanitize_session( $this->fixture( 'session-malformed.json' ) );
		self::assertInstanceOf( WP_Error::class, $invalid );
	}
}
