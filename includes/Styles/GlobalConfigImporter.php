<?php
/**
 * Import CSS variables or JSON into Cresco Global Design settings.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Styles;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GlobalConfigImporter {
	const MAX_INPUT = 24000;

	/**
	 * Parse a CSS or JSON payload into sanitized Cresco settings without saving it.
	 *
	 * @param mixed $input User supplied import payload.
	 * @return array|WP_Error
	 */
	public static function preview( $input ) {
		if ( is_array( $input ) ) {
			return self::from_array( $input, 'json' );
		}

		$text = trim( (string) $input );
		if ( '' === $text ) {
			return new WP_Error( 'cresco_global_import_empty', __( 'Paste CSS variables or a JSON Global Config first.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( strlen( $text ) > self::MAX_INPUT ) {
			return new WP_Error( 'cresco_global_import_size', __( 'Global Config import is too large.', 'cresco-canvas' ), array( 'status' => 413 ) );
		}
		if ( preg_match( '/(?:@import|url\s*\(|expression\s*\(|javascript:|<\/?(?:script|style)|<!--|-->)/i', $text ) ) {
			return new WP_Error( 'cresco_global_import_forbidden', __( 'Global Config import contains a forbidden construct.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		if ( '{' === substr( ltrim( $text ), 0, 1 ) ) {
			$decoded = json_decode( $text, true );
			if ( is_array( $decoded ) ) {
				return self::from_array( $decoded, 'json' );
			}
		}

		return self::from_css( $text );
	}

	private static function from_array( $input, $format ) {
		if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
			$input = $input['settings'];
		}
		$base = GlobalStyles::get_settings();
		$settings = $base;
		$mapping = array();
		$ignored = array();
		$known = array( 'primary', 'text', 'muted', 'background', 'containerMax', 'contentMax', 'radius', 'fontFamily', 'fluidTokens', 'breakpoints', 'customColors', 'aliases', 'removeDataOnUninstall', 'schemaVersion' );
		$has_structured_key = false;

		foreach ( $known as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$has_structured_key = true;
				$settings[ $key ] = $input[ $key ];
				$mapping[] = array( 'source' => $key, 'target' => $key );
			}
		}

		if ( $has_structured_key ) {
			foreach ( $input as $key => $value ) {
				unset( $value );
				if ( ! in_array( $key, $known, true ) ) $ignored[] = (string) $key;
			}
			return self::result( $format, $settings, $mapping, $ignored );
		}

		$css_lines = array();
		foreach ( $input as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				$ignored[] = (string) $key;
				continue;
			}
			$key = trim( (string) $key );
			if ( str_starts_with( $key, '--' ) || in_array( strtolower( $key ), array( 'font-family', 'color' ), true ) ) {
				$css_lines[] = $key . ': ' . (string) $value . ';';
			} else {
				$ignored[] = $key;
			}
		}
		$result = self::from_css( implode( "\n", $css_lines ) );
		if ( ! is_wp_error( $result ) && $ignored ) $result['ignored'] = array_values( array_unique( array_merge( $result['ignored'], $ignored ) ) );
		if ( ! is_wp_error( $result ) ) $result['format'] = $format;
		return $result;
	}

	private static function from_css( $text ) {
		$base = GlobalStyles::get_settings();
		$settings = $base;
		$settings['customColors'] = (array) ( $base['customColors'] ?? array() );
		$settings['aliases'] = (array) ( $base['aliases'] ?? array() );
		$mapping = array();
		$ignored = array();
		$variables = array();
		$builtins = array(
			'bg' => 'background', 'background' => 'background',
			'ink' => 'text', 'text' => 'text', 'foreground' => 'text', 'fg' => 'text',
			'ink-muted' => 'muted', 'muted' => 'muted', 'text-muted' => 'muted',
			'primary' => 'primary', 'brand' => 'primary', 'accent' => 'primary', 'blue' => 'primary',
		);

		if ( preg_match_all( '/(--[a-zA-Z0-9_-]+)\s*:\s*([^;{}]+)\s*;?/', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$source = strtolower( trim( $match[1] ) );
				$slug = sanitize_key( substr( $source, 2 ) );
				$value = trim( $match[2] );
				if ( '' === $slug ) continue;
				$variables[ $slug ] = $value;

				if ( in_array( $slug, array( 'font', 'font-family', 'font-body', 'body-font' ), true ) ) {
					$font = GlobalStyles::sanitize_font_family_value( $value );
					if ( '' !== $font ) {
						$settings['fontFamily'] = $font;
						$mapping[] = array( 'source' => $source, 'target' => 'typography.fontFamily', 'value' => $font );
					} else $ignored[] = $source;
					continue;
				}

				$color = GlobalStyles::sanitize_color_value( $value );
				if ( '' === $color ) {
					$ignored[] = $source;
					continue;
				}
				if ( isset( $builtins[ $slug ] ) ) {
					$target = $builtins[ $slug ];
					$settings[ $target ] = $color;
					$settings['aliases'][ $slug ] = $target;
					$mapping[] = array( 'source' => $source, 'target' => 'colors.' . $target, 'value' => $color );
				} else {
					$settings['customColors'][ $slug ] = $color;
					$settings['aliases'][ $slug ] = 'custom-' . $slug;
					$mapping[] = array( 'source' => $source, 'target' => 'colors.custom-' . $slug, 'value' => $color );
				}
			}
		}

		if ( preg_match( '/(?:^|[;{}\s])font-family\s*:\s*([^;{}]+)/i', $text, $font_match ) ) {
			$font = GlobalStyles::sanitize_font_family_value( trim( $font_match[1] ) );
			if ( '' !== $font ) {
				$settings['fontFamily'] = $font;
				$mapping[] = array( 'source' => 'font-family', 'target' => 'typography.fontFamily', 'value' => $font );
			}
		}

		if ( preg_match( '/(?:^|[;{}\s])color\s*:\s*var\(\s*--([a-zA-Z0-9_-]+)\s*\)/i', $text, $color_match ) ) {
			$slug = sanitize_key( $color_match[1] );
			if ( isset( $variables[ $slug ] ) ) {
				$color = GlobalStyles::sanitize_color_value( $variables[ $slug ] );
				if ( '' !== $color ) {
					$settings['text'] = $color;
					$settings['aliases'][ $slug ] = 'text';
					$mapping[] = array( 'source' => 'color: var(--' . $slug . ')', 'target' => 'colors.text', 'value' => $color );
				}
			}
		}

		if ( ! $mapping ) {
			return new WP_Error( 'cresco_global_import_unrecognized', __( 'No supported Global Design values were found in the import.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		return self::result( 'css', $settings, $mapping, $ignored );
	}

	private static function result( $format, $settings, $mapping, $ignored ) {
		$settings = GlobalStyles::sanitize_settings( $settings );
		return array(
			'valid' => true,
			'format' => $format,
			'settings' => $settings,
			'tokens' => DesignTokens::catalog( $settings ),
			'mapping' => array_values( $mapping ),
			'ignored' => array_values( array_unique( $ignored ) ),
		);
	}
}
