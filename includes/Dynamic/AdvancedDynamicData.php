<?php
/**
 * Advanced structured ACF and relationship data support.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdvancedDynamicData {
	const GALLERY_BLOCK      = 'cresco/dynamic-gallery';
	const RELATIONSHIP_BLOCK = 'cresco/relationship-loop';

	/** Register blocks and field-inspection endpoint. */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ), 31 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register server-rendered structured-data blocks. */
	public function register_blocks() {
		register_block_type(
			self::GALLERY_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'source'  => array( 'type' => 'string', 'default' => 'acf' ),
					'key'     => array( 'type' => 'string', 'default' => '' ),
					'columns' => array( 'type' => 'number', 'default' => 3 ),
					'size'    => array( 'type' => 'string', 'default' => 'large' ),
					'limit'   => array( 'type' => 'number', 'default' => 12 ),
				),
				'render_callback' => array( $this, 'render_gallery' ),
				'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
			)
		);

		register_block_type(
			self::RELATIONSHIP_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'source'       => array( 'type' => 'string', 'default' => 'acf' ),
					'key'          => array( 'type' => 'string', 'default' => '' ),
					'limit'        => array( 'type' => 'number', 'default' => 12 ),
					'columns'      => array( 'type' => 'number', 'default' => 3 ),
					'emptyMessage' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render_relationship_loop' ),
				'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
			)
		);
	}

	/** Register safe structured-field inspection. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/dynamic/field-inspect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'inspect_field' ),
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
			)
		);
	}

	/** Return only type/count information, never raw sensitive values. */
	public function inspect_field( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();
		$post_id = absint( $payload['postId'] ?? 0 );
		$key     = sanitize_key( (string) ( $payload['key'] ?? '' ) );
		$source  = 'meta' === ( $payload['source'] ?? '' ) ? 'meta' : 'acf';
		if ( ! $post_id || ! $key || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_REST_Response( array( 'type' => 'empty', 'count' => 0 ), 400 );
		}
		$value = self::get_value( $source, $key, $post_id );
		return new WP_REST_Response( self::describe_value( $value ) );
	}

	/** Render an ACF or meta gallery. */
	public function render_gallery( $attributes, $content, $block ) {
		unset( $content );
		$post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();
		$key     = sanitize_key( (string) ( $attributes['key'] ?? '' ) );
		$source  = 'meta' === ( $attributes['source'] ?? '' ) ? 'meta' : 'acf';
		$images  = self::normalize_gallery( self::get_value( $source, $key, $post_id ) );
		$limit   = min( 24, max( 1, absint( $attributes['limit'] ?? 12 ) ) );
		$columns = min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) );
		$size    = sanitize_key( (string) ( $attributes['size'] ?? 'large' ) );
		if ( ! $images ) {
			return '';
		}
		$items = '';
		foreach ( array_slice( $images, 0, $limit ) as $image ) {
			if ( $image['id'] ) {
				$html = wp_get_attachment_image( $image['id'], $size, false, array( 'alt' => $image['alt'], 'loading' => 'lazy' ) );
			} else {
				$html = '<img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $image['alt'] ) . '" loading="lazy" decoding="async" />';
			}
			if ( $html ) {
				$items .= '<figure class="cresco-dynamic-gallery__item">' . $html . '</figure>';
			}
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-dynamic-gallery', 'style' => '--cresco-gallery-columns:' . $columns . ';' ) ) . '>' . $items . '</div>';
	}

	/** Render related posts using native inner blocks. */
	public function render_relationship_loop( $attributes, $content, $block ) {
		$post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();
		$key     = sanitize_key( (string) ( $attributes['key'] ?? '' ) );
		$source  = 'meta' === ( $attributes['source'] ?? '' ) ? 'meta' : 'acf';
		$posts   = self::normalize_relationship( self::get_value( $source, $key, $post_id ) );
		$limit   = min( 24, max( 1, absint( $attributes['limit'] ?? 12 ) ) );
		$columns = min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) );
		if ( ! $posts ) {
			$message = sanitize_text_field( (string) ( $attributes['emptyMessage'] ?? '' ) );
			return $message ? '<p class="cresco-relationship-loop__empty">' . esc_html( $message ) . '</p>' : '';
		}
		$original = $GLOBALS['post'] ?? null;
		$items    = '';
		foreach ( array_slice( $posts, 0, $limit ) as $related ) {
			$GLOBALS['post'] = $related; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for native dynamic block context.
			setup_postdata( $related );
			$items .= '<div class="cresco-relationship-loop__item">' . do_blocks( $content ) . '</div>';
		}
		wp_reset_postdata();
		if ( $original instanceof WP_Post ) {
			$GLOBALS['post'] = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-relationship-loop', 'style' => '--cresco-relationship-columns:' . $columns . ';' ) ) . '>' . $items . '</div>';
	}

	/** Fetch a field through ACF when available, otherwise post meta. */
	private static function get_value( $source, $key, $post_id ) {
		if ( ! $key || ! $post_id ) {
			return null;
		}
		if ( 'acf' === $source && function_exists( 'get_field' ) ) {
			return get_field( $key, $post_id );
		}
		return get_post_meta( $post_id, $key, true );
	}

	/** Normalize gallery values to safe image records. */
	public static function normalize_gallery( $value ) {
		$items = is_array( $value ) ? $value : array( $value );
		$out   = array();
		foreach ( $items as $item ) {
			$id = 0; $url = ''; $alt = '';
			if ( is_numeric( $item ) ) {
				$id = absint( $item );
				$url = $id ? (string) wp_get_attachment_url( $id ) : '';
			} elseif ( is_string( $item ) ) {
				$url = esc_url_raw( $item );
			} elseif ( is_array( $item ) ) {
				$id  = absint( $item['ID'] ?? $item['id'] ?? 0 );
				$url = $id ? (string) wp_get_attachment_url( $id ) : esc_url_raw( (string) ( $item['url'] ?? '' ) );
				$alt = sanitize_text_field( (string) ( $item['alt'] ?? '' ) );
			}
			if ( $url ) {
				$out[] = array( 'id' => $id, 'url' => $url, 'alt' => $alt );
			}
			if ( count( $out ) >= 24 ) { break; }
		}
		return $out;
	}

	/** Normalize relationship/post-object values to readable posts. */
	public static function normalize_relationship( $value ) {
		$items = is_array( $value ) ? $value : array( $value );
		$out   = array();
		foreach ( $items as $item ) {
			$post = $item instanceof WP_Post ? $item : get_post( absint( $item ) );
			if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
				$out[ $post->ID ] = $post;
			}
			if ( count( $out ) >= 24 ) { break; }
		}
		return array_values( $out );
	}

	/** Describe structured data without exposing its content. */
	public static function describe_value( $value ) {
		if ( null === $value || '' === $value || array() === $value ) {
			return array( 'type' => 'empty', 'count' => 0 );
		}
		if ( $value instanceof WP_Post ) {
			return array( 'type' => 'post', 'count' => 1 );
		}
		if ( is_array( $value ) ) {
			return array( 'type' => 'array', 'count' => min( 999, count( $value ) ) );
		}
		return array( 'type' => gettype( $value ), 'count' => 1 );
	}
}
