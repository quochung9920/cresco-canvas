<?php
/**
 * ACF Repeater and Flexible Content block rendering.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StructuredDynamicData {
	const REPEATER_BLOCK  = 'cresco/acf-repeater';
	const FLEXIBLE_BLOCK  = 'cresco/acf-flexible';
	const LAYOUT_BLOCK    = 'cresco/acf-layout';
	const SUB_FIELD_BLOCK = 'cresco/acf-sub-field';

	/** @var array<int,array<string,mixed>> */
	private static $row_stack = array();

	/** Register structured blocks and the safe ACF field catalog. */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ), 32 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register server-rendered structured ACF blocks. */
	public function register_blocks() {
		register_block_type(
			self::SUB_FIELD_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'fieldPath' => array( 'type' => 'string', 'default' => '' ),
					'fallback'  => array( 'type' => 'string', 'default' => '' ),
					'tagName'   => array( 'type' => 'string', 'default' => 'span' ),
				),
				'render_callback' => array( $this, 'render_sub_field' ),
				'supports'        => array( 'html' => false, 'className' => true, 'color' => true, 'typography' => true, 'spacing' => true ),
			)
		);

		register_block_type(
			self::REPEATER_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'source'       => array( 'type' => 'string', 'default' => 'acf' ),
					'key'          => array( 'type' => 'string', 'default' => '' ),
					'limit'        => array( 'type' => 'number', 'default' => 12 ),
					'columns'      => array( 'type' => 'number', 'default' => 1 ),
					'emptyMessage' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render_repeater' ),
				'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
			)
		);

		register_block_type(
			self::LAYOUT_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'layoutName' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => static function ( $attributes, $content ) {
					unset( $attributes );
					return $content;
				},
				'supports'        => array( 'html' => false, 'className' => false ),
			)
		);

		register_block_type(
			self::FLEXIBLE_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'source'       => array( 'type' => 'string', 'default' => 'acf' ),
					'key'          => array( 'type' => 'string', 'default' => '' ),
					'limit'        => array( 'type' => 'number', 'default' => 12 ),
					'emptyMessage' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render_flexible' ),
				'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
			)
		);
	}

	/** Register an ACF schema catalog without exposing field values. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/dynamic/acf-fields',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'acf_fields' ),
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
				'args'                => array(
					'postId' => array( 'type' => 'integer', 'required' => false ),
				),
			)
		);
	}

	/** Return supported field definitions only. */
	public function acf_fields( WP_REST_Request $request ) {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return new WP_REST_Response( array( 'available' => false, 'fields' => array() ) );
		}

		$post_id = absint( $request->get_param( 'postId' ) );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'available' => true, 'fields' => array() ), 403 );
		}

		$groups  = acf_get_field_groups( $post_id ? array( 'post_id' => $post_id ) : array() );
		$allowed = array( 'repeater', 'flexible_content', 'relationship', 'post_object', 'gallery', 'image', 'text', 'textarea', 'number', 'select', 'true_false', 'url', 'email', 'date_picker' );
		$fields  = array();

		foreach ( is_array( $groups ) ? $groups : array() as $group ) {
			foreach ( (array) acf_get_fields( $group ) as $field ) {
				$type = sanitize_key( (string) ( $field['type'] ?? '' ) );
				$name = sanitize_key( (string) ( $field['name'] ?? '' ) );
				if ( ! $name || ! in_array( $type, $allowed, true ) ) {
					continue;
				}
				$record = array(
					'name'  => $name,
					'label' => sanitize_text_field( (string) ( $field['label'] ?? $name ) ),
					'type'  => $type,
				);
				if ( 'flexible_content' === $type ) {
					$record['layouts'] = array_values(
						array_filter(
							array_map(
								static function ( $layout ) {
									$name = sanitize_key( (string) ( $layout['name'] ?? '' ) );
									return $name ? array( 'name' => $name, 'label' => sanitize_text_field( (string) ( $layout['label'] ?? $name ) ) ) : null;
								},
								(array) ( $field['layouts'] ?? array() )
							)
						)
					);
				}
				$fields[] = $record;
			}
		}

		return new WP_REST_Response( array( 'available' => true, 'fields' => $fields ) );
	}

	/** Render a scalar value from the current repeater/flexible row. */
	public function render_sub_field( $attributes ) {
		$row      = self::current_row();
		$path     = self::sanitize_path( $attributes['fieldPath'] ?? '' );
		$value    = self::resolve_path( $row, $path );
		$fallback = sanitize_text_field( (string) ( $attributes['fallback'] ?? '' ) );
		$text     = self::scalar( $value );
		if ( '' === $text ) {
			$text = $fallback;
		}
		if ( '' === $text ) {
			return '';
		}

		$tag = sanitize_key( (string) ( $attributes['tagName'] ?? 'span' ) );
		if ( ! in_array( $tag, array( 'span', 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
			$tag = 'span';
		}

		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag,
			get_block_wrapper_attributes( array( 'class' => 'cresco-acf-sub-field' ) ),
			esc_html( $text )
		);
	}

	/** Render one native block template for every repeater row. */
	public function render_repeater( $attributes, $content, $block ) {
		unset( $content );
		$post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();
		$rows    = self::normalize_rows( self::get_value( $attributes, $post_id ) );
		$limit   = min( 24, max( 1, absint( $attributes['limit'] ?? 12 ) ) );
		$columns = min( 6, max( 1, absint( $attributes['columns'] ?? 1 ) ) );
		$template = serialize_blocks( (array) ( $block->parsed_block['innerBlocks'] ?? array() ) );

		if ( ! $rows || '' === trim( $template ) ) {
			return self::empty_message( $attributes, 'cresco-acf-repeater__empty' );
		}

		$items = '';
		foreach ( array_slice( $rows, 0, $limit ) as $index => $row ) {
			$row['_index'] = $index + 1;
			self::$row_stack[] = $row;
			$items .= '<div class="cresco-acf-repeater__item">' . do_blocks( $template ) . '</div>';
			array_pop( self::$row_stack );
		}

		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-acf-repeater', 'style' => '--cresco-repeater-columns:' . $columns . ';' ) ) . '>' . $items . '</div>';
	}

	/** Render the matching native block template for every flexible-content row. */
	public function render_flexible( $attributes, $content, $block ) {
		unset( $content );
		$post_id   = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();
		$rows      = self::normalize_rows( self::get_value( $attributes, $post_id ) );
		$limit     = min( 24, max( 1, absint( $attributes['limit'] ?? 12 ) ) );
		$templates = self::layout_templates( (array) ( $block->parsed_block['innerBlocks'] ?? array() ) );

		if ( ! $rows || ! $templates ) {
			return self::empty_message( $attributes, 'cresco-acf-flexible__empty' );
		}

		$items = '';
		foreach ( array_slice( $rows, 0, $limit ) as $index => $row ) {
			$layout = sanitize_key( (string) ( $row['acf_fc_layout'] ?? '' ) );
			$template = $templates[ $layout ] ?? ( $templates['fallback'] ?? '' );
			if ( ! $template ) {
				continue;
			}
			$row['_index']  = $index + 1;
			$row['_layout'] = $layout;
			self::$row_stack[] = $row;
			$items .= '<section class="cresco-acf-flexible__layout cresco-acf-flexible__layout--' . esc_attr( $layout ?: 'unknown' ) . '">' . do_blocks( $template ) . '</section>';
			array_pop( self::$row_stack );
		}

		return $items ? '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-acf-flexible' ) ) . '>' . $items . '</div>' : self::empty_message( $attributes, 'cresco-acf-flexible__empty' );
	}

	/** Normalize arbitrary ACF/meta values into bounded associative rows. */
	public static function normalize_rows( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$rows = array();
		foreach ( $value as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
			if ( count( $rows ) >= 24 ) {
				break;
			}
		}
		return $rows;
	}

	/** Normalize a dot-delimited row path to at most four safe segments. */
	public static function sanitize_path( $path ) {
		$segments = array_slice( explode( '.', (string) $path ), 0, 4 );
		$segments = array_values( array_filter( array_map( 'sanitize_key', $segments ) ) );
		return implode( '.', $segments );
	}

	/** Resolve a bounded path from a row without object traversal. */
	public static function resolve_path( $row, $path ) {
		if ( ! is_array( $row ) || ! $path ) {
			return null;
		}
		$value = $row;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return null;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/** Build a layout-name to serialized-inner-block-template map. */
	public static function layout_templates( $blocks ) {
		$templates = array();
		foreach ( $blocks as $block ) {
			if ( self::LAYOUT_BLOCK !== ( $block['blockName'] ?? '' ) ) {
				continue;
			}
			$name = sanitize_key( (string) ( $block['attrs']['layoutName'] ?? '' ) );
			if ( ! $name ) {
				continue;
			}
			$templates[ $name ] = serialize_blocks( (array) ( $block['innerBlocks'] ?? array() ) );
			if ( count( $templates ) >= 24 ) {
				break;
			}
		}
		return $templates;
	}

	/** Fetch an ACF field or post meta fallback. */
	private static function get_value( $attributes, $post_id ) {
		$key    = sanitize_key( (string) ( $attributes['key'] ?? '' ) );
		$source = 'meta' === ( $attributes['source'] ?? '' ) ? 'meta' : 'acf';
		if ( ! $key || ! $post_id ) {
			return null;
		}
		if ( 'acf' === $source && function_exists( 'get_field' ) ) {
			return get_field( $key, $post_id );
		}
		return get_post_meta( $post_id, $key, true );
	}

	/** Return the innermost structured row. */
	private static function current_row() {
		return self::$row_stack ? self::$row_stack[ count( self::$row_stack ) - 1 ] : array();
	}

	/** Convert supported row values to safe scalar text. */
	private static function scalar( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_scalar( $value ) ) {
			return wp_strip_all_tags( (string) $value );
		}
		if ( is_array( $value ) ) {
			$flat = array_filter(
				array_map(
					static function ( $item ) {
						return is_scalar( $item ) ? (string) $item : '';
					},
					array_slice( $value, 0, 24 )
				)
			);
			return wp_strip_all_tags( implode( ', ', $flat ) );
		}
		return '';
	}

	/** Render a sanitized empty-state message. */
	private static function empty_message( $attributes, $class_name ) {
		$message = sanitize_text_field( (string) ( $attributes['emptyMessage'] ?? '' ) );
		return $message ? '<p class="' . esc_attr( $class_name ) . '">' . esc_html( $message ) . '</p>' : '';
	}
}
