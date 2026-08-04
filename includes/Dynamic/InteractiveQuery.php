<?php
/**
 * Signed public AJAX filtering, facets, load-more, infinite scroll, and WooCommerce presets.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Dynamic;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InteractiveQuery {
	const BLOCK = 'cresco/filterable-loop';

	/** @var int */
	private static $loop_depth = 0;

	/** Register the block, public render endpoint, and frontend assets. */
	public function register() {
		add_action( 'init', array( $this, 'register_assets' ), 20 );
		add_action( 'init', array( $this, 'register_block' ), 34 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register checked-in frontend assets. */
	public function register_assets() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/dynamic-alpha5-frontend.asset.php';
		$script     = CRESCO_CANVAS_PATH . 'build/dynamic-alpha5-frontend.js';
		$style      = CRESCO_CANVAS_PATH . 'assets/css/dynamic-alpha5.css';
		if ( is_readable( $asset_file ) && is_readable( $script ) ) {
			$asset = require $asset_file;
			wp_register_script(
				'cresco-canvas-dynamic-alpha5-frontend',
				CRESCO_CANVAS_URL . 'build/dynamic-alpha5-frontend.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}
		if ( is_readable( $style ) ) {
			wp_register_style(
				'cresco-canvas-dynamic-alpha5',
				CRESCO_CANVAS_URL . 'assets/css/dynamic-alpha5.css',
				array(),
				CRESCO_CANVAS_VERSION
			);
		}
	}

	/** Register a server-rendered filterable Loop block. */
	public function register_block() {
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'postType'          => array( 'type' => 'string', 'default' => 'post' ),
					'postsPerPage'      => array( 'type' => 'number', 'default' => 6 ),
					'order'             => array( 'type' => 'string', 'default' => 'DESC' ),
					'orderby'           => array( 'type' => 'string', 'default' => 'date' ),
					'authorId'          => array( 'type' => 'number', 'default' => 0 ),
					'parentId'          => array( 'type' => 'number', 'default' => 0 ),
					'search'            => array( 'type' => 'string', 'default' => '' ),
					'dateAfter'         => array( 'type' => 'string', 'default' => '' ),
					'dateBefore'        => array( 'type' => 'string', 'default' => '' ),
					'includeIds'        => array( 'type' => 'string', 'default' => '' ),
					'excludeIds'        => array( 'type' => 'string', 'default' => '' ),
					'metaKey'           => array( 'type' => 'string', 'default' => '' ),
					'metaValue'         => array( 'type' => 'string', 'default' => '' ),
					'metaCompare'       => array( 'type' => 'string', 'default' => '=' ),
					'metaType'          => array( 'type' => 'string', 'default' => 'CHAR' ),
					'taxFilters'        => array( 'type' => 'array', 'default' => array() ),
					'columns'           => array( 'type' => 'number', 'default' => 3 ),
					'interactionMode'   => array( 'type' => 'string', 'default' => 'ajax' ),
					'syncUrl'           => array( 'type' => 'boolean', 'default' => true ),
					'instanceId'        => array( 'type' => 'string', 'default' => '' ),
					'searchFilter'      => array( 'type' => 'boolean', 'default' => true ),
					'facetTaxonomies'   => array( 'type' => 'array', 'default' => array() ),
					'wooPreset'         => array( 'type' => 'string', 'default' => 'none' ),
					'emptyMessage'      => array( 'type' => 'string', 'default' => '' ),
					'loadMoreLabel'     => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => array( $this, 'render' ),
				'supports'        => array(
					'html'      => false,
					'className' => true,
					'align'     => array( 'wide', 'full' ),
					'spacing'   => true,
				),
			)
		);
	}

	/** Register a signed public read-only render route. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/dynamic/interactive-query',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_render' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Render the initial, progressively enhanced filterable Loop. */
	public function render( $attributes, $content ) {
		if ( self::$loop_depth > 0 || ! self::is_safe_template( $content ) ) {
			return current_user_can( 'edit_pages' )
				? '<p class="cresco-filterable-loop__warning">' . esc_html__( 'Nested or unsafe Filterable Loop templates are not supported.', 'cresco-canvas' ) . '</p>'
				: '';
		}

		self::$loop_depth++;
		$payload_data = self::build_payload( $attributes, $content );
		$encoded      = self::encode_payload( $payload_data );
		$signature    = self::sign_payload( $encoded );
		$instance     = self::sanitize_instance_id( $attributes['instanceId'] ?? '', $encoded );
		$facets       = self::sanitize_facet_taxonomies( $attributes['facetTaxonomies'] ?? array(), $payload_data['query']['postType'] );
		$filters      = self::filters_from_request( $instance, $facets );
		$page         = self::page_from_request( $instance );
		$result       = self::run_query( $payload_data, $filters, $page );
		$mode         = self::sanitize_mode( $attributes['interactionMode'] ?? 'ajax' );

		self::$loop_depth--;
		wp_enqueue_script( 'cresco-canvas-dynamic-alpha5-frontend' );
		wp_enqueue_style( 'cresco-canvas-dynamic-alpha5' );

		$controls = self::render_controls( $payload_data, $filters, $facets, $instance );
		$empty    = sanitize_text_field( (string) ( $attributes['emptyMessage'] ?? '' ) );
		$items    = $result['html'];
		if ( '' === $items && $empty ) {
			$items = '<p class="cresco-filterable-loop__empty">' . esc_html( $empty ) . '</p>';
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'cresco-filterable-loop',
				'style' => '--cresco-filterable-columns:' . min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) ) . ';',
			)
		);
		$pagination = self::pagination_html( $result['page'], $result['maxPages'], $instance );
		$label      = sanitize_text_field( (string) ( $attributes['loadMoreLabel'] ?? '' ) );
		if ( '' === $label ) {
			$label = __( 'Load more', 'cresco-canvas' );
		}

		return '<section ' . $wrapper
			. ' data-cresco-query="1" data-endpoint="' . esc_url( rest_url( 'cresco-canvas/v1/dynamic/interactive-query' ) )
			. '" data-payload="' . esc_attr( $encoded ) . '" data-signature="' . esc_attr( $signature )
			. '" data-mode="' . esc_attr( $mode ) . '" data-sync-url="' . ( ! empty( $attributes['syncUrl'] ) ? '1' : '0' )
			. '" data-instance="' . esc_attr( $instance ) . '" data-current-page="' . esc_attr( (string) $result['page'] )
			. '" data-max-pages="' . esc_attr( (string) $result['maxPages'] ) . '">'
			. $controls
			. '<div class="cresco-filterable-loop__status" aria-live="polite" aria-atomic="true"></div>'
			. '<div class="cresco-filterable-loop__results">' . $items . '</div>'
			. '<div class="cresco-filterable-loop__navigation">'
			. ( 'ajax' === $mode ? $pagination : '' )
			. ( 'load_more' === $mode ? '<button type="button" class="cresco-filterable-loop__more"' . ( $result['hasMore'] ? '' : ' hidden' ) . '>' . esc_html( $label ) . '</button>' : '' )
			. ( 'infinite' === $mode ? '<div class="cresco-filterable-loop__sentinel" aria-hidden="true"' . ( $result['hasMore'] ? '' : ' hidden' ) . '></div>' : '' )
			. '</div>'
			. '<noscript>' . $pagination . '</noscript>'
			. '</section>';
	}

	/** Process a signed public AJAX render request. */
	public function rest_render( WP_REST_Request $request ) {
		$limited = $this->rate_limit();
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$params    = (array) $request->get_json_params();
		$encoded   = sanitize_text_field( (string) ( $params['payload'] ?? '' ) );
		$signature = sanitize_text_field( (string) ( $params['signature'] ?? '' ) );
		if ( ! self::verify_payload( $encoded, $signature ) ) {
			return new WP_Error( 'cresco_invalid_query_signature', __( 'The query request signature is invalid.', 'cresco-canvas' ), array( 'status' => 403 ) );
		}

		$payload = self::decode_payload( $encoded );
		if ( ! is_array( $payload ) || empty( $payload['content'] ) || ! self::is_safe_template( (string) $payload['content'] ) ) {
			return new WP_Error( 'cresco_invalid_query_payload', __( 'The query request payload is invalid.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$post_type = sanitize_key( (string) ( $payload['query']['postType'] ?? 'post' ) );
		$facets    = self::sanitize_facet_taxonomies( $payload['facets'] ?? array(), $post_type );
		$filters   = self::sanitize_public_filters( $params['filters'] ?? array(), $facets );
		$page      = min( 999, max( 1, absint( $params['page'] ?? 1 ) ) );
		$result    = self::run_query( $payload, $filters, $page );
		$instance  = self::sanitize_instance_id( $payload['instanceId'] ?? '', $encoded );

		return new WP_REST_Response(
			array(
				'html'       => $result['html'],
				'page'       => $result['page'],
				'maxPages'   => $result['maxPages'],
				'foundPosts' => $result['foundPosts'],
				'hasMore'    => $result['hasMore'],
				'pagination' => self::pagination_html( $result['page'], $result['maxPages'], $instance ),
			)
		);
	}

	/** Build the bounded server-owned payload signed into the page. */
	public static function build_payload( $attributes, $content ) {
		$query_keys = array(
			'postType', 'postsPerPage', 'order', 'orderby', 'authorId', 'parentId', 'search',
			'dateAfter', 'dateBefore', 'includeIds', 'excludeIds', 'metaKey', 'metaValue',
			'metaCompare', 'metaType', 'taxFilters',
		);
		$query = array();
		foreach ( $query_keys as $key ) {
			if ( array_key_exists( $key, (array) $attributes ) ) {
				$query[ $key ] = $attributes[ $key ];
			}
		}
		$query['postType']     = sanitize_key( (string) ( $query['postType'] ?? 'post' ) );
		$query['postsPerPage'] = min( 24, max( 1, absint( $query['postsPerPage'] ?? 6 ) ) );

		return array(
			'query'       => $query,
			'content'     => (string) $content,
			'facets'      => self::sanitize_facet_taxonomies( $attributes['facetTaxonomies'] ?? array(), $query['postType'] ),
			'wooPreset'   => self::sanitize_woo_preset( $attributes['wooPreset'] ?? 'none' ),
			'instanceId'  => sanitize_key( (string) ( $attributes['instanceId'] ?? '' ) ),
			'columns'     => min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) ),
			'searchFilter'=> ! empty( $attributes['searchFilter'] ),
		);
	}

	/** Execute a signed query and render its native block template. */
	private static function run_query( $payload, $filters, $page ) {
		$query_input          = (array) ( $payload['query'] ?? array() );
		$query_input['paged'] = min( 999, max( 1, absint( $page ) ) );
		$args                 = AdvancedQuery::sanitize_query( $query_input );
		$filters              = self::sanitize_public_filters( $filters, $payload['facets'] ?? array() );
		if ( ! empty( $filters['search'] ) ) {
			$args['s'] = $filters['search'];
		}
		foreach ( $filters['tax'] as $taxonomy => $terms ) {
			self::append_tax_clause(
				$args,
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $terms,
					'operator' => 'IN',
				)
			);
		}
		self::apply_woo_preset( $args, $payload['wooPreset'] ?? 'none', $args['post_type'] );

		$query = new WP_Query( $args );
		$html  = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= '<div class="cresco-filterable-loop__item">' . do_blocks( (string) $payload['content'] ) . '</div>';
		}
		wp_reset_postdata();

		$page      = (int) ( $args['paged'] ?? 1 );
		$max_pages = (int) $query->max_num_pages;
		return array(
			'html'       => $html,
			'page'       => $page,
			'maxPages'   => $max_pages,
			'foundPosts' => (int) $query->found_posts,
			'hasMore'    => $page < $max_pages,
		);
	}

	/** Render search and taxonomy facet controls with a no-JS GET fallback. */
	private static function render_controls( $payload, $filters, $facets, $instance ) {
		if ( empty( $payload['searchFilter'] ) && ! $facets ) {
			return '';
		}
		$html = '<form class="cresco-filterable-loop__filters" method="get">';
		if ( ! empty( $payload['searchFilter'] ) ) {
			$name  = $instance . '_s';
			$value = (string) ( $filters['search'] ?? '' );
			$html .= '<label><span>' . esc_html__( 'Search', 'cresco-canvas' ) . '</span><input type="search" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" /></label>';
		}
		foreach ( $facets as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			$terms  = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 100 ) );
			if ( ! $object || is_wp_error( $terms ) ) {
				continue;
			}
			$selected = (string) ( $filters['tax'][ $taxonomy ][0] ?? '' );
			$name     = $instance . '_' . $taxonomy;
			$html    .= '<label><span>' . esc_html( $object->labels->singular_name ) . '</span><select name="' . esc_attr( $name ) . '" data-taxonomy="' . esc_attr( $taxonomy ) . '"><option value="">' . esc_html__( 'All', 'cresco-canvas' ) . '</option>';
			foreach ( $terms as $term ) {
				$html .= '<option value="' . esc_attr( $term->slug ) . '"' . selected( $selected, $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
			$html .= '</select></label>';
		}
		$html .= '<button type="submit">' . esc_html__( 'Apply filters', 'cresco-canvas' ) . '</button></form>';
		return $html;
	}

	/** Read scoped filter values from a normal page request. */
	private static function filters_from_request( $instance, $facets ) {
		$raw = array( 'search' => '', 'tax' => array() );
		$search_key = $instance . '_s';
		if ( isset( $_GET[ $search_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only filtering.
			$raw['search'] = wp_unslash( $_GET[ $search_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		foreach ( $facets as $taxonomy ) {
			$key = $instance . '_' . $taxonomy;
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$raw['tax'][ $taxonomy ] = array( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		return self::sanitize_public_filters( $raw, $facets );
	}

	/** Read the scoped page number from a normal page request. */
	private static function page_from_request( $instance ) {
		$key = $instance . '_page';
		return isset( $_GET[ $key ] ) ? min( 999, max( 1, absint( wp_unslash( $_GET[ $key ] ) ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only pagination.
	}

	/** Normalize public filter input to declared dimensions only. */
	public static function sanitize_public_filters( $filters, $allowed_taxonomies ) {
		$filters = is_array( $filters ) ? $filters : array();
		$allowed = array_fill_keys( array_slice( array_map( 'sanitize_key', (array) $allowed_taxonomies ), 0, 3 ), true );
		$output  = array(
			'search' => substr( sanitize_text_field( (string) ( $filters['search'] ?? '' ) ), 0, 100 ),
			'tax'    => array(),
		);
		foreach ( (array) ( $filters['tax'] ?? array() ) as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( ! isset( $allowed[ $taxonomy ] ) ) {
				continue;
			}
			$terms = is_array( $terms ) ? $terms : array( $terms );
			$terms = array_values( array_unique( array_filter( array_map( 'sanitize_title', array_slice( $terms, 0, 24 ) ) ) ) );
			if ( $terms ) {
				$output['tax'][ $taxonomy ] = $terms;
			}
		}
		return $output;
	}

	/** Restrict facets to public taxonomies assigned to the selected post type. */
	public static function sanitize_facet_taxonomies( $facets, $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		$output    = array();
		foreach ( array_slice( is_array( $facets ) ? $facets : explode( ',', (string) $facets ), 0, 3 ) as $taxonomy ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( $taxonomy && taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				$output[] = $taxonomy;
			}
		}
		return array_values( array_unique( $output ) );
	}

	/** Apply a bounded WooCommerce query preset only to products. */
	public static function apply_woo_preset( &$args, $preset, $post_type ) {
		$preset = self::sanitize_woo_preset( $preset );
		if ( 'product' !== $post_type || 'none' === $preset || ! post_type_exists( 'product' ) ) {
			return;
		}
		switch ( $preset ) {
			case 'newest':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'featured':
				if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
					$ids = wc_get_product_visibility_term_ids();
					if ( ! empty( $ids['featured'] ) ) {
						self::append_tax_clause( $args, array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => array( absint( $ids['featured'] ) ), 'operator' => 'IN' ) );
					}
				}
				break;
			case 'sale':
				if ( function_exists( 'wc_get_product_ids_on_sale' ) ) {
					$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', wc_get_product_ids_on_sale() ) ) ) ), 0, 5000 );
					$args['post__in'] = $ids ?: array( 0 );
				}
				break;
			case 'in_stock':
				self::append_meta_clause( $args, array( 'key' => '_stock_status', 'value' => 'instock', 'compare' => '=', 'type' => 'CHAR' ) );
				break;
			case 'best_selling':
				$args['meta_key'] = 'total_sales';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'top_rated':
				$args['meta_key'] = '_wc_average_rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
		}
	}

	/** Normalize a supported WooCommerce preset. */
	public static function sanitize_woo_preset( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'none', 'newest', 'featured', 'sale', 'in_stock', 'best_selling', 'top_rated' ), true ) ? $value : 'none';
	}

	/** Normalize the interaction behavior. */
	public static function sanitize_mode( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'ajax', 'load_more', 'infinite' ), true ) ? $value : 'ajax';
	}

	/** Reject executable, third-party, and recursively querying templates. */
	public static function is_safe_template( $content ) {
		$denied = array(
			'core/html', 'core/shortcode', 'core/freeform', self::BLOCK,
			'cresco/loop', 'cresco/advanced-loop', 'cresco/relationship-loop',
			'cresco/acf-repeater', 'cresco/acf-flexible',
		);
		$walk = static function ( $blocks ) use ( &$walk, $denied ) {
			foreach ( $blocks as $block ) {
				$name = (string) ( $block['blockName'] ?? '' );
				if ( $name && ! str_starts_with( $name, 'core/' ) && ! str_starts_with( $name, 'cresco/' ) ) {
					return false;
				}
				if ( in_array( $name, $denied, true ) ) {
					return false;
				}
				if ( ! empty( $block['innerBlocks'] ) && ! $walk( $block['innerBlocks'] ) ) {
					return false;
				}
			}
			return true;
		};
		return '' !== trim( (string) $content ) && $walk( parse_blocks( (string) $content ) );
	}

	/** Base64url encode a JSON payload. */
	public static function encode_payload( $payload ) {
		return rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
	}

	/** Decode a base64url JSON payload. */
	public static function decode_payload( $encoded ) {
		$encoded = strtr( (string) $encoded, '-_', '+/' );
		$padding = strlen( $encoded ) % 4;
		if ( $padding ) {
			$encoded .= str_repeat( '=', 4 - $padding );
		}
		$decoded = base64_decode( $encoded, true );
		return false === $decoded ? null : json_decode( $decoded, true );
	}

	/** Sign a payload with the site's rotating nonce salt. */
	public static function sign_payload( $encoded ) {
		return hash_hmac( 'sha256', (string) $encoded, wp_salt( 'nonce' ) );
	}

	/** Verify a signed payload in constant time. */
	public static function verify_payload( $encoded, $signature ) {
		return $encoded && $signature && hash_equals( self::sign_payload( $encoded ), (string) $signature );
	}

	/** Build a stable safe query-instance identifier. */
	public static function sanitize_instance_id( $value, $encoded = '' ) {
		$value = sanitize_key( (string) $value );
		return $value ? substr( $value, 0, 32 ) : 'ccq_' . substr( hash( 'sha256', (string) $encoded ), 0, 10 );
	}

	/** Generate accessible fallback/interceptable pagination links. */
	private static function pagination_html( $current, $total, $instance ) {
		$current = max( 1, absint( $current ) );
		$total   = max( 0, absint( $total ) );
		if ( $total <= 1 ) {
			return '';
		}
		$start = max( 1, $current - 2 );
		$end   = min( $total, $current + 2 );
		$html  = '<nav class="cresco-filterable-loop__pagination" aria-label="' . esc_attr__( 'Filterable Loop pagination', 'cresco-canvas' ) . '"><ul>';
		if ( $current > 1 ) {
			$html .= self::page_link( $current - 1, __( 'Previous', 'cresco-canvas' ), $instance, false );
		}
		for ( $page = $start; $page <= $end; $page++ ) {
			$html .= self::page_link( $page, (string) $page, $instance, $page === $current );
		}
		if ( $current < $total ) {
			$html .= self::page_link( $current + 1, __( 'Next', 'cresco-canvas' ), $instance, false );
		}
		return $html . '</ul></nav>';
	}

	/** Render one pagination link. */
	private static function page_link( $page, $label, $instance, $current ) {
		$url = add_query_arg( $instance . '_page', $page );
		return '<li><a href="' . esc_url( $url ) . '" data-cresco-page="' . esc_attr( (string) $page ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}

	/** Append one taxonomy clause while preserving existing clauses. */
	private static function append_tax_clause( &$args, $clause ) {
		$existing = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();
		$relation = isset( $existing['relation'] ) ? $existing['relation'] : 'AND';
		unset( $existing['relation'] );
		$args['tax_query'] = array_merge( array( 'relation' => $relation ), array_values( $existing ), array( $clause ) );
	}

	/** Append one meta clause while preserving existing clauses. */
	private static function append_meta_clause( &$args, $clause ) {
		$existing = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$relation = isset( $existing['relation'] ) ? $existing['relation'] : 'AND';
		unset( $existing['relation'] );
		$args['meta_query'] = array_merge( array( 'relation' => $relation ), array_values( $existing ), array( $clause ) );
	}

	/** Apply a conservative anonymous-request rate limit. */
	private function rate_limit() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key     = 'cresco_iq_' . substr( hash_hmac( 'sha256', $address, wp_salt( 'auth' ) ), 0, 32 );
		$count   = (int) get_transient( $key );
		if ( $count >= 120 ) {
			return new WP_Error( 'cresco_query_rate_limited', __( 'Too many query requests. Please try again shortly.', 'cresco-canvas' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
