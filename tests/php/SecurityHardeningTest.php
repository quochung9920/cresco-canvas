<?php
use CrescoCanvas\API\RestApi;
use CrescoCanvas\Dynamic\QueryCache;
use CrescoCanvas\Security\SecurityHardening;
use PHPUnit\Framework\TestCase;

final class SecurityHardeningTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_transients'] = array();
		$GLOBALS['cresco_test_routes'] = array();
		$GLOBALS['cresco_test_capabilities'] = array();
		$GLOBALS['cresco_test_filters'] = array();
	}

	public function test_admin_rest_route_rejects_user_without_required_capability(): void {
		$api = new RestApi();
		$api->register_routes();
		$GLOBALS['cresco_test_capabilities']['edit_theme_options'] = false;
		$route = $GLOBALS['cresco_test_routes']['cresco-canvas/v1/settings'];
		self::assertFalse( call_user_func( $route[0]['permission_callback'] ) );
	}

	public function test_large_rest_payload_is_rejected_before_callback(): void {
		$guard = new SecurityHardening();
		$request = new WP_REST_Request( array(), '/cresco-canvas/v1/forms/submit', 'POST', '', array( 'content-length' => (string) ( SecurityHardening::MAX_FORM_JSON_BYTES + 1 ) ) );
		$result = $guard->protect_rest_request( null, null, $request );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_request_too_large', $result->get_error_code() );
		self::assertSame( 413, $result->get_error_data()['status'] );
	}

	public function test_upload_count_uses_actual_file_leaves_for_nested_file_params(): void {
		$files = array(
			'fields' => array( 'tmp_name' => array( 'a' => '/tmp/a', 'b' => '/tmp/b' ) ),
			'c' => array( 'tmp_name' => '/tmp/c' ),
		);
		self::assertSame( 3, SecurityHardening::upload_file_count( $files ) );
	}

	public function test_multipart_size_falls_back_to_uploaded_file_sizes(): void {
		$guard = new SecurityHardening();
		$files = array(
			'one' => array( 'size' => 5 * 1024 * 1024 ),
			'two' => array( 'size' => 4 * 1024 * 1024 ),
		);
		$request = new WP_REST_Request( array(), '/cresco-canvas/v1/forms/submit-multipart', 'POST', '', array(), $files );
		$result = $guard->protect_rest_request( null, null, $request );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_request_too_large', $result->get_error_code() );
	}

	public function test_public_query_shape_is_bounded(): void {
		$request = new WP_REST_Request( array( 'page' => 101, 'filters' => array() ), '/cresco-canvas/v1/dynamic/interactive-query', 'POST' );
		$result = SecurityHardening::validate_public_shape( '/cresco-canvas/v1/dynamic/interactive-query', $request );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_query_page_limit', $result->get_error_code() );
	}

	public function test_facet_counts_has_tight_public_rate_budget(): void {
		self::assertSame( 3, SecurityHardening::public_rate_limit_for_route( '/cresco-canvas/v1/dynamic/facet-counts' ) );
	}

	public function test_facet_filter_shape_and_cache_key_are_bounded_and_stable(): void {
		$request = new WP_REST_Request( array( 'filters' => array( 'tax' => array( 'category' => range( 1, 13 ) ) ) ), '/cresco-canvas/v1/dynamic/facet-counts', 'POST' );
		$result = SecurityHardening::validate_public_shape( '/cresco-canvas/v1/dynamic/facet-counts', $request );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_query_term_limit', $result->get_error_code() );

		$a = new WP_REST_Request( array( 'b' => 2, 'a' => array( 'y' => 2, 'x' => 1 ) ), '/cresco-canvas/v1/dynamic/interactive-query', 'POST' );
		$b = new WP_REST_Request( array( 'a' => array( 'x' => 1, 'y' => 2 ), 'b' => 2 ), '/cresco-canvas/v1/dynamic/interactive-query', 'POST' );
		self::assertSame( QueryCache::key_for_request( $a ), QueryCache::key_for_request( $b ) );
	}

	public function test_webhook_rejects_private_ipv4_and_ipv6_answers(): void {
		foreach ( array( '127.0.0.1', '10.0.0.4', '169.254.169.254', '::1', 'fc00::1', 'fe80::1' ) as $ip ) {
			$result = SecurityHardening::validate_public_https_url( 'https://hooks.example.test/event', static fn() => array( $ip ) );
			self::assertInstanceOf( WP_Error::class, $result, $ip );
			self::assertSame( 'cresco_webhook_private_network', $result->get_error_code(), $ip );
		}
	}

	public function test_webhook_rejects_mixed_dns_answers_and_disables_redirects(): void {
		$result = SecurityHardening::validate_public_https_url( 'https://hooks.example.test/event', static fn() => array( '93.184.216.34', '10.0.0.2' ) );
		self::assertInstanceOf( WP_Error::class, $result );
		$guard = new SecurityHardening();
		$args = $guard->harden_webhook_args( array( 'headers' => array( 'X-Cresco-Signature' => 'sha256=x' ), 'timeout' => 99, 'redirection' => 8 ), 'https://hooks.example.test' );
		self::assertSame( 0, $args['redirection'] );
		self::assertSame( 8, $args['timeout'] );
		self::assertSame( 65536, $args['limit_response_size'] );
		self::assertTrue( $args['reject_unsafe_urls'] );
	}

	public function test_webhook_requires_https_without_credentials_or_custom_port(): void {
		$http = SecurityHardening::validate_public_https_url( 'http://hooks.example.test/event', static fn() => array( '93.184.216.34' ) );
		self::assertInstanceOf( WP_Error::class, $http );
		self::assertSame( 'cresco_webhook_unsafe_url', $http->get_error_code() );

		$credentials = SecurityHardening::validate_public_https_url( 'https://user:secret@hooks.example.test/event', static fn() => array( '93.184.216.34' ) );
		self::assertInstanceOf( WP_Error::class, $credentials );
		self::assertSame( 'cresco_webhook_credentials', $credentials->get_error_code() );

		$port = SecurityHardening::validate_public_https_url( 'https://hooks.example.test:8443/event', static fn() => array( '93.184.216.34' ) );
		self::assertInstanceOf( WP_Error::class, $port );
		self::assertSame( 'cresco_webhook_unsafe_port', $port->get_error_code() );
	}

	public function test_webhook_fails_closed_when_dns_is_empty_and_accepts_public_answers(): void {
		$empty = SecurityHardening::validate_public_https_url( 'https://hooks.example.test/event', static fn() => array() );
		self::assertInstanceOf( WP_Error::class, $empty );
		self::assertSame( 'cresco_webhook_dns_failed', $empty->get_error_code() );

		self::assertTrue(
			SecurityHardening::validate_public_https_url(
				'https://hooks.example.test/event',
				static fn() => array( '93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946' )
			)
		);
	}

	public function test_sensitive_diagnostics_are_redacted_recursively(): void {
		$result = SecurityHardening::redact_sensitive( array( 'status'=>'ok', 'authorization'=>'Bearer secret', 'nested'=>array( 'captchaToken'=>'abc', 'name'=>'safe' ) ) );
		self::assertSame( '[REDACTED]', $result['authorization'] );
		self::assertSame( '[REDACTED]', $result['nested']['captchaToken'] );
		self::assertSame( 'safe', $result['nested']['name'] );
	}
}
