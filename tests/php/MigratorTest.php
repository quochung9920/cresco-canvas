<?php
/**
 * Migration tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Migration\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_post_meta'] = array();
		$GLOBALS['cresco_test_user_meta'] = array();
		$GLOBALS['cresco_test_options'] = array(
			'cresco_canvas_settings' => array(
				'containerMax' => 1440,
				'primary'      => '#123456',
			),
		);
	}

	public function test_migration_is_idempotent_and_preserves_legacy_values(): void {
		self::assertTrue( Migrator::run() );
		self::assertSame( 2, get_option( Migrator::VERSION_OPTION ) );
		self::assertSame( '#123456', get_option( 'cresco_canvas_settings' )['primary'] );
		self::assertSame( 2, get_option( 'cresco_canvas_settings' )['schemaVersion'] );
		self::assertArrayNotHasKey( 'editorPreference', get_option( 'cresco_canvas_settings' ) );

		self::assertTrue( Migrator::run() );
		self::assertSame( 2, get_option( Migrator::VERSION_OPTION ) );
		self::assertArrayNotHasKey( Migrator::LOCK_OPTION, $GLOBALS['cresco_test_options'] );
	}

	public function test_version_two_removes_only_retired_editor_preferences(): void {
		$GLOBALS['cresco_test_options'][ Migrator::VERSION_OPTION ] = 1;
		$GLOBALS['cresco_test_options']['cresco_canvas_settings']['editorPreference'] = 'canvas';
		$GLOBALS['cresco_test_post_meta'][10] = array(
			'_cresco_canvas_enabled'           => true,
			'_cresco_canvas_editor_preference' => 'canvas',
		);
		$GLOBALS['cresco_test_user_meta'][7] = array( 'cresco_canvas_last_editor' => 'canvas' );

		self::assertTrue( Migrator::run() );
		self::assertSame( 2, get_option( Migrator::VERSION_OPTION ) );
		self::assertArrayNotHasKey( 'editorPreference', get_option( 'cresco_canvas_settings' ) );
		self::assertTrue( $GLOBALS['cresco_test_post_meta'][10]['_cresco_canvas_enabled'] );
		self::assertArrayNotHasKey( '_cresco_canvas_editor_preference', $GLOBALS['cresco_test_post_meta'][10] );
		self::assertArrayNotHasKey( 'cresco_canvas_last_editor', $GLOBALS['cresco_test_user_meta'][7] );
	}

	public function test_active_lock_fails_without_mutating_schema_version(): void {
		$GLOBALS['cresco_test_options'][ Migrator::LOCK_OPTION ] = time();
		$result = Migrator::run();

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_canvas_migration_locked', $result->get_error_code() );
		self::assertFalse( get_option( Migrator::VERSION_OPTION, false ) );
	}
}
