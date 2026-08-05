<?php
/**
 * Advanced form delivery: multipart uploads, conditional fields, and signed webhooks.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Forms;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FormEnhancements {
	const MAX_UPLOAD_BYTES = 5_242_880;
	const RETRY_HOOK       = 'cresco_canvas_retry_webhook';

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
		if ( ! $field || '' === trim( (string) ( $attrs['conditionValue'] ?? '' ) ) ) {
			return $html;
		}
		$operator = sanitize_key( (string) ( $attrs['conditionOperator'] ?? 'equals' ) );
		if ( ! in_array( $operator, array( 'equals', 'not_equals', 'contains', 'not_empty', 'empty' ), true ) ) {
			$operator = 'equals';
		}
		$value = sanitize_text_field( (string) $attrs['conditionValue'] );
		return preg_replace(
			'/^(<div\s+)/',
			'$1data-cresco-condition-field="' . esc_attr( $field ) . '" data-cresco-condition-operator="' . esc_attr( $operator ) . '" data-cresco-condition-value="' . esc_attr( $value ) . '" ',
			$html,
			1
		) ?: $html;
	}

	/** Validate a signed multipart request and safely persist accepted uploads. */
	public function submit_multipart( WP_REST_Request $request ) {
		$params    = (array) $request->get_body_params();
		$payload   = sanitize_text_field( (string) ( $params['payload'] ?? '' ) );
		$signature = sanitize_text_field( (string) ( $params['signature'] ?? '' ) );
		if ( ! FormBuilder::verify( $payload, $signature ) ) {
			return new WP_Error( 'cresco_form_invalid_signature', __( 'Invalid form signature.', 'cresco-canvas' ), array( 'status' => 403 ) );
		}
		$config = FormBuilder::decode( $payload );
		if ( ! is_array( $config ) || empty( $config['schema'] ) ) {
			return new WP_Error( 'cresco_form_invalid_payload', __( 'Invalid form configuration.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $params['website'] ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => $config['successMessage'] ?? '' ) );
		}

		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
		$result = FormBuilder::validate( $fields, (array) $config['schema'] );
		$files  = (array) $request->get_file_params();
		foreach ( (array) $config['schema'] as $name => $rule ) {
			if ( 'file' !== ( $rule['type'] ?? '' ) ) {
				continue;
			}
			$file = $files[ $name ] ?? null;
			if ( empty( $file['tmp_name'] ) ) {
				if ( ! empty( $rule['required'] ) ) {
					$result['errors'][ $name ] = __( 'This file is required.', 'cresco-canvas' );
				}
				continue;
			}
			$upload = $this->handle_upload( $file );
			if ( is_wp_error( $upload ) ) {
				$result['errors'][ $name ] = $upload->get_error_message();
			} else {
				$result['values'][ $name ] = $upload;
			}
		}
		if ( $result['errors'] ) {
			return new WP_REST_Response( array( 'success' => false, 'errors' => $result['errors'] ), 422 );
		}

		if ( ! empty( $config['storeSubmissions'] ) ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => FormBuilder::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => sprintf( '%s — %s', sanitize_text_field( (string) $config['formId'] ), current_time( 'mysql' ) ),
				),
				true
			);
			if ( ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_cresco_submission_data', $result['values'] );
			}
		}
		if ( ! empty( $config['emailTo'] ) ) {
			$this->send_email( $config, $result['values'] );
		}
		if ( ! empty( $config['webhookUrl'] ) ) {
			$this->deliver_webhook( $config, $result['values'], 0 );
		}
		return new WP_REST_Response(
			array(
				'success'     => true,
				'message'     => sanitize_text_field( (string) ( $config['successMessage'] ?? '' ) ),
				'redirectUrl' => esc_url_raw( (string) ( $config['redirectUrl'] ?? '' ) ),
			)
		);
	}

	/** Validate MIME/size and create a private attachment record. */
	private function handle_upload( $file ) {
		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error( 'cresco_upload_too_large', __( 'The file exceeds the 5 MB limit.', 'cresco-canvas' ) );
		}
		$allowed = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'gif'      => 'image/gif',
			'webp'     => 'image/webp',
			'pdf'      => 'application/pdf',
			'txt'      => 'text/plain',
			'csv'      => 'text/csv',
		);
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
		if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) {
			return new WP_Error( 'cresco_upload_invalid_type', __( 'This file type is not allowed.', 'cresco-canvas' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$_FILES['cresco_upload'] = $file;
		$attachment_id = media_handle_upload( 'cresco_upload', 0, array(), array( 'test_form' => false, 'mimes' => $allowed ) );
		unset( $_FILES['cresco_upload'] );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		update_post_meta( $attachment_id, '_cresco_form_upload', 1 );
		return array(
			'attachmentId' => (int) $attachment_id,
			'name'         => sanitize_file_name( $file['name'] ),
			'url'          => esc_url_raw( wp_get_attachment_url( $attachment_id ) ),
			'mime'         => $checked['type'],
		);
	}

	/** Send a plain-text email with safe reply-to mapping. */
	private function send_email( $config, $values ) {
		$lines = array();
		foreach ( $values as $name => $value ) {
			$lines[] = $name . ': ' . ( is_array( $value ) ? wp_json_encode( $value ) : $value );
		}
		$headers = array();
		$reply_field = FormBuilder::field_name( $config['replyToField'] ?? '' );
		if ( $reply_field && ! empty( $values[ $reply_field ] ) && is_email( $values[ $reply_field ] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $values[ $reply_field ] );
		}
		wp_mail( sanitize_email( $config['emailTo'] ), sprintf( '[Cresco Canvas] %s', sanitize_text_field( $config['formId'] ) ), implode( "\n", $lines ), $headers );
	}

	/** Deliver an HMAC-signed webhook and schedule bounded retries. */
	private function deliver_webhook( $config, $values, $attempt ) {
		$url = esc_url_raw( (string) ( $config['webhookUrl'] ?? '' ) );
		if ( ! wp_http_validate_url( $url ) || 3 < $attempt ) {
			return;
		}
		$body      = wp_json_encode( array( 'formId' => $config['formId'], 'submittedAt' => gmdate( 'c' ), 'values' => $values ) );
		$signature = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
		$response  = wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'headers' => array( 'Content-Type' => 'application/json', 'X-Cresco-Signature' => 'sha256=' . $signature ),
				'body'    => $body,
			)
		);
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( 200 > $code || 299 < $code ) {
			$this->log_webhook_failure( $config['formId'], $url, $attempt + 1, $code );
			if ( $attempt < 3 ) {
				wp_schedule_single_event( time() + ( 60 * ( 2 ** $attempt ) ), self::RETRY_HOOK, array( array( 'config' => $config, 'values' => $values ), $attempt + 1 ) );
			}
		}
	}

	/** Retry a previously failed webhook. */
	public function retry_webhook( $payload, $attempt ) {
		$this->deliver_webhook( (array) ( $payload['config'] ?? array() ), (array) ( $payload['values'] ?? array() ), absint( $attempt ) );
	}

	/** Store bounded failure details for administrators. */
	private function log_webhook_failure( $form_id, $url, $attempt, $code ) {
		$logs   = (array) get_option( 'cresco_canvas_webhook_failures', array() );
		$logs[] = array( 'formId' => sanitize_key( $form_id ), 'urlHost' => wp_parse_url( $url, PHP_URL_HOST ), 'attempt' => absint( $attempt ), 'status' => absint( $code ), 'time' => time() );
		update_option( 'cresco_canvas_webhook_failures', array_slice( $logs, -100 ), false );
	}
}
