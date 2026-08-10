<?php
/**
 * Keep Website Builder editor and frontend output aligned for document rendering.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Session\SessionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderRendererParity {
	const EDITOR_STYLE_HANDLE = 'cresco-canvas-website-builder';

	/** Register frontend repair and editor parity styles after the core builder. */
	public function register() {
		add_filter( 'the_content', array( $this, 'repair_frontend_forms' ), 26 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_parity_styles' ), 1000 );
	}

	/**
	 * Repair Website Builder Form widgets through the native Cresco Form blocks.
	 *
	 * WebsiteRenderer historically serialized synthetic parsed blocks with an
	 * empty innerContent array. WordPress could then reparse the outer Form with
	 * no inner field blocks, so FormBuilder rejected an otherwise valid widget.
	 * This compatibility pass reconstructs valid nested block comments and lets
	 * the native FormBuilder own rendering, validation, signing, and submission.
	 */
	public function repair_frontend_forms( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) return $content;

		$post_id = get_the_ID();
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return $content;

		$session = $this->load_session( $post_id );
		if ( ! $session ) return $content;

		$forms = array();
		$this->collect_form_nodes( $session['nodes'] ?? array(), $forms );
		if ( ! $forms ) return $content;

		foreach ( $forms as $node ) {
			$id = (string) ( $node['id'] ?? '' );
			if ( '' === $id ) continue;
			$native = $this->render_native_form( (array) ( $node['props'] ?? array() ) );
			if ( '' === $native ) continue;

			$quoted_id = preg_quote( $id, '~' );
			$pattern = '~(<div\b[^>]*\bdata-cresco-id="' . $quoted_id . '"[^>]*\bdata-cresco-widget="form"[^>]*>)\s*<p\b[^>]*class="[^"]*(?:cresco-form__warning|cresco-builder-placeholder)[^"]*"[^>]*>.*?</p>\s*(</div>)~s';
			$content = preg_replace_callback(
				$pattern,
				static function ( $matches ) use ( $native ) {
					return $matches[1] . $native . $matches[2];
				},
				$content,
				1
			);
		}

		return $content;
	}

	/** Add compiled document CSS to the visual canvas instead of a second mock renderer. */
	public function enqueue_editor_parity_styles() {
		if ( ! wp_style_is( self::EDITOR_STYLE_HANDLE, 'enqueued' ) ) return;

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing value.
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;

		$session = $this->load_session( $post_id );
		if ( ! $session ) return;

		$compiled = WebsiteBuilderCssCompiler::compile( $session );
		$compiled = str_replace( '.cresco-website-builder-root', '.cc-builder-canvas', $compiled );
		$compiled = preg_replace( '/@media\s*\(/', '@container (', $compiled );
		$compiled = is_string( $compiled ) ? $compiled : '';

		$parity = '.cc-builder-frame{container-type:inline-size;}'
			. '.cc-builder-canvas .cc-widget-form{display:grid;gap:1rem;}'
			. '.cc-builder-canvas .cc-widget-form>label{display:grid;gap:.4rem;min-width:0;}'
			. '.cc-builder-canvas .cc-widget-form input,.cc-builder-canvas .cc-widget-form textarea,.cc-builder-canvas .cc-widget-form select{box-sizing:border-box;width:100%;min-height:2.75rem;padding:.65rem .75rem;border:1px solid currentColor;border-radius:.25rem;background:Canvas;color:CanvasText;font:inherit;}'
			. '.cc-builder-canvas .cc-widget-form textarea{min-height:6rem;resize:vertical;}'
			. '.cc-builder-canvas .cc-widget-form button{justify-self:start;font:inherit;}'
			. $this->decoration_css( $session['nodes'] ?? array() )
			. $compiled;

		wp_add_inline_style( self::EDITOR_STYLE_HANDLE, $parity );
	}

	private function load_session( $post_id ) {
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		if ( '' === $raw ) return null;
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) return null;
		$session = WebsiteBuilder::sanitize_session( $decoded );
		return is_wp_error( $session ) ? null : $session;
	}

	private function collect_form_nodes( $nodes, &$forms ) {
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			if ( 'form' === ( $node['type'] ?? '' ) ) $forms[] = $node;
			if ( ! empty( $node['children'] ) ) $this->collect_form_nodes( $node['children'], $forms );
		}
	}

	private function render_native_form( $props ) {
		$fields_markup = '';
		$valid_fields  = 0;

		foreach ( (array) ( $props['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) ) continue;
			$name = sanitize_key( (string) ( $field['name'] ?? '' ) );
			if ( '' === $name ) continue;

			$field_attrs = array_filter(
				array(
					'name'        => $name,
					'label'       => (string) ( $field['label'] ?? '' ),
					'type'        => (string) ( $field['type'] ?? 'text' ),
					'required'    => ! empty( $field['required'] ),
					'placeholder' => (string) ( $field['placeholder'] ?? '' ),
					'options'     => (string) ( $field['options'] ?? '' ),
					'min'         => $field['min'] ?? null,
					'max'         => $field['max'] ?? null,
				),
				static function ( $value ) {
					return null !== $value && '' !== $value;
				}
			);
			$json = wp_json_encode( $field_attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( ! is_string( $json ) ) continue;
			$fields_markup .= '<!-- wp:cresco/form-field ' . $json . ' /-->';
			++$valid_fields;
		}

		if ( 0 === $valid_fields ) return '';

		$form_id = sanitize_key( (string) ( $props['formId'] ?? 'contact' ) );
		if ( '' === $form_id ) return '';
		$form_attrs = array(
			'formId'           => $form_id,
			'submitLabel'      => (string) ( $props['submitLabel'] ?? 'Submit' ),
			'successMessage'   => (string) ( $props['successMessage'] ?? 'Thank you.' ),
			'emailTo'          => sanitize_email( (string) ( $props['emailTo'] ?? '' ) ),
			'storeSubmissions' => ! empty( $props['storeSubmissions'] ),
			'redirectUrl'      => esc_url_raw( (string) ( $props['redirectUrl'] ?? '' ) ),
			'retentionDays'    => min( 365, max( 1, absint( $props['retentionDays'] ?? 30 ) ) ),
		);
		$json = wp_json_encode( $form_attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) return '';

		return do_blocks( '<!-- wp:cresco/form ' . $json . ' -->' . $fields_markup . '<!-- /wp:cresco/form -->' );
	}

	private function decoration_css( $nodes ) {
		$css = '';
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			if ( 'container' === ( $node['type'] ?? '' ) && empty( $node['children'] ) && $this->is_decoration( $node ) ) {
				$id = preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) ( $node['id'] ?? '' ) );
				if ( '' !== $id ) $css .= '.cc-builder-canvas [data-cresco-id="' . $id . '"]>.cc-builder-dropzone{display:none;}';
			}
			if ( ! empty( $node['children'] ) ) $css .= $this->decoration_css( $node['children'] );
		}
		return $css;
	}

	private function is_decoration( $node ) {
		$style = (array) ( $node['style'] ?? array() );
		if ( in_array( strtolower( (string) ( $style['position'] ?? '' ) ), array( 'absolute', 'fixed' ), true ) ) return true;

		$custom = (array) ( $node['customCSS'] ?? array() );
		foreach ( $custom as $css ) {
			if ( preg_match( '/position\s*:\s*(?:absolute|fixed)\b|pointer-events\s*:\s*none\b/i', (string) $css ) ) return true;
		}
		return false;
	}

	private function __construct() {}
}
