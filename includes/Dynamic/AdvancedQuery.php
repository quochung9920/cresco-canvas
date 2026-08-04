<?php
/**
 * Bounded advanced Query Builder and Loop rendering.
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

final class AdvancedQuery {
	const BLOCK = 'cresco/advanced-loop';

	/** @var int */
	private static $loop_depth = 0;

	/** Register the advanced loop and discovery routes. */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ), 33 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register a server-rendered advanced query block. */
	public function register_block() {
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'postType'     => array( 'type' => 'string', 'default' => 'post' ),
					'postsPerPage' => array( 'type' => 'number', 'default' => 6 ),
					'order'        => array( 'type' => 'string', 'default' => 'DESC' ),
					'orderby'      => array( 'type' => 'string', 'default' => 'date' ),
					'authorId'     => array( 'type' => 'number', 'default' => 0 ),
					'parentId'     => array( 'type' => 'number', 'default' => 0 ),
					'search'       => array( 'type' => 'string', 'default' => '' ),
					'dateAfter'    => array( 'type' => 'string', 'default' => '' ),
					'dateBefore'   => array( 'type' => 'string', 'default' => '' ),
					'includeIds'   => array( 'type' => 'string', 'default' => '' ),
					'excludeIds'   => array( 'type' => 'string', 'default' => '' ),
					'metaKey'      => array( 'type' => 'string', 'default' => '' ),
					'metaValue'    => array( 'type' => 'string', 'default' => '' ),
					'metaCompare'  => array( 'type' => 'string', 'default' => '=' ),
					'metaType'     => array( 'type' => 'string', 'default' => 'CHAR' ),
					'taxFilters'   => array( 'type' => 'array', 'default' => array() ),
					'columns'      => array( 'type' => 'number', 'default' => 3 ),
					'pagination'   => array( 'type' => 'boolean', 'default' => false ),
					'pageParam'    => array( 'type' => 'string', 'default' => 'cc_advanced_page' ),
					'emptyMessage' => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render' ),
				'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
			)
		);
	}

	/** Register safe options and preview endpoints. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/dynamic/advanced-query-options',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'options' ),
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
			)
		);
		register_rest_route(
			'cresco-canvas/v1',
			'/dynamic/advanced-query-preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
			)
		);
	}

	/** Return public query dimensions for editor selectors. */
	public function options() {
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true, 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			$post_types[] = array( 'slug' => $post_type->name, 'label' => $post_type->labels->singular_name );
		}

		$taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true, 'show_in_rest' => true ), 'objects' ) as $taxonomy ) {
			$taxonomies[] = array( 'slug' => $taxonomy->name, 'label' => $taxonomy->labels->singular_name );
		}

		$authors = array();
		foreach ( get_users( array( 'who' => 'authors', 'number' => 100, 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) {
			$authors[] = array( 'id' => (int) $user->ID, 'label' => $user->display_name );
		}

		return new WP_REST_Response(
			array(
				'postTypes'    => $post_types,
				'taxonomies'   => $taxonomies,
				'authors'      => $authors,
				'orderBy'      => array( 'date', 'modified', 'title', 'menu_order', 'rand', 'meta_value', 'meta_value_num' ),
				'metaCompare'  => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'EXISTS', 'NOT EXISTS' ),
				'metaTypes'    => array( 'CHAR', 'NUMERIC', 'DATE' ),
				'maxItems'     => 24,
				'maxTaxFilters'=> 3,
			)
		);
	}

	/** Preview normalized arguments and readable results. */
	public function preview( WP_REST_Request $request ) {
		$args  = self::sanitize_query( (array) $request->get_json_params() );
		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'read_post', $post->ID ) ) {
				$items[] = array(
					'id'       => (int) $post->ID,
					'title'    => get_the_title( $post ),
					'postType' => $post->post_type,
					'url'      => get_permalink( $post ),
				);
			}
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

	/** Render inner blocks for every result in the advanced query. */
	public function render( $attributes, $content ) {
		if ( self::$loop_depth > 0 ) {
			return current_user_can( 'edit_pages' ) ? '<p class="cresco-advanced-loop__warning">' . esc_html__( 'Nested Advanced Loops are not supported.', 'cresco-canvas' ) . '</p>' : '';
		}

		self::$loop_depth++;
		$page_param = self::sanitize_page_param( $attributes['pageParam'] ?? 'cc_advanced_page' );
		$paged = ! empty( $attributes['pagination'] ) && isset( $_GET[ $page_param ] ) ? absint( wp_unslash( $_GET[ $page_param ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only pagination.
		$args  = self::sanitize_query( array_merge( $attributes, array( 'paged' => max( 1, $paged ) ) ) );
		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			self::$loop_depth--;
			$message = sanitize_text_field( (string) ( $attributes['emptyMessage'] ?? '' ) );
			return $message ? '<p class="cresco-advanced-loop__empty">' . esc_html( $message ) . '</p>' : '';
		}

		$columns = min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) );
		$items   = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$items .= '<div class="cresco-advanced-loop__item">' . do_blocks( $content ) . '</div>';
		}
		wp_reset_postdata();
		self::$loop_depth--;

		$output = '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-advanced-loop', 'style' => '--cresco-advanced-columns:' . $columns . ';' ) ) . '>' . $items . '</div>';
		if ( ! empty( $attributes['pagination'] ) && $query->max_num_pages > 1 ) {
			$base = str_replace( '%25%23%25', '%#%', add_query_arg( $page_param, '%#%' ) );
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
				$output .= '<nav class="cresco-advanced-loop__pagination" aria-label="' . esc_attr__( 'Advanced Loop pagination', 'cresco-canvas' ) . '">' . wp_kses_post( $links ) . '</nav>';
			}
		}

		return $output;
	}

	/** Normalize a bounded advanced WordPress query. */
	public static function sanitize_query( $input ) {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_type  = sanitize_key( (string) ( $input['postType'] ?? 'post' ) );
		if ( ! in_array( $post_type, $post_types, true ) ) {
			$post_type = 'post';
		}

		$orderby = sanitize_key( (string) ( $input['orderby'] ?? 'date' ) );
		$allowed_orderby = array( 'date', 'modified', 'title', 'menu_order', 'rand', 'meta_value', 'meta_value_num' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$meta_key = sanitize_key( (string) ( $input['metaKey'] ?? '' ) );
		if ( in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true ) && ! $meta_key ) {
			$orderby = 'date';
		}

		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => min( 24, max( 1, absint( $input['postsPerPage'] ?? 6 ) ) ),
			'paged'               => min( 999, max( 1, absint( $input['paged'] ?? 1 ) ) ),
			'order'               => 'ASC' === strtoupper( (string) ( $input['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC',
			'orderby'             => $orderby,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		$author_id = absint( $input['authorId'] ?? 0 );
		$parent_id = absint( $input['parentId'] ?? 0 );
		$search    = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
		if ( $author_id ) {
			$args['author'] = $author_id;
		}
		if ( $parent_id ) {
			$args['post_parent'] = $parent_id;
		}
		if ( $search ) {
			$args['s'] = substr( $search, 0, 100 );
		}

		$include_ids = self::sanitize_ids( $input['includeIds'] ?? '' );
		$exclude_ids = self::sanitize_ids( $input['excludeIds'] ?? '' );
		if ( $include_ids ) {
			$args['post__in'] = $include_ids;
			if ( 'post__in' === ( $input['orderby'] ?? '' ) ) {
				$args['orderby'] = 'post__in';
			}
		}
		if ( $exclude_ids ) {
			$args['post__not_in'] = $exclude_ids;
		}

		$after  = self::sanitize_date( $input['dateAfter'] ?? '' );
		$before = self::sanitize_date( $input['dateBefore'] ?? '' );
		if ( $after || $before ) {
			$args['date_query'] = array(
				array_filter(
					array(
						'after'     => $after ?: null,
						'before'    => $before ?: null,
						'inclusive' => true,
					)
				),
			);
		}

		if ( $meta_key ) {
			$args['meta_key'] = $meta_key;
			$compare = strtoupper( (string) ( $input['metaCompare'] ?? '=' ) );
			$allowed_compare = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'EXISTS', 'NOT EXISTS' );
			if ( ! in_array( $compare, $allowed_compare, true ) ) {
				$compare = '=';
			}
			$type = strtoupper( (string) ( $input['metaType'] ?? 'CHAR' ) );
			if ( ! in_array( $type, array( 'CHAR', 'NUMERIC', 'DATE' ), true ) ) {
				$type = 'CHAR';
			}
			$clause = array( 'key' => $meta_key, 'compare' => $compare, 'type' => $type );
			if ( ! in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$clause['value'] = substr( sanitize_text_field( (string) ( $input['metaValue'] ?? '' ) ), 0, 200 );
			}
			$args['meta_query'] = array( $clause );
		}

		$tax_query = self::sanitize_tax_filters( $input['taxFilters'] ?? array(), $post_type );
		if ( $tax_query ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query );
		}

		return $args;
	}

	/** Normalize a comma-separated or array ID list. */
	public static function sanitize_ids( $value ) {
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		$ids   = array_values( array_unique( array_filter( array_map( 'absint', array_slice( $items, 0, 24 ) ) ) ) );
		return array_slice( $ids, 0, 24 );
	}

	/** Normalize an ISO date accepted by WP_Date_Query. */
	public static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $value ) );
		return checkdate( $month, $day, $year ) ? $value : '';
	}

	/** Normalize at most three taxonomy filters assigned to the post type. */
	public static function sanitize_tax_filters( $filters, $post_type ) {
		$output = array();
		foreach ( array_slice( is_array( $filters ) ? $filters : array(), 0, 3 ) as $filter ) {
			if ( ! is_array( $filter ) ) {
				continue;
			}
			$taxonomy = sanitize_key( (string) ( $filter['taxonomy'] ?? '' ) );
			$terms = is_array( $filter['terms'] ?? null ) ? $filter['terms'] : explode( ',', (string) ( $filter['terms'] ?? '' ) );
			$terms = array_values( array_filter( array_map( 'sanitize_title', array_slice( $terms, 0, 24 ) ) ) );
			if ( ! $taxonomy || ! $terms || ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				continue;
			}
			$output[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
				'operator' => 'NOT IN' === strtoupper( (string) ( $filter['operator'] ?? 'IN' ) ) ? 'NOT IN' : 'IN',
			);
		}
		return $output;
	}

	/** Normalize the public pagination query parameter. */
	public static function sanitize_page_param( $value ) {
		$value = sanitize_key( (string) $value );
		return $value ? substr( $value, 0, 32 ) : 'cc_advanced_page';
	}
}
