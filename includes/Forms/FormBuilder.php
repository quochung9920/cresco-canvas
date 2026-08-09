<?php
/**
 * Native form builder, validation, submissions, notifications, and spam controls.
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

final class FormBuilder {
	const FORM_BLOCK  = 'cresco/form';
	const FIELD_BLOCK = 'cresco/form-field';
	const POST_TYPE   = 'cresco_submission';
	const MAX_FIELDS  = 50;
	const MAX_ENCODED_PAYLOAD = 350000;
	const DEFAULT_RETENTION_DAYS = 30;

	/** Register post type, blocks, assets, and public submit route. */
	public function register() {
		add_action( 'init', array( $this, 'register_submission_type' ), 10 );
		add_action( 'init', array( $this, 'register_assets' ), 20 );
		add_action( 'init', array( $this, 'register_blocks' ), 41 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register private submission records. */
	public function register_submission_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array( 'name' => __( 'Form Submissions', 'cresco-canvas' ), 'singular_name' => __( 'Form Submission', 'cresco-canvas' ) ),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => 'tools.php',
				'show_in_rest' => false,
				'supports' => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap' => true,
			)
		);
	}

	/** Register checked-in frontend runtime. */
	public function register_assets() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/forms-frontend.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_register_script( 'cresco-canvas-forms-frontend', CRESCO_CANVAS_URL . 'build/forms-frontend.js', $asset['dependencies'], $asset['version'], true );
		}
		wp_register_style( 'cresco-canvas-forms', CRESCO_CANVAS_URL . 'assets/css/forms.css', array(), CRESCO_CANVAS_VERSION );
	}

	/** Register form and field blocks. */
	public function register_blocks() {
		register_block_type(
			self::FIELD_BLOCK,
			array(
				'api_version' => 3,
				'attributes' => array(
					'name' => array( 'type' => 'string', 'default' => '' ),
					'label' => array( 'type' => 'string', 'default' => 'Field' ),
					'type' => array( 'type' => 'string', 'default' => 'text' ),
					'required' => array( 'type' => 'boolean', 'default' => false ),
					'placeholder' => array( 'type' => 'string', 'default' => '' ),
					'options' => array( 'type' => 'string', 'default' => '' ),
					'min' => array( 'type' => 'number' ),
					'max' => array( 'type' => 'number' ),
				),
				'render_callback' => array( $this, 'render_field' ),
				'supports' => array( 'html' => false, 'className' => true, 'spacing' => true ),
			)
		);
		register_block_type(
			self::FORM_BLOCK,
			array(
				'api_version' => 3,
				'attributes' => array(
					'formId' => array( 'type' => 'string', 'default' => '' ),
					'submitLabel' => array( 'type' => 'string', 'default' => 'Submit' ),
					'successMessage' => array( 'type' => 'string', 'default' => 'Thank you.' ),
					'emailTo' => array( 'type' => 'string', 'default' => '' ),
					'storeSubmissions' => array( 'type' => 'boolean', 'default' => true ),
					'redirectUrl' => array( 'type' => 'string', 'default' => '' ),
					'retentionDays' => array( 'type' => 'number', 'default' => self::DEFAULT_RETENTION_DAYS ),
					'replyToField' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render_form' ),
				'supports' => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
			)
		);
	}

	/** Register public submission route. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/forms/submit',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Render one field. */
	public function render_field( $attributes ) {
		$name = self::field_name( $attributes['name'] ?? '' );
		if ( ! $name ) {
			return current_user_can( 'edit_pages' ) ? '<p class="cresco-form-field__warning">' . esc_html__( 'Form field requires a name.', 'cresco-canvas' ) . '</p>' : '';
		}
		$type = self::field_type( $attributes['type'] ?? 'text' );
		$label = sanitize_text_field( (string) ( $attributes['label'] ?? 'Field' ) );
		$required = ! empty( $attributes['required'] );
		$id = wp_unique_id( 'cresco-field-' );
		$common = ' id="' . esc_attr( $id ) . '" name="fields[' . esc_attr( $name ) . ']"' . ( $required ? ' required aria-required="true"' : '' );
		$control = '';
		if ( 'textarea' === $type ) {
			$control = '<textarea' . $common . ' maxlength="8192" placeholder="' . esc_attr( sanitize_text_field( (string) ( $attributes['placeholder'] ?? '' ) ) ) . '"></textarea>';
		} elseif ( 'select' === $type || 'radio' === $type || 'checkbox_group' === $type ) {
			$options = self::options( $attributes['options'] ?? '' );
			if ( 'select' === $type ) {
				$control = '<select' . $common . '><option value="">' . esc_html__( 'Select', 'cresco-canvas' ) . '</option>';
				foreach ( $options as $option ) {
					$control .= '<option value="' . esc_attr( $option['value'] ) . '">' . esc_html( $option['label'] ) . '</option>';
				}
				$control .= '</select>';
			} else {
				$control = '<fieldset><legend>' . esc_html( $label ) . '</legend>';
				foreach ( $options as $index => $option ) {
					$input_name = 'fields[' . $name . ']' . ( 'checkbox_group' === $type ? '[]' : '' );
					$control .= '<label><input type="' . ( 'radio' === $type ? 'radio' : 'checkbox' ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $option['value'] ) . '"' . ( $required && 0 === $index ? ' required' : '' ) . '> ' . esc_html( $option['label'] ) . '</label>';
				}
				$control .= '</fieldset>';
				return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-form-field cresco-form-field--' . $type, 'data-field-name' => $name, 'data-field-type' => $type, 'data-required' => $required ? '1' : '0' ) ) . '>' . $control . '</div>';
			}
		} elseif ( 'consent' === $type ) {
			$control = '<label><input type="checkbox"' . $common . ' value="1"> ' . esc_html( $label ) . '</label>';
			return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-form-field cresco-form-field--consent', 'data-field-name' => $name, 'data-field-type' => $type, 'data-required' => $required ? '1' : '0' ) ) . '>' . $control . '</div>';
		} else {
			$input_type = in_array( $type, array( 'text', 'email', 'tel', 'number', 'url', 'date', 'file' ), true ) ? $type : 'text';
			$extra = '';
			if ( 'number' === $type ) {
				if ( isset( $attributes['min'] ) ) $extra .= ' min="' . esc_attr( (string) $attributes['min'] ) . '"';
				if ( isset( $attributes['max'] ) ) $extra .= ' max="' . esc_attr( (string) $attributes['max'] ) . '"';
			} elseif ( 'file' !== $type ) {
				$extra .= ' maxlength="' . esc_attr( (string) self::max_length_for_type( $type ) ) . '"';
			}
			$control = '<input type="' . esc_attr( $input_type ) . '"' . $common . $extra . ' placeholder="' . esc_attr( sanitize_text_field( (string) ( $attributes['placeholder'] ?? '' ) ) ) . '">';
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-form-field cresco-form-field--' . $type, 'data-field-name' => $name, 'data-field-type' => $type, 'data-required' => $required ? '1' : '0' ) ) . '><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span aria-hidden="true">*</span>' : '' ) . '</label>' . $control . '</div>';
	}

	/** Render a signed progressively enhanced form. */
	public function render_form( $attributes, $content, $block ) {
		$form_id = self::form_id( $attributes['formId'] ?? '' );
		$inner_blocks = (array) ( $block->parsed_block['innerBlocks'] ?? array() );
		$schema = self::schema_from_blocks( $inner_blocks );
		if ( ! $form_id || ! $schema ) {
			return current_user_can( 'edit_pages' ) ? '<p class="cresco-form__warning">' . esc_html__( 'Form requires a Form ID and at least one valid field.', 'cresco-canvas' ) . '</p>' : '';
		}
		$config = array(
			'formId' => $form_id,
			'schema' => $schema,
			'emailTo' => sanitize_email( (string) ( $attributes['emailTo'] ?? '' ) ),
			'storeSubmissions' => ! empty( $attributes['storeSubmissions'] ),
			'successMessage' => substr( sanitize_text_field( (string) ( $attributes['successMessage'] ?? 'Thank you.' ) ), 0, 500 ),
			'redirectUrl' => self::safe_redirect_url( $attributes['redirectUrl'] ?? '' ),
			'replyToField' => self::field_name( $attributes['replyToField'] ?? '' ),
			'retentionDays' => min( 365, max( 1, absint( $attributes['retentionDays'] ?? self::DEFAULT_RETENTION_DAYS ) ) ),
		);
		$captcha = self::captcha_from_blocks( $inner_blocks );
		if ( $captcha ) {
			$config['captcha'] = $captcha;
		}
		$payload = self::encode( $config );
		$signature = self::sign( $payload );
		wp_enqueue_script( 'cresco-canvas-forms-frontend' );
		wp_enqueue_style( 'cresco-canvas-forms' );
		return '<form ' . get_block_wrapper_attributes( array( 'class' => 'cresco-form', 'data-cresco-form' => $form_id, 'data-endpoint' => esc_url( rest_url( 'cresco-canvas/v1/forms/submit' ) ), 'data-payload' => $payload, 'data-signature' => $signature ) ) . ' method="post" enctype="multipart/form-data" novalidate>' . $content . '<div class="cresco-form__honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><div class="cresco-form__status" aria-live="polite" aria-atomic="true"></div><button type="submit">' . esc_html( sanitize_text_field( (string) ( $attributes['submitLabel'] ?? 'Submit' ) ) ) . '</button></form>';
	}

	/** Validate and process a public submission. */
	public function submit( WP_REST_Request $request ) {
		if ( $this->rate_limited() ) {
			return new WP_Error( 'cresco_form_rate_limited', __( 'Too many submissions. Please try again later.', 'cresco-canvas' ), array( 'status' => 429 ) );
		}
		$params = (array) $request->get_json_params();
		$payload = sanitize_text_field( (string) ( $params['payload'] ?? '' ) );
		$signature = sanitize_text_field( (string) ( $params['signature'] ?? '' ) );
		if ( ! self::verify( $payload, $signature ) ) {
			return new WP_Error( 'cresco_form_invalid_signature', __( 'Invalid form signature.', 'cresco-canvas' ), array( 'status' => 403 ) );
		}
		$config = self::decode( $payload );
		if ( ! self::valid_config( $config ) ) {
			return new WP_Error( 'cresco_form_invalid_payload', __( 'Invalid form configuration.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $params['website'] ) ) {
			return new WP_REST_Response( array( 'success' => true, 'message' => sanitize_text_field( (string) ( $config['successMessage'] ?? '' ) ) ) );
		}
		$captcha = self::verify_submission_captcha( $config, $params['captchaToken'] ?? '', $request );
		if ( is_wp_error( $captcha ) ) {
			return $captcha;
		}
		$result = self::validate( (array) ( $params['fields'] ?? array() ), (array) $config['schema'] );
		if ( $result['errors'] ) {
			return new WP_REST_Response( array( 'success' => false, 'errors' => $result['errors'] ), 422 );
		}
		if ( ! empty( $config['storeSubmissions'] ) ) {
			$stored = self::store_submission( $config, $result['values'] );
			if ( is_wp_error( $stored ) ) {
				return new WP_Error( 'cresco_form_storage_failed', __( 'The submission could not be stored.', 'cresco-canvas' ), array( 'status' => 500 ) );
			}
		}
		if ( ! empty( $config['emailTo'] ) ) {
			$this->send_notification( $config, $result['values'] );
		}
		return new WP_REST_Response( array( 'success' => true, 'message' => sanitize_text_field( (string) ( $config['successMessage'] ?? '' ) ), 'redirectUrl' => self::safe_redirect_url( $config['redirectUrl'] ?? '' ) ) );
	}

	/** Build a bounded field schema recursively, including fields inside form steps. */
	public static function schema_from_blocks( $blocks ) {
		$schema = array();
		$walk = static function ( $items ) use ( &$walk, &$schema ) {
			foreach ( (array) $items as $block ) {
				if ( count( $schema ) >= self::MAX_FIELDS ) return;
				if ( self::FIELD_BLOCK === ( $block['blockName'] ?? '' ) ) {
					$attrs = (array) ( $block['attrs'] ?? array() );
					$name = self::field_name( $attrs['name'] ?? '' );
					if ( $name && ! isset( $schema[ $name ] ) ) {
						$schema[ $name ] = array(
							'type' => self::field_type( $attrs['type'] ?? 'text' ),
							'required' => ! empty( $attrs['required'] ),
							'options' => array_column( self::options( $attrs['options'] ?? '' ), 'value' ),
							'min' => isset( $attrs['min'] ) ? (float) $attrs['min'] : null,
							'max' => isset( $attrs['max'] ) ? (float) $attrs['max'] : null,
						);
					}
				}
				if ( ! empty( $block['innerBlocks'] ) ) $walk( $block['innerBlocks'] );
			}
		};
		$walk( $blocks );
		return $schema;
	}

	/** Validate submitted values against the signed schema and hard field bounds. */
	public static function validate( $fields, $schema ) {
		$values = array();
		$errors = array();
		foreach ( array_slice( (array) $schema, 0, self::MAX_FIELDS, true ) as $name => $rule ) {
			$name = self::field_name( $name );
			if ( ! $name || ! is_array( $rule ) ) continue;
			$type = self::field_type( $rule['type'] ?? 'text' );
			$raw = $fields[ $name ] ?? '';
			$normalized = self::normalize_value( $raw, $type );
			if ( is_wp_error( $normalized ) ) {
				$errors[ $name ] = $normalized->get_error_message();
				continue;
			}
			$value = $normalized;
			$empty = is_array( $value ) ? ! $value : '' === $value;
			if ( ! empty( $rule['required'] ) && $empty ) {
				$errors[ $name ] = __( 'This field is required.', 'cresco-canvas' );
				continue;
			}
			if ( $empty ) {
				$values[ $name ] = $value;
				continue;
			}
			if ( 'email' === $type && ( is_array( $value ) || ! is_email( $value ) ) ) {
				$errors[ $name ] = __( 'Enter a valid email address.', 'cresco-canvas' );
			} elseif ( 'url' === $type && ( is_array( $value ) || ! wp_http_validate_url( $value ) ) ) {
				$errors[ $name ] = __( 'Enter a valid URL.', 'cresco-canvas' );
			} elseif ( 'number' === $type ) {
				$number = is_array( $value ) ? null : filter_var( $value, FILTER_VALIDATE_FLOAT );
				if ( false === $number || null === $number || ! is_finite( (float) $number ) ) {
					$errors[ $name ] = __( 'Enter a valid number.', 'cresco-canvas' );
				} elseif ( null !== ( $rule['min'] ?? null ) && (float) $number < (float) $rule['min'] ) {
					$errors[ $name ] = __( 'The number is below the allowed minimum.', 'cresco-canvas' );
				} elseif ( null !== ( $rule['max'] ?? null ) && (float) $number > (float) $rule['max'] ) {
					$errors[ $name ] = __( 'The number exceeds the allowed maximum.', 'cresco-canvas' );
				}
			} elseif ( in_array( $type, array( 'select', 'radio', 'checkbox_group' ), true ) ) {
				$allowed = array_fill_keys( array_map( 'strval', array_slice( (array) ( $rule['options'] ?? array() ), 0, 50 ) ), true );
				$check = is_array( $value ) ? $value : array( $value );
				foreach ( $check as $item ) {
					if ( ! isset( $allowed[ $item ] ) ) {
						$errors[ $name ] = __( 'Invalid option.', 'cresco-canvas' );
						break;
					}
				}
			}
			$values[ $name ] = $value;
		}
		return array( 'values' => $values, 'errors' => $errors );
	}

	/** Store sanitized submission data with ownership and retention metadata. */
	public static function store_submission( $config, $values ) {
		$retention = min( 365, max( 1, absint( $config['retentionDays'] ?? self::DEFAULT_RETENTION_DAYS ) ) );
		$post_id = wp_insert_post(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'private',
				'post_title' => sprintf( '%s — %s', self::form_id( $config['formId'] ?? '' ), current_time( 'mysql' ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) return $post_id;
		update_post_meta( $post_id, '_cresco_submission_data', $values );
		update_post_meta( $post_id, '_cresco_form_id', self::form_id( $config['formId'] ?? '' ) );
		update_post_meta( $post_id, '_cresco_delete_after', time() + ( $retention * DAY_IN_SECONDS ) );
		return (int) $post_id;
	}

	/** Verify CAPTCHA only when a CAPTCHA requirement is part of the signed form config. */
	public static function verify_submission_captcha( $config, $token, $request = null ) {
		$captcha = isset( $config['captcha'] ) && is_array( $config['captcha'] ) ? $config['captcha'] : array();
		if ( ! $captcha ) return true;
		return FormCompletion::verify_token( $captcha['provider'] ?? 'turnstile', $token, $captcha['action'] ?? 'cresco_form', $request );
	}

	/** Ensure a decoded signed config stays within the supported contract. */
	public static function valid_config( $config ) {
		return is_array( $config ) && ! empty( $config['formId'] ) && isset( $config['schema'] ) && is_array( $config['schema'] ) && count( $config['schema'] ) <= self::MAX_FIELDS;
	}

	/** Send plain-text email notification. */
	private function send_notification( $config, $values ) {
		$lines = array();
		foreach ( $values as $name => $value ) $lines[] = $name . ': ' . ( is_array( $value ) ? wp_json_encode( $value ) : $value );
		wp_mail( sanitize_email( $config['emailTo'] ), sprintf( '[Cresco Canvas] %s', self::form_id( $config['formId'] ?? '' ) ), implode( "\n", $lines ) );
	}

	/** Anonymous per-IP defense in depth; central security also applies a route limit. */
	private function rate_limited() {
		$ip = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$key = 'cresco_form_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 32 );
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) return true;
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}

	private static function normalize_value( $value, $type ) {
		if ( is_array( $value ) ) {
			if ( 'checkbox_group' !== $type ) return new WP_Error( 'cresco_form_scalar_required', __( 'Invalid field value.', 'cresco-canvas' ) );
			if ( count( $value ) > 24 ) return new WP_Error( 'cresco_form_too_many_values', __( 'Too many values were submitted for this field.', 'cresco-canvas' ) );
			$output = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) || is_object( $item ) || strlen( (string) $item ) > 2048 ) return new WP_Error( 'cresco_form_field_too_large', __( 'A form field is too large.', 'cresco-canvas' ) );
				$item = sanitize_text_field( (string) $item );
				if ( '' !== $item ) $output[] = $item;
			}
			return array_values( $output );
		}
		if ( is_object( $value ) ) return new WP_Error( 'cresco_form_invalid_value', __( 'Invalid field value.', 'cresco-canvas' ) );
		$raw = (string) $value;
		if ( strlen( $raw ) > self::max_length_for_type( $type ) ) return new WP_Error( 'cresco_form_field_too_large', __( 'A form field is too large.', 'cresco-canvas' ) );
		if ( 'textarea' === $type ) return sanitize_textarea_field( $raw );
		if ( 'email' === $type ) return sanitize_email( $raw );
		if ( 'url' === $type ) return esc_url_raw( $raw );
		return sanitize_text_field( $raw );
	}

	private static function max_length_for_type( $type ) {
		$limits = array( 'textarea' => 8192, 'email' => 320, 'url' => 2048, 'number' => 128, 'date' => 64, 'consent' => 8 );
		return (int) ( $limits[ $type ] ?? 2048 );
	}

	private static function safe_redirect_url( $url ) {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
		return strlen( $url ) <= 2048 ? $url : '';
	}

	private static function captcha_from_blocks( $blocks ) {
		$captcha = null;
		$visited = 0;
		$walk = static function ( $items ) use ( &$walk, &$captcha, &$visited ) {
			foreach ( (array) $items as $block ) {
				if ( ++$visited > 200 || $captcha ) return;
				if ( FormCompletion::CAPTCHA_BLOCK === ( $block['blockName'] ?? '' ) ) {
					$attrs = (array) ( $block['attrs'] ?? array() );
					$captcha = array( 'provider' => FormCompletion::sanitize_provider( $attrs['provider'] ?? 'turnstile' ), 'action' => substr( sanitize_key( (string) ( $attrs['action'] ?? 'cresco_form' ) ), 0, 64 ) );
					return;
				}
				if ( ! empty( $block['innerBlocks'] ) ) $walk( $block['innerBlocks'] );
			}
		};
		$walk( $blocks );
		return $captcha;
	}

	public static function field_name( $value ) { $value = sanitize_key( (string) $value ); return $value ? substr( $value, 0, 48 ) : ''; }
	public static function form_id( $value ) { $value = sanitize_key( (string) $value ); return $value ? substr( $value, 0, 48 ) : ''; }
	public static function field_type( $value ) { $value = sanitize_key( (string) $value ); return in_array( $value, array( 'text','email','tel','number','url','date','textarea','select','radio','checkbox_group','consent','file' ), true ) ? $value : 'text'; }
	public static function options( $value ) { $out = array(); foreach ( array_slice( preg_split( '/\r\n|\r|\n/', (string) $value ), 0, 50 ) as $line ) { if ( strlen( $line ) > 2048 ) continue; $parts = array_map( 'trim', explode( '|', $line, 2 ) ); $label = substr( sanitize_text_field( $parts[0] ?? '' ), 0, 500 ); $val = substr( sanitize_key( $parts[1] ?? $label ), 0, 100 ); if ( $label && $val ) $out[] = array( 'label' => $label, 'value' => $val ); } return $out; }
	public static function encode( $value ) { return rtrim( strtr( base64_encode( wp_json_encode( $value ) ), '+/', '-_' ), '=' ); }
	public static function decode( $value ) { if ( strlen( (string) $value ) > self::MAX_ENCODED_PAYLOAD ) return null; $value = strtr( (string) $value, '-_', '+/' ); $pad = strlen( $value ) % 4; if ( $pad ) $value .= str_repeat( '=', 4 - $pad ); $json = base64_decode( $value, true ); return false === $json ? null : json_decode( $json, true, 32 ); }
	public static function sign( $payload ) { return hash_hmac( 'sha256', (string) $payload, wp_salt( 'nonce' ) ); }
	public static function verify( $payload, $signature ) { return $payload && strlen( (string) $payload ) <= self::MAX_ENCODED_PAYLOAD && $signature && hash_equals( self::sign( $payload ), (string) $signature ); }
}
