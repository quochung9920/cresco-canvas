<?php
/**
 * Widget Architecture v2 frontend renderer.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Dynamic\DynamicData;
use CrescoCanvas\Forms\FormBuilder;
use CrescoCanvas\Forms\FormCompletion;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteRendererV2 {
	public static function render_document( $session, $post_id = 0, $architecture = array() ) {
		$html = self::render_nodes( (array) ( $session['nodes'] ?? array() ), $post_id, $architecture );
		return '<div class="cresco-session-root cresco-website-builder-root" data-cresco-builder="website-core/v1" data-cresco-architecture="v2" data-cresco-document="' . esc_attr( (string) ( $session['documentId'] ?? '' ) ) . '">' . $html . '</div>';
	}

	private static function render_nodes( $nodes, $post_id, $architecture ) {
		$html = '';
		foreach ( (array) $nodes as $node ) $html .= self::render_node( $node, $post_id, $architecture );
		return $html;
	}

	private static function render_node( $node, $post_id, $architecture ) {
		if ( ! is_array( $node ) || ! empty( $node['meta']['hidden'] ) ) return '';
		$id = (string) ( $node['id'] ?? '' );
		$type = (string) ( $node['type'] ?? '' );
		$config = isset( $architecture['nodes'][ $id ] ) && is_array( $architecture['nodes'][ $id ] ) ? $architecture['nodes'][ $id ] : array();
		$node['props'] = DynamicBindingResolver::apply_props( (array) ( $node['props'] ?? array() ), $config['bindings'] ?? array(), $post_id );
		if ( 'container' === $type ) return self::render_container( $node, $post_id, $architecture );
		if ( 'columns' === $type ) return self::render_columns( $node, $post_id, $architecture );
		if ( 'tabs' === $type ) return self::render_tabs( $node, $post_id, $architecture, $config );
		if ( 'accordion' === $type ) return self::render_accordion( $node, $post_id, $architecture, $config );
		if ( 'loop-grid' === $type && ! empty( $config['slots']['templateComponentId'] ) ) return self::render_loop_template( $node, $post_id, $architecture, $config );
		if ( 'woo-products' === $type && ! empty( $config['slots']['templateComponentId'] ) ) return self::render_product_template( $node, $post_id, $architecture, $config );
		if ( 'form' === $type && ! empty( $config['form'] ) ) return self::render_form_v2( $node, $config['form'] );
		if ( 'woo-product-title' === $type ) return self::render_woo_product_title( $node, $post_id );
		if ( 'woo-product-price' === $type ) return self::render_woo_product_price( $node, $post_id );
		if ( 'woo-product-image' === $type ) return self::render_woo_product_image( $node, $post_id );
		if ( 'woo-add-to-cart' === $type ) return self::render_woo_add_to_cart( $node, $post_id );
		return self::legacy_fragment( $node, $post_id );
	}

	private static function render_container( $node, $post_id, $architecture ) {
		$props = (array) ( $node['props'] ?? array() );
		$tag = in_array( $props['tag'] ?? '', array( 'div', 'section', 'main', 'header', 'footer', 'aside', 'nav' ), true ) ? $props['tag'] : 'div';
		$aria = '' !== (string) ( $props['ariaLabel'] ?? '' ) ? ' aria-label="' . esc_attr( $props['ariaLabel'] ) . '"' : '';
		return '<' . $tag . self::attrs( $node ) . ' data-cresco-content-width="' . esc_attr( (string) ( $props['contentWidth'] ?? 'full' ) ) . '"' . $aria . '>' . self::render_nodes( $node['children'] ?? array(), $post_id, $architecture ) . '</' . $tag . '>';
	}

	private static function render_columns( $node, $post_id, $architecture ) {
		$props = (array) ( $node['props'] ?? array() );
		return '<div' . self::attrs( $node ) . ' data-columns="' . esc_attr( (string) ( $props['columns'] ?? 2 ) ) . '" data-collapse-at="' . esc_attr( (string) ( $props['collapseAt'] ?? 'tablet' ) ) . '">' . self::render_nodes( $node['children'] ?? array(), $post_id, $architecture ) . '</div>';
	}

	private static function render_tabs( $node, $post_id, $architecture, $config ) {
		$props = (array) ( $node['props'] ?? array() );
		$items = (array) ( $props['items'] ?? array() );
		$slots = (array) ( $config['slots']['items'] ?? array() );
		$widget_id = self::safe_id( $node['id'] ?? 'tabs' );
		$direction = in_array( $props['direction'] ?? '', array( 'top', 'bottom', 'start', 'end' ), true ) ? $props['direction'] : 'top';
		$justify = in_array( $props['justify'] ?? '', array( 'start', 'center', 'end', 'stretch' ), true ) ? $props['justify'] : 'start';
		$title_align = in_array( $props['titleAlign'] ?? '', array( 'start', 'center', 'end' ), true ) ? $props['titleAlign'] : 'center';
		$side_width = WebsiteBuilder::sanitize_css_value( $props['sideWidth'] ?? '240px' ) ?: '240px';
		$tab_gap = WebsiteBuilder::sanitize_css_value( $props['tabGap'] ?? '.25rem' ) ?: '.25rem';
		$tabs = ''; $panels = '';
		foreach ( $items as $index => $item ) {
			$tab_id = $widget_id . '-tab-' . $index; $panel_id = $widget_id . '-tabpanel-' . $index; $selected = 0 === $index;
			$title = (string) ( $item['title'] ?? ( 'Tab ' . ( $index + 1 ) ) );
			$content = wp_kses_post( (string) ( $item['content'] ?? '' ) );
			$component_id = absint( $slots[ (string) $index ] ?? $slots[ $index ] ?? 0 );
			if ( $component_id ) {
				$component = self::render_component( $component_id, $post_id, $architecture );
				if ( '' !== $component ) $content = $component;
			}
			$tabs .= '<button type="button" role="tab" id="' . esc_attr( $tab_id ) . '" aria-selected="' . ( $selected ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '" tabindex="' . ( $selected ? '0' : '-1' ) . '">' . esc_html( $title ) . '</button>';
			$panels .= '<div role="tabpanel" id="' . esc_attr( $panel_id ) . '" aria-labelledby="' . esc_attr( $tab_id ) . '"' . ( $selected ? '' : ' hidden' ) . '>' . $content . '</div>';
		}
		$style = '--cresco-tabs-side-width:' . $side_width . ';--cresco-tabs-gap:' . $tab_gap . ';';
		return '<div' . self::attrs( $node ) . ' data-cresco-tabs data-direction="' . esc_attr( $direction ) . '" data-justify="' . esc_attr( $justify ) . '" data-title-align="' . esc_attr( $title_align ) . '" data-horizontal-scroll="' . ( ! empty( $props['horizontalScroll'] ) ? '1' : '0' ) . '" style="' . esc_attr( $style ) . '"><div class="cresco-tabs__list" role="tablist" aria-orientation="' . ( in_array( $direction, array( 'start', 'end' ), true ) ? 'vertical' : 'horizontal' ) . '">' . $tabs . '</div><div class="cresco-tabs__panels">' . $panels . '</div></div>';
	}

	private static function render_accordion( $node, $post_id, $architecture, $config ) {
		$props = (array) ( $node['props'] ?? array() );
		$slots = (array) ( $config['slots']['items'] ?? array() );
		$widget_id = self::safe_id( $node['id'] ?? 'accordion' );
		$title_tag = in_array( $props['titleTag'] ?? '', array( 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $props['titleTag'] : 'h3';
		$icon_position = 'start' === ( $props['iconPosition'] ?? '' ) ? 'start' : 'end';
		$expand_icon = sanitize_key( (string) ( $props['expandIcon'] ?? 'plus-alt2' ) ) ?: 'plus-alt2';
		$collapse_icon = sanitize_key( (string) ( $props['collapseIcon'] ?? 'minus' ) ) ?: 'minus';
		$html = '';
		foreach ( (array) ( $props['items'] ?? array() ) as $index => $item ) {
			$button_id = $widget_id . '-trigger-' . $index; $panel_id = $widget_id . '-panel-' . $index; $open = ! empty( $item['open'] );
			$content = wp_kses_post( (string) ( $item['content'] ?? '' ) );
			$component_id = absint( $slots[ (string) $index ] ?? $slots[ $index ] ?? 0 );
			if ( $component_id ) {
				$component = self::render_component( $component_id, $post_id, $architecture );
				if ( '' !== $component ) $content = $component;
			}
			$icon = '<span class="cresco-accordion__icon" aria-hidden="true" data-expand-icon="' . esc_attr( $expand_icon ) . '" data-collapse-icon="' . esc_attr( $collapse_icon ) . '"><span class="dashicons dashicons-' . esc_attr( $open ? $collapse_icon : $expand_icon ) . '"></span></span>';
			$title = '<span class="cresco-accordion__title">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</span>';
			$trigger = 'start' === $icon_position ? $icon . $title : $title . $icon;
			$html .= '<div class="cresco-accordion__item"><' . $title_tag . ' class="cresco-accordion__heading"><button type="button" id="' . esc_attr( $button_id ) . '" class="cresco-accordion__trigger" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '">' . $trigger . '</button></' . $title_tag . '><div id="' . esc_attr( $panel_id ) . '" class="cresco-accordion__panel" role="region" aria-labelledby="' . esc_attr( $button_id ) . '"' . ( $open ? '' : ' hidden' ) . '>' . $content . '</div></div>';
		}
		return '<div' . self::attrs( $node ) . ' data-cresco-accordion data-allow-multi="' . ( ! empty( $props['allowMulti'] ) ? '1' : '0' ) . '" data-icon-position="' . esc_attr( $icon_position ) . '">' . $html . '</div>';
	}

	private static function render_loop_template( $node, $post_id, $architecture, $config ) {
		$props = (array) ( $node['props'] ?? array() );
		$query_config = array_merge( array(
			'postType' => $props['postType'] ?? 'post',
			'postsPerPage' => $props['perPage'] ?? 6,
			'order' => $props['order'] ?? 'DESC',
			'orderby' => $props['orderBy'] ?? 'date',
			'taxonomy' => $props['taxonomy'] ?? '',
			'term' => $props['term'] ?? '',
		), (array) ( $config['query'] ?? array() ) );
		$page_param = DynamicData::sanitize_page_param( $query_config['pageParam'] ?? 'cc_page' );
		$paged = ! empty( $query_config['pagination'] ) && isset( $_GET[ $page_param ] ) ? absint( wp_unslash( $_GET[ $page_param ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only pagination.
		$query_config['paged'] = max( 1, $paged );
		$query = new WP_Query( DynamicData::sanitize_query( $query_config ) );
		if ( ! $query->have_posts() ) {
			$message = sanitize_text_field( (string) ( $query_config['emptyMessage'] ?? '' ) );
			return '<div' . self::attrs( $node ) . '>' . ( $message ? '<p class="cresco-loop__empty">' . esc_html( $message ) . '</p>' : '' ) . '</div>';
		}
		$component_id = absint( $config['slots']['templateComponentId'] ?? 0 );
		$items = '';
		foreach ( $query->posts as $post ) {
			$items .= '<article class="cresco-loop-template-item" data-cresco-loop-post="' . esc_attr( (string) $post->ID ) . '">' . self::render_component( $component_id, $post->ID, $architecture ) . '</article>';
		}
		$columns = min( 6, max( 1, absint( $props['columns'] ?? 3 ) ) );
		$output = '<div' . self::attrs( $node ) . ' data-cresco-loop-template="component" style="--cresco-loop-columns:' . esc_attr( (string) $columns ) . '">' . $items . '</div>';
		if ( ! empty( $query_config['pagination'] ) && $query->max_num_pages > 1 ) {
			$links = paginate_links( array( 'base' => add_query_arg( $page_param, '%#%' ), 'format' => '', 'current' => max( 1, $paged ), 'total' => (int) $query->max_num_pages, 'type' => 'list', 'prev_text' => __( 'Previous', 'cresco-canvas' ), 'next_text' => __( 'Next', 'cresco-canvas' ) ) );
			if ( $links ) $output .= '<nav class="cresco-loop__pagination" aria-label="' . esc_attr__( 'Loop pagination', 'cresco-canvas' ) . '">' . wp_kses_post( $links ) . '</nav>';
		}
		wp_reset_postdata();
		return $output;
	}

	private static function render_product_template( $node, $post_id, $architecture, $config ) {
		if ( ! function_exists( 'wc_get_product' ) ) return self::legacy_fragment( $node, $post_id );
		$props = (array) ( $node['props'] ?? array() );
		$query_config = array_merge( array( 'limit' => $props['limit'] ?? 8, 'columns' => $props['columns'] ?? 4, 'orderby' => $props['orderby'] ?? 'date', 'order' => $props['order'] ?? 'DESC', 'category' => $props['category'] ?? '' ), (array) ( $config['query'] ?? array() ) );
		$orderby = sanitize_key( (string) ( $query_config['orderby'] ?? 'date' ) );
		$args = array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => min( 24, max( 1, absint( $query_config['limit'] ?? 8 ) ) ), 'orderby' => in_array( $orderby, array( 'date', 'title', 'rand', 'menu_order' ), true ) ? $orderby : 'date', 'order' => 'ASC' === ( $query_config['order'] ?? '' ) ? 'ASC' : 'DESC', 'ignore_sticky_posts' => true, 'no_found_rows' => true );
		if ( 'price' === $orderby ) { $args['meta_key'] = '_price'; $args['orderby'] = 'meta_value_num'; } // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded WooCommerce product sort.
		elseif ( 'popularity' === $orderby ) { $args['meta_key'] = 'total_sales'; $args['orderby'] = 'meta_value_num'; } // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded WooCommerce product sort.
		elseif ( 'rating' === $orderby ) { $args['meta_key'] = '_wc_average_rating'; $args['orderby'] = 'meta_value_num'; } // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded WooCommerce product sort.
		$category = sanitize_title( (string) ( $query_config['category'] ?? '' ) );
		if ( $category ) $args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( $category ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- User-configured bounded product query.
		$query = new WP_Query( $args );
		$component_id = absint( $config['slots']['templateComponentId'] ?? 0 );
		$items = '';
		foreach ( $query->posts as $post ) $items .= '<article class="cresco-product-template-item" data-cresco-product="' . esc_attr( (string) $post->ID ) . '">' . self::render_component( $component_id, $post->ID, $architecture ) . '</article>';
		$columns = min( 6, max( 1, absint( $query_config['columns'] ?? 4 ) ) );
		return '<div' . self::attrs( $node ) . ' data-cresco-product-template="component" style="--cresco-product-columns:' . esc_attr( (string) $columns ) . '">' . $items . '</div>';
	}

	private static function render_form_v2( $node, $form_config ) {
		$props = (array) ( $node['props'] ?? array() );
		$fields = array();
		$field_blocks = array();
		foreach ( (array) ( $props['fields'] ?? array() ) as $field ) {
			$name = FormBuilder::field_name( $field['name'] ?? '' );
			if ( ! $name ) continue;
			$attrs = array_filter( array( 'name' => $name, 'label' => (string) ( $field['label'] ?? '' ), 'type' => (string) ( $field['type'] ?? 'text' ), 'required' => ! empty( $field['required'] ), 'placeholder' => (string) ( $field['placeholder'] ?? '' ), 'options' => (string) ( $field['options'] ?? '' ), 'min' => $field['min'] ?? null, 'max' => $field['max'] ?? null ), static function ( $value ) { return null !== $value && '' !== $value; } );
			$condition = $form_config['conditions'][ $name ] ?? null;
			if ( is_array( $condition ) ) {
				$attrs['conditionField'] = $condition['field'] ?? '';
				$attrs['conditionOperator'] = $condition['operator'] ?? 'equals';
				$attrs['conditionValue'] = $condition['value'] ?? '';
			}
			$field_blocks[ $name ] = array( 'blockName' => FormBuilder::FIELD_BLOCK, 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		}
		$used = array();
		foreach ( (array) ( $form_config['steps'] ?? array() ) as $step ) {
			$inner = array();
			foreach ( (array) ( $step['fields'] ?? array() ) as $name ) if ( isset( $field_blocks[ $name ] ) ) { $inner[] = $field_blocks[ $name ]; $used[ $name ] = true; }
			if ( $inner ) $fields[] = array( 'blockName' => FormCompletion::STEP_BLOCK, 'attrs' => array( 'title' => $step['title'] ?? 'Step', 'nextLabel' => $step['nextLabel'] ?? 'Next', 'previousLabel' => $step['previousLabel'] ?? 'Previous' ), 'innerBlocks' => $inner, 'innerHTML' => '', 'innerContent' => array() );
		}
		foreach ( $field_blocks as $name => $block ) if ( ! isset( $used[ $name ] ) ) $fields[] = $block;
		foreach ( (array) ( $form_config['calculations'] ?? array() ) as $calc ) $fields[] = array( 'blockName' => FormCompletion::CALC_BLOCK, 'attrs' => $calc, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		if ( ! empty( $form_config['captcha']['enabled'] ) ) $fields[] = array( 'blockName' => FormCompletion::CAPTCHA_BLOCK, 'attrs' => array( 'provider' => $form_config['captcha']['provider'] ?? 'turnstile', 'siteKey' => $form_config['captcha']['siteKey'] ?? '', 'action' => $form_config['captcha']['action'] ?? 'cresco_form' ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		if ( ! $fields ) return '<div' . self::attrs( $node ) . '><p class="cresco-builder-placeholder">' . esc_html__( 'Add at least one form field.', 'cresco-canvas' ) . '</p></div>';
		$form_attrs = array( 'formId' => sanitize_key( (string) ( $props['formId'] ?? 'contact' ) ), 'submitLabel' => (string) ( $props['submitLabel'] ?? 'Submit' ), 'successMessage' => (string) ( $props['successMessage'] ?? 'Thank you.' ), 'emailTo' => sanitize_email( (string) ( $props['emailTo'] ?? '' ) ), 'storeSubmissions' => ! empty( $props['storeSubmissions'] ), 'redirectUrl' => esc_url_raw( (string) ( $props['redirectUrl'] ?? '' ) ), 'retentionDays' => min( 365, max( 1, absint( $props['retentionDays'] ?? 30 ) ) ), 'replyToField' => FormBuilder::field_name( $form_config['replyToField'] ?? '' ) );
		$block = array( 'blockName' => FormBuilder::FORM_BLOCK, 'attrs' => $form_attrs, 'innerBlocks' => $fields, 'innerHTML' => '', 'innerContent' => array() );
		return '<div' . self::attrs( $node ) . ' data-cresco-form-engine="v2">' . do_blocks( serialize_blocks( array( $block ) ) ) . '</div>';
	}

	private static function product( $post_id ) {
		return function_exists( 'wc_get_product' ) ? wc_get_product( absint( $post_id ) ) : false;
	}

	private static function render_woo_product_title( $node, $post_id ) {
		$product = self::product( $post_id );
		if ( ! $product ) return '<div' . self::attrs( $node ) . '></div>';
		$props = (array) ( $node['props'] ?? array() );
		$tag = in_array( $props['tag'] ?? '', array( 'h1', 'h2', 'h3', 'div' ), true ) ? $props['tag'] : 'h1';
		return '<' . $tag . self::attrs( $node ) . '>' . esc_html( $product->get_name() ) . '</' . $tag . '>';
	}

	private static function render_woo_product_price( $node, $post_id ) {
		$product = self::product( $post_id );
		return '<div' . self::attrs( $node ) . '>' . ( $product ? wp_kses_post( $product->get_price_html() ) : '' ) . '</div>';
	}

	private static function render_woo_product_image( $node, $post_id ) {
		$product = self::product( $post_id );
		if ( ! $product ) return '<figure' . self::attrs( $node ) . '></figure>';
		$props = (array) ( $node['props'] ?? array() );
		$size = in_array( $props['size'] ?? '', array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $props['size'] : 'large';
		return '<figure' . self::attrs( $node ) . '>' . wp_get_attachment_image( $product->get_image_id(), $size, false, array( 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</figure>';
	}

	private static function render_woo_add_to_cart( $node, $post_id ) {
		$product = self::product( $post_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) return '<div' . self::attrs( $node ) . '></div>';
		$props = (array) ( $node['props'] ?? array() );
		$url = add_query_arg( 'add-to-cart', $product->get_id(), wc_get_cart_url() );
		$label = $product->add_to_cart_text() ?: (string) ( $props['label'] ?? 'Add to cart' );
		return '<div' . self::attrs( $node ) . '><a class="button add_to_cart_button" href="' . esc_url( $url ) . '" data-product_id="' . esc_attr( (string) $product->get_id() ) . '">' . esc_html( $label ) . '</a></div>';
	}

	private static function render_component( $component_id, $post_id, $architecture ) {
		if ( ! $component_id || WebsiteBuilder::COMPONENT_TYPE !== get_post_type( $component_id ) ) return '';
		$raw = (string) get_post_meta( $component_id, WebsiteBuilder::COMPONENT_META, true );
		$node = json_decode( $raw, true );
		if ( ! is_array( $node ) ) return '';
		return '<div class="cresco-architecture-component" data-cresco-component="' . esc_attr( (string) $component_id ) . '">' . self::render_node( $node, $post_id, $architecture ) . '</div>';
	}

	private static function legacy_fragment( $node, $post_id ) {
		$node['children'] = array();
		$html = WebsiteRenderer::render_document( array( 'schema' => 'cresco-session/v1', 'version' => 1, 'documentId' => 'fragment', 'nodes' => array( $node ) ), $post_id );
		if ( preg_match( '/^<div class="cresco-session-root cresco-website-builder-root"[^>]*>(.*)<\/div>$/s', $html, $matches ) ) return $matches[1];
		return $html;
	}

	private static function attrs( $node ) {
		$type = sanitize_html_class( (string) ( $node['type'] ?? 'widget' ) );
		$id = (string) ( $node['id'] ?? '' );
		$classes = 'cresco-session-node cresco-builder-widget cresco-widget-' . $type;
		if ( ! empty( $node['meta']['locked'] ) ) $classes .= ' is-locked';
		return ' class="' . esc_attr( $classes ) . '" data-cresco-id="' . esc_attr( $id ) . '" data-cresco-widget="' . esc_attr( $type ) . '"';
	}

	private static function safe_id( $value ) { return preg_replace( '/[^a-z0-9_-]/i', '-', (string) $value ); }
	private function __construct() {}
}
