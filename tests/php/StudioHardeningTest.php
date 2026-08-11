<?php

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderConcurrencyGuard;
use CrescoCanvas\Builder\WebsiteBuilderSessionIsolation;
use CrescoCanvas\Security\PublicRequestAtomicity;
use PHPUnit\Framework\TestCase;

final class StudioHardeningTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_options'] = array();
		$GLOBALS['cresco_test_posts'] = array();
		$GLOBALS['cresco_test_post_meta'] = array();
		$GLOBALS['cresco_test_transients'] = array();
		$GLOBALS['cresco_test_capabilities'] = array();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	}

	public function test_session_write_requires_the_checksum_loaded_by_the_editor(): void {
		$post_id = 42;
		$GLOBALS['cresco_test_posts'][ $post_id ] = (object) array( 'ID' => $post_id, 'post_type' => 'page', 'post_title' => 'Hardening' );
		$session = WebsiteBuilder::empty_session( $post_id );
		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$checksum = hash( 'sha256', $json );
		$guard = new WebsiteBuilderConcurrencyGuard();

		$missing = new WP_REST_Request( array( 'postId' => $post_id, 'session' => $session ), '/cresco-canvas/v1/website-builder/session/' . $post_id, 'POST' );
		$result = $guard->guard_session_write( null, null, $missing );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 428, $result->get_error_data()['status'] );

		$stale = new WP_REST_Request( array( 'postId' => $post_id, 'session' => $session, 'baseChecksum' => str_repeat( '0', 64 ) ), '/cresco-canvas/v1/website-builder/session/' . $post_id, 'POST' );
		$result = $guard->guard_session_write( null, null, $stale );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 409, $result->get_error_data()['status'] );

		$current = new WP_REST_Request( array( 'postId' => $post_id, 'session' => $session, 'baseChecksum' => $checksum ), '/cresco-canvas/v1/website-builder/session/' . $post_id, 'POST' );
		self::assertNull( $guard->guard_session_write( null, null, $current ) );
	}

	public function test_builder_owned_documents_reject_legacy_session_writes(): void {
		$post_id = 43;
		$GLOBALS['cresco_test_posts'][ $post_id ] = (object) array( 'ID' => $post_id, 'post_type' => 'page', 'post_title' => 'Builder owned' );
		$GLOBALS['cresco_test_post_meta'][ $post_id ][ WebsiteBuilder::BUILDER_META ] = WebsiteBuilder::BUILDER_VERSION;
		$guard = new WebsiteBuilderSessionIsolation();
		$request = new WP_REST_Request( array( 'postId' => $post_id ), '/cresco-canvas/v1/session/' . $post_id, 'POST' );
		$result = $guard->block_legacy_write( null, null, $request );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_legacy_session_write_blocked', $result->get_error_code() );
		self::assertSame( 409, $result->get_error_data()['status'] );
	}

	public function test_public_rate_and_idempotency_state_is_serialized_across_requests(): void {
		$atomic = new PublicRequestAtomicity();
		$route = '/cresco-canvas/v1/forms/submit';
		$headers = array( 'x-cresco-idempotency-key' => 'same-submit' );
		$body = '{"formId":"contact","fields":{"email":"a@example.test"}}';
		$first = new WP_REST_Request( array( 'formId' => 'contact' ), $route, 'POST', $body, $headers );
		$second = new WP_REST_Request( array( 'formId' => 'contact' ), $route, 'POST', $body, $headers );

		self::assertNull( $atomic->acquire( null, null, $first ) );
		$blocked = $atomic->acquire( null, null, $second );
		self::assertInstanceOf( WP_Error::class, $blocked );
		self::assertSame( 'cresco_request_busy', $blocked->get_error_code() );
		$atomic->release( null, null, $first );
		self::assertNull( $atomic->acquire( null, null, $second ) );
		$atomic->release( null, null, $second );
	}
}
