<?php
/**
 * Central security controls for public Cresco endpoints and outbound delivery.
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
	const MAX_JSON_BYTES      = 262144;
	const MAX_MULTIPART_BYTES = 8388608;
	const IDEMPOTENCY_TTL     = 600;
	const RATE_WINDOW         = 60;
	const RATE_LIMIT          = 12;

	/** Register request and HTTP safety controls. */
	public function register() {
		add_filter( 'rest_pre_dispatch', array( $this, 'protect_rest_request' ), 5, 3 );
		add_filter( 'pre_http_request', array( $this, 'protect_outbound_request' ), 5, 3 );
		add_filter( 'http_request_args', array( $this, 'harden_webhook_args' ), 20, 2 );
	}

	/** Bound public Cresco form requests and reject rapid duplicate submissions. */
	public function protect_rest_request( $result, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request || null !== $result ) {
			return $result;
		}

		$route = (string) $request->get_route();
		$public_routes = array(
			'/cresco-canvas/v1/forms/submit',
			'/cresco-canvas/v1/forms/submit-multipart',
			'/cresco-canvas/v1/forms/verify-captcha',
		);
		if ( ! in_array( $route, $public_routes, true ) ) {
			return $result;
		}

		$content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? absint( $_SERVER['CONTENT_LENGTH'] ) : strlen( (string) $request->get_body() );
		$limit = str_contains( $route, 'multipart' ) ? self::MAX_MULTIPART_BYTES : self::MAX_JSON_BYTES;
		if ( $content_length > $limit ) {
			return new WP_Error( 'cresco_request_too_large', __( 'The request is too large.', 'cresco-canvas' ), array( 'status' => 413 ) );
		}

		$form_id = $this->request_form_id( $request );
		$identity = $this->request_identity();
		$rate_key = 'cc_rate_' . substr( hash( 'sha256', $route . '|' . $form_id . '|' . $identity ), 0, 40 );
		$count = (int) get_transient( $rate_key );
		if ( $count >= self::RATE_LIMIT ) {
			return new WP_Error( 'cresco_rate_limited', __( 'Too many requests. Please wait and try again.', 'cresco-canvas' ), array( 'status' => 429 ) );
		}
		set_transient( $rate_key, $count + 1, self::RATE_WINDOW );

		if ( str_contains( $route, '/forms/submit' ) ) {
			$idempotency = sanitize_text_field( (string) $request->get_header( 'x-cresco-idempotency-key' ) );
			if ( '' === $idempotency ) {
				$idempotency = substr( hash( 'sha256', (string) $request->get_body() . '|' . $identity ), 0, 48 );
			}
			$idempotency = substr( preg_replace( '/[^a-zA-Z0-9_-]/', '', $idempotency ), 0, 80 );
			if ( '' !== $idempotency ) {
				$duplicate_key = 'cc_once_' . substr( hash( 'sha256', $form_id . '|' . $idempotency ), 0, 40 );
				if ( get_transient( $duplicate_key ) ) {
					return new WP_Error( 'cresco_duplicate_submission', __( 'This submission was already received.', 'cresco-canvas' ), array( 'status' => 409 ) );
				}
				set_transient( $duplicate_key, 1, self::IDEMPOTENCY_TTL );
			}
		}

		return $result;
	}

	/** Block Cresco-signed webhook traffic to unsafe network destinations. */
	public function protect_outbound_request( $preempt, $args, $url ) {
		if ( null !== $preempt || ! $this->is_cresco_webhook( $args ) ) {
			return $preempt;
		}
		$safe = $this->validate_public_https_url( (string) $url );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		return $preempt;
	}

	/** Force conservative transport behavior for Cresco webhook requests. */
	public function harden_webhook_args( $args, $url ) {
		if ( ! $this->is_cresco_webhook( $args ) ) {
			return $args;
		}
		$args['redirection'] = 0;
		$args['timeout'] = min( 8, max( 2, absint( $args['timeout'] ?? 5 ) ) );
		$args['reject_unsafe_urls'] = true;
		$args['sslverify'] = true;
		$args['limit_response_size'] = 65536;
		return $args;
	}

	private function is_cresco_webhook( $args ) {
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? array_change_key_case( $args['headers'], CASE_LOWER ) : array();
		return isset( $headers['x-cresco-signature'] );
	}

	private function validate_public_https_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'cresco_webhook_unsafe_url', __( 'Webhook URLs must use HTTPS.', 'cresco-canvas' ) );
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new WP_Error( 'cresco_webhook_credentials', __( 'Webhook URLs cannot contain credentials.', 'cresco-canvas' ) );
		}
		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( in_array( $host, array( 'localhost', 'localhost.localdomain' ), true ) || str_ends_with( $host, '.local' ) ) {
			return new WP_Error( 'cresco_webhook_private_host', __( 'Webhook URLs cannot target local hosts.', 'cresco-canvas' ) );
		}
		$addresses = $this->resolve_addresses( $host );
		if ( ! $addresses ) {
			return new WP_Error( 'cresco_webhook_dns_failed', __( 'The webhook host could not be resolved safely.', 'cresco-canvas' ) );
		}
		foreach ( $addresses as $address ) {
			if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return new WP_Error( 'cresco_webhook_private_network', __( 'Webhook URLs cannot target private or reserved networks.', 'cresco-canvas' ) );
			}
		}
		return true;
	}

	private function resolve_addresses( $host ) {
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( $host );
		}
		$addresses = array();
		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_A | DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			foreach ( is_array( $records ) ? $records : array() as $record ) {
				if ( ! empty( $record['ip'] ) ) $addresses[] = $record['ip'];
				if ( ! empty( $record['ipv6'] ) ) $addresses[] = $record['ipv6'];
			}
		}
		if ( ! $addresses ) {
			$fallback = gethostbyname( $host );
			if ( $fallback && $fallback !== $host ) $addresses[] = $fallback;
		}
		return array_values( array_unique( $addresses ) );
	}

	private function request_form_id( WP_REST_Request $request ) {
		$params = array_merge( (array) $request->get_json_params(), (array) $request->get_body_params() );
		if ( ! empty( $params['formId'] ) ) {
			return sanitize_key( (string) $params['formId'] );
		}
		if ( ! empty( $params['payload'] ) ) {
			$decoded = json_decode( base64_decode( (string) $params['payload'], true ), true );
			if ( is_array( $decoded ) && ! empty( $decoded['formId'] ) ) return sanitize_key( (string) $decoded['formId'] );
		}
		return 'unknown';
	}

	private function request_identity() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		return substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 32 );
	}
}
