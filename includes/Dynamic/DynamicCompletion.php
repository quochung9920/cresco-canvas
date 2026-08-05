<?php
/** Final 0.8 Dynamic Data completion layer. @package CrescoCanvas */
namespace CrescoCanvas\Dynamic;

use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DynamicCompletion {
	const ROW_IMAGE = 'cresco/acf-row-image';
	const ROW_GALLERY = 'cresco/acf-row-gallery';
	const ROW_RELATIONSHIP = 'cresco/acf-row-relationship';

	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ), 35 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_blocks() {
		register_block_type( self::ROW_IMAGE, array(
			'api_version' => 3,
			'attributes' => array(
				'path' => array( 'type' => 'string', 'default' => '' ),
				'size' => array( 'type' => 'string', 'default' => 'large' ),
				'altPath' => array( 'type' => 'string', 'default' => '' ),
				'fallbackUrl' => array( 'type' => 'string', 'default' => '' ),
			),
			'render_callback' => array( $this, 'render_row_image' ),
			'supports' => array( 'html' => false, 'className' => true, 'spacing' => true ),
		) );
		register_block_type( self::ROW_GALLERY, array(
			'api_version' => 3,
			'attributes' => array(
				'path' => array( 'type' => 'string', 'default' => '' ),
				'size' => array( 'type' => 'string', 'default' => 'medium' ),
				'columns' => array( 'type' => 'number', 'default' => 3 ),
				'limit' => array( 'type' => 'number', 'default' => 12 ),
			),
			'render_callback' => array( $this, 'render_row_gallery' ),
			'supports' => array( 'html' => false, 'className' => true, 'spacing' => true ),
		) );
		register_block_type( self::ROW_RELATIONSHIP, array(
			'api_version' => 3,
			'attributes' => array(
				'path' => array( 'type' => 'string', 'default' => '' ),
				'limit' => array( 'type' => 'number', 'default' => 12 ),
				'columns' => array( 'type' => 'number', 'default' => 3 ),
				'emptyMessage' => array( 'type' => 'string', 'default' => '' ),
			),
			'render_callback' => array( $this, 'render_row_relationship' ),
			'supports' => array( 'html' => false, 'className' => true, 'spacing' => true ),
		) );
	}

	public function register_routes() {
		register_rest_route( 'cresco-canvas/v1', '/dynamic/facet-counts', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'facet_counts' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'cresco-canvas/v1', '/dynamic/diagnostics/(?P<id>\d+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'diagnostics' ),
			'permission_callback' => static function ( $request ) { return current_user_can( 'edit_post', absint( $request['id'] ) ); },
		) );
	}

	public function render_row_image( $attributes ) {
		$image = $this->normalize_image( $this->row_value( $attributes['path'] ?? '' ) );
		if ( ! $image['url'] ) { $image['url'] = esc_url_raw( (string) ( $attributes['fallbackUrl'] ?? '' ) ); }
		if ( ! $image['url'] ) { return ''; }
		$alt_value = $this->row_value( $attributes['altPath'] ?? '' );
		$alt = is_scalar( $alt_value ) ? sanitize_text_field( (string) $alt_value ) : $image['alt'];
		$size = sanitize_key( (string) ( $attributes['size'] ?? 'large' ) );
		$html = $image['id'] ? wp_get_attachment_image( $image['id'], $size, false, array( 'alt' => $alt, 'loading' => 'lazy' ) ) : '<img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
		return $html ? '<figure ' . get_block_wrapper_attributes( array( 'class' => 'cresco-acf-row-image' ) ) . '>' . $html . '</figure>' : '';
	}

	public function render_row_gallery( $attributes ) {
		$images = AdvancedDynamicData::normalize_gallery( $this->row_value( $attributes['path'] ?? '' ) );
		$limit = min( 24, max( 1, absint( $attributes['limit'] ?? 12 ) ) );
		$columns = min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) );
		$size = sanitize_key( (string) ( $attributes['size'] ?? 'medium' ) );
		$items = '';
		foreach ( array_slice( $images, 0, $limit ) as $image ) {
			$html = $image['id'] ? wp_get_attachment_image( $image['id'], $size, false, array( 'alt' => $image['alt'], 'loading' => 'lazy' ) ) : '<img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $image['alt'] ) . '" loading="lazy" decoding="async" />';
			if ( $html ) { $items .= '<figure class="cresco-acf-row-gallery__item">' . $html . '</figure>'; }
		}
		return $items ? '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-acf-row-gallery', 'style' => '--cresco-row-gallery-columns:' . $columns . ';' ) ) . '>' . $items . '</div>' : '';
	}

	public function render_row_relationship( $attributes, $content ) {
		$posts = AdvancedDynamicData::normalize_relationship( $this->row_value( $attributes['path'] ?? '' ) );
		$limit = min( 24, max( 1, absint( $attributes['limit'] ?? 12 ) ) );
		$columns = min( 6, max( 1, absint( $attributes['columns'] ?? 3 ) ) );
		if ( ! $posts ) {
			$message = sanitize_text_field( (string) ( $attributes['emptyMessage'] ?? '' ) );
			return $message ? '<p class="cresco-acf-row-relationship__empty">' . esc_html( $message ) . '</p>' : '';
		}
		$original = $GLOBALS['post'] ?? null;
		$items = '';
		foreach ( array_slice( $posts, 0, $limit ) as $related ) {
			$GLOBALS['post'] = $related; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $related );
			$items .= '<div class="cresco-acf-row-relationship__item">' . do_blocks( $content ) . '</div>';
		}
		wp_reset_postdata();
		if ( $original instanceof WP_Post ) { $GLOBALS['post'] = $original; } // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-acf-row-relationship', 'style' => '--cresco-row-relationship-columns:' . $columns . ';' ) ) . '>' . $items . '</div>';
	}

	public function facet_counts( WP_REST_Request $request ) {
		$params = (array) $request->get_json_params();
		$encoded = sanitize_text_field( (string) ( $params['payload'] ?? '' ) );
		$signature = sanitize_text_field( (string) ( $params['signature'] ?? '' ) );
		if ( ! InteractiveQuery::verify_payload( $encoded, $signature ) ) { return new WP_REST_Response( array( 'message' => 'Invalid signature.' ), 403 ); }
		$payload = InteractiveQuery::decode_payload( $encoded );
		if ( ! is_array( $payload ) ) { return new WP_REST_Response( array( 'message' => 'Invalid payload.' ), 400 ); }
		$post_type = sanitize_key( (string) ( $payload['query']['postType'] ?? 'post' ) );
		$facets = InteractiveQuery::sanitize_facet_taxonomies( $payload['facets'] ?? array(), $post_type );
		$filters = InteractiveQuery::sanitize_public_filters( $params['filters'] ?? array(), $facets );
		$counts = array();
		foreach ( array_slice( $facets, 0, 3 ) as $taxonomy ) {
			$counts[ $taxonomy ] = array();
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 50 ) );
			if ( is_wp_error( $terms ) ) { continue; }
			foreach ( $terms as $term ) {
				$input = (array) ( $payload['query'] ?? array() );
				$input['paged'] = 1;
				$args = AdvancedQuery::sanitize_query( $input );
				$args['fields'] = 'ids'; $args['posts_per_page'] = 1;
				$tax_query = array( 'relation' => 'AND' );
				foreach ( $filters['tax'] as $filter_taxonomy => $slugs ) {
					if ( $filter_taxonomy !== $taxonomy && $slugs ) { $tax_query[] = array( 'taxonomy' => $filter_taxonomy, 'field' => 'slug', 'terms' => $slugs ); }
				}
				$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => array( $term->slug ) );
				$args['tax_query'] = $tax_query;
				if ( ! empty( $filters['search'] ) ) { $args['s'] = $filters['search']; }
				$query = new WP_Query( $args );
				$counts[ $taxonomy ][ $term->slug ] = min( 999999, (int) $query->found_posts );
			}
		}
		return new WP_REST_Response( array( 'counts' => $counts ) );
	}

	public function diagnostics( WP_REST_Request $request ) {
		$post = get_post( absint( $request['id'] ) );
		if ( ! $post ) { return new WP_REST_Response( array( 'issues' => array() ), 404 ); }
		$issues = array(); $seen = array();
		$this->scan_blocks( parse_blocks( $post->post_content ), $seen, $issues );
		return new WP_REST_Response( array( 'issues' => $issues, 'count' => count( $issues ) ) );
	}

	private function scan_blocks( $blocks, &$seen, &$issues ) {
		foreach ( (array) $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' ); $attrs = (array) ( $block['attrs'] ?? array() );
			if ( 'cresco/filterable-loop' === $name ) {
				$id = InteractiveQuery::sanitize_instance_id( $attrs['instanceId'] ?? '' );
				if ( isset( $seen[ $id ] ) ) { $issues[] = array( 'code' => 'duplicate_instance_id', 'message' => 'Filterable Loops must use unique Instance IDs.', 'instanceId' => $id ); }
				$seen[ $id ] = true;
				if ( empty( $attrs['facetTaxonomies'] ) && empty( $attrs['searchFilter'] ) ) { $issues[] = array( 'code' => 'no_filters', 'message' => 'Filterable Loop has no enabled filters.', 'instanceId' => $id ); }
			}
			if ( in_array( $name, array( self::ROW_IMAGE, self::ROW_GALLERY, self::ROW_RELATIONSHIP ), true ) && empty( $attrs['path'] ) ) { $issues[] = array( 'code' => 'missing_row_path', 'message' => 'Row binding block is missing its field path.', 'block' => $name ); }
			if ( ! empty( $block['innerBlocks'] ) ) { $this->scan_blocks( $block['innerBlocks'], $seen, $issues ); }
		}
	}

	private function row_value( $path ) {
		$path = StructuredDynamicData::sanitize_path( $path );
		if ( ! $path ) { return null; }
		$reader = \Closure::bind( static function () { return self::$row_stack ? self::$row_stack[ count( self::$row_stack ) - 1 ] : array(); }, null, StructuredDynamicData::class );
		$row = $reader ? $reader() : array();
		return is_array( $row ) ? StructuredDynamicData::resolve_path( $row, $path ) : null;
	}

	private function normalize_image( $value ) {
		$images = AdvancedDynamicData::normalize_gallery( array( $value ) );
		return $images ? $images[0] : array( 'id' => 0, 'url' => '', 'alt' => '' );
	}
}
