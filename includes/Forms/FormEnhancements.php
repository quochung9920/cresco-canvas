<?php
/**
 * Advanced form delivery: multipart uploads, conditional fields, and signed webhooks.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Forms;

use CrescoCanvas\Security\SecurityHardening;
use CrescoCanvas\Security\UploadSecurity;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FormEnhancements {
	const RETRY_HOOK = 'cresco_canvas_retry_webhook';
	const RETRY_TTL  = 3600;
	const MAX_RETRIES = 3;

	/** Register routes, filters, and retry processing. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'render_block_cresco/form-field', array( $this, 'decorate_conditional_field' ), 10, 2 );
		add_action( self::RETRY_HOOK, array( $this, 'retry_webhook' ), 10, 2 );
	}

	/** Register multipart form endpoint. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/forms/submit-multipart',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_multipart' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Add declarative conditional-field data attributes without changing saved markup. */
	public function decorate_conditional_field( $html, $block ) {
		$attrs = (array) ( $block['attrs'] ?? array() );
		$field = FormBuilder::field_name( $attrs['conditionField'] ?? '' );
		if ( ! $field || '' === trim( (string) ( $attrs['conditionValue'] ?? '' ) ) ) return $html;
		$operator = sanitize_key( (string) ( $attrs['conditionOperator'] ?? 'equals' ) );
		if ( ! in_array( $operator, array( 'equals', 'not_equals', 'contains', 'not_empty', 'empty' ), true ) ) $operator = 'equals';
		$value = substr( sanitize_text_field( (string) $attrs['conditionValue'] ), 0, 2048 );
		return preg_replace(
			'/^(<div\s+)/',
			'$1data-cresco-condition-field="' . esc_attr( $field ) . '" data-cresco-condition-operator="' . esc_attr( $operator ) . '" data-cresco-condition-value="' . esc_attr( $value ) . '" ',
			$html,
			1
		) ?: $html;
	}

	/** Validate a signed multipart request and persist accepted files in private storage. */
	public function submit_multipart( WP_REST_Request $request ) {
		$params    = (array) $request->get_body_params();
		$payload   = sanitize_text_field( (string) ( $params['payload'] ?? '' ) );
		$signature = sanitize_text_field( (string) ( $params['signature'] ?? '' ) );
		if ( ! FormBuilder::verify( $payload, $signature ) ) {
			return new WP_Error( 'cresco_form_invalid_signature', __( 'Invalid form signature.', 'cresco-canvas' ), array( 'status' => 403 ) );
		}
		$config = FormBuilder::decode( $payload );
		if ( ! FormBuilder::valid_config( $config ) ) {
			return new WP_Error( 'cresco_form_invalid_payload', __( 'Invalid form configuration.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $params['website'] ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => sanitize_text_field( (string) ( $config['successMessage'] ?? '' ) ) ) );
		}

		$captcha = FormBuilder::verify_submission_captcha( $config, $params['cresco_captcha_token'] ?? '', $request );
		if ( is_wp_error( $captcha ) ) return $captcha;

		$files = (array) $request->get_file_params();
		if ( SecurityHardening::upload_file_count( $files ) > UploadSecurity::MAX_UPLOADS ) {
			return new WP_Error( 'cresco_upload_count_limit', __( 'Too many files were uploaded.', 'cresco-canvas' ), array( 'status' => 413 ) );
		}

		$validation_schema = (array) $config['schema'];
		foreach ( $validation_schema as &$validation_rule ) {
			if ( 'file' === ( $validation_rule['type'] ?? '' ) ) $validation_rule['required'] = false;
		}
		unset( $validation_rule );
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
		$result = FormBuilder::validate( $fields, $validation_schema );
		$upload_ids = array();
		$retention = min( 365, max( 1, absint( $config['retentionDays'] ?? FormBuilder::DEFAULT_RETENTION_DAYS ) ) );

		foreach ( (array) $config['schema'] as $name => $rule ) {
			if ( 'file' !== ( $rule['type'] ?? '' ) ) continue;
			$file = $files[ $name ] ?? null;
			if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
				if ( ! empty( $rule['required'] ) ) $result['errors'][ $name ] = __( 'This file is required.', 'cresco-canvas' );
				continue;
			}
			$upload = UploadSecurity::store( $file, $config['formId'] ?? '', $retention );
			if ( is_wp_error( $upload ) ) {
				$result['errors'][ $name ] = $upload->get_error_message();
			} else {
				$result['values'][ $name ] = $upload;
				$upload_ids[] = absint( $upload['uploadId'] ?? 0 );
			}
		}

		if ( $result['errors'] ) {
			foreach ( $upload_ids as $upload_id ) UploadSecurity::delete_upload( $upload_id );
			return new WP_REST_Response( array( 'success' => false, 'errors' => $result['errors'] ), 422 );
		}

		$submission_id = 0;
		if ( ! empty( $config['storeSubmissions'] ) ) {
			$stored = FormBuilder::store_submission( $config, $result['values'] );
			if ( is_wp_error( $stored ) ) {
				foreach ( $upload_ids as $upload_id ) UploadSecurity::delete_upload( $upload_id );
				return new WP_Error( 'cresco_form_storage_failed', __( 'The submission could not be stored.', 'cresco-canvas' ), array( 'status' => 500 ) );
			}
			$submission_id = absint( $stored );
			UploadSecurity::attach_to_submission( $upload_ids, $submission_id, $retention );
		}
		if ( ! empty( $config['emailTo'] ) ) $this->send_email( $config, $result['values'] );
		$this->maybe_deliver_webhook( $config, $result['values'] );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'message'     => sanitize_text_field( (string) ( $config['successMessage'] ?? '' ) ),
				'redirectUrl' => esc_url_raw( (string) ( $config['redirectUrl'] ?? '' ), array( 'http', 'https' ) ),
			)
		);
	}

	/** Send a plain-text email with safe reply-to mapping. */
	private function send_email( $config, $values ) {
		$lines = array();
		foreach ( $values as $name => $value ) $lines[] = $name . ': ' . ( is_array( $value ) ? wp_json_encode( $value ) : $value );
		$headers = array();
		$reply_field = FormBuilder::field_name( $config['replyToField'] ?? '' );
		if ( $reply_field && ! empty( $values[ $reply_field ] ) && ! is_array( $values[ $reply_field ] ) && is_email( $values[ $reply_field ] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $values[ $reply_field ] );
		}
		wp_mail( sanitize_email( $config['emailTo'] ), sprintf( '[Cresco Canvas] %s', FormBuilder::form_id( $config['formId'] ?? '' ) ), implode( "\n", $lines ), $headers );
	}

	/** Resolve sensitive delivery settings server-side and start one bounded delivery. */
	private function maybe_deliver_webhookh $config, $values ) {
		$form_id = FormBuilder::form_id( $config['formId'] ?? '' );
		$server_config = apply_filters( 'cresco_canvas_form_delivery_config', array(), $form_id );
		$server_config = is_array( $server_config ) ? $server_config : array();
		$url = esc_url_raw( (string) ( $server_config['webhookUrl'] ?? ( $config['webhookUrl'] ?? '' ) ), array( 'https' ) );
		if ( '' === $url ) return;
		$safe = SecurityHardening::validate_public_https_url( $url );
		if ( is_wp_error( $safe ) ) {
			$this->log_webhook_failure( $form_id, $url, 0, 0, 'unsafe_destination' );
			return;
		}

		$delivery_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
		$submitted_at = gmdate( 'c' );
		$body = wp_json_encode( array( 'formId' => $form_id, 'submittedAt' => $submitted_at, 'deliveryId' => $delivery_id, 'values' => $values ) );
		if ( ! is_string( $body ) || strlen( $body ) > SecurityHardening::MAX_FORM_JSON_BYTES ) return;
		$token = strtolower( wp_generate_password( 40, false, false ) );
		$secret = isset( $server_config['webhookSecret'] ) && is_string( $server_config['webhookSecret'] ) && '' !== $server_config['webhookSecret'] ? $server_config['webhookSecret'] : wp_salt( 'auth' );
		set_transient(
			'cresco_webhook_retry_' . $token,
			array( 'url' => $url, 'body' => $body, 'formId' => $form_id, 'deliveryId' => $delivery_id, 'secret' => $secret ),
			self::RETRY_TTL
		);
		$this->deliver_webhook_token( $token, 0 );
	}

	/** Retry callback carries only an opaque token and attempt number, never submission values. */
	public function retry_webhook( $token, $attempt = 0 ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[a-zA-Z0-9]{20,80}$/', $token ) ) return;
		$this->deliver_webhook_token( $token, min( self::MAX_RETRIES, absint( $attempt ) ) );
	}

	/** Revalidate DNS and destination for every attempt, with redirects disabled. */
	private function deliver_webhook_token( $token, $attempt ) {
		$key = 'cresco_webhook_retry_' . $token;
		$state = get_transient( $key );
		if ( ! is_array( $state ) || $attempt > self::MAX_RETRIES ) return;
		$url = esc_url_raw( (string) ( $state['url'] ?? '' ), array( 'https' ) );
		$safe = SecurityHardening::validate_public_https_url( $url );
		if ( is_wp_error( $safe ) ) {
			$this->log_webhook_failure( $state['formId'] ?? '', $url, $attempt + 1, 0, 'unsafe_destination' );
			delete_transient( $key );
			return;
		}
		$body = (string) ( $state['body'] ?? '' );
		$signature = hash_hmac( 'sha256', $body, (string) ( $state['secret'] ?? wp_salt( 'auth' ) ) );
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => 8,
				'redirection' => 0,
				'reject_unsafe_urls' => true,
				'sslverify' => true,
				'limit_response_size' => 65536,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-Cresco-Signature' => 'sha256=' . $signature,
					'X-Cresco-Delivery-Id' => sanitize_text_field( (string) ( $state['deliveryId'] ?? '' ) ),
				),
				'body' => $body,
			)
		);
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code <= 299 ) {
			delete_transient( $key );
			return;
		}
		$this->log_webhook_failure( $state['formId'] ?? '', $url, $attempt + 1, $code, is_wp_error( $response ) ? 'transport_error' : 'http_error' );
		if ( $attempt < self::MAX_RETRIES ) {
			set_transient( $key, $state, self::RETRY_TTL );
			wp_schedule_single_event( time() + ( 60 * ( 2 ** $attempt ) ), self::RETRY_HOOK, array( $token, $attempt + 1 ) );
		} else {
			delete_transient( $key );
		}
	}

	/** Store only non-sensitive failure metadata. */
	private function log_webhook_failure( $form_id, $url, $attempt, $code, $reason ) {
		$logs = (array) get_option( 'cresco_canvas_webhook_failures', array() );
		$logs[] = array(
			'formId' => FormBuilder::form_id( $form_id ),
			'urlHost' => sanitize_text_field( (string) wp_parse_url( $url, PHP_URL_HOST ) ),
			'attempt' => absint( $attempt ),
			'status' => absint( $code ),
			'reason' => sanitize_key( $reason ),
			'time' => time(),
		);
		update_option( 'cresco_canvas_webhook_failures', array_slice( $logs, -100 ), false );
	}
}
