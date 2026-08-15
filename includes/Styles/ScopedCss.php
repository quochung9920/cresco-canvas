<?php
/**
 * Safe, widget-scoped Custom CSS parser and compiler.
 *
 * Supports arbitrary CSS declarations plus scoped group at-rules and local
 * keyframes. External-resource and document-global escape hatches remain
 * intentionally unavailable.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopedCss {
	const GROUP_AT_RULES = array( 'media', 'supports', 'container', 'layer' );
	const KEYFRAME_AT_RULES = array( 'keyframes', '-webkit-keyframes' );

	/** Validate and normalize one Custom CSS bucket. */
	public static function sanitize( $value, $max_bytes = 16000 ) {
		$css = trim( (string) $value );
		if ( '' === $css ) return '';
		if ( strlen( $css ) > max( 1, (int) $max_bytes ) ) return self::error( 'cresco_builder_css_size', 'Widget Custom CSS is too large.' );
		if ( false !== strpos( $css, "\0" ) || preg_match( '/<\/?(?:style|script)\b|<!--|-->/i', $css ) ) return self::error( 'cresco_builder_css_forbidden', 'Widget Custom CSS contains a forbidden construct.' );
		if ( preg_match( '/(?:expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding\s*:)/i', $css ) ) return self::error( 'cresco_builder_css_forbidden', 'Widget Custom CSS contains a forbidden construct.' );
		if ( preg_match( '/(?:^|[;{}\s])@(?:import|charset|namespace|document)\b/i', $css ) ) return self::error( 'cresco_builder_css_forbidden', 'Widget Custom CSS cannot load or redefine document-global resources.' );
		if ( preg_match( '/url\s*\(/i', $css ) ) return self::error( 'cresco_builder_css_forbidden', 'Widget Custom CSS cannot load external URL resources.' );

		$clean = preg_replace( '#/\*.*?\*/#s', '', $css );
		if ( null === $clean ) return self::error( 'cresco_builder_css_invalid', 'Widget Custom CSS could not be parsed.' );
		$parsed = self::parse_stylesheet( $clean );
		if ( is_wp_error( $parsed ) ) return $parsed;
		return trim( $clean );
	}

	/** Compile one validated bucket against a concrete widget selector. */
	public static function compile( $value, $selector, $scope_id = '', $max_bytes = 16000 ) {
		$clean = self::sanitize( $value, $max_bytes );
		if ( is_wp_error( $clean ) || '' === $clean ) return $clean;
		$ast = self::parse_stylesheet( $clean );
		if ( is_wp_error( $ast ) ) return $ast;

		$scope = preg_replace( '/[^a-zA-Z0-9_-]+/', '-', (string) $scope_id );
		$scope = trim( (string) $scope, '-' );
		if ( '' === $scope ) $scope = substr( hash( 'sha256', (string) $selector ), 0, 16 );

		$keyframes = array();
		self::collect_keyframes( $ast, $scope, $keyframes );
		return self::compile_rules( $ast, (string) $selector, $keyframes );
	}

	private static function parse_stylesheet( $css ) {
		$rules = array();
		$length = strlen( $css );
		$cursor = 0;
		while ( true ) {
			self::skip_space( $css, $cursor, $length );
			if ( $cursor >= $length ) break;
			$header = self::read_header( $css, $cursor, $length );
			if ( is_wp_error( $header ) ) return $header;
			if ( ';' === $header['terminator'] ) return self::error( 'cresco_builder_css_at_rule', 'Blockless Custom CSS at-rules are not supported.' );
			$body = self::read_block( $css, $cursor, $length );
			if ( is_wp_error( $body ) ) return $body;
			$prelude = trim( $header['text'] );
			if ( '' === $prelude ) return self::error( 'cresco_builder_css_selector', 'Widget Custom CSS contains an empty selector or at-rule.' );

			if ( '@' === $prelude[0] ) {
				if ( ! preg_match( '/^@(-?[a-zA-Z][a-zA-Z0-9-]*)(?:\s+([\s\S]*))?$/', $prelude, $match ) ) return self::error( 'cresco_builder_css_at_rule', 'Widget Custom CSS contains an invalid at-rule.' );
				$name = strtolower( $match[1] );
				$params = trim( (string) ( $match[2] ?? '' ) );
				if ( in_array( $name, self::KEYFRAME_AT_RULES, true ) ) {
					if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $params ) ) return self::error( 'cresco_builder_css_keyframes', 'Custom CSS keyframes require a simple local animation name.' );
					$steps = self::parse_keyframe_steps( $body );
					if ( is_wp_error( $steps ) ) return $steps;
					$rules[] = array( 'kind' => 'keyframes', 'name' => $name, 'animation' => $params, 'steps' => $steps );
					continue;
				}
				if ( in_array( $name, self::GROUP_AT_RULES, true ) ) {
					if ( '' === $params && 'layer' !== $name ) return self::error( 'cresco_builder_css_at_rule', 'Custom CSS group at-rule is missing its condition.' );
					if ( preg_match( '/[{};]/', $params ) ) return self::error( 'cresco_builder_css_at_rule', 'Custom CSS group at-rule has an invalid prelude.' );
					$children = self::parse_stylesheet( $body );
					if ( is_wp_error( $children ) ) return $children;
					$rules[] = array( 'kind' => 'group', 'name' => $name, 'params' => $params, 'rules' => $children );
					continue;
				}
				return self::error( 'cresco_builder_css_at_rule', 'Widget Custom CSS contains an unsupported at-rule.' );
			}

			$selector_check = self::validate_selectors( $prelude );
			if ( is_wp_error( $selector_check ) ) return $selector_check;
			$declarations = self::parse_declarations( $body );
			if ( is_wp_error( $declarations ) ) return $declarations;
			$rules[] = array( 'kind' => 'style', 'selector' => $prelude, 'declarations' => $declarations );
		}
		return $rules;
	}

	private static function parse_keyframe_steps( $css ) {
		$steps = array();
		$length = strlen( $css );
		$cursor = 0;
		while ( true ) {
			self::skip_space( $css, $cursor, $length );
			if ( $cursor >= $length ) break;
			$header = self::read_header( $css, $cursor, $length );
			if ( is_wp_error( $header ) ) return $header;
			if ( ';' === $header['terminator'] ) return self::error( 'cresco_builder_css_keyframes', 'Keyframe steps must use declaration blocks.' );
			$body = self::read_block( $css, $cursor, $length );
			if ( is_wp_error( $body ) ) return $body;
			$selector = trim( $header['text'] );
			foreach ( self::split_top_level( $selector, ',' ) as $part ) {
				$part = trim( $part );
				if ( ! preg_match( '/^(?:from|to|(?:100|[0-9]{1,2})(?:\.\d+)?%)$/i', $part ) ) return self::error( 'cresco_builder_css_keyframes', 'Keyframe step selectors must be from, to, or percentages.' );
			}
			$declarations = self::parse_declarations( $body );
			if ( is_wp_error( $declarations ) ) return $declarations;
			$steps[] = array( 'selector' => $selector, 'declarations' => $declarations );
		}
		return $steps;
	}

	private static function parse_declarations( $body ) {
		if ( false !== strpos( $body, '{' ) || false !== strpos( $body, '}' ) ) return self::error( 'cresco_builder_css_declarations', 'Nested selectors must be written as explicit widget-scoped rules.' );
		$items = self::split_top_level( $body, ';' );
		$output = array();
		foreach ( $items as $item ) {
			$item = trim( $item );
			if ( '' === $item ) continue;
			$pair = self::split_declaration( $item );
			if ( is_wp_error( $pair ) ) return $pair;
			$property = trim( $pair[0] );
			$value = trim( $pair[1] );
			if ( ! preg_match( '/^(?:--[a-zA-Z0-9_-]+|-?[a-zA-Z][a-zA-Z0-9-]*)$/', $property ) ) return self::error( 'cresco_builder_css_property', 'Widget Custom CSS contains an invalid property name.' );
			if ( '' === $value ) return self::error( 'cresco_builder_css_value', 'Widget Custom CSS contains an empty property value.' );
			if ( preg_match( '/(?:expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding\s*:|url\s*\()/i', $value ) ) return self::error( 'cresco_builder_css_forbidden', 'Widget Custom CSS contains a forbidden property value.' );
			if ( preg_match( '/<\/?(?:style|script)\b/i', $value ) ) return self::error( 'cresco_builder_css_forbidden', 'Widget Custom CSS contains a forbidden property value.' );
			$output[] = array( 'property' => $property, 'value' => $value );
		}
		if ( empty( $output ) ) return self::error( 'cresco_builder_css_declarations', 'Widget Custom CSS rule contains no declarations.' );
		return $output;
	}

	private static function validate_selectors( $selector ) {
		foreach ( self::split_top_level( $selector, ',' ) as $part ) {
			$part = trim( $part );
			if ( '' === $part || false === strpos( $part, '&' ) ) return self::error( 'cresco_builder_css_scope', 'Every Widget Custom CSS selector must include &.' );
			if ( preg_match( '/(^|[\s>+~,(])(?:html|body|:root|#wpwrap|#wpcontent)(?=$|[\s>+~.#:\[])/i', $part ) ) return self::error( 'cresco_builder_css_global', 'Widget Custom CSS cannot escape its widget scope.' );
			if ( false !== strpos( $part, '@' ) || false !== strpos( $part, '{' ) || false !== strpos( $part, '}' ) ) return self::error( 'cresco_builder_css_selector', 'Widget Custom CSS contains an invalid selector.' );
		}
		return true;
	}

	private static function collect_keyframes( $rules, $scope, &$map ) {
		foreach ( $rules as $rule ) {
			if ( 'keyframes' === $rule['kind'] ) {
				$name = (string) $rule['animation'];
				if ( ! isset( $map[ $name ] ) ) $map[ $name ] = substr( 'cresco-kf-' . $scope . '-' . $name, 0, 180 );
			} elseif ( 'group' === $rule['kind'] ) {
				self::collect_keyframes( $rule['rules'], $scope, $map );
			}
		}
	}

	private static function compile_rules( $rules, $selector, $keyframes ) {
		$output = '';
		foreach ( $rules as $rule ) {
			if ( 'style' === $rule['kind'] ) {
				$selectors = array();
				foreach ( self::split_top_level( $rule['selector'], ',' ) as $part ) $selectors[] = str_replace( '&', $selector, trim( $part ) );
				$output .= implode( ',', $selectors ) . '{' . self::compile_declarations( $rule['declarations'], $keyframes ) . '}';
				continue;
			}
			if ( 'keyframes' === $rule['kind'] ) {
				$name = $keyframes[ $rule['animation'] ] ?? $rule['animation'];
				$output .= '@' . $rule['name'] . ' ' . $name . '{';
				foreach ( $rule['steps'] as $step ) $output .= $step['selector'] . '{' . self::compile_declarations( $step['declarations'], $keyframes ) . '}';
				$output .= '}';
				continue;
			}
			if ( 'group' === $rule['kind'] ) {
				$output .= '@' . $rule['name'] . ( '' !== $rule['params'] ? ' ' . $rule['params'] : '' ) . '{' . self::compile_rules( $rule['rules'], $selector, $keyframes ) . '}';
			}
		}
		return $output;
	}

	private static function compile_declarations( $declarations, $keyframes ) {
		$output = '';
		foreach ( $declarations as $declaration ) {
			$property = (string) $declaration['property'];
			$value = (string) $declaration['value'];
			if ( preg_match( '/^(?:-webkit-)?animation(?:-name)?$/i', $property ) ) {
				foreach ( $keyframes as $local => $scoped ) $value = preg_replace( '/(?<![a-zA-Z0-9_-])' . preg_quote( $local, '/' ) . '(?![a-zA-Z0-9_-])/', $scoped, $value );
			}
			$output .= $property . ':' . $value . ';';
		}
		return $output;
	}

	private static function read_header( $css, &$cursor, $length ) {
		$start = $cursor;
		$quote = '';
		$escape = false;
		$paren = 0;
		$bracket = 0;
		for ( ; $cursor < $length; ++$cursor ) {
			$char = $css[ $cursor ];
			if ( $quote ) {
				if ( $escape ) { $escape = false; continue; }
				if ( '\\' === $char ) { $escape = true; continue; }
				if ( $quote === $char ) $quote = '';
				continue;
			}
			if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
			if ( '(' === $char ) { ++$paren; continue; }
			if ( ')' === $char ) { $paren = max( 0, $paren - 1 ); continue; }
			if ( '[' === $char ) { ++$bracket; continue; }
			if ( ']' === $char ) { $bracket = max( 0, $bracket - 1 ); continue; }
			if ( 0 === $paren && 0 === $bracket && ( '{' === $char || ';' === $char ) ) {
				$text = substr( $css, $start, $cursor - $start );
				$terminator = $char;
				++$cursor;
				return array( 'text' => $text, 'terminator' => $terminator );
			}
		}
		return self::error( 'cresco_builder_css_braces', 'Widget Custom CSS has an unterminated rule.' );
	}

	private static function read_block( $css, &$cursor, $length ) {
		$start = $cursor;
		$depth = 1;
		$quote = '';
		$escape = false;
		for ( ; $cursor < $length; ++$cursor ) {
			$char = $css[ $cursor ];
			if ( $quote ) {
				if ( $escape ) { $escape = false; continue; }
				if ( '\\' === $char ) { $escape = true; continue; }
				if ( $quote === $char ) $quote = '';
				continue;
			}
			if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
			if ( '{' === $char ) { ++$depth; continue; }
			if ( '}' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					$body = substr( $css, $start, $cursor - $start );
					++$cursor;
					return $body;
				}
			}
		}
		return self::error( 'cresco_builder_css_braces', 'Widget Custom CSS has unbalanced braces.' );
	}

	private static function split_top_level( $value, $delimiter ) {
		$output = array();
		$start = 0;
		$length = strlen( $value );
		$quote = '';
		$escape = false;
		$paren = 0;
		$bracket = 0;
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $value[ $i ];
			if ( $quote ) {
				if ( $escape ) { $escape = false; continue; }
				if ( '\\' === $char ) { $escape = true; continue; }
				if ( $quote === $char ) $quote = '';
				continue;
			}
			if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
			if ( '(' === $char ) { ++$paren; continue; }
			if ( ')' === $char ) { $paren = max( 0, $paren - 1 ); continue; }
			if ( '[' === $char ) { ++$bracket; continue; }
			if ( ']' === $char ) { $bracket = max( 0, $bracket - 1 ); continue; }
			if ( 0 === $paren && 0 === $bracket && $delimiter === $char ) {
				$output[] = substr( $value, $start, $i - $start );
				$start = $i + 1;
			}
		}
		$output[] = substr( $value, $start );
		return $output;
	}

	private static function split_declaration( $value ) {
		$length = strlen( $value );
		$quote = '';
		$escape = false;
		$paren = 0;
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $value[ $i ];
			if ( $quote ) {
				if ( $escape ) { $escape = false; continue; }
				if ( '\\' === $char ) { $escape = true; continue; }
				if ( $quote === $char ) $quote = '';
				continue;
			}
			if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
			if ( '(' === $char ) { ++$paren; continue; }
			if ( ')' === $char ) { $paren = max( 0, $paren - 1 ); continue; }
			if ( 0 === $paren && ':' === $char ) return array( substr( $value, 0, $i ), substr( $value, $i + 1 ) );
		}
		return self::error( 'cresco_builder_css_declaration', 'Widget Custom CSS declaration is missing a colon.' );
	}

	private static function skip_space( $css, &$cursor, $length ) {
		while ( $cursor < $length && preg_match( '/\s/', $css[ $cursor ] ) ) ++$cursor;
	}

	private static function error( $code, $message ) {
		return new WP_Error( $code, __( $message, 'cresco-canvas' ), array( 'status' => 400 ) );
	}

	private function __construct() {}
}
