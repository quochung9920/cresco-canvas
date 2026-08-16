<?php
/**
 * Rendering adapters and shared assets for the professional widget expansion.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProfessionalWidgetExpansion {
	private static $rendering = false;
	private static $compiling = false;

	private const TYPES = array(
		'nested-card', 'clickable-container', 'cta', 'icon-list', 'badge',
		'advanced-divider', 'team-member', 'faq', 'data-table', 'table-of-contents',
		'video-popup', 'stats-card', 'advanced-icon', 'logo-grid', 'steps', 'alert',
		'blockquote', 'mega-menu', 'nested-tabs', 'tab-panel', 'nested-accordion',
		'accordion-item', 'filterable-grid', 'filter-item', 'gallery',
	);

	public function register() {
		// Run after the existing ProfessionalWidgets adapter. During our nested
		// canonical render it can still translate the original professional suite.
		add_filter( 'cresco_canvas_rendered_document', array( $this, 'render_document' ), 100, 3 );
		add_filter( 'cresco_canvas_compiled_css', array( $this, 'compile_css' ), 100, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ), 145 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 65 );
	}

	public function render_document( $html, $session, $post_id = 0 ) {
		if ( self::$rendering || ! self::contains_expansion_widgets( $session['nodes'] ?? array() ) ) return $html;
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

	public function compile_css( $css, $session ) {
		if ( self::$compiling || ! self::contains_expansion_widgets( $session['nodes'] ?? array() ) ) return $css;
		self::$compiling = true;
		try {
			$adapted = self::transform_session( $session );
			$css = WebsiteRenderer::compile_css( $adapted );
		} finally {
			self::$compiling = false;
		}
		return $css;
	}

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

		if ( ! self::is_expansion_widget( $node ) ) return $node;
		$id = (string) ( $node['id'] ?? '' );
		$props = (array) ( $node['props'] ?? array() );
		$configs[ $id ] = array( 'type' => $type, 'props' => $props );

		// Gallery remains a canonical Gallery; the expansion runtime only adds
		// masonry/responsive/hover behavior from its newly structured props.
		if ( 'gallery' === $type ) return $node;

		if ( 'faq' === $type ) {
			$node['type'] = 'accordion';
			$node['props'] = array(
				'items' => (array) ( $props['items'] ?? array() ),
				'allowMulti' => ! empty( $props['allowMulti'] ),
				'titleTag' => 'h3',
				'iconPosition' => 'end',
				'expandIcon' => (string) ( $props['expandIcon'] ?? 'plus-alt2' ),
				'collapseIcon' => (string) ( $props['collapseIcon'] ?? 'minus' ),
			);
			return $node;
		}

		if ( 'mega-menu' === $type ) {
			$node['type'] = 'nav-menu';
			$node['props'] = array(
				'menu' => absint( $props['menu'] ?? 0 ),
				'orientation' => 'horizontal',
				'depth' => min( 5, max( 1, absint( $props['depth'] ?? 3 ) ) ),
			);
			return $node;
		}

		// Every other expansion widget renders through a safe canonical Container.
		// Nested children are preserved and remain individually editable/renderable.
		$layout = 'block';
		if ( in_array( $type, array( 'cta', 'logo-grid', 'steps', 'filterable-grid' ), true ) ) $layout = 'flex';
		$node['type'] = 'container';
		$node['props'] = self::container_props( $layout );
		return $node;
	}

	private static function container_props( $layout = 'block' ) {
		return array(
			'contentWidth' => 'full', 'layout' => $layout, 'direction' => 'column',
			'wrap' => 'nowrap', 'align' => 'stretch', 'justify' => 'flex-start',
			'columns' => 2, 'gridTemplate' => 'repeat(2,minmax(0,1fr))',
			'tag' => 'div', 'ariaLabel' => '',
		);
	}

	private static function is_expansion_widget( $node ) {
		$type = (string) ( $node['type'] ?? '' );
		if ( ! in_array( $type, self::TYPES, true ) ) return false;
		// Every Gallery is annotated so responsive column controls always work.
		// Enhanced navigation is still opt-in inside the browser runtime.
		return true;
	}

	private static function contains_expansion_widgets( $nodes ) {
		foreach ( (array) $nodes as $node ) {
			if ( self::is_expansion_widget( $node ) ) return true;
			if ( self::contains_expansion_widgets( $node['children'] ?? array() ) ) return true;
		}
		return false;
	}

	private static function annotate_markup( $html, $configs ) {
		$faq_scripts = '';
		foreach ( (array) $configs as $id => $config ) {
			$safe_id = preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) $id );
			$needle = ' data-cresco-id="' . $safe_id . '"';
			if ( false === strpos( $html, $needle ) ) continue;
			$json = wp_json_encode( $config['props'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$encoded = rtrim( strtr( base64_encode( (string) $json ), '+/', '-_' ), '=' );
			$attrs = $nedle . ' data-cresco-exp-widget="' . esc_attr( $config['type'] ) . '" data-cresco-exp-config="' . esc_attr( $encoded ) . '"';
			$html = preg_replace( '/' . preg_quote( $needle, '/' ) . '/', $attrs, $html, 1 );
			if ( 'faq' === ( $config['type'] ?? '' ) && ! empty( $config['props']['faqSchema'] ) ) $faq_scripts .= self::faq_schema_script( $safe_id, $config['props'] );
		}
		return $html . $faq_scripts;
	}

	private static function faq_schema_script( $id, $props ) {
		$entities = array();
		foreach ( (array) ( $props['items'] ?? array() ) as $item ) {
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$answer = trim( wp_strip_all_tags( (string) ( $item['content'] ?? '' ) ) );
			if ( '' === $title || '' === $answer ) continue;
			$entities[] = array(
				'@type' => 'Question',
				'name' => $title,
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $answer ),
			);
		}
		if ( ! $entities ) return '';
		$data = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities );
		return '<script type="application/ld+json" data-cresco-faq-schema="' . esc_attr( $id ) . '">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}

	public function enqueue_editor_assets() {
		if ( ! wp_script_is( 'cresco-canvas-website-builder', 'enqueued' ) ) return;
		wp_enqueue_style(
			'cresco-canvas-professional-widget-expansion-editor',
			CRESCO_CANVAS_URL . 'assets/css/professional-widget-expansion-editor.css',
			array( 'cresco-canvas-website-builder' ),
			WebsiteBuilderAsset::version( 'assets/css/professional-widget-expansion-editor.css' )
		);
	}

	public function enqueue_frontend_assets() {
		if ( ! wp_script_is( 'cresco-canvas-website-builder-frontend', 'enqueued' ) ) return;
		wp_enqueue_style(
			'cresco-canvas-professional-widget-expansion',
			CRESCO_CANVAS_URL . 'assets/css/professional-widget-expansion.css',
			array( 'cresco-canvas-website-builder-frontend' ),
			WebsiteBuilderAsset::version( 'assets/css/professional-widget-expansion.css' )
		);
		wp_enqueue_script(
			'cresco-canvas-professional-widget-expansion',
			CRESCO_CANVAS_URL . 'assets/js/professional-widget-expansion.js',
			array( 'cresco-canvas-website-builder-frontend' ),
			WebsiteBuilderAsset::version( 'assets/js/professional-widget-expansion.js' ),
			true
		);
	}
}
