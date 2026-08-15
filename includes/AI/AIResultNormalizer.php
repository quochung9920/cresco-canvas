<?php
/**
 * Deterministic clean-up of pasted AI results.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\AI;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes formatting noise that has nothing to do with whether a result is safe.
 *
 * Models reliably wrap JSON in Markdown fences and occasionally emit a BOM. Both
 * make a perfectly valid patch fail to parse, which costs the user a second round
 * trip for a problem Cresco can resolve without judgement.
 *
 * The line this class does not cross: it never guesses. It will not scan prose for
 * the first `{`, will not pick one of several JSON objects, and will not repair
 * malformed JSON. Each of those turns "the model returned something unclear" into
 * "Cresco silently applied something the user did not review". Ambiguity returns
 * an error, and everything that survives normalization still goes through
 * PatchValidator unchanged.
 */
final class AIResultNormalizer {
	/**
	 * Normalize a pasted result into a PHP array.
	 *
	 * @param mixed $result Raw editor input: array, JSON string, or fenced block.
	 * @return array|WP_Error
	 */
	public static function normalize( $result ) {
		// Already decoded by an earlier boundary; nothing to clean.
		if ( is_array( $result ) ) {
			return self::unwrap_legacy_session( $result );
		}

		if ( ! is_string( $result ) ) {
			return self::error( __( 'AI result must be a JSON object.', 'cresco-canvas' ) );
		}

		$text = self::strip_bom( $result );
		$text = trim( $text );
		if ( '' === $text ) {
			return self::error( __( 'AI result is empty.', 'cresco-canvas' ) );
		}

		$fenced = self::strip_single_fence( $text );
		if ( is_wp_error( $fenced ) ) return $fenced;
		$text = $fenced;

		if ( '{' !== substr( $text, 0, 1 ) ) {
			return self::error(
				__( 'AI result must be a single JSON object. Remove any explanation before or after the JSON, then paste again.', 'cresco-canvas' )
			);
		}

		$decoded = json_decode( $text, true );
		if ( ! is_array( $decoded ) ) {
			return self::error(
				sprintf(
					/* translators: %s: JSON parser message. */
					__( 'AI result is not valid JSON: %s', 'cresco-canvas' ),
					json_last_error_msg()
				)
			);
		}

		return self::unwrap_legacy_session( $decoded );
	}

	/**
	 * Remove a UTF-8 byte order mark.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	private static function strip_bom( $value ) {
		return 0 === strpos( $value, "\xEF\xBB\xBF" ) ? substr( $value, 3 ) : $value;
	}

	/**
	 * Unwrap a result that is exactly one Markdown fenced block.
	 *
	 * Only the outer fence of a response that is *entirely* one block is removed.
	 * A response containing two blocks, or prose around a block, is ambiguous:
	 * choosing one would be a guess about which the user meant.
	 *
	 * @param string $text Trimmed input.
	 * @return string|WP_Error
	 */
	private static function strip_single_fence( $text ) {
		if ( 0 !== strpos( $text, '```' ) ) {
			// Not fenced at all, but a stray fence later means mixed content.
			return false !== strpos( $text, '```' )
				? self::error( __( 'AI result mixes prose and code blocks. Paste only the JSON object.', 'cresco-canvas' ) )
				: $text;
		}

		$fences = substr_count( $text, '```' );
		if ( 2 !== $fences ) {
			return self::error(
				__( 'AI result contains more than one code block. Paste only the block holding the Cresco patch.', 'cresco-canvas' )
			);
		}

		if ( ! preg_match( '/^```[a-zA-Z0-9_-]*\s*\R?(.*?)\R?\s*```$/s', $text, $matches ) ) {
			return self::error( __( 'AI result has an unterminated code block.', 'cresco-canvas' ) );
		}

		return trim( $matches[1] );
	}

	/**
	 * Accept the legacy `{"session": {...}}` wrapper.
	 *
	 * Only when there is no competing schema of its own, so a patch that happens
	 * to carry a `session` key is never mistaken for a wrapper.
	 *
	 * @param array $decoded Decoded result.
	 * @return array
	 */
	private static function unwrap_legacy_session( $decoded ) {
		if ( isset( $decoded['session'] ) && is_array( $decoded['session'] ) && empty( $decoded['schema'] ) ) {
			return $decoded['session'];
		}
		return $decoded;
	}

	/**
	 * Build a client-facing error.
	 *
	 * @param string $message Human-readable explanation.
	 * @return WP_Error
	 */
	private static function error( $message ) {
		return new WP_Error( 'cresco_ai_result_format', $message, array( 'status' => 400 ) );
	}
}
