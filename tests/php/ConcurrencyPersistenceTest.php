<?php
/**
 * Persistence/concurrency regression tests for the canonical document boundary.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Builder\WebsiteBuilderConcurrencyGuard;
use CrescoCanvas\Core\Document\Document;
use CrescoCanvas\Infrastructure\WordPress\Storage\WordPressDocumentRepository;
use CrescoCanvas\Session\SessionManager;
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

	private function escaped_css_session(): array {
		return array(
			'schema'     => 'cresco-session/v1',
			'version'    => 1,
			'documentId' => 'page-' . self::POST_ID,
			'nodes'      => array(
				array(
					'id'         => 'heading-escape-test',
					'type'       => 'heading',
					'props'      => array( 'text' => 'Escaped selector', 'level' => 2 ),
					'style'      => array(),
					'responsive' => array(),
					'states'     => array(),
					'customCSS'  => array( 'base' => "& button[type=\"submit\"] {\n\ttransform: translateX(0);\n}" ),
					'meta'       => array(),
					'children'   => array(),
				),
			),
		);
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

	public function test_repository_round_trips_custom_css_with_quoted_attribute_selectors(): void {
		$repository = $this->repository();
		$saved = $repository->save( self::POST_ID, $this->escaped_css_session() );
		self::assertFalse( is_wp_error( $saved ) );
		$loaded = $repository->load( self::POST_ID );
		self::assertFalse( is_wp_error( $loaded ) );
		self::assertSame(
			$save_css = $saved['nodes'][0]['customCSS']['base'],
			$loaded['nodes'][0]['customCSS']['base']
		);
		self::assertStringContainsString( 'button[type="submit"]', $save_css );
	}

	public function test_builder_dispatch_commits_through_verified_repository_and_survives_reload(): void {
		$repository = $this->repository();
		$baseline = $repository->save( self::POST_ID, WebsiteBuilder::empty_session( self::POST_ID ) );
		self::assertFalse( is_wp_error( $baseline ) );
		$checksum = $repository->checksum( self::POST_ID );

		$guard = new WebsiteBuilderConcurrencyGuard( $repository );
		$request = new WP_REST_Request(
			array( 'session' => $this->escaped_css_session(), 'baseChecksum' => $checksum ),
			'/cresco-canvas/v1/website-builder/session/' . self::POST_ID,
			'POST'
		);
		self::assertNull( $guard->guard_session_write( null, null, $request ) );
		$response = $guard->commit_session_write( null, $request, '', array() );
		self::assertInstanceOf( WP_REST_Response::class, $response );
		$response = $guard->verify_and_release( $response, null, $request );
		self::assertSame( 200, $response->get_status() );

		$loaded = $repository->load( self::POST_ID );
		self::assertFalse( is_wp_error( $loaded ) );
		self::assertCount( 1, $loaded['nodes'] );
		self::assertSame(
			"& button[type=\"submit\"] {\n\ttransform: translateX(0);\n}",
			$loaded['nodes'][0]['customCSS']['base']
		);
	}

	public function test_corrupt_legacy_json_can_be_replaced_after_the_editor_falls_back_to_empty_session(): void {
		$GLOBALS['cresco_test_post_meta'][ self::POST_ID ][ SessionManager::META_KEY ] = '{"schema":"cresco-session/v1","nodes":[{"customCSS":"button[type="submit"]"}]}';
		$repository = $this->repository();
		self::assertTrue( is_wp_error( $repository->load( self::POST_ID ) ) );

		$empty_checksum = Document::checksum( WebsiteBuilder::empty_session( self::POST_ID ) );
		$guard = new WebsiteBuilderConcurrencyGuard( $repository );
		$request = new WP_REST_Request(
			array( 'session' => $this->escaped_css_session(), 'baseChecksum' => $empty_checksum ),
			'/cresco-canvas/v1/website-builder/session/' . self::POST_ID,
			'POST'
		);
		self::assertNull( $guard->guard_session_write( null, null, $request ) );
		$response = $guard->commit_session_write( null, $request, '', array() );
		self::assertInstanceOf( WP_REST_Response::class, $response );
		$response = $guard->verify_and_release( $response, null, $request );
		self::assertSame( 200, $response->get_status() );
		self::assertCount( 1, $repository->load( self::POST_ID )['nodes'] );
	}

	public function test_document_json_and_revision_writes_are_pre_slashed_for_wordpress_meta(): void {
		$root = dirname( __DIR__, 2 );
		$repository = file_get_contents( $root . '/includes/Infrastructure/WordPress/Storage/WordPressDocumentRepository.php' );
		$history = file_get_contents( $root . '/includes/Session/HistoryManager.php' );
		$guard = file_get_contents( $root . '/includes/Builder/WebsiteBuilderConcurrencyGuard.php' );

		self::assertIsString( $repository );
		self::assertIsString( $history );
		self::assertIsString( $guard );
		self::assertStringContainsString( "wp_slash( \$json )", $repository );
		self::assertStringContainsString( "self::DOCUMENT_META, function_exists( 'wp_slash' ) ? wp_slash( \$json ) : \$json", $history );
		self::assertStringContainsString( "SessionManager::META_KEY, function_exists( 'wp_slash' ) ? wp_slash( \$json ) : \$json", $history );
		self::assertStringContainsString( "'rest_dispatch_request'", $guard );
		self::assertStringContainsString( '$this->repository->save( $post_id, $session )', $guard );
		self::assertStringContainsString( 'cresco_document_storage_decode', $guard );
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
