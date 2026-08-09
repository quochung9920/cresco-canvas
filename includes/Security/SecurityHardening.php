<?php
/**
 * Central security controls for Cresco REST endpoints and outbound delivery.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Security;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SecurityHardening {
	const MAX_DEFAULT_JSON_BYTES   = 1048576;
	const MAX_FORM_JSON_BYTES      = 262144;
	const MAX_DYNAMIC_JSON_BYTES   = 131072;
	const MAX_CAPTCHA_JSON_BYTES   = 16384;
	const MAX_MULTIPART_BYTES      = 8388608;
	const IDEMPOTENCY_TTL          = 600;
	const IDEMPOTENCY_PENDING_TTL  = 60;
	const RATE_WINDOW              = 60;

	/** @var array<int,string> Pending idempotency transient keys owned by the current REST request objects. */
	private $pending_idempotency = array();

	/** Register request and HTTP safety controls. */
	public function register() {
		add_filter( 'rest_pre_dispatch', array( $this, 'protect_rest_request' ), 5, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'finalize_idempotent_request' ), 5, 3 );
		add_filter( 'pre_http_request', array( $this, 'protect_outbound_request' ), 5, 3 );
		add_filter( 'http_request_args', array( $this, 'harden_webhook_args' ), 20, 2 );
	}

	/**
	 * Apply payload bounds to all Cresco writes and abuse controls to public routes.
	 *
	 * WordPress REST cookie authentication validates its REST nonce before endpoint
	 * permission callbacks. Cresco therefore relies on route capabilities for
	 * authenticated routes rather than duplicating nonce checks here.
	 */
	public function protect_rest_request( $result, $server, $request ) {
		unset( $server );
		if ( ! $request instanceof WP_REST_Request || null !== $result ) {
			return $result;
		}

		$route = (string) $request->get_route();
		if ( ! str_starts_with( $route, '/cresco-canvas/v1/' ) ) {
			return $result;
		}

		$method = strtoupper( (string) $request->get_method() );
		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			$limit = self::payload_limit_for_route( $route );
			$size  = self::request_size( $request );
			if ( $size > $limit ) {
				return new WP_Error(
					'cresco_request_too_large',
					__( 'The request is too large.', 'cresco-canvas' ),
					array( 'status' => 413, 'limit' => $limit )
				);
			}
		}

		$shape = self::validate_public_shape( $route, $request );
		if ( is_wp_error( $shape ) ) {
			return $shape;
		}

		$rate_limit = self::public_rate_limit_for_route( $route );
		if ( $rate_limit > 0 ) {
			$form_id  = $this->request_form_id( $request );
			$identity = $this->request_identity();
			$rate_key = 'cc_rate_' . substr( hash( 'sha256', $route . '|' . $form_id . '|' . $identity ), 0, 40 );
			$count    = (int) get_transient( $rate_key );
			if ( $count >= $rate_limit ) {
				return new WP_Error( 'cresco_rate_limited', __( 'Too many requests. Please wait and try again.', 'cresco-canvas' ), array( 'status' => 429 ) );
			}
			set_transient( $rate_key, $count + 1, self::RATE_WINDOW );
		}

		$duplicate_key = $this->idempotency_key_for_request( $request );
		if ( $duplicate_key ) {
			if ( get_transient( $duplicate_key ) ) {
				return new WP_Error( 'cresco_duplicate_submission', __( 'This submission was already received.', 'cresco-canvas' ), array( 'status' => 409 ) );
			}
			set_transient( $duplicate_key, 'pending', self::IDEMPOTENCY_PENDING_TTL );
			$this->pending_idempotency[ spl_object_id( $request ) ] = $duplicate_key;
		}

		return $result;
	}

	/** Mark only successful form requests complete; failed callbacks release their pending key. */
	public function finalize_idempotent_request( $response, $server, $request ) {
		unset( $server );
		if ( ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$request_id = spl_object_id( $request );
		if ( empty( $this->pending_idempotency[ $request_id ] ) ) {
			return $response;
		}

		$key = $this->pending_idempotency[ $request_id ];
		unset( $this->pending_idempotency[ $request_id ] );
		$status = is_object( $response ) && method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 500;
		if ( $status >= 200 && $status < 300 ) {
			set_transient( $key, 'complete', self::IDEMPOTENCY_TTL );
		} else {
			delete_transient( $key );
		}
		return $response;
	}

	/** Return a hard byte limit for a Cresco REST route. */
	public static function payload_limit_for_route( $route ) {
		$route = (string) $route;
		if ( '/cresco-canvas/v1/forms/submit-multipart' === $route ) {
			return self::MAX_MULTIPART_BYTES;
		}
		if ( '/cresco-canvas/v1/forms/submit' === $route ) {
			return self::MAX_FORM_JSON_BYTES;
		}
		if ( '/cresco-canvas/v1/forms/verify-captcha' === $route ) {
			return self::MAX_CAPTCHA_JSON_BYTES;
		}
		if ( in_array( $route, array( '/cresco-canvas/v1/dynamic/interactive-query', '/cresco-canvas/v1/dynamic/facet-counts' ), true ) ) {
			return self::MAX_DYNAMIC_JSON_BYTES;
		}
		return self::MAX_DEFAULT_JSON_BYTES;
	}

	/** Return the anonymous per-minute rate bound for public Cresco routes. */
	public static function public_rate_limit_for_route( $route ) {
		$limits = array(
			'/cresco-canvas/v1/forms/submit'              => 12,
			'/cresco-canvas/v1/forms/submit-multipart'    => 8,
			'/cresco-canvas/v1/forms/verify-captcha'      => 20,
			'/cresco-canvas/v1/dynamic/interactive-query' => 40,
			'/cresco-canvas/v1/dynamic/facet-counts'      => 3,
		);
		return (int) ( $limits[ (string) $route ] ?? 0 );
	}

	/** Count actual uploaded file leaves instead of relying on the top-level $_FILES shape. */
	public static function upload_file_count( $files ) {
		$count = 0;
		foreach ( (array) $files as $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			if ( array_key_exists( 'tmp_name', $file ) ) {
				$count += self::scalar_file_leaf_count( $file['tmp_name'] );
			} else {
				$count += self::upload_file_count( $file );
			}
		}
		return $count;
	}

	private static function scalar_file_leaf_count( $value ) {
		if ( is_array( $value ) ) {
			$total = 0;
			foreach ( $value as $item ) {
				$total += self::scalar_file_leaf_count( $item );
			}
			return $total;
		}
		return '' !== (string) $value ? 1 : 0;
	}

	/** Reject high-cost public request shapes before endpoint callbacks allocate queries. */
	public static function validate_public_shape( $route, WP_REST_Request $request ) {
		$route = (string) $route;
		if ( in_array( $route, array( '/cresco-canvas/v1/dynamic/interactive-query', '/cresco-canvas/v1/dynamic/facet-counts' ), true ) ) {
			$params = (array) $request->get_json_params();
			if ( '/cresco-canvas/v1/dynamic/interactive-query' === $route && absint( $params['page'] ?? 1 ) > 100 ) {
				return new WP_Error( 'cresco_query_page_limit', __( 'The requested page exceeds the public query limit.', 'cresco-canvas' ), array( 'status' => 422 ) );
			}
			$filters = isset( $params['filters'] ) && is_array( $params['filters'] ) ? $params['filters'] : array();
			$tax     = isset( $filters['tax'] ) && is_array( $filters['tax'] ) ? $filters['tax'] : array();
			if ( count( $tax ) > 3 ) {
				return new WP_Error( 'cresco_query_filter_limit', __( 'Too many taxonomy filters were requested.', 'cresco-canvas' ), array( 'status' => 422 ) );
			}
			foreach ( $tax as $terms ) {
				if ( is_array( $terms ) && count( $terms ) > 12 ) {
					return new WP_Error( 'cresco_query_term_limit', __( 'Too many terms were requested for one filter.', 'cresco-canvas' ), array( 'status' => 422 ) );
				}
			}
		}
		if ( in_array( $route, array( '/cresco-canvas/v1/forms/submit', '/cresco-canvas/v1/forms/submit-multipart' ), true ) ) {
			$params = array_merge( (array) $request->get_json_params(), (array) $request->get_body_params() );
			if ( isset( $params['fields'] ) && is_array( $params['fields'] ) && count( $params['fields'] ) > 50 ) {
				return new WP_Error( 'cresco_form_field_limit', __( 'Too many form fields were submitted.', 'cresco-canvas' ), array( 'status' => 422 ) );
			}
			if ( '/cresco-canvas/v1/forms/submit-multipart' === $route && self::upload_file_count( (array) $request->get_file_params() ) > 5 ) {
				return new WP_Error( 'cresco_upload_count_limit', __( 'Too many files were uploaded.', 'cresco-canvas' ), array( 'status' => 413 ) );
			}
		}
		return true;
	}

	/** Block Cresco-signed webhook traffic to unsafe network destinations. */
	public function protect_outbound_request( $preempt, $args, $url ) {
		if ( null !== $preempt || ! self::is_cresco_webhook( $args ) ) {
			return $preempt;
		}
		$safe = self::validate_public_https_url( (string) $url );
		return is_wp_error( $safe ) ? $safe : $preempt;
	}

	/** Force conservative transport behavior for Cresco webhook requests. */
	public function harden_webhook_args( $args, $url ) {
		unset( $url );
		if ( ! self::is_cresco_webhook( $args ) ) {
			return $args;
		}
		$args['redirection']         = 0;
		$args['timeout']             = min( 8, max( 2, absint( $args['timeout'] ?? 5 ) ) );
		$args['reject_unsafe_urls']  = true;
		$args['sslverify']           = true;
		$args['limit_response_size'] = 65536;
		return $args;
	}

	/** Validate a webhook URL and every DNS answer against public-network policy. */
	public static function validate_public_https_url( $url, $resolver = null ) {
		$parts = wp_parse_url( trim( (string) $url ) );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'cresco_webhook_unsafe_url', __( 'Webhook URLs must use HTTPS.', 'cresco-canvas' ) );
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new WP_Error( 'cresco_webhook_credentials', __( 'Webhook URLs cannot contain credentials.', 'cresco-canvas' ) );
		}
		$port          = isset( $parts['port'] ) ? absint( $parts['port'] ) : 443;
		$allowed_ports = apply_filters( 'cresco_canvas_webhook_allowed_ports', array( 443 ) );
		$allowed_ports = array_values( array_unique( array_filter( array_map( 'absint', (array) $allowed_ports ) ) ) );
		if ( ! in_array( $port, $allowed_ports, true ) ) {
			return new WP_Error( 'cresco_webhook_unsafe_port', __( 'Webhook URLs use a port that is not allowed.', 'cresco-canvas' ) );
		}

		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( '' === $host || in_array( $host, array( 'localhost', 'localhost.localdomain' ), true ) || str_ends_with( $host, '.local' ) ) {
			return new WP_Error( 'cresco_webhook_private_host', __( 'Webhook URLs cannot target local hosts.', 'cresco-canvas' ) );
		}

		$addresses = is_callable( $resolver ) ? call_user_func( $resolver, $host ) : self::resolve_addresses( $host );
		$addresses = array_values( array_unique( array_filter( array_map( 'strval', is_array( $addresses ) ? $addresses : array() ) ) ) );
		if ( ! $addresses ) {
			return new WP_Error( 'cresco_webhook_dns_failed', __( 'The webhook host could not be resolved safely.', 'cresco-canvas' ) );
		}
		foreach ( $addresses as $address ) {
			if ( ! self::is_public_ip( $address ) ) {
				return new WP_Error( 'cresco_webhook_private_network', __( 'Webhook URLs cannot target private or reserved networks.', 'cresco-canvas' ) );
			}
		}
		return true;
	}

	/** Return true only for globally routable IPv4/IPv6 addresses. */
	public static function is_public_ip( $address ) {
		return false !== filter_var( (string) $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/** Recursively redact credential-shaped data before diagnostics or logging. */
	public static function redact_sensitive( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$output = array();
		foreach ( $value as $key => $item ) {
			$name = strtolower( (string) $key );
			if ( preg_match( '/(?:password|passwd|secret|token|authorization|cookie|captcha|signature|api[_-]?key|private[_-]?key)/i', $name ) ) {
				$output[ $key ] = '[REDACTED]';
			} else {
				$output[ $key ] = ( is_array( $item ) || is_object( $item ) ) ? self::redact_sensitive( $item ) : $item;
			}
		}
		return $output;
	}

	private static function is_cresco_webhook( $args ) {
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? array_change_key_case( $args['headers'], CASE_LOWER ) : array();
		return isset( $headers['x-cresco-signature'] );
	}

	private static function resolve_addresses( $host ) {
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( $host );
		}
		$addresses = array();
		if ( function_exists( 'dns_get_record' ) ) {
			$dns_type = defined( 'DNS_AAAA' ) ? DNS_A | DNS_AAAA : DNS_A;
			$records  = @dns_get_record( $host, $dns_type ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			foreach ( is_array( $records ) ? $records : array() as $record ) {
				if ( ! empty( $record['ip'] ) ) {
					$addresses[] = $record['ip'];
				}
				if ( ! empty( $record['ipv6'] ) ) {
					$addresses[] = $record['ipv6'];
				}
			}
		}
		if ( ! $addresses ) {
			$fallback = gethostbyname( $host );
			if ( $fallback && $fallback !== $host ) {
				$addresses[] = $fallback;
			}
		}
		return array_values( array_unique( $addresses ) );
	}

	private static function request_size( WP_REST_Request $request ) {
		$content_length = absint( $request->get_header( 'content-length' ) );
		if ( 0 === $content_length && isset( $_SERVER['CONTENT_LENGTH'] ) ) {
			$content_length = absint( $_SERVER['CONTENT_LENGTH'] );
		}
		if ( 0 === $content_length ) {
			$content_length = strlen( (string) $request->get_body() );
			$content_length += self::file_payload_size( (array) $request->get_file_params() );
		}
		return $content_length;
	}

	/** Sum uploaded file sizes when an upstream server omits Content-Length. */
	private static function file_payload_size( $files ) {
		$total = 0;
		foreach ( (array) $files as $file ) {
			if ( is_array( $file ) && isset( $file['size'] ) && is_scalar( $file['size'] ) ) {
				$total += max( 0, (int) $file['size'] );
			} elseif ( is_array( $file ) ) {
				$total += self::file_payload_size( $file );
			}
		}
		return $total;
	}

	private static function request_fingerprint( WP_REST_Request $request ) {
		$body = (string) $request->get_body();
		if ( '' !== $body ) {
			return $body;
		}
		return (string) wp_json_encode( array( $request->get_body_params(), array_keys( (array) $request->get_file_params() ) ) );
	}

	private function idempotency_key_for_request( WP_REST_Request $request ) {
		$route = (string) $request->get_route();
		if ( ! in_array( $route, array( '/cresco-canvas/v1/forms/submit', '/cresco-canvas/v1/forms/submit-multipart' ), true ) ) {
			return '';
		}
		$idempotency = substr( preg_replace( '/[^a-zA-Z0-9_-]/', '', sanitize_text_field( (string) $request->get_header( 'x-cresco-idempotency-key' ) ) ), 0, 80 );
		if ( '' === $idempotency ) {
			return '';
		}
		$body_hash = substr( hash( 'sha256', self::request_fingerprint( $request ) ), 0, 24 );
		return 'cc_once_' . substr( hash( 'sha256', $this->request_form_id( $request ) . '|' . $idempotency . '|' . $body_hash ), 0, 40 );
	}

	private function request_form_id( WP_REST_Request $request ) {
		$params = array_merge( (array) $request->get_json_params(), (array) $request->get_body_params() );
		if ( ! empty( $params['formId'] ) ) {
			return substr( sanitize_key( (string) $params['formId'] ), 0, 48 );
		}
		if ( ! empty( $params['payload'] ) ) {
			return 'signed-' . substr( hash( 'sha256', (string) $params['payload'] ), 0, 12 );
		}
		return 'unknown';
	}

	private function request_identity() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		return substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 32 );
	}
}
