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
	const IMAGE_BLOCK = 'cresco/dynamic-image';
	const LOOP_BLOCK  = 'cresco/loop';

	/** @var int */
	private static $loop_depth = 0;

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
			self::IMAGE_BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'source'      => array( 'type' => 'string', 'default' => 'featured' ),
					'key'         => array( 'type' => 'string', 'default' => '' ),
					'size'        => array( 'type' => 'string', 'default' => 'large' ),
					'altFallback' => array( 'type' => 'string', 'default' => '' ),
					'linkTo'      => array( 'type' => 'string', 'default' => 'none' ),
					'fallbackUrl' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render_dynamic_image' ),
				'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'left', 'center', 'right', 'wide', 'full' ), 'spacing' => true ),
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
					'preset'       => array( 'type' => 'string', 'default' => 'custom' ),
					'taxonomy'     => array( 'type' => 'string', 'default' => '' ),
					'term'         => array( 'type' => 'string', 'default' => '' ),
					'offset'       => array( 'type' => 'number', 'default' => 0 ),
					'columns'      => array( 'type' => 'number', 'default' => 3 ),
					'pagination'   => array( 'type' => 'boolean', 'default' => false ),
					'pageParam'    => array( 'type' => 'string', 'default' => 'cc_page' ),
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
				'postTypes'      => $types,
				'sources'        => array( 'post', 'meta', 'acf', 'site' ),
				'imageSources'   => array( 'featured', 'meta', 'acf' ),
				'postFields'     => array( 'title', 'excerpt', 'content', 'date', 'modified', 'author', 'permalink', 'featured_image_url' ),
				'orderBy'        => array( 'date', 'modified', 'title', 'menu_order', 'rand' ),
				'queryPresets'   => array( 'custom', 'recent', 'oldest', 'alphabetical', 'random' ),
				'imageSizes'     => array_values( get_intermediate_image_sizes() ),
				'acfAvailable'   => function_exists( 'get_field' ),
				'maxLoopItems'   => 24,
				'maxLoopOffset'  => 200,
				'maxNestedLoops' => 1,
			)
		);
	}

	/** Return a safe preview of a normalized query. */
	public function query_preview( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();
		$args    = self::sanitize_query( $payload );
		$query   = new WP_Query( $args );
		$items   = array();
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
		return new WP_REST_Response(
			array(
				'args'       => $args,
				'page'       => (int) ( $args['paged'] ?? 1 ),
				'maxPages'   => (int) $query->max_num_pages,
				'foundPosts' => (int) $query->found_posts,
				'items'      => $items,
			)
		);
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

	/** Render a featured, meta, or ACF image. */
	public function render_dynamic_image( $attributes, $content, $block ) {
		unset( $content );
		$post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : get_the_ID();
		$image   = self::resolve_image( $attributes, $post_id );
		if ( empty( $image['url'] ) ) {
			$fallback = esc_url_raw( (string) ( $attributes['fallbackUrl'] ?? '' ) );
			if ( ! $fallback ) {
				return '';
			}
			$image = array( 'id' => 0, 'url' => $fallback, 'alt' => '' );
		}

		$alt = '' !== (string) ( $image['alt'] ?? '' ) ? (string) $image['alt'] : sanitize_text_field( (string) ( $attributes['altFallback'] ?? '' ) );
		$size = sanitize_key( (string) ( $attributes['size'] ?? 'large' ) );
		if ( ! in_array( $size, array_merge( array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), get_intermediate_image_sizes() ), true ) ) {
			$size = 'large';
		}

		if ( ! empty( $image['id'] ) ) {
			$html = wp_get_attachment_image( (int) $image['id'], $size, false, array( 'alt' => $alt, 'class' => 'cresco-dynamic-image__img' ) );
		} else {
			$html = '<img class="cresco-dynamic-image__img" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
		}
		if ( ! $html ) {
			return '';
		}
		if ( 'post' === sanitize_key( (string) ( $attributes['linkTo'] ?? 'none' ) ) && $post_id ) {
			$html = '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . $html . '</a>';
		}
		return '<figure ' . get_block_wrapper_attributes( array( 'class' => 'cresco-dynamic-image' ) ) . '>' . $html . '</figure>';
	}

	/** Render inner blocks for every post returned by the normalized query. */
	public function render_loop( $attributes, $content ) {
		if ( self::$loop_depth > 0 ) {
			return current_user_can( 'edit_pages' ) ? '<p class="cresco-loop__warning">' . esc_html__( 'Nested Cresco Loops are not supported.', 'cresco-canvas' ) . '</p>' : '';
		}

		self::$loop_depth++;
		$page_param = self::sanitize_page_param( $attributes['pageParam'] ?? 'cc_page' );
		$paged      = ! empty( $attributes['pagination'] ) && isset( $_GET[ $page_param ] ) ? absint( wp_unslash( $_GET[ $page_param ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only pagination.
		$input      = array_merge( $attributes, array( 'paged' => max( 1, $paged ) ) );
		$args       = self::sanitize_query( $input );
		$query      = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			self::$loop_depth--;
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
		self::$loop_depth--;

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'cresco-loop',
				'style' => '--cresco-loop-columns:' . $columns . ';',
			)
		);
		$output = '<div ' . $wrapper . '>' . $items . '</div>';
		if ( ! empty( $attributes['pagination'] ) && $query->max_num_pages > 1 ) {
			$base = add_query_arg( $page_param, '%#%' );
			$links = paginate_links(
				array(
					'base'      => $base,
					'format'    => '',
					'current'   => max( 1, $paged ),
					'total'     => (int) $query->max_num_pages,
					'type'      => 'list',
					'prev_text' => __( 'Previous', 'cresco-canvas' ),
					'next_text' => __( 'Next', 'cresco-canvas' ),
				)
			);
			if ( $links ) {
				$output .= '<nav class="cresco-loop__pagination" aria-label="' . esc_attr__( 'Loop pagination', 'cresco-canvas' ) . '">' . wp_kses_post( $links ) . '</nav>';
			}
		}
		return $output;
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

	/** Resolve common ACF and metadata image return formats. */
	public static function resolve_image( $attributes, $post_id ) {
		if ( ! $post_id ) {
			return array( 'id' => 0, 'url' => '', 'alt' => '' );
		}
		$source = sanitize_key( (string) ( $attributes['source'] ?? 'featured' ) );
		$key    = sanitize_key( (string) ( $attributes['key'] ?? '' ) );
		if ( 'featured' === $source ) {
			$id = get_post_thumbnail_id( $post_id );
			return array( 'id' => (int) $id, 'url' => $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '', 'alt' => $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '' );
		}
		$value = 'acf' === $source && function_exists( 'get_field' ) ? get_field( $key, $post_id ) : get_post_meta( $post_id, $key, true );
		if ( is_numeric( $value ) ) {
			$id = absint( $value );
			return array( 'id' => $id, 'url' => (string) wp_get_attachment_image_url( $id, 'full' ), 'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		}
		if ( is_string( $value ) ) {
			return array( 'id' => 0, 'url' => esc_url_raw( $value ), 'alt' => '' );
		}
		if ( is_array( $value ) ) {
			$id  = absint( $value['ID'] ?? $value['id'] ?? 0 );
			$url = esc_url_raw( (string) ( $value['url'] ?? ( $id ? wp_get_attachment_image_url( $id, 'full' ) : '' ) ) );
			$alt = sanitize_text_field( (string) ( $value['alt'] ?? ( $id ? get_post_meta( $id, '_wp_attachment_image_alt', true ) : '' ) ) );
			return array( 'id' => $id, 'url' => $url, 'alt' => $alt );
		}
		return array( 'id' => 0, 'url' => '', 'alt' => '' );
	}

	/** Normalize loop query arguments to a bounded allow-list. */
	public static function sanitize_query( $input ) {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_type  = sanitize_key( (string) ( $input['postType'] ?? 'post' ) );
		if ( ! in_array( $post_type, $post_types, true ) ) {
			$post_type = 'post';
		}

		$preset  = sanitize_key( (string) ( $input['preset'] ?? 'custom' ) );
		$order   = 'ASC' === strtoupper( (string) ( $input['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$orderby = sanitize_key( (string) ( $input['orderby'] ?? 'date' ) );
		if ( ! in_array( $orderby, array( 'date', 'modified', 'title', 'menu_order', 'rand' ), true ) ) {
			$orderby = 'date';
		}
		switch ( $preset ) {
			case 'recent': $orderby = 'date'; $order = 'DESC'; break;
			case 'oldest': $orderby = 'date'; $order = 'ASC'; break;
			case 'alphabetical': $orderby = 'title'; $order = 'ASC'; break;
			case 'random': $orderby = 'rand'; $order = 'DESC'; break;
			default: $preset = 'custom';
		}

		$per_page   = min( 24, max( 1, absint( $input['postsPerPage'] ?? 6 ) ) );
		$base_offset = min( 200, max( 0, absint( $input['offset'] ?? 0 ) ) );
		$paged       = min( 999, max( 1, absint( $input['paged'] ?? 1 ) ) );
		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'offset'              => min( 2000, $base_offset + ( ( $paged - 1 ) * $per_page ) ),
			'paged'               => $paged,
			'order'               => $order,
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

	/** Normalize a query-string key used by one loop instance. */
	public static function sanitize_page_param( $value ) {
		$value = sanitize_key( (string) $value );
		return $value ? substr( $value, 0, 32 ) : 'cc_page';
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
