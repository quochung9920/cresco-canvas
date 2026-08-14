<?php
/**
 * Update channel transport, origin, and integrity gates.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Commercial\UpdateManager;
use CrescoCanvas\Support\Logger;
use PHPUnit\Framework\TestCase;

final class UpdateSecurityTest extends TestCase {
	public function test_plaintext_urls_are_rejected(): void {
		self::assertSame( '', UpdateManager::secure_url( 'http://updates.example.com/p.zip' ) );
		self::assertSame( '', UpdateManager::secure_url( 'ftp://updates.example.com/p.zip' ) );
		self::assertSame( '', UpdateManager::secure_url( '' ) );
	}

	public function test_https_urls_are_accepted(): void {
		self::assertSame(
			'https://updates.example.com/p.zip',
			UpdateManager::secure_url( 'https://updates.example.com/p.zip' )
		);
	}

	public function test_host_pinning_compares_case_insensitively(): void {
		self::assertTrue( UpdateManager::same_host( 'https://A.example.com/x', 'https://a.example.com/y' ) );
		self::assertFalse( UpdateManager::same_host( 'https://evil.test/x', 'https://a.example.com/y' ) );
		self::assertFalse( UpdateManager::same_host( '', 'https://a.example.com/y' ) );
	}

	public function test_digest_normalization(): void {
		$hex = str_repeat( 'a1', 32 );
		self::assertSame( $hex, UpdateManager::normalize_digest( $hex ) );
		self::assertSame( $hex, UpdateManager::normalize_digest( strtoupper( $hex ) ) );
		self::assertSame( $hex, UpdateManager::normalize_digest( 'sha256:' . $hex ) );
		// Anything that is not a 64-character hex string is not a usable digest.
		self::assertSame( '', UpdateManager::normalize_digest( 'deadbeef' ) );
		self::assertSame( '', UpdateManager::normalize_digest( '' ) );
		self::assertSame( '', UpdateManager::normalize_digest( str_repeat( 'z', 64 ) ) );
	}

	public function test_manifest_requires_https_package(): void {
		$manifest = array( 'version' => '2.0.0', 'packageUrl' => 'http://updates.example.com/p.zip' );
		self::assertNull( UpdateManager::validate_manifest( $manifest, 'https://updates.example.com/m.json' ) );
	}

	public function test_manifest_rejects_a_package_on_another_host(): void {
		$manifest = array( 'version' => '2.0.0', 'packageUrl' => 'https://evil.test/p.zip' );
		self::assertNull( UpdateManager::validate_manifest( $manifest, 'https://updates.example.com/m.json' ) );
	}

	public function test_manifest_accepts_a_same_host_https_package(): void {
		$manifest = array( 'version' => '2.0.0', 'packageUrl' => 'https://updates.example.com/p.zip' );
		$result   = UpdateManager::validate_manifest( $manifest, 'https://updates.example.com/m.json' );
		self::assertIsArray( $result );
		self::assertSame( 'https://updates.example.com/p.zip', $result['packageUrl'] );
	}

	public function test_manifest_rejects_incomplete_payloads(): void {
		$url = 'https://updates.example.com/m.json';
		self::assertNull( UpdateManager::validate_manifest( null, $url ) );
		self::assertNull( UpdateManager::validate_manifest( array( 'version' => '2.0.0' ), $url ) );
		self::assertNull( UpdateManager::validate_manifest( array( 'packageUrl' => 'https://updates.example.com/p.zip' ), $url ) );
	}

	public function test_logger_redacts_secret_bearing_context(): void {
		$seen = array();
		add_action(
			Logger::ACTION,
			static function ( $level, $message, $context ) use ( &$seen ) {
				$seen = $context;
			}
		);
		Logger::warning( 'test', array( 'token' => 'abc123', 'apiKey' => 'k', 'version' => '2.0.0' ) );

		self::assertSame( '[redacted]', $seen['token'] );
		self::assertSame( '[redacted]', $seen['apiKey'] );
		self::assertSame( '2.0.0', $seen['version'] );
	}
}
