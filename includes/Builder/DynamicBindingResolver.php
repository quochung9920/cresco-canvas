<?php
/**
 * Resolve Widget Architecture v2 dynamic bindings against safe WordPress context.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Dynamic\DynamicData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DynamicBindingResolver {
	/** Apply safe dynamic bindings to one widget property map. */
	public static function apply_props( $props, $bindings, $post_id = 0 ) {
		$props = is_array( $props ) ? $props : array();
		foreach ( (array) $bindings as $property => $binding ) {
			if ( ! array_key_exists( $property, $props ) || ! is_array( $binding ) ) continue;
			$value = self::resolve( $binding, $post_id );
			if ( null !== $value ) $props[ $property ] = $value;
		}
		return $props;
	}

	/** Resolve one binding. Null means keep the static property value. */
	public static function resolve( $binding, $post_id = 0 ) {
		$binding = is_array( $binding ) ? $binding : array();
		$source = sanitize_key( (string) ( $binding['source'] ?? 'post' ) );
		$field = sanitize_key( (string) ( $binding['field'] ?? '' ) );
		$key = preg_replace( '/[^a-zA-Z0-9_.\-]/', '', (string) ( $binding['key'] ?? '' ) );
		$value = '';
		if ( in_array( $source, array( 'post', 'meta', 'acf', 'site' ), true ) ) {
			$value = DynamicData::resolve_value( array( 'source' => $source, 'field' => $field ?: 'title', 'key' => $key ), $post_id );
		} elseif ( 'user' === $source ) {
			$value = self::user_value( $field, $post_id );
		} elseif ( 'term' === $source ) {
			$value = self::term_value( $field, $key, $post_id );
		} elseif ( 'woo' === $source ) {
			$value = self::woo_value( $field, $post_id );
		} else {
			return null;
		}
		if ( is_array( $value ) || is_object( $value ) ) $value = '';
		$value = (string) $value;
		if ( '' === trim( $value ) ) $value = (string) ( $binding['fallback'] ?? '' );
		$value = self::format( $value, (string) ( $binding['format'] ?? 'text' ) );
		return (string) ( $binding['before'] ?? '' ) . $value . (string) ( $binding['after'] ?? '' );
	}

	private static function user_value( $field, $post_id ) {
		$user_id = $post_id ? absint( get_post_field( 'post_author', $post_id ) ) : get_current_user_id();
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) return '';
		switch ( $field ) {
			case 'description': return (string) $user->description;
			case 'user_url': return (string) $user->user_url;
			case 'user_email': return current_user_can( 'edit_users' ) ? (string) $user->user_email : '';
			case 'first_name': return (string) get_user_meta( $user_id, 'first_name', true );
			case 'last_name': return (string) get_user_meta( $user_id, 'last_name', true );
			case 'display_name':
			default: return (string) $user->display_name;
		}
	}

	private static function term_value( $field, $taxonomy, $post_id ) {
		$term = null;
		if ( $taxonomy && $post_id && taxonomy_exists( $taxonomy ) ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( is_array( $terms ) && $terms ) $term = reset( $terms );
		}
		if ( ! $term ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Term ) $term = $queried;
		}
		if ( ! $term instanceof \WP_Term ) return '';
		switch ( $field ) {
			case 'description': return (string) $term->description;
			case 'slug': return (string) $term->slug;
			case 'link':
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? '' : (string) $link;
			case 'name':
			default: return (string) $term->name;
		}
	}

	private static function woo_value( $field, $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) return '';
		$product = wc_get_product( $post_id ?: get_the_ID() );
		if ( ! $product ) return '';
		switch ( $field ) {
			case 'price': return (string) $product->get_price();
			case 'regular_price': return (string) $product->get_regular_price();
			case 'sale_price': return (string) $product->get_sale_price();
			case 'sku': return (string) $product->get_sku();
			case 'stock_status': return (string) $product->get_stock_status();
			case 'short_description': return wp_strip_all_tags( (string) $product->get_short_description() );
			case 'description': return wp_strip_all_tags( (string) $product->get_description() );
			case 'permalink': return (string) get_permalink( $product->get_id() );
			case 'image_url':
				$id = $product->get_image_id();
				return $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
			case 'rating': return (string) $product->get_average_rating();
			case 'name':
			default: return (string) $product->get_name();
		}
	}

	private static function format( $value, $format ) {
		$format = sanitize_key( (string) $format );
		if ( 'url' === $format ) return esc_url_raw( $value );
		if ( 'number' === $format ) return is_numeric( $value ) ? (string) $value : '0';
		if ( 'date' === $format ) {
			$timestamp = strtotime( $value );
			return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : sanitize_text_field( $value );
		}
		if ( 'richtext' === $format ) return wp_kses_post( $value );
		return sanitize_text_field( $value );
	}

	private function __construct() {}
}
