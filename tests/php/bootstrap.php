<?php
/**
 * Minimal WordPress function surface for isolated PHP unit tests.
 *
 * @package CrescoCanvas
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CRESCO_CANVAS_SCHEMA_VERSION', 1 );
define( 'CRESCO_CANVAS_MINIMUM_PHP', '8.1' );
define( 'CRESCO_CANVAS_MINIMUM_WORDPRESS', '6.7' );
define( 'CRESCO_CANVAS_PATH', dirname( __DIR__, 2 ) . '/' );

$GLOBALS['cresco_test_options']   = array();
$GLOBALS['cresco_test_posts']     = array();
$GLOBALS['cresco_test_post_meta'] = array();
$GLOBALS['cresco_test_user_meta'] = array();
$GLOBALS['cresco_test_updates']   = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;
		/** @var mixed */
		private $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/** @return string */
		public function get_error_code() {
			return $this->code;
		}

		/** @return string */
		public function get_error_message() {
			return $this->message;
		}

		/** @return mixed */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request implements ArrayAccess {
		/** @var array<string, mixed> */
		private $params;

		/** @param array<string, mixed> $params Request parameters. */
		public function __construct( $params = array() ) {
			$this->params = $params;
		}

		/** @return mixed */
		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		/** @return array<string, mixed> */
		public function get_json_params() {
			return $this->params;
		}

		/** @return bool */
		public function offsetExists( $offset ): bool {
			return isset( $this->params[ $offset ] );
		}

		/** @return mixed */
		public function offsetGet( $offset ): mixed {
			return $this->params[ $offset ] ?? null;
		}

		/** @param mixed $value Value. */
		public function offsetSet( $offset, $value ): void {
			$this->params[ $offset ] = $value;
		}

		public function offsetUnset( $offset ): void {
			unset( $this->params[ $offset ] );
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private $data;

		/** @param mixed $data Response data. */
		public function __construct( $data = null ) {
			$this->data = $data;
		}

		/** @return mixed */
		public function get_data() {
			return $this->data;
		}
	}
}

function __( $text ) {
	return $text;
}

function absint( $value ) {
	return abs( (int) $value );
}

function add_option( $name, $value ) {
	if ( array_key_exists( $name, $GLOBALS['cresco_test_options'] ) ) {
		return false;
	}

	$GLOBALS['cresco_test_options'][ $name ] = $value;
	return true;
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

function delete_option( $name ) {
	unset( $GLOBALS['cresco_test_options'][ $name ] );
	return true;
}

function get_bloginfo( $field ) {
	return 'version' === $field ? '7.0.1' : '';
}

function get_current_user_id() {
	return 7;
}

function get_option( $name, $default = false ) {
	return $GLOBALS['cresco_test_options'][ $name ] ?? $default;
}

function get_post( $post_id ) {
	return $GLOBALS['cresco_test_posts'][ $post_id ] ?? null;
}

function get_preview_post_link( $post ) {
	return 'https://example.test/?preview_id=' . (int) $post->ID;
}

function get_the_title( $post ) {
	return (string) $post->post_title;
}

function get_post_meta( $post_id, $key ) {
	return $GLOBALS['cresco_test_post_meta'][ $post_id ][ $key ] ?? '';
}

function get_user_meta( $user_id, $key ) {
	return $GLOBALS['cresco_test_user_meta'][ $user_id ][ $key ] ?? '';
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function mysql2date( $format, $date ) {
	return gmdate( $format, strtotime( $date . ' UTC' ) );
}

function rest_sanitize_boolean( $value ) {
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

function sanitize_hex_color( $value ) {
	return is_string( $value ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : null;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function update_option( $name, $value ) {
	$GLOBALS['cresco_test_options'][ $name ] = $value;
	return true;
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

require_once CRESCO_CANVAS_PATH . 'includes/Autoloader.php';
CrescoCanvas\Autoloader::register();
