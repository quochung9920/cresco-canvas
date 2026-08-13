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
	const EDITOR_STYLE_HANDLE   = 'cresco-canvas-website-builder';
	const FRONTEND_STYLE_HANDLE = 'cresco-canvas-website-builder-frontend';

	/** Register frontend repair and editor/frontend parity styles after the core builder. */
	public function register() {
		// Run after every legacy/core render filter so a later renderer cannot
		// overwrite the canonical Session output or repaired native Form output.
		add_filter( 'the_content', array( $this, 'repair_frontend_forms' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_form_assets' ), 46 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_parity_styles' ), 47 );
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

	/**
	 * Append the canonical compiler after the core frontend enqueue.
	 *
	 * WebsiteBuilder historically enqueued WebsiteRenderer::compile_css(), whose
	 * breakpoint interpretation predates Studio's desktop-first inheritance.
	 * Appending the canonical compiler on the same handle makes the final CSS
	 * cascade match Studio immediately while preserving backward compatibility
	 * for any third-party code that still calls the legacy compiler directly.
	 */
	public function enqueue_frontend_parity_styles() {
		if ( ! is_singular( 'page' ) ) return;
		$post_id = get_queried_object_id();
		if ( ! $post_id || WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;
		if ( ! wp_style_is( self::FRONTEND_STYLE_HANDLE, 'enqueued' ) ) return;
		$session = $this->load_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) return;
		$compiled = WebsiteBuilderCssCompiler::compile( $session );
		if ( '' !== $compiled ) wp_add_inline_style( self::FRONTEND_STYLE_HANDLE, $compiled );
	}

	/** Add compiled document CSS to the visual canvas instead of a second mock render. */
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
			. '.cc-builder-canvas .cc-studio-canvas-node{box-sizing:border-box;min-width:0;}'
			. '.cc-builder-canvas .cc-studio-canvas-node>h1,.cc-builder-canvas .cc-studio-canvas-node>h2,.cc-builder-canvas .cc-studio-canvas-node>kkºwµç