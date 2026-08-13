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
		// Run after every legacy/core render filter so a later renderer cannot
		// overwrite the canonical Session output or repaired native Form output.
		add_filter( 'the_content', array( $this, 'repair_frontend_forms' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_form_assets' ), 46 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_parity_styles' ), 1000 );
	}

	/**
	 * Finalize the authoritative Website Builder document at the last Cresco
	 * the_content boundary, then repair legacy Form placeholders if necessary.
	 *
	 * WordPress previews and some block themes can render the queried Page while
	 * in_the_loop()/is_main_query() are false. Using the queried Page identity,
	 * with revision-parent normalization, keeps Preview aligned with Studio
	 * without hijacking nested posts rendered by secondary queries.
	 */
	public function repair_frontend_forms( $content ) {
		$post_id = self::frontend_page_id();
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return $content;
		$session = $this->load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return $content;

		$architecture = WebsiteBuilderArchitectureV2::load_document( $post_id, $session );
		$rendered     = WebsiteRendererV2::render_document( $session, $post_id, $architecture );
		return self::repair_document_html( $rendered, $session );
	}

	/**
	 * Decide whether a content post belongs to the queried Page.
	 *
	 * A zero content id is accepted because some block-theme preview paths apply
	 * the_content without populating the traditional Loop globals. A revision or
	 * autosave is accepted only when its parent is the queried Page.
	 */
	public static function matches_frontend_page( $queried_id, $content_id = 0, $revision_parent_id = 0 ) {
		$queried_id         = absint( $queried_id );
		$content_id         = absint( $content_id );
		$revision_parent_id = absint( $revision_parent_id );
		if ( ! $queried_id ) return false;
		if ( $revision_parent_id ) $content_id = $revision_parent_id;
		return ! $content_id || $content_id === $queried_id;
	}

	/** Resolve the canonical frontend Page without requiring the classic Loop. */
	private static function frontend_page_id() {
		if ( is_admin() || ! is_singular( 'page' ) ) return 0;
		$queried_id = absint( get_queried_object_id() );
		if ( ! $queried_id || 'page' !== get_post_type( $queried_id ) ) return 0;

		$content_id = absint( get_the_ID() );
		$parent_id  = 0;
		if ( $content_id && function_exists( 'wp_is_post_revision' ) ) {
			$parent_id = absint( wp_is_post_revision( $content_id ) );
		}
		return self::matches_frontend_page( $queried_id, $content_id, $parent_id ) ? $queried_id : 0;
	}

	/**
	 * Repair a rendered Website Builder document using its authoritative Session.
	 *
	 * This is intentionally pure with respect to storage so RenderEngine, Theme
	 * Builder, preview endpoints and the_content can share the same boundary.
	 */
	public static function repair_document_html( $content, $session ) {
		if ( ! is_string( $content ) || '' === $content || ! is_array( $session ) ) return $content;
		$forms = array();
		self::collect_form_nodes_static( $session['nodes'] ?? array(), $forms );
		if ( ! $forms ) return $content;

		foreach ( $forms as $node ) {
			$id = (string) ( $node['id'] ?? '' );
			if ( '' === $id ) continue;
			$native = self::render_native_form( (array) ( $node['props'] ?? array() ) );
			if ( '' === $native ) continue;

			$quoted_id = preg_quote( $id, '~' );
			$pattern = '~(<div\b[^>]*\bdata-cresco-id="' . $quoted_id . '"[^>]*\bdata-cresco-widget="form"[^>]*>)\s*<p\b[^>]*class="[^"]*(?:cresco-form__warning|cresco-builder-placeholder)[^"]*"[^>]*>.*?</p>\s*(</div>)~s';
			$replaced = preg_replace_callback(
				$pattern,
				static function ( $matches ) use ( $native ) {
					return $matches[1] . $native . $matches[2];
				},
				$content,
				1
			);
			if ( is_string( $replaced ) ) $content = $replaced;
		}
		return $content;
	}

	/** Enqueue native Form assets early when a builder document contains Form widgets. */
	public function enqueue_frontend_form_assets() {
		if ( ! is_singular( 'page' ) ) return;
		$post_id = get_queried_object_id();
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;
		$session = $this->load_session( $post_id );
		if ( ! $session ) return;
		$forms = array();
		self::collect_form_nodes_static( $session['nodes'] ?? array(), $forms );
		if ( ! $forms ) return;
		wp_enqueue_style( 'cresco-canvas-forms' );
		wp_enqueue_script( 'cresco-canvas-forms-frontend' );
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

	private static function collect_form_nodes_static( $nodes, &$forms ) {
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) continue;
			if ( 'form' === ( $node['type'] ?? '' ) ) $forms[] = $node;
			if ( ! empty( $node['children'] ) ) self::collect_form_nodes_static( $node['children'], $forms );
		}
	}

	private static function render_native_form( $props ) {
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
				static function ( $value ) { return null !== $value && '' !== $value; }
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
		foreach ( $custom as $css ) if ( preg_match( '/position\s*:\s*(?:absolute|fixed)\b|pointer-events\s*:\s*none\b/i', (string) $css ) ) return true;
		return false;
	}

	public function __construct() {}
}
