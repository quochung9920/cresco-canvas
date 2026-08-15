<?php
/**
 * Professional widget adapters and shared runtime bootstrap.
 *
 * Keeps the canonical WebsiteRenderer small by translating higher-level
 * widgets to existing safe primitives before render/compile, then annotates
 * the resulting markup for the shared browser interaction engine.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProfessionalWidgets {
	/** @var bool */
	private static $rendering = false;

	/** @var bool */
	private static $compiling = false;

	/** Professional widget types understood by this adapter. */
	private const TYPES = array(
		'carousel', 'slides', 'loop-carousel', 'marquee', 'image-carousel',
		'testimonial-carousel', 'logo-carousel', 'media-carousel', 'before-after',
		'timeline', 'pricing-table', 'countdown', 'modal', 'off-canvas',
		'comparison-table', 'hotspot-image', 'flip-card', 'animated-headline',
		'progress-circle', 'rating', 'site-search', 'advanced-breadcrumbs', 'map',
	);

	/** Register render adapters plus editor/frontend assets. */
	public function register() {
		add_filter( 'cresco_canvas_rendered_document', array( $this, 'render_document' ), 90, 3 );
		add_filter( 'cresco_canvas_compiled_css', array( $this, 'compile_css' ), 90, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ), 130 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 60 );
	}

	/** Replace a rendered document only when it contains a professional widget. */
	public function render_document( $html, $session, $post_id = 0 ) {
		if ( self::$rendering || ! self::contains_professional_widgets( $session['nodes'] ?? array() ) ) return $html;
		self::$rendering = true;
		$configs = array();
		try {
			$adapted = self::transform_session( $session, $configs );
			$html = WebsiteRenderer::render_document( $adapted, $post_id );
		} finally {
			self::$rendering = false;
		}
		return self::annotate_markup( $html, $configs );
	}

	/** Recompile CSS against the translated primitive document. */
	public function compile_css( $css, $session ) {
		if ( self::$compiling || ! self::contains_professional_widgets( $session['nodes'] ?? array() ) ) return $css;
		self::$compiling = true;
		try {
			$adapted = self::transform_session( $session );
			$css = WebsiteRenderer::compile_css( $adapted );
		} finally {
			self::$compiling = false;
		}
		return $css;
	}

	/** Public for regression tests and integration tooling. */
	public static function transform_session( $session, &$configs = null ) {
		$session = is_array( $session ) ? $session : array();
		$configs = is_array( $configs ) ? $configs : array();
		$nodes = array();
		foreach ( (array) ( $session['nodes'] ?? array() ) as $node ) $nodes[] = self::transform_node( $node, $configs );
		$session['nodes'] = $nodes;
		return $session;
	}

	private static function transform_node( $node, &$configs ) {
		$node = is_array( $node ) ? $node : array();
		$type = (string) ( $node['type'] ?? '' );
		$children = array();
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) $children[] = self::transform_node( $child, $configs );
		$node['children'] = $children;
		if ( ! in_array( $type, self::TYPES, true ) ) return $node;

		$id = (string) ( $node['id'] ?? '' );
		$configs[ $id ] = array( 'type' => $type, 'props' => (array) ( $node['props'] ?? array() ) );
		$props = (array) ( $node['props'] ?? array() );

		if ( 'loop-carousel' === $type ) {
			$node['type'] = 'loop-grid';
			$node['props'] = self::pick( $props, array( 'postType', 'perPage', 'columns', 'order', 'orderBy', 'taxonomy', 'term', 'showImage', 'showExcerpt', 'showDate', 'buttonLabel' ) );
			return $node;
		}
		if ( 'image-carousel' === $type ) {
			$node['type'] = 'gallery';
			$node['props'] = array(
				'images' => (array) ( $props['images'] ?? array() ), 'columns' => 1, 'gap' => '0px',
				'aspectRatio' => (string) ( $props['aspectRatio'] ?? '16 / 9' ),
				'objectFit' => (string) ( $props['objectFit'] ?? 'cover' ),
				'showCaptions' => ! empty( $props['showCaptions'] ), 'captionAlign' => 'center', 'lightbox' => false,
			);
			return $node;
		}
		if ( 'before-after' === $type ) {
			$node['type'] = 'container';
			$node['props'] = self::container_props( 'block' );
			$node['children'] = array(
				self::image_node( $id . '-before', $props['beforeImage'] ?? '', $props['beforeLabel'] ?? 'Before' ),
				self::image_node( $id . '-after', $props['afterImage'] ?? '', $props['afterLabel'] ?? 'After' ),
			);
			return $node;
		}
		if ( 'advanced-breadcrumbs' === $type ) {
			$node['type'] = 'breadcrumbs';
			$node['props'] = array( 'separator' => (string) ( $props['separator'] ?? '/' ), 'showHome' => ! empty( $props['showHome'] ) );
			return $node;
		}

		// All remaining advanced widgets render through a safe Container shell.
		$node['type'] = 'container';
		$node['props'] = self::container_props( 'block' );
		return $node;
	}

	private static function image_node( $id, $url, $alt ) {
		return array(
			'id' => sanitize_key( $id ), 'type' => 'image',
			'props' => array( 'url' => esc_url_raw( (string) $url ), 'alt' => sanitize_text_field( (string) $alt ), 'caption' => '', 'link' => '', 'objectFit' => 'cover', 'aspectRatio' => '' ),
			'style' => array(), 'responsive' => array(), 'states' => array(), 'customCSS' => array(),
			'meta' => array( 'label' => '', 'componentId' => 0, 'locked' => false, 'hidden' => false ), 'children' => array(),
		);
	}

	private static function container_props( $layout = 'block' ) {
		return array( 'contentWidth' => 'full', 'layout' => $layout, 'direction' => 'column', 'wrap' => 'nowrap', 'align' => 'stretch', 'justify' => 'flex-start', 'columns' => 2, 'gridTemplate' => 'repeat(2,minmax(0,1fr))', 'tag' => 'div', 'ariaLabel' => '' );
	}

	private static function pick( $source, $keys ) {
		$output = array();
		foreach ( $keys as $key ) if ( array_key_exists( $key, $source ) ) $output[ $key ] = $source[ $key ];
		return $output;
	}

	private static function contains_professional_widgets( $nodes ) {
		foreach ( (array) $nodes as $node ) {
			if ( in_array( (string) ( $node['type'] ?? '' ), self::TYPES, true ) ) return true;
			if ( self::contains_professional_widgets( $node['children'] ?? array() ) ) return true;
		}
		return false;
	}

	private static function annotate_markup( $html, $configs ) {
		foreach ( $configs as $id => $config ) {
			$safe_id = preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) $id );
			$needle = ' data-cresco-id="' . $safe_id . '"';
			if ( false === strpos( $html, $needle ) ) continue;
			$json = wp_json_encode( $config['props'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$encoded = rtrim( strtr( base64_encode( (string) $json ), '+/', '-_' ), '=' );
			$attrs = $needle . ' data-cresco-pro-widget="' . esc_attr( $config['type'] ) . '" data-cresco-pro-config="' . esc_attr( $encoded ) . '"';
			$html = preg_replace( '/' . preg_quote( $needle, '/' ) . '/', $attrs, $html, 1 );
		}
		return $html;
	}

	/** Add visual border controls after the canonical editor has been enqueued. */
	public function enqueue_editor_assets() {
		if ( ! wp_script_is( 'cresco-canvas-website-builder', 'enqueued' ) ) return;
		wp_enqueue_style( 'cresco-canvas-professional-widget-controls', CRESCO_CANVAS_URL . 'assets/css/professional-widget-controls.css', array( 'cresco-canvas-website-builder' ), CRESCO_CANVAS_VERSION );
		wp_enqueue_script( 'cresco-canvas-professional-widget-controls', CRESCO_CANVAS_URL . 'assets/js/professional-widget-controls.js', array( 'cresco-canvas-website-builder' ), CRESCO_CANVAS_VERSION, true );
	}

	/** Load one shared interaction engine for all professional frontend widgets. */
	public function enqueue_frontend_assets() {
		if ( ! wp_script_is( 'cresco-canvas-website-builder-frontend', 'enqueued' ) ) return;
		wp_enqueue_style( 'cresco-canvas-professional-widgets', CRESCO_CANVAS_URL . 'assets/css/professional-widgets.css', array( 'cresco-canvas-website-builder-frontend' ), CRESCO_CANVAS_VERSION );
		wp_enqueue_script( 'cresco-canvas-professional-widgets', CRESCO_CANVAS_URL . 'assets/js/professional-widgets.js', array( 'cresco-canvas-website-builder-frontend' ), CRESCO_CANVAS_VERSION, true );
	}
}
