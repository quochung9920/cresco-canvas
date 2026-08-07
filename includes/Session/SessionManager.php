<?php
/**
 * Cresco Session v1 document storage, AI interchange, validation, and rendering.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Session;

use CrescoCanvas\Admin\EditorIntegration;
use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SessionManager {
	const SCHEMA = 'cresco-session/v1';
	const VERSION = 1;
	const META_KEY = '_cresco_canvas_document';
	const MAX_NODES = 500;
	const MAX_DEPTH = 12;
	const MAX_CUSTOM_CSS = 12000;

	public function register() {
		add_action( 'init', array( $this, 'register_meta' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_css' ), 30 );
		add_filter( 'the_content', array( $this, 'render_frontend_content' ), 20 );
	}

	public function register_meta() {
		register_post_meta(
			'page',
			self::META_KEY,
			array(
				'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'default' => '',
				'description' => __( 'Authoritative Cresco Canvas session document.', 'cresco-canvas' ),
				'show_in_rest' => false,
				'single' => true,
				'type' => 'string',
			)
		);
	}

	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/session/(?P<postId>\d+)',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'rest_get_session' ),
					'permission_callback' => array( $this, 'can_edit_post' ),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'rest_save_session' ),
					'permission_callback' => array( $this, 'can_edit_post' ),
				),
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/session/validate',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'rest_validate_session' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/ai-context/(?P<postId>\d+)',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'rest_ai_context' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
			)
		);
	}

	public function can_edit_post( $request ) {
		$post_id = absint( $request['postId'] ?? 0 );
		return $post_id > 0 && 'page' === get_post_type( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	public function rest_get_session( WP_REST_Request $request ) {
		return new WP_REST_Response( $this->session_payload( absint( $request['postId'] ) ) );
	}

	public function rest_save_session( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $payload;
		$session = self::sanitize_session( $input );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$encoded = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			return new WP_Error( 'cresco_session_encode_failed', __( 'The Cresco session could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, self::META_KEY, $encoded );
		update_post_meta( $post_id, EditorIntegration::ENABLED_META, true );

		if ( isset( $payload['postTitle'] ) ) {
			$title = sanitize_text_field( (string) $payload['postTitle'] );
			if ( '' !== $title && $title !== get_the_title( $post_id ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
			}
		}

		return new WP_REST_Response(
			array(
				'session' => $session,
				'checksum' => hash( 'sha256', $encoded ),
				'savedAt' => gmdate( 'c' ),
			)
		);
	}

	public function rest_validate_session( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();
		$input = isset( $payload['session'] ) && is_array( $payload['session'] ) ? $payload['session'] : $payload;
		$session = self::sanitize_session( $input );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return new WP_REST_Response(
			array(
				'valid' => true,
				'session' => $session,
				'nodeCount' => self::count_nodes( $session['nodes'] ),
				'checksum' => hash( 'sha256', (string) wp_json_encode( $session ) ),
			)
		);
	}

	public function rest_ai_context( WP_REST_Request $request ) {
		$post_id = absint( $request['postId'] );
		$session_payload = $this->session_payload( $post_id );
		$settings = GlobalStyles::get_settings();

		return new WP_REST_Response(
			array(
				'format' => 'cresco-ai-context/v1',
				'global' => DesignTokens::catalog( $settings ),
				'cssVariables' => self::css_variable_catalog(),
				'widgets' => self::widget_catalog(),
				'session' => $session_payload['session'],
				'postTitle' => get_the_title( $post_id ),
				'instructions' => array(
					'Use only widget types declared in widgets.',
					'Prefer global token references such as {colors.primary} and {spacing.xl}.',
					'Use structured style properties before customCSS.',
					'Use customCSS only when a widget capability is missing.',
					'Every customCSS selector must be scoped with &. Do not use @media, @import, external url(), or global selectors.',
					'For customCSS, use the published --cc-* CSS variables when a design token is needed.',
					'Return a valid cresco-session/v1 object when generating a full session.',
				),
			)
		);
	}

	private function session_payload( $post_id ) {
		$raw = (string) get_post_meta( $post_id, self::META_KEY, true );
		$decoded = $raw ? json_decode( $raw, true ) : null;
		$session = is_array( $decoded ) ? self::sanitize_session( $decoded ) : null;
		if ( is_wp_error( $session ) || ! is_array( $session ) ) {
			$session = self::empty_session( $post_id );
		}
		$encoded = (string) wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return array(
			'session' => $session,
			'checksum' => hash( 'sha256', $encoded ),
			'nodeCount' => self::count_nodes( $session['nodes'] ),
			'postTitle' => get_the_title( $post_id ),
		);
	}

	public static function empty_session( $post_id = 0 ) {
		return array(
			'schema' => self::SCHEMA,
			'version' => self::VERSION,
			'documentId' => $post_id ? 'page-' . absint( $post_id ) : 'untitled',
			'nodes' => array(),
		);
	}

	public static function widget_catalog() {
		$shared_style = self::style_properties();
		return array(
			'container' => array(
				'label' => 'Container',
				'allowsChildren' => true,
				'props' => array(
					'layout' => array( 'type' => 'enum', 'values' => array( 'block', 'flex', 'grid' ), 'default' => 'block' ),
					'direction' => array( 'type' => 'enum', 'values' => array( 'row', 'column' ), 'default' => 'column' ),
					'align' => array( 'type' => 'enum', 'values' => array( 'stretch', 'flex-start', 'center', 'flex-end', 'baseline' ), 'default' => 'stretch' ),
					'justify' => array( 'type' => 'enum', 'values' => array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ), 'default' => 'flex-start' ),
					'columns' => array( 'type' => 'int', 'min' => 1, 'max' => 12, 'default' => 2 ),
				),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&' ) ),
			),
			'heading' => array(
				'label' => 'Heading',
				'allowsChildren' => false,
				'props' => array(
					'text' => array( 'type' => 'text', 'default' => 'Heading' ),
					'level' => array( 'type' => 'int', 'min' => 1, 'max' => 6, 'default' => 2 ),
				),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&' ) ),
			),
			'text' => array(
				'label' => 'Text',
				'allowsChildren' => false,
				'props' => array( 'text' => array( 'type' => 'text', 'default' => 'Add your text.' ) ),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&' ) ),
			),
			'button' => array(
				'label' => 'Button',
				'allowsChildren' => false,
				'props' => array(
					'text' => array( 'type' => 'string', 'default' => 'Button' ),
					'url' => array( 'type' => 'url', 'default' => '#' ),
					'target' => array( 'type' => 'enum', 'values' => array( '_self', '_blank' ), 'default' => '_self' ),
				),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&', 'text' => '& [data-cresco-part="text"]' ) ),
			),
			'image' => array(
				'label' => 'Image',
				'allowsChildren' => false,
				'props' => array(
					'url' => array( 'type' => 'url', 'default' => '' ),
					'alt' => array( 'type' => 'string', 'default' => '' ),
					'caption' => array( 'type' => 'text', 'default' => '' ),
				),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&', 'media' => '& [data-cresco-part="media"]', 'caption' => '& [data-cresco-part="caption"]' ) ),
			),
			'list' => array(
				'label' => 'List',
				'allowsChildren' => false,
				'props' => array( 'items' => array( 'type' => 'string_list', 'default' => array( 'First item', 'Second item' ) ) ),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&', 'item' => '& [data-cresco-part="item"]' ) ),
			),
			'divider' => array(
				'label' => 'Divider',
				'allowsChildren' => false,
				'props' => array(),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&' ) ),
			),
			'spacer' => array(
				'label' => 'Spacer',
				'allowsChildren' => false,
				'props' => array( 'height' => array( 'type' => 'css', 'default' => '48px' ) ),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&' ) ),
			),
			'columns' => array(
				'label' => 'Columns',
				'allowsChildren' => true,
				'props' => array( 'columns' => array( 'type' => 'int', 'min' => 1, 'max' => 12, 'default' => 2 ) ),
				'style' => $shared_style,
				'css' => array( 'allowed' => true, 'selector' => '&', 'parts' => array( 'root' => '&' ) ),
			),
		);
	}

	private static function style_properties() {
		return array(
			'display', 'width', 'maxWidth', 'minHeight', 'color', 'background', 'backgroundColor',
			'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textAlign',
			'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
			'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'gap',
			'border', 'borderColor', 'borderWidth', 'borderStyle', 'borderRadius', 'boxShadow',
			'opacity', 'position', 'top', 'right', 'bottom', 'left', 'zIndex', 'overflow',
			'alignItems', 'justifyContent', 'flexDirection', 'flexWrap', 'gridTemplateColumns',
		);
	}

	public static function sanitize_session( $input ) {
		if ( ! is_array( $input ) ) {
			return new WP_Error( 'cresco_session_invalid', __( 'Cresco Session must be a JSON object.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( isset( $input['schema'] ) && self::SCHEMA !== (string) $input['schema'] ) {
			return new WP_Error( 'cresco_session_schema', __( 'Unsupported Cresco Session schema.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( isset( $input['version'] ) && self::VERSION !== absint( $input['version'] ) ) {
			return new WP_Error( 'cresco_session_version', __( 'Unsupported Cresco Session version.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$state = array( 'count' => 0, 'ids' => array() );
		$nodes = self::sanitize_nodes( $input['nodes'] ?? array(), 0, $state );
		if ( is_wp_error( $nodes ) ) {
			return $nodes;
		}

		$document_id = sanitize_key( (string) ( $input['documentId'] ?? 'untitled' ) );
		if ( '' === $document_id ) {
			$document_id = 'untitled';
		}

		return array(
			'schema' => self::SCHEMA,
			'version' => self::VERSION,
			'documentId' => $document_id,
			'nodes' => $nodes,
		);
	}

	private static function sanitize_nodes( $nodes, $depth, &$state ) {
		if ( $depth > self::MAX_DEPTH ) {
			return new WP_Error( 'cresco_session_depth', __( 'Cresco Session nesting is too deep.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( ! is_array( $nodes ) ) {
			return new WP_Error( 'cresco_session_nodes', __( 'Cresco Session nodes must be an array.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$output = array();
		$catalog = self::widget_catalog();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				return new WP_Error( 'cresco_session_node', __( 'Every Cresco Session node must be an object.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}
			$state['count']++;
			if ( $state['count'] > self::MAX_NODES ) {
				return new WP_Error( 'cresco_session_node_limit', __( 'Cresco Session contains too many widgets.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}

			$type = sanitize_key( (string) ( $node['type'] ?? '' ) );
			if ( ! isset( $catalog[ $type ] ) ) {
				return new WP_Error( 'cresco_session_widget', sprintf( __( 'Unknown Cresco widget type: %s', 'cresco-canvas' ), $type ?: '?' ), array( 'status' => 400 ) );
			}
			$id = self::sanitize_node_id( $node['id'] ?? '' );
			if ( '' === $id ) {
				return new WP_Error( 'cresco_session_widget_id', __( 'Every Cresco widget requires a stable id.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}
			if ( isset( $state['ids'][ $id ] ) ) {
				return new WP_Error( 'cresco_session_duplicate_id', sprintf( __( 'Duplicate Cresco widget id: %s', 'cresco-canvas' ), $id ), array( 'status' => 400 ) );
			}
			$state['ids'][ $id ] = true;

			$children = array();
			if ( ! empty( $node['children'] ) ) {
				if ( empty( $catalog[ $type ]['allowsChildren'] ) ) {
					return new WP_Error( 'cresco_session_children', sprintf( __( '%s does not allow child widgets.', 'cresco-canvas' ), $catalog[ $type ]['label'] ), array( 'status' => 400 ) );
				}
				$children = self::sanitize_nodes( $node['children'], $depth + 1, $state );
				if ( is_wp_error( $children ) ) {
					return $children;
				}
			}

			$custom_css = self::sanitize_custom_css_map( $node['customCSS'] ?? array() );
			if ( is_wp_error( $custom_css ) ) {
				return $custom_css;
			}

			$output[] = array(
				'id' => $id,
				'type' => $type,
				'props' => self::sanitize_props( $type, $node['props'] ?? array() ),
				'style' => self::sanitize_style( $node['style'] ?? array() ),
				'responsive' => self::sanitize_responsive( $node['responsive'] ?? array() ),
				'customCSS' => $custom_css,
				'children' => $children,
			);
		}
		return $output;
	}

	private static function sanitize_node_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9_-]+/', '-', $value );
		$value = trim( (string) $value, '-' );
		return substr( $value, 0, 80 );
	}

	private static function sanitize_props( $type, $input ) {
		$input = is_array( $input ) ? $input : array();
		$definition = self::widget_catalog()[ $type ];
		$output = array();
		foreach ( $definition['props'] as $key => $schema ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : ( $schema['default'] ?? null );
			$kind = $schema['type'] ?? 'string';
			if ( 'int' === $kind ) {
				$value = (int) $value;
				$value = max( (int) ( $schema['min'] ?? PHP_INT_MIN ), min( (int) ( $schema['max'] ?? PHP_INT_MAX ), $value ) );
			} elseif ( 'enum' === $kind ) {
				$value = (string) $value;
				if ( ! in_array( $value, (array) ( $schema['values'] ?? array() ), true ) ) {
					$value = (string) ( $schema['default'] ?? '' );
				}
			} elseif ( 'url' === $kind ) {
				$value = esc_url_raw( (string) $value );
				if ( '#' === (string) ( $input[ $key ] ?? '' ) ) {
					$value = '#';
				}
			} elseif ( 'text' === $kind ) {
				$value = sanitize_textarea_field( (string) $value );
			} elseif ( 'string_list' === $kind ) {
				$value = is_array( $value ) ? $value : preg_split( '/\r?\n/', (string) $value );
				$value = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) $value, 0, 50 ) ), 'strlen' ) );
			} elseif ( 'css' === $kind ) {
				$value = self::sanitize_css_value( $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			$output[ $key ] = $value;
		}
		return $output;
	}

	private static function sanitize_style( $input ) {
		$input = is_array( $input ) ? $input : array();
		$allowed = array_flip( self::style_properties() );
		$output = array();
		foreach ( $input as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			$value = self::sanitize_css_value( $value );
			if ( '' !== $value ) {
				$output[ $key ] = $value;
			}
		return $output;
	}

	private static function sanitize_responsive( $input ) {
		$input = is_array( $input ) ? $input : array();
		$output = array();
		foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			if ( isset( $input[ $device ] ) && is_array( $input[ $device ] ) ) {
				$style = self::sanitize_style( $input[ $device ] );
				if ( $style ) {
					$output[ $device ] = $style;
				}
			}
		}
		return $output;
	}

	private static function sanitize_css_value( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $value || strlen( $value ) > 180 ) {
			return '';
		}
		if ( preg_match( '/[;{}<>]/', $value ) || preg_match( '/(?:url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding)/i', $value ) ) {
			return '';
		}
		if ( preg_match( '/^\{[a-zA-Z0-9._-]+\}$/', $value ) ) {
			return $value;
		}
		return preg_match( "/^[#a-zA-Z0-9.,%+\-*\/() _\"']+$/", $value ) ? $value : '';
	}

	private static function sanitize_custom_css_map( $input ) {
		$input = is_array( $input ) ? $input : array();
		$output = array();
		foreach ( array( 'base', 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			if ( empty( $input[ $device ] ) ) {
				continue;
			}
			$css = self::sanitize_custom_css( $input[ $device ] );
			if ( is_wp_error( $css ) ) {
				return $css;
			}
			if ( '' !== $css ) {
				$output[ $device ] = $css;
			}
		}
		return $output;
	}

	public static function sanitize_custom_css( $value ) {
		$css = trim( (string) $value );
		if ( '' === $css ) {
			return '';
		}
		if ( strlen( $css ) > self::MAX_CUSTOM_CSS ) {
			return new WP_Error( 'cresco_session_css_size', __( 'Widget Custom CSS is too large.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( preg_match( '/(?:@import|@charset|@namespace|@media|@supports|url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding|<\/?style|<!--|-->)/i', $css ) ) {
			return new WP_Error( 'cresco_session_css_forbidden', __( 'Widget Custom CSS contains a forbidden construct.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		if ( substr_count( $css, '{' ) !== substr_count( $css, '}' ) ) {
			return new WP_Error( 'cresco_session_css_braces', __( 'Widget Custom CSS has unbalanced braces.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$cursor = 0;
		$length = strlen( $css );
		while ( $cursor < $length ) {
			$open = strpos( $css, '{', $cursor );
			if ( false === $open ) {
				break;
			}
			$selector = trim( substr( $css, $cursor, $open - $cursor ) );
			$close = strpos( $css, '}', $open + 1 );
			if ( false === $close || '' === $selector || false === strpos( $selector, '&' ) ) {
				return new WP_Error( 'cresco_session_css_scope', __( 'Every Widget Custom CSS selector must include &.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}
			if ( preg_match( '/(?:^|,)\s*(?:html|body|:root|#wpwrap|#wpcontent)\b/i', $selector ) ) {
				return new WP_Error( 'cresco_session_css_global', __( 'Widget Custom CSS cannot target global document selectors.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}
			$declarations = substr( $css, $open + 1, $close - $open - 1 );
			if ( preg_match( '/[<>]/', $declarations ) ) {
				return new WP_Error( 'cresco_session_css_markup', __( 'Widget Custom CSS cannot contain markup.', 'cresco-canvas' ), array( 'status' => 400 ) );
			}
			$cursor = $close + 1;
		}
		return $css;
	}

	public function render_frontend_content( $content ) {
		if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		$session = $this->load_saved_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) ) {
			return $content;
		}

		$html = '';
		foreach ( $session['nodes'] as $node ) {
			$html .= $this->render_node( $node );
		}
		return '<div class="cresco-session-root" data-cresco-document="' . esc_attr( $session['documentId'] ) . '">' . $html . '</div>';
	}

	public function enqueue_frontend_css() {
		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		$session = $this->load_saved_session( $post_id );
		if ( ! $session || empty( $session['nodes'] ) || ! wp_style_is( 'cresco-canvas-frontend', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_style( 'cresco-canvas-frontend', self::compile_session_css( $session ) );
	}

	private function load_saved_session( $post_id ) {
		$raw = (string) get_post_meta( $post_id, self::META_KEY, true );
		if ( '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		$session = self::sanitize_session( $decoded );
		return is_wp_error( $session ) ? null : $session;
	}

	private function render_node( $node ) {
		$id = esc_attr( $node['id'] );
		$type = $node['type'];
		$props = (array) $node['props'];
		$attributes = ' class="cresco-session-node cresco-widget-' . esc_attr( $type ) . '" data-cresco-id="' . $id . '" data-cresco-widget="' . esc_attr( $type ) . '"';
		$children = '';
		foreach ( (array) $node['children'] as $child ) {
			$children .= $this->render_node( $child );
		}

		if ( 'heading' === $type ) {
			$level = min( 6, max( 1, absint( $props['level'] ?? 2 ) ) );
			return '<h' . $level . $attributes . '>' . esc_html( (string) ( $props['text'] ?? '' ) ) . '</h' . $level . '>';
		}
		if ( 'text' === $type ) {
			return '<p' . $attributes . '>' . nl2br( esc_html( (string) ( $props['text'] ?? '' ) ) ) . '</p>';
		}
		if ( 'button' === $type ) {
			$url = (string) ( $props['url'] ?? '#' );
			$target = '_blank' === ( $props['target'] ?? '' ) ? '_blank' : '_self';
			$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
			return '<a' . $attributes . ' href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '"' . $rel . '><span data-cresco-part="text">' . esc_html( (string) ( $props['text'] ?? 'Button' ) ) . '</span></a>';
		}
		if ( 'image' === $type ) {
			$url = esc_url( (string) ( $props['url'] ?? '' ) );
			$caption = (string) ( $props['caption'] ?? '' );
			$media = $url ? '<img data-cresco-part="media" src="' . $url . '" alt="' . esc_attr( (string) ( $props['alt'] ?? '' ) ) . '">' : '<span class="cresco-widget-image__placeholder" data-cresco-part="media" aria-hidden="true"></span>';
			return '<figure' . $attributes . '>' . $media . ( '' !== $caption ? '<figcaption data-cresco-part="caption">' . esc_html( $caption ) . '</figcaption>' : '' ) . '</figure>';
		}
		if ( 'list' === $type ) {
			$items = '';
			foreach ( (array) ( $props['items'] ?? array() ) as $item ) {
				$items .= '<li data-cresco-part="item">' . esc_html( (string) $item ) . '</li>';
			}
			return '<ul' . $attributes . '>' . $items . '</ul>';
		}
		if ( 'divider' === $type ) {
			return '<hr' . $attributes . '>';
		}
		if ( 'spacer' === $type ) {
			return '<div' . $attributes . ' aria-hidden="true"></div>';
		}
		return '<div' . $attributes . '>' . $children . '</div>';
	}

	public static function compile_session_css( $session ) {
		$settings = GlobalStyles::get_settings();
		$breakpoints = (array) $settings['breakpoints'];
		$buckets = array( 'base' => '', 'desktop' => '', 'laptop' => '', 'tablet' => '', 'mobile' => '' );
		self::compile_nodes_css( (array) $session['nodes'], $buckets );

		$css = '.cresco-session-root{width:100%;}.cresco-session-node{box-sizing:border-box;}'
			. '.cresco-widget-button{display:inline-flex;align-items:center;justify-content:center;min-height:var(--cc-control-height);padding:.75rem var(--cc-button-padding);border-radius:var(--cc-radius-md);background:var(--cc-primary);color:#fff;text-decoration:none;}'
			. '.cresco-widget-image img{display:block;max-width:100%;height:auto;}.cresco-widget-columns{display:grid;gap:var(--cc-grid-gap);}'
			. $buckets['base'];

		$ranges = array(
			'desktop' => array( (int) $breakpoints['desktop'], max( (int) $breakpoints['desktop'], (int) $breakpoints['wide'] - 1 ) ),
			'laptop' => array( (int) $breakpoints['laptop'], max( (int) $breakpoints['laptop'], (int) $breakpoints['desktop'] - 1 ) ),
			'tablet' => array( (int) $breakpoints['tablet'], max( (int) $breakpoints['tablet'], (int) $breakpoints['laptop'] - 1 ) ),
			'mobile' => array( 0, max( 0, (int) $breakpoints['tablet'] - 1 ) ),
		);
		foreach ( $ranges as $device => $range ) {
			if ( '' === $buckets[ $device ] ) {
				continue;
			}
			if ( 0 === $range[0] ) {
				$css .= '@media (max-width:' . $range[1] . 'px){' . $buckets[ $device ] . '}';
			} else {
				$css .= '@media (min-width:' . $range[0] . 'px) and (max-width:' . $range[1] . 'px){' . $buckets[ $device ] . '}';
			}
		}
		return $css;
	}

	private static function compile_nodes_css( $nodes, &$buckets ) {
		foreach ( $nodes as $node ) {
			$selector = '[data-cresco-id="' . self::css_escape_identifier( $node['id'] ) . '"]';
			$base_style = array_merge( self::props_style( $node ), (array) $node['style'] );
			$declarations = self::style_declarations( $base_style );
			if ( $declarations ) {
				$buckets['base'] .= $selector . '{' . $declarations . '}';
			}
			foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
				$declarations = self::style_declarations( (array) ( $node['responsive'][ $device ] ?? array() ) );
				if ( $declarations ) {
					$buckets[ $device ] .= $selector . '{' . $declarations . '}';
				}
			}
			foreach ( (array) $node['customCSS'] as $device => $custom_css ) {
				if ( isset( $buckets[ $device ] ) ) {
					$buckets[ $device ] .= str_replace( '&', $selector, (string) $custom_css );
				}
			}
			self::compile_nodes_css( (array) $node['children'], $buckets );
		}
	}

	private static function props_style( $node ) {
		$type = $node['type'];
		$props = (array) $node['props'];
		if ( 'container' === $type ) {
			$layout = $props['layout'] ?? 'block';
			$style = array( 'display' => $layout );
			if ( 'flex' === $layout ) {
				$style['flexDirection'] = $props['direction'] ?? 'column';
				$style['alignItems'] = $props['align'] ?? 'stretch';
				$style['justifyContent'] = $props['justify'] ?? 'flex-start';
			}
			if ( 'grid' === $layout ) {
				$style['gridTemplateColumns'] = 'repeat(' . min( 12, max( 1, absint( $props['columns'] ?? 2 ) ) ) . ', minmax(0, 1fr))';
			}
			return $style;
		}
		if ( 'columns' === $type ) {
			return array( 'display' => 'grid', 'gridTemplateColumns' => 'repeat(' . min( 12, max( 1, absint( $props['columns'] ?? 2 ) ) ) . ', minmax(0, 1fr))' );
		}
		if ( 'spacer' === $type ) {
			return array( 'minHeight' => (string) ( $props['height'] ?? '48px' ) );
		}
		return array();
	}

	private static function style_declarations( $style ) {
		$css = '';
		foreach ( (array) $style as $key => $value ) {
			$value = self::css_value_to_output( $value );
			if ( '' === $value ) {
				continue;
			}
			$property = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', (string) $key ) );
			$css .= $property . ':' . $value . ';';
		}
		return $css;
	}

	private static function css_value_to_output( $value ) {
		$value = self::sanitize_css_value( $value );
		if ( preg_match( '/^\{([a-zA-Z0-9._-]+)\}$/', $value, $matches ) ) {
			return self::token_css_var( $matches[1] );
		}
		return $value;
	}

	private static function token_css_var( $path ) {
		$map = self::css_variable_catalog();
		return isset( $map[ $path ] ) ? 'var(' . $map[ $path ] . ')' : '';
	}

	public static function css_variable_catalog() {
		$map = array(
			'colors.primary' => '--cc-primary', 'colors.text' => '--cc-text', 'colors.muted' => '--cc-muted', 'colors.background' => '--cc-background',
			'typography.fontFamily' => '--cc-font',
			'typography.sizes.xs' => '--cc-font-xs', 'typography.sizes.sm' => '--cc-font-sm', 'typography.sizes.base' => '--cc-font-base', 'typography.sizes.lg' => '--cc-font-lg', 'typography.sizes.xl' => '--cc-font-xl',
			'typography.sizes.h1' => '--cc-h1', 'typography.sizes.h2' => '--cc-h2', 'typography.sizes.h3' => '--cc-h3', 'typography.sizes.h4' => '--cc-h4', 'typography.sizes.h5' => '--cc-h5', 'typography.sizes.h6' => '--cc-h6',
			'spacing.2xs' => '--cc-space-2xs', 'spacing.xs' => '--cc-space-xs', 'spacing.sm' => '--cc-space-sm', 'spacing.md' => '--cc-space-md', 'spacing.lg' => '--cc-space-lg', 'spacing.xl' => '--cc-space-xl', 'spacing.2xl' => '--cc-space-2xl', 'spacing.3xl' => '--cc-space-3xl',
			'spacing.sectionBlock' => '--cc-section-padding-block', 'spacing.containerGutter' => '--cc-container-gutter', 'spacing.gridGap' => '--cc-grid-gap',
			'layout.containerMax' => '--cc-container-max', 'layout.contentMax' => '--cc-content-max',
			'radius.base' => '--cc-radius', 'radius.sm' => '--cc-radius-sm', 'radius.md' => '--cc-radius-md', 'radius.lg' => '--cc-radius-lg', 'radius.pill' => '--cc-radius-pill',
			'controls.height' => '--cc-control-height', 'controls.buttonPadding' => '--cc-button-padding',
			'shadows.sm' => '--cc-shadow-sm', 'shadows.md' => '--cc-shadow-md', 'shadows.lg' => '--cc-shadow-lg',
			'motion.fast' => '--cc-motion-fast', 'motion.normal' => '--cc-motion', 'motion.slow' => '--cc-motion-slow', 'motion.easing' => '--cc-easing',
		);
		$settings = GlobalStyles::get_settings();
		foreach ( (array) ( $settings['customColors'] ?? array() ) as $slug => $color ) {
			unset( $color );
			$map[ 'colors.custom-' . sanitize_key( $slug ) ] = '--cc-color-' . sanitize_key( $slug );
		}
		foreach ( (array) ( $settings['aliases'] ?? array() ) as $alias => $target ) {
			unset( $target );
			$map[ 'colors.alias-' . sanitize_key( $alias ) ] = '--cc-alias-' . sanitize_key( $alias );
		}
		return $map;
	}

	private static function css_escape_identifier( $value ) {
		return preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) $value );
	}

	private static function count_nodes( $nodes ) {
		$count = 0;
		foreach ( (array) $nodes as $node ) {
			$count++;
			$count += self::count_nodes( (array) ( $node['children'] ?? array() ) );
		}
		return $count;
	}
}
