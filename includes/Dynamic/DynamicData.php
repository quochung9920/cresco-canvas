<?php
/**
 * Dynamic data, ACF integration, Query Builder, and Loop rendering.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DynamicData {
	const FIELD_BLOCK = 'cresco/dynamic-field';
	const LOOP_BLOCK  = 'cresco/loop';

	/** Register dynamic blocks and REST discovery endpoints. */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ), 30 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register server-rendered native blocks. */
	public function register_blocks() {
		register_block_type(
			self::FIELD_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'source'   => array( 'type' => 'string', 'default' => 'post' ),
					'field'    => array( 'type' => 'string', 'default' => 'title' ),
					'key'      => array( 'type' => 'string', 'default' => '' ),
					'fallback' => array( 'type' => 'string', 'default' => '' ),
					'tagName'  => array( 'type' => 'string', 'default' => 'span' ),
					'linkTo'   => array( 'type' => 'string', 'default' => 'none' ),
				),
				'render_callback' => array( $this, 'render_dynamic_field' ),
				'supports'        => array( 'html' => false, 'className' => true, 'color' => true, 'typography' => true, 'spacing' => true ),
			)
		);

		register_block_type(
			self::LOOP_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'postType'     => array( 'type' => 'string', 'default' => 'post' ),
					'postsPerPage' => array( 'type' => 'number', 'default' => 6 ),
					'order'        => array( 'type' => 'string', 'default' => 'DESC' ),
					'orderby'      => array( 'type' => 'string', 'default' => 'date' ),
					'taxonomy'     => array( 'type' => 'string', 'default' => '' ),
					'term'         => array( 'type' => 'string', 'default' => '' ),
					'offset'       => array( 'type' => 'number', 'default' => 0 ),
					'columns'      => array( 'type' => 'number', 'default' => 3 ),
					'emptyMessage' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render_loop' ),
				'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
			)
		);
	}

	/** Register discovery and query-preview routes. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/dynamic/options',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/dynamic/query-preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'query_preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	/** @return bool */
	public function can_edit() {
		return current_user_can( 'edit_pages' );
	}

	/** Return available public content types and supported fields. */
	public function get_options() {
		$post_types = get_post_types( array( 'public' => true, 'show_in_rest' => true ), 'objects' );
		$types      = array();
		foreach ( $post_types as $post_type ) {
			$types[] = array( 'slug' => $post_type->name, 'label' => $post_type->labels->singular_name );
		}

		return new WP_REST_Response(
			array(
				'postTypes'     => $types,
				'sources'       => array( 'post', 'meta', 'acf', 'site' ),
				'postFields'    => array( 'title', 'excerpt', 'content', 'date', 'modified', 'author', 'permalink', 'featured_image_url' ),
				'orderBy'       => array( 'date', 'modified', 'title', 'menu_order', 'rand' ),
				'acfAvailable'  => function_exists( 'get_field' ),
				'maxLoopItems'  => 24,
				'maxLoopOffset' => 200,
			)
		);
	}

	/** Return a safe preview of a normalized query. */
	public function query_preview( WP_REST_Request $request ) {
		$args  = self::sanitize_query( (array) $request->get_json_params() );
		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}
			$items[] = array(
				'id'       => (int) $post->ID,
				'title'    => get_the_title( $post ),
				'postType' => $post->post_type,
				'status'   => $post->post_status,
				'url'      => get_permalink( $post ),
			);
		}
		return new WP_REST_Response( array( 'args' => $args, 'foundPosts' => (int) $query->found_posts, 'items' => $items ) );
	}

	/** Render a dynamic scalar value. */
	public function render_dynamic_field( $attributes, $content, $block ) {
		unset( $content );
		$post_id  = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();
		$value    = self::resolve_value( $attributes, $post_id );
		$fallback = sanitize_text_field( (string) ( $attributes['fallback'] ?? '' ) );
		if ( '' === $value ) {
			$value = $fallback;
		}
		if ( '' === $value ) {
			return '';
		}

		$tag = sanitize_key( (string) ( $attributes['tagName'] ?? 'span' ) );
		if ( ! in_array( $tag, array( 'span', 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
			$tag = 'span';
		}
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'cresco-dynamic-field' ) );
		$output  = esc_html( $value );
		$link_to = sanitize_key( (string) ( $attributes['linkTo'] ?? 'none' ) );
		if ( 'post' === $link_to && $post_id ) {
			$output = '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . $output . '</a>';
		}
		return sprintf( '<%1$s %2$s>%3$s</%1$s>', $tag, $wrapper, $output );
	}

	/** Render inner blocks for every post returned by the normalized query. */
	public function render_loop( $attributes, $content ) {
		$args  = self::sanitize_query( $attributes );
		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			$message = sanitize_text_field( (string) ( $attributes['emptyMessage'] ?? '' ) );
			return $message ? '<p class="cresco-loop__empty">' . esc_html( $message ) . '</p>' : '';
		}

		$columns = min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) );
		$items   = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$items .= '<div class="cresco-loop__item">' . do_blocks( $content ) . '</div>';
		}
		wp_reset_postdata();
		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'cresco-loop',
				'style' => '--cresco-loop-columns:' . $columns . ';',
			)
		);
		return '<div ' . $wrapper . '>' . $items . '</div>';
	}

	/** Resolve an allow-listed dynamic value. */
	public static function resolve_value( $attributes, $post_id ) {
		$source = sanitize_key( (string) ( $attributes['source'] ?? 'post' ) );
		$field  = sanitize_key( (string) ( $attributes['field'] ?? 'title' ) );
		$key    = sanitize_key( (string) ( $attributes['key'] ?? '' ) );

		if ( 'site' === $source ) {
			return 'description' === $field ? (string) get_bloginfo( 'description' ) : (string) get_bloginfo( 'name' );
		}
		if ( ! $post_id ) {
			return '';
		}
		if ( 'meta' === $source ) {
			return self::scalar( get_post_meta( $post_id, $key, true ) );
		}
		if ( 'acf' === $source ) {
			$value = function_exists( 'get_field' ) ? get_field( $key, $post_id ) : get_post_meta( $post_id, $key, true );
			return self::scalar( $value );
		}

		switch ( $field ) {
			case 'excerpt': return wp_strip_all_tags( get_the_excerpt( $post_id ) );
			case 'content': return wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );
			case 'date': return (string) get_the_date( '', $post_id );
			case 'modified': return (string) get_the_modified_date( '', $post_id );
			case 'author': return (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
			case 'permalink': return (string) get_permalink( $post_id );
			case 'featured_image_url': return (string) get_the_post_thumbnail_url( $post_id, 'full' );
			case 'title':
			default: return (string) get_the_title( $post_id );
		}
	}

	/** Normalize loop query arguments to a bounded allow-list. */
	public static function sanitize_query( $input ) {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_type  = sanitize_key( (string) ( $input['postType'] ?? 'post' ) );
		if ( ! in_array( $post_type, $post_types, true ) ) {
			$post_type = 'post';
		}
		$orderby = sanitize_key( (string) ( $input['orderby'] ?? 'date' ) );
		if ( ! in_array( $orderby, array( 'date', 'modified', 'title', 'menu_order', 'rand' ), true ) ) {
			$orderby = 'date';
		}
		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => min( 24, max( 1, absint( $input['postsPerPage'] ?? 6 ) ) ),
			'offset'              => min( 200, max( 0, absint( $input['offset'] ?? 0 ) ) ),
			'order'               => 'ASC' === strtoupper( (string) ( $input['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC',
			'orderby'             => $orderby,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);
		$taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
		$term     = sanitize_title( (string) ( $input['term'] ?? '' ) );
		if ( $taxonomy && $term && taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			$args['tax_query'] = array( array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => array( $term ) ) );
		}
		return $args;
	}

	/** Convert a dynamic value to safe scalar text. */
	private static function scalar( $value ) {
		if ( is_scalar( $value ) ) {
			return wp_strip_all_tags( (string) $value );
		}
		if ( is_array( $value ) ) {
			$flat = array_filter( array_map( static function ( $item ) { return is_scalar( $item ) ? (string) $item : ''; }, $value ) );
			return wp_strip_all_tags( implode( ', ', $flat ) );
		}
		return '';
	}
}
