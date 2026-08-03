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
		$GLOBALS['cresco_test_options'] = array(
			'cresco_canvas_settings' => array(
				'containerMax' => 1440,
				'primary'      => '#123456',
			),
		);
	}

	public function test_migration_is_idempotent_and_preserves_legacy_values(): void {
		self::assertTrue( Migrator::run() );
		self::assertSame( 1, get_option( Migrator::VERSION_OPTION ) );
		self::assertSame( '#123456', get_option( 'cresco_canvas_settings' )['primary'] );
		self::assertArrayHasKey( 'editorPreference', get_option( 'cresco_canvas_settings' ) );

		self::assertTrue( Migrator::run() );
		self::assertSame( 1, get_option( Migrator::VERSION_OPTION ) );
		self::assertArrayNotHasKey( Migrator::LOCK_OPTION, $GLOBALS['cresco_test_options'] );
	}

	public function test_active_lock_fails_without_mutating_schema_version(): void {
		$GLOBALS['cresco_test_options'][ Migrator::LOCK_OPTION ] = time();
		$result = Migrator::run();

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_canvas_migration_locked', $result->get_error_code() );
		self::assertFalse( get_option( Migrator::VERSION_OPTION, false ) );
	}
}

