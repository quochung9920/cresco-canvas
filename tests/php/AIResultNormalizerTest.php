<?php
/**
 * Deterministic normalization of pasted AI results.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\AIResultNormalizer;
use PHPUnit\Framework\TestCase;

final class AIResultNormalizerTest extends TestCase {
	private const PATCH = '{"schema":"cresco-patch/v1","baseChecksum":"abc","target":{"scope":"subtree","nodeId":"x"},"operations":[]}';

	public function test_accepts_raw_json(): void {
		$result = AIResultNormalizer::normalize( self::PATCH );
		self::assertSame( 'cresco-patch/v1', $result['schema'] );
	}

	public function test_accepts_surrounding_whitespace(): void {
		$result = AIResultNormalizer::normalize( "\n\n  " . self::PATCH . "  \n\n" );
		self::assertSame( 'cresco-patch/v1', $result['schema'] );
	}

	public function test_accepts_a_utf8_bom(): void {
		$result = AIResultNormalizer::normalize( "\xEF\xBB\xBF" . self::PATCH );
		self::assertSame( 'cresco-patch/v1', $result['schema'] );
	}

	public function test_accepts_a_json_tagged_fence(): void {
		$result = AIResultNormalizer::normalize( "```json\n" . self::PATCH . "\n```" );
		self::assertSame( 'cresco-patch/v1', $result['schema'] );
	}

	public function test_accepts_an_untagged_fence(): void {
		$result = AIResultNormalizer::normalize( "```\n" . self::PATCH . "\n```" );
		self::assertSame( 'cresco-patch/v1', $result['schema'] );
	}

	public function test_accepts_an_already_decoded_array(): void {
		$result = AIResultNormalizer::normalize( array( 'schema' => 'cresco-patch/v1', 'operations' => array() ) );
		self::assertSame( 'cresco-patch/v1', $result['schema'] );
	}

	public function test_unwraps_the_legacy_session_wrapper(): void {
		$result = AIResultNormalizer::normalize( '{"session":{"schema":"cresco-session/v1","nodes":[]}}' );
		self::assertSame( 'cresco-session/v1', $result['schema'] );
	}

	public function test_a_patch_carrying_a_session_key_is_not_treated_as_a_wrapper(): void {
		$result = AIResultNormalizer::normalize( '{"schema":"cresco-patch/v1","session":{"nodes":[]},"operations":[]}' );
		self::assertSame( 'cresco-patch/v1', $result['schema'] );
	}

	public function test_rejects_prose_around_json(): void {
		// Scanning prose for the first brace would mean guessing which object the
		// user meant, and applying something they never reviewed.
		$result = AIResultNormalizer::normalize( "Here is your patch:\n" . self::PATCH . "\nHope that helps." );
		self::assertTrue( is_wp_error( $result ) );
	}

	public function test_rejects_two_fenced_blocks(): void {
		$result = AIResultNormalizer::normalize( "```json\n" . self::PATCH . "\n```\n\n```json\n" . self::PATCH . "\n```" );
		self::assertTrue( is_wp_error( $result ) );
	}

	public function test_rejects_malformed_json(): void {
		self::assertTrue( is_wp_error( AIResultNormalizer::normalize( '{"schema":"cresco-patch/v1", "operations": [' ) ) );
	}

	public function test_rejects_empty_and_non_string_input(): void {
		self::assertTrue( is_wp_error( AIResultNormalizer::normalize( '' ) ) );
		self::assertTrue( is_wp_error( AIResultNormalizer::normalize( null ) ) );
		self::assertTrue( is_wp_error( AIResultNormalizer::normalize( 42 ) ) );
	}

	public function test_rejects_an_unterminated_fence(): void {
		self::assertTrue( is_wp_error( AIResultNormalizer::normalize( "```json\n" . self::PATCH ) ) );
	}
}
