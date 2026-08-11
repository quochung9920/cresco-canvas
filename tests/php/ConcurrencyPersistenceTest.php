<?php
/**
 * Persistence/concurrency regression tests for the canonical document boundary.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderConcurrencyGuard;
use CrescoCanvas\Infrastructure\WordPress\Storage\WordPressDocumentRepository;
use PHPUnit\Framework\TestCase;

final class ConcurrencyPersistenceTest extends TestCase {
	private const POST_ID = 91;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['cresco_test_options'] = array();
		$GLOBALS['cresco_test_post_meta'] = array();
		$GLOBALS['cresco_test_posts'][ self::POST_ID ] = (object) array(
			'ID'            => self::POST_ID,
			'post_type'     => 'page',
			'post_status'   => 'draft',
			'post_title'    => 'Concurrency test',
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	private function repository(): WordPressDocumentRepository {
		return new WordPressDocumentRepository();
	}

	public function test_repository_checksum_and_verify_share_the_canonical_document_shape(): void {
		$repository = $this->repository();
		$session = WebsiteBuilder::empty_session( self::POST_ID );
		$saved = $repository->save( self::POST_ID, $session );
		self::assertFalse( is_wp_error( $saved ) );
		$checksum = $repository->checksum( self::POST_ID );
		self::assertIsString( $checksum );
		self::assertNotSame( '', $checksum );
		self::assertTrue( $repository->verify( self::POST_ID, $checksum ) );
		self::assertFalse( $repository->verify( self::POST_ID, str_repeat( '0', 64 ) ) );
	}

	public function test_legacy_session_write_is_fail_closed_without_a_base_checksum(): void {
		$repository = $this->repository();
		$saved = $repository->save( self::POST_ID, WebsiteBuilder::empty_session( self::POST_ID ) );
		self::assertFalse( is_wp_error( $saved ) );

		$guard = new WebsiteBuilderConcurrencyGuard( $repository );
		$request = new WP_REST_Request(
			array( 'session' => $saved ),
			'/cresco-canvas/v1/session/' . self::POST_ID,
			'POST'
		);
		$result = $guard->guard_session_write( null, null, $request );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_builder_precondition_required', $result->get_error_code() );
		self::assertSame( 428, $result->get_error_data()['status'] );
	}

	public function test_legacy_and_builder_routes_use_the_same_checksum_precondition(): void {
		$repository = $this->repository();
		$session = $repository->save( self::POST_ID, WebsiteBuilder::empty_session( self::POST_ID ) );
		self::assertFalse( is_wp_error( $session ) );
		$checksum = $repository->checksum( self::POST_ID );

		foreach ( array(
			'/cresco-canvas/v1/session/' . self::POST_ID,
			'/cresco-canvas/v1/website-builder/session/' . self::POST_ID,
		) as $route ) {
			$guard = new WebsiteBuilderConcurrencyGuard( $repository );
			$request = new WP_REST_Request(
				array( 'session' => $session, 'baseChecksum' => $checksum ),
				$route,
				'POST'
			);
			self::assertNull( $guard->guard_session_write( null, null, $request ) );
			$response = $guard->verify_and_release( new WP_REST_Response( array( 'checksum' => $checksum ), 200 ), null, $request );
			self::assertSame( 200, $response->get_status() );
		}
	}

	public function test_stale_write_is_rejected_before_route_dispatch(): void {
		$repository = $this->repository();
		$session = $repository->save( self::POST_ID, WebsiteBuilder::empty_session( self::POST_ID ) );
		self::assertFalse( is_wp_error( $session ) );
		$guard = new WebsiteBuilderConcurrencyGuard( $repository );
		$request = new WP_REST_Request(
			array( 'session' => $session, 'baseChecksum' => str_repeat( 'a', 64 ) ),
			'/cresco-canvas/v1/website-builder/session/' . self::POST_ID,
			'POST'
		);
		$result = $guard->guard_session_write( null, null, $request );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_builder_conflict', $result->get_error_code() );
		self::assertSame( 409, $result->get_error_data()['status'] );
	}
}
