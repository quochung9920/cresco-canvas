<?php
/**
 * Final 0.9 form workflows: steps, calculations, CAPTCHA adapters, cleanup, and diagnostics.
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

final class FormCompletion {
	const STEP_BLOCK = 'cresco/form-step';
	const CALC_BLOCK = 'cresco/calculated-field';
	const CAPTCHA_BLOCK = 'cresco/form-captcha';
	const MAX_CAPTCHA_TOKEN_BYTES = 4096;

	/** Register completion services. */
	public function register() {
		add_action( 'init', array( $this, 'register_assets' ), 20 );
		add_action( 'init', array( $this, 'register_blocks' ), 42 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'cresco_canvas_daily_cleanup', array( $this, 'cleanup_uploads' ) );
		add_action( 'admin_init', array( $this, 'schedule_cleanup' ) );
		add_filter( 'render_block_cresco/form', array( $this, 'enhance_form_markup' ), 20, 2 );
		add_filter( 'render_block_cresco/form-field', array( $this, 'enhance_field_markup' ), 20, 2 );
	}

	/** Register checked-in completion runtime. */
	public function register_assets() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/forms-completion.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_register_script( 'cresco-canvas-forms-completion', CRESCO_CANVAS_URL . 'build/forms-completion.js', $asset['dependencies'], $asset['version'], true );
		}
		wp_register_style( 'cresco-canvas-forms-completion', CRESCO_CANVAS_URL . 'assets/css/forms-completion.css', array(), CRESCO_CANVAS_VERSION );
	}

	/** Register step, calculated field, and CAPTCHA blocks. */
	public function register_blocks() {
		register_block_type(
			self::STEP_BLOCK,
			array(
				'api_version' => 3,
				'attributes' => array(
					'title' => array( 'type' => 'string', 'default' => 'Step' ),
					'nextLabel' => array( 'type' => 'string', 'default' => 'Next' ),
					'previousLabel' => array( 'type' => 'string', 'default' => 'Previous' ),
				),
				'render_callback' => array( $this, 'render_step' ),
				'supports' => array( 'html' => false, 'className' => true, 'spacing' => true ),
			)
		);
		register_block_type(
			self::CALC_BLOCK,
			array(
				'api_version' => 3,
				'attributes' => array(
					'name' => array( 'type' => 'string', 'default' => 'total' ),
					'label' => array( 'type' => 'string', 'default' => 'Total' ),
					'formula' => array( 'type' => 'string', 'default' => '' ),
					'decimals' => array( 'type' => 'number', 'default' => 2 ),
					'prefix' => array( 'type' => 'string', 'default' => '' ),
					'suffix' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render_calculated_field' ),
				'supports' => array( 'html' => false, 'className' => true, 'spacing' => true ),
			)
		);
		register_block_type(
			self::CAPTCHA_BLOCK,
			array(
				'api_version' => 3,
				'attributes' => array(
					'provider' => array( 'type' => 'string', 'default' => 'turnstile' ),
					'siteKey' => array( 'type' => 'string', 'default' => '' ),
					'action' => array( 'type' => 'string', 'default' => 'cresco_form' ),
				),
				'render_callback' => array( $this, 'render_captcha' ),
				'supports' => array( 'html' => false, 'className' => true ),
			)
		);
	}

	/** Register diagnostics and CAPTCHA verification endpoints. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/forms/verify-captcha',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'verify_captcha' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/forms/diagnostics/(?P<postId>\d+)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'diagnostics' ),
				'permission_callback' => static function ( $request ) {
					return current_user_can( 'edit_post', absint( $request['postId'] ) );
				},
			)
		);
	}

	/** Render one multi-step panel. */
	public function render_step( $attributes, $content ) {
		$title = sanitize_text_field( (string) ( $attributes['title'] ?? 'Step' ) );
		return '<section ' . get_block_wrapper_attributes( array( 'class' => 'cresco-form-step', 'data-cresco-form-step' => '' ) ) . '><h3 class="cresco-form-step__title">' . esc_html( $title ) . '</h3><div class="cresco-form-step__content">' . $content . '</div><div class="cresco-form-step__actions"><button type="button" data-cresco-step-previous>' . esc_html( sanitize_text_field( (string) ( $attributes['previousLabel'] ?? 'Previous' ) ) ) . '</button><button type="button" data-cresco-step-next>' . esc_html( sanitize_text_field( (string) ( $attributes['nextLabel'] ?? 'Next' ) ) ) . '</button></div></section>';
	}

	/** Render a client-calculated hidden field and accessible result. */
	public function render_calculated_field( $attributes ) {
		$name = FormBuilder::field_name( $attributes['name'] ?? 'total' );
		$formula = self::sanitize_formula( $attributes['formula'] ?? '' );
		$decimals = min( 6, max( 0, absint( $attributes['decimals'] ?? 2 ) ) );
		if ( ! $name || ! $formula ) {
			return current_user_can( 'edit_pages' ) ? '<p class="cresco-form__warning">' . esc_html__( 'Calculated field requires a name and a valid formula.', 'cresco-canvas' ) . '</p>' : '';
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-calculated-field', 'data-cresco-calculation' => $formula, 'data-decimals' => (string) $decimals ) ) . '><span class="cresco-calculated-field__label">' . esc_html( sanitize_text_field( (string) ( $attributes['label'] ?? 'Total' ) ) ) . '</span><output aria-live="polite"><span data-cresco-prefix>' . esc_html( sanitize_text_field( (string) ( $attributes['prefix'] ?? '' ) ) ) . '</span><span data-cresco-result>0</span><span data-cresco-suffix>' . esc_html( sanitize_text_field( (string) ( $attributes['suffix'] ?? '' ) ) ) . '</span></output><input type="hidden" name="fields[' . esc_attr( $name ) . ']" value="0" data-cresco-calculated-input></div>';
	}

	/** Render a provider-neutral CAPTCHA mount point. */
	public function render_captcha( $attributes ) {
		$provider = self::sanitize_provider( $attributes['provider'] ?? 'turnstile' );
		$site_key = substr( sanitize_text_field( (string) ( $attributes['siteKey'] ?? '' ) ), 0, 500 );
		if ( ! $site_key ) {
			return current_user_can( 'edit_pages' ) ? '<p class="cresco-form__warning">' . esc_html__( 'CAPTCHA requires a site key.', 'cresco-canvas' ) . '</p>' : '';
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-form-captcha', 'data-cresco-captcha' => $provider, 'data-site-key' => $site_key, 'data-action' => substr( sanitize_key( (string) ( $attributes['action'] ?? 'cresco_form' ) ), 0, 64 ) ) ) . '><input type="hidden" name="cresco_captcha_token" value=""><p>' . esc_html__( 'Spam protection is loading…', 'cresco-canvas' ) . '</p></div>';
	}

	/** Enqueue final runtime when a Cresco form renders. */
	public function enhance_form_markup( $html ) {
		wp_enqueue_script( 'cresco-canvas-forms-completion' );
		wp_enqueue_style( 'cresco-canvas-forms-completion' );
		return $html;
	}

	/** Add normalized conditional metadata to fields. */
	public function enhance_field_markup( $html, $block ) {
		$attrs = (array) ( $block['attrs'] ?? array() );
		if ( empty( $attrs['conditionField'] ) ) return $html;
		$condition = array(
			'field' => FormBuilder::field_name( $attrs['conditionField'] ),
			'operator' => self::sanitize_operator( $attrs['conditionOperator'] ?? 'equals' ),
			'value' => substr( sanitize_text_field( (string) ( $attrs['conditionValue'] ?? '' ) ), 0, 2048 ),
		);
		return preg_replace( '/class="cresco-form-field/', 'data-cresco-condition="' . esc_attr( wp_json_encode( $condition ) ) . '" class="cresco-form-field', $html, 1 );
	}

	/** Verify CAPTCHA through the provider-neutral adapter filter. */
	public function verify_captcha( WP_REST_Request $request ) {
		$result = self::verify_token( $request->get_param( 'provider' ), $request->get_param( 'token' ), $request->get_param( 'action' ), $request );
		if ( is_wp_error( $result ) ) return $result;
		return new WP_REST_Response( array( 'success' => true ) );
	}

	/** Reusable CAPTCHA boundary used by both JSON and multipart submissions. */
	public static function verify_token( $provider, $token, $action = 'cresco_form', $request = null ) {
		$provider = self::sanitize_provider( $provider );
		$raw_token = (string) $token;
		if ( '' === trim( $raw_token ) ) {
			return new WP_Error( 'cresco_captcha_missing', __( 'Complete the spam-protection challenge.', 'cresco-canvas' ), array( 'status' => 422 ) );
		}
		if ( strlen( $raw_token ) > self::MAX_CAPTCHA_TOKEN_BYTES ) {
			return new WP_Error( 'cresco_captcha_too_large', __( 'Spam-protection token is too large.', 'cresco-canvas' ), array( 'status' => 413 ) );
		}
		$token = sanitize_text_field( $raw_token );
		$action = substr( sanitize_key( (string) $action ), 0, 64 );
		$result = apply_filters( 'cresco_canvas_verify_captcha', null, $provider, $token, $request, $action );
		if ( true !== $result ) {
			return new WP_Error( 'cresco_captcha_failed', __( 'Spam-protection verification failed.', 'cresco-canvas' ), array( 'status' => 422 ) );
		}
		return true;
	}

	/** Inspect form structure without exposing submitted values. */
	public function diagnostics( WP_REST_Request $request ) {
		$post = get_post( absint( $request['postId'] ) );
		if ( ! $post ) return new WP_Error( 'cresco_form_post_missing', __( 'Post not found.', 'cresco-canvas' ), array( 'status' => 404 ) );
		$issues = array();
		$names = array();
		$visited = 0;
		$walk = static function ( $blocks ) use ( &$walk, &$issues, &$names, &$visited ) {
			foreach ( (array) $blocks as $block ) {
				if ( ++$visited > 500 ) {
					$issues[] = array( 'code' => 'structure_limit', 'message' => __( 'Form structure exceeds the diagnostics limit.', 'cresco-canvas' ) );
					return;
				}
				$name = (string) ( $block['blockName'] ?? '' );
				$attrs = (array) ( $block['attrs'] ?? array() );
				if ( FormBuilder::FIELD_BLOCK === $name ) {
					$field = FormBuilder::field_name( $attrs['name'] ?? '' );
					if ( ! $field ) $issues[] = array( 'code' => 'missing_field_name', 'message' => __( 'A form field has no valid name.', 'cresco-canvas' ) );
					elseif ( isset( $names[ $field ] ) ) $issues[] = array( 'code' => 'duplicate_field_name', 'message' => sprintf( __( 'Field name "%s" is duplicated.', 'cresco-canvas' ), $field ) );
					else $names[ $field ] = true;
				}
				if ( self::CALC_BLOCK === $name && ! self::sanitize_formula( $attrs['formula'] ?? '' ) ) $issues[] = array( 'code' => 'invalid_formula', 'message' => __( 'A calculated field has an invalid formula.', 'cresco-canvas' ) );
				if ( self::CAPTCHA_BLOCK === $name && empty( $attrs['siteKey'] ) ) $issues[] = array( 'code' => 'captcha_site_key', 'message' => __( 'A CAPTCHA block has no site key.', 'cresco-canvas' ) );
				if ( ! empty( $block['innerBlocks'] ) ) $walk( $block['innerBlocks'] );
			}
		};
		$walk( parse_blocks( (string) $post->post_content ) );
		return new WP_REST_Response( array( 'issues' => array_slice( $issues, 0, 100 ), 'fieldCount' => count( $names ) ) );
	}

	/** Schedule daily cleanup for non-network lifecycle paths. */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'cresco_canvas_daily_cleanup' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cresco_canvas_daily_cleanup' );
	}

	/** Delete orphaned legacy Media Library uploads after the retention window. */
	public function cleanup_uploads() {
		$retention = min( 365, max( 1, absint( get_option( 'cresco_canvas_upload_retention_days', 30 ) ) ) );
		$attachments = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 100, 'date_query' => array( array( 'before' => $retention . ' days ago' ) ), 'meta_key' => '_cresco_form_upload', 'meta_value' => '1', 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $attachments as $attachment_id ) {
			if ( ! get_post_meta( $attachment_id, '_cresco_submission_id', true ) ) wp_delete_attachment( $attachment_id, true );
		}
	}

	/** Formula grammar: numbers, field identifiers, parentheses, and arithmetic operators only. */
	public static function sanitize_formula( $formula ) {
		$formula = trim( (string) $formula );
		if ( '' === $formula || strlen( $formula ) > 240 || ! preg_match( '/^[a-zA-Z0-9_+\-*\/().\s]+$/', $formula ) ) return '';
		return preg_replace( '/\s+/', ' ', $formula );
	}

	public static function sanitize_provider( $value ) { $value = sanitize_key( (string) $value ); return in_array( $value, array( 'turnstile', 'recaptcha_v3', 'hcaptcha' ), true ) ? $value : 'turnstile'; }
	public static function sanitize_operator( $value ) { $value = sanitize_key( (string) $value ); return in_array( $value, array( 'equals', 'not_equals', 'contains', 'empty', 'not_empty' ), true ) ? $value : 'equals'; }
}
