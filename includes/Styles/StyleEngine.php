<?php
/** Sanitized cross-block style engine. @package CrescoCanvas */
namespace CrescoCanvas\Styles;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class StyleEngine {
	const ATTRIBUTE = 'crescoStyle';
	const VERSION_ATTRIBUTE = 'crescoStyleVersion';
	const SCHEMA_VERSION = 1;

	public function register() {
		add_filter( 'register_block_type_args', array( $this, 'register_attributes' ), 20, 2 );
		add_filter( 'render_block', array( $this, 'render_block' ), 20, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ), 110 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 30 );
	}

	public function register_attributes( $args, $block_name ) {
		$args['attributes'] = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
		$args['attributes'][ self::ATTRIBUTE ] = $args['attributes'][ self::ATTRIBUTE ] ?? array( 'type' => 'object', 'default' => array() );
		$args['attributes'][ self::VERSION_ATTRIBUTE ] = $args['attributes'][ self::VERSION_ATTRIBUTE ] ?? array( 'type' => 'number', 'default' => self::SCHEMA_VERSION );
		return $args;
	}

	public function render_block( $block_content, $block ) {
		if ( '' === trim( (string) $block_content ) || empty( $block['attrs'] ) ) return $block_content;
		$declarations = self::declarations( $this->style_from_attributes( (array) $block['attrs'] ) );
		if ( ! $declarations || ! class_exists( '\\WP_HTML_Tag_Processor' ) ) return $block_content;
		$processor = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag() ) return $block_content;
		$processor->add_class( 'cresco-style-engine' );
		$existing = trim( (string) $processor->get_attribute( 'style' ) );
		$compiled = implode( ';', $declarations ) . ';';
		$processor->set_attribute( 'style', $existing ? rtrim( $existing, ';' ) . ';' . $compiled : $compiled );
		return $processor->get_updated_html();
	}

	public function enqueue_editor_assets() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/style-engine-editor.asset.php';
		if ( is_readable( $asset_file ) && is_readable( CRESCO_CANVAS_PATH . 'build/style-engine-editor.js' ) ) {
			$asset = require $asset_file;
			wp_enqueue_script( 'cresco-canvas-style-engine-editor', CRESCO_CANVAS_URL . 'build/style-engine-editor.js', (array) ( $asset['dependencies'] ?? array() ), (string) ( $asset['version'] ?? CRESCO_CANVAS_VERSION ), true );
		}
		$this->enqueue_shared_style();
	}

	public function enqueue_frontend_assets() { $this->enqueue_shared_style(); }

	public static function declarations( $style ) {
		$style = is_array( $style ) ? $style : array();
		$out = array();
		$map = array(
			array( array( 'dimensions', 'width' ), 'width', 'length' ), array( array( 'dimensions', 'minHeight' ), 'min-height', 'length' ), array( array( 'dimensions', 'height' ), 'height', 'length' ), array( array( 'dimensions', 'maxWidth' ), 'max-width', 'length' ),
			array( array( 'spacing', 'margin', 'top' ), 'margin-top', 'length' ), array( array( 'spacing', 'margin', 'right' ), 'margin-right', 'length' ), array( array( 'spacing', 'margin', 'bottom' ), 'margin-bottom', 'length' ), array( array( 'spacing', 'margin', 'left' ), 'margin-left', 'length' ),
			array( array( 'spacing', 'padding', 'top' ), 'padding-top', 'length' ), array( array( 'spacing', 'padding', 'right' ), 'padding-right', 'length' ), array( array( 'spacing', 'padding', 'bottom' ), 'padding-bottom', 'length' ), array( array( 'spacing', 'padding', 'left' ), 'padding-left', 'length' ),
			array( array( 'color', 'text' ), 'color', 'color' ), array( array( 'color', 'background' ), 'background-color', 'color' ), array( array( 'border', 'radius' ), 'border-radius', 'length' ),
			array( array( 'typography', 'fontSize' ), 'font-size', 'length' ), array( array( 'typography', 'lineHeight' ), 'line-height', 'number_or_length' ),
			array( array( 'effects', 'opacity' ), 'opacity', 'opacity' ), array( array( 'effects', 'transform' ), 'transform', 'transform' ), array( array( 'effects', 'boxShadow' ), 'box-shadow', 'shadow' ),
			array( array( 'position', 'top' ), 'top', 'length' ), array( array( 'position', 'right' ), 'right', 'length' ), array( array( 'position', 'bottom' ), 'bottom', 'length' ), array( array( 'position', 'left' ), 'left', 'length' ), array( array( 'position', 'zIndex' ), 'z-index', 'integer' ),
		);
		foreach ( $map as $item ) {
			$value = self::sanitize_value( self::path( $style, $item[0] ), $item[2] );
			if ( null !== $value && '' !== $value ) $out[] = $item[1] . ':' . $value;
		}
		$position = self::sanitize_choice( self::path( $style, array( 'position', 'type' ) ), array( 'static', 'relative', 'absolute', 'fixed', 'sticky' ) );
		if ( $position && 'static' !== $position ) $out[] = 'position:' . $position;
		$overflow = self::sanitize_choice( self::path( $style, array( 'position', 'overflow' ) ), array( 'visible', 'hidden', 'clip', 'auto', 'scroll' ) );
		if ( $overflow ) $out[] = 'overflow:' . $overflow;
		return array_values( array_unique( $out ) );
	}

	private function style_from_attributes( $attrs ) {
		$metadata = isset( $attrs['metadata'] ) && is_array( $attrs['metadata'] ) ? $attrs['metadata'] : array();
		$managed = isset( $metadata[ self::ATTRIBUTE ] ) && is_array( $metadata[ self::ATTRIBUTE ] ) ? $metadata[ self::ATTRIBUTE ] : array();
		if ( ! $managed && isset( $attrs[ self::ATTRIBUTE ] ) && is_array( $attrs[ self::ATTRIBUTE ] ) ) $managed = $attrs[ self::ATTRIBUTE ];
		$legacy = isset( $attrs['style'] ) && is_array( $attrs['style'] ) ? $attrs['style'] : array();
		foreach ( array( 'dimensions', 'spacing', 'color', 'border', 'typography', 'effects', 'position' ) as $group ) {
			if ( ! isset( $managed[ $group ] ) && isset( $legacy[ $group ] ) && is_array( $legacy[ $group ] ) ) $managed[ $group ] = $legacy[ $group ];
		}
		return $managed;
	}

	private function enqueue_shared_style() {
		wp_enqueue_style( 'cresco-canvas-style-engine', CRESCO_CANVAS_URL . 'assets/css/style-engine.css', array(), CRESCO_CANVAS_VERSION );
		$breakpoints = GlobalStyles::get_settings()['breakpoints'] ?? array();
		$mobile = max( 320, absint( $breakpoints['mobile'] ?? 480 ) );
		$tablet = max( $mobile + 1, absint( $breakpoints['tablet'] ?? 782 ) );
		$laptop = max( $tablet + 1, absint( $breakpoints['laptop'] ?? 1200 ) );
		$css = '@media (max-width:' . ( $tablet - 1 ) . 'px){.cresco-hide-mobile{display:none!important;}}';
		$css .= '@media (min-width:' . $tablet . 'px) and (max-width:' . ( $laptop - 1 ) . 'px){.cresco-hide-tablet{display:none!important;}}';
		$css .= '@media (min-width:' . $laptop . 'px){.cresco-hide-desktop{display:none!important;}}';
		wp_add_inline_style( 'cresco-canvas-style-engine', $css );
	}

	private static function path( $value, $path ) { foreach ( $path as $key ) { if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) return null; $value = $value[ $key ]; } return $value; }

	private static function sanitize_value( $value, $type ) {
		if ( null === $value || '' === $value ) return null;
		$value = trim( (string) $value );
		if ( strlen( $value ) > 180 || preg_match( '/[;{}<>]/', $value ) ) return null;
		switch ( $type ) {
			case 'length': return preg_match( '/^(?:-?\d+(?:\.\d+)?(?:px|rem|em|%|vw|vh|vmin|vmax|ch)|0|auto|fit-content|max-content|min-content|(?:calc|min|max|clamp)\([0-9a-zA-Z.%+*\/,_()\s-]+\))$/', $value ) ? $value : null;
			case 'number_or_length': return preg_match( '/^(?:\d+(?:\.\d+)?|-?\d+(?:\.\d+)?(?:px|rem|em|%))$/', $value ) ? $value : null;
			case 'color': if ( sanitize_hex_color( $value ) ) return $value; return preg_match( '/^(?:transparent|currentColor|var\(--[a-z0-9_-]+\)|rgba?\([0-9.,%\s]+\)|hsla?\([0-9.,%\s]+\))$/i', $value ) ? $value : null;
			case 'opacity': return (string) min( 1, max( 0, (float) $value ) );
			case 'integer': return preg_match( '/^-?\d{1,7}$/', $value ) ? (string) (int) $value : null;
			case 'transform': return preg_match( '/^(?:none|(?:translate(?:X|Y|3d)?|scale(?:X|Y|3d)?|rotate(?:X|Y|Z|3d)?|skew(?:X|Y)?|matrix(?:3d)?)\([-0-9a-zA-Z.%+,\s]+\)(?:\s+|$))+$/', $value ) ? $value : null;
			case 'shadow': return preg_match( '/^(?:none|[-0-9a-zA-Z.%(),\s\/]+)$/', $value ) ? $value : null;
		}
		return null;
	}

	private static function sanitize_choice( $value, $allowed ) { $value = sanitize_key( (string) $value ); return in_array( $value, $allowed, true ) ? $value : null; }
}
