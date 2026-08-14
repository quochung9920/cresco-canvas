<?php
/**
 * Safe frontend renderer and CSS compiler for Website Builder sessions.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteRenderer {
	/** Render a complete Website Builder document. */
	public static function render_document( $session, $post_id = 0 ) {
		$html = '';
		foreach ( (array) ( $session['nodes'] ?? array() ) as $node ) $html .= self::render_node( $node, $post_id );
		$html = '<div class="cresco-session-root cresco-website-builder-root" data-cresco-builder="website-core/v1" data-cresco-document="' . esc_attr( (string) ( $session['documentId'] ?? '' ) ) . '">' . $html . '</div>';

		/**
		 * Filter the rendered markup of a Website Builder document.
		 *
		 * Runs once per document, after every node is rendered. Integrations that
		 * post-process public output belong here rather than in an output buffer.
		 *
		 * @param string $html    Rendered document markup.
		 * @param array  $session Session that produced it.
		 * @param int    $post_id Page being rendered, or 0.
		 */
		return (string) apply_filters( 'cresco_canvas_rendered_document', $html, $session, $post_id );
	}

	/** Compile structured styles, states, responsive overrides, and scoped Custom CSS. */
	public static function compile_css( $session ) {
		$css = '';
		foreach ( (array) ( $session['nodes'] ?? array() ) as $node ) $css .= self::compile_node_css( $node );

		/**
		 * Filter the compiled stylesheet for a document.
		 *
		 * Appending here keeps additions inside the scoped selector namespace the
		 * compiler already established, which an enqueued stylesheet cannot rely on.
		 *
		 * @param string $css     Compiled CSS.
		 * @param array  $session Session that produced it.
		 */
		return (string) apply_filters( 'cresco_canvas_compiled_css', $css, $session );
	}

	private static function compile_node_css( $node ) {
		$id       = preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) ( $node['id'] ?? '' ) );
		$selector = '.cresco-website-builder-root [data-cresco-id="' . $id . '"]';
		$css      = '';
		$base     = array_merge( self::props_style( $node ), (array) ( $node['style'] ?? array() ) );
		$decl     = self::style_declarations( $base );
		if ( '' !== $decl ) $css .= $selector . '{' . $decl . '}';

		foreach ( array( 'hover', 'focus', 'active' ) as $state ) {
			$decl = self::style_declarations( (array) ( $node['states'][ $state ] ?? array() ) );
			if ( '' !== $decl ) $css .= $selector . ':' . $state . '{' . $decl . '}';
		}

		$settings    = GlobalStyles::get_settings();
		$breakpoints = (array) ( $settings['breakpoints'] ?? array() );
		foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			$decl = self::style_declarations( (array) ( $node['responsive'][ $device ] ?? array() ) );
			if ( '' === $decl ) continue;
			$max = absint( $breakpoints[ $device ] ?? 0 );
			if ( $max > 0 ) $css .= '@media (max-width:' . $max . 'px){' . $selector . '{' . $decl . '}}';
		}

		$custom = (array) ( $node['customCSS'] ?? array() );
		if ( ! empty( $custom['base'] ) ) $css .= self::scope_custom_css( $selector, $custom['base'] );
		foreach ( array( 'desktop', 'laptop', 'tablet', 'mobile' ) as $device ) {
			if ( empty( $custom[ $device ] ) ) continue;
			$max = absint( $breakpoints[ $device ] ?? 0 );
			if ( $max > 0 ) $css .= '@media (max-width:' . $max . 'px){' . self::scope_custom_css( $selector, $custom[ $device ] ) . '}';
		}

		foreach ( (array) ( $node['children'] ?? array() ) as $child ) $css .= self::compile_node_css( $child );
		return $css;
	}

	private static function props_style( $node ) {
		$type  = (string) ( $node['type'] ?? '' );
		$props = (array) ( $node['props'] ?? array() );
		if ( 'container' === $type ) {
			$layout = in_array( $props['layout'] ?? '', array( 'block', 'flex', 'grid' ), true ) ? $props['layout'] : 'flex';
			$style = array( 'display' => $layout, 'width' => '100%' );
			if ( 'boxed' === ( $props['contentWidth'] ?? '' ) ) {
				$style['maxWidth'] = '{layout.containerMax}';
				$style['marginLeft'] = 'auto';
				$style['marginRight'] = 'auto';
			}
			if ( 'flex' === $layout ) {
				$style['flexDirection'] = (string) ( $props['direction'] ?? 'column' );
				$style['flexWrap'] = (string) ( $props['wrap'] ?? 'nowrap' );
				$style['alignItems'] = (string) ( $props['align'] ?? 'stretch' );
				$style['justifyContent'] = (string) ( $props['justify'] ?? 'flex-start' );
			}
			if ( 'grid' === $layout ) {
				$template = WebsiteBuilder::sanitize_css_value( $props['gridTemplate'] ?? '' );
				$style['gridTemplateColumns'] = $template ?: 'repeat(' . min( 12, max( 1, absint( $props['columns'] ?? 2 ) ) ) . ',minmax(0,1fr))';
			}
			return $style;
		}
		if ( 'columns' === $type ) return array( 'display' => 'grid', 'gridTemplateColumns' => 'repeat(' . min( 12, max( 1, absint( $props['columns'] ?? 2 ) ) ) . ',minmax(0,1fr))', 'gap' => '{spacing.gridGap}' );
		if ( 'spacer' === $type ) return array( 'minHeight' => (string) ( $props['height'] ?? '48px' ) );
		return array();
	}

	private static function style_declarations( $styles ) {
		$output = '';
		$allowed = array_flip( WidgetCatalog::style_properties() );
		foreach ( (array) $styles as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) continue;
			$value = self::resolve_token( WebsiteBuilder::sanitize_css_value( $value ) );
			if ( '' === $value ) continue;
			$property = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', (string) $key ) );
			$output .= $property . ':' . $value . ';';
		}
		return $output;
	}

	private static function resolve_token( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\{([a-zA-Z0-9._-]+)\}$/', $value, $matches ) ) return (string) $value;
		$current = DesignTokens::catalog( GlobalStyles::get_settings() );
		foreach ( explode( '.', $matches[1] ) as $part ) {
			if ( ! is_array( $current ) || ! array_key_exists( $part, $current ) ) return '';
			$current = $current[ $part ];
		}
		return is_scalar( $current ) ? WebsiteBuilder::sanitize_css_value( (string) $current ) : '';
	}

	private static function scope_custom_css( $selector, $css ) {
		$clean = WebsiteBuilder::sanitize_custom_css( $css );
		if ( is_wp_error( $clean ) || '' === $clean ) return '';
		$output = '';
		$cursor = 0;
		while ( false !== ( $open = strpos( $clean, '{', $cursor ) ) ) {
			$raw_selector = trim( substr( $clean, $cursor, $open - $cursor ) );
			$close = strpos( $clean, '}', $open + 1 );
			if ( false === $close ) break;
			$scoped = array();
			foreach ( explode( ',', $raw_selector ) as $part ) $scoped[] = str_replace( '&', $selector, trim( $part ) );
			$output .= implode( ',', $scoped ) . '{' . substr( $clean, $open + 1, $close - $open - 1 ) . '}';
			$cursor = $close + 1;
		}
		return $output;
	}

	private static function render_node( $node, $context_post_id ) {
		if ( ! empty( $node['meta']['hidden'] ) ) return '';
		$type     = (string) ( $node['type'] ?? '' );
		$props    = (array) ( $node['props'] ?? array() );
		$children = '';
		foreach ( (array) ( $node['children'] ?? array() ) as $child ) $children .= self::render_node( $child, $context_post_id );
		$attrs = self::attrs( $node );

		switch ( $type ) {
			case 'container':
				$tag = in_array( $props['tag'] ?? '', array( 'div', 'section', 'main', 'header', 'footer', 'aside', 'nav' ), true ) ? $props['tag'] : 'div';
				$aria = '' !== (string) ( $props['ariaLabel'] ?? '' ) ? ' aria-label="' . esc_attr( $props['ariaLabel'] ) . '"' : '';
				return '<' . $tag . $attrs . ' data-cresco-content-width="' . esc_attr( (string) ( $props['contentWidth'] ?? 'full' ) ) . '"' . $aria . '>' . $children . '</' . $tag . '>';
			case 'columns':
				return '<div' . $attrs . ' data-columns="' . esc_attr( (string) ( $props['columns'] ?? 2 ) ) . '" data-collapse-at="' . esc_attr( (string) ( $props['collapseAt'] ?? 'tablet' ) ) . '">' . $children . '</div>';
			case 'heading':
				$level = min( 6, max( 1, absint( $props['level'] ?? 2 ) ) );
				$text = esc_html( (string) ( $props['text'] ?? '' ) );
				if ( ! empty( $props['url'] ) ) $text = '<a href="' . esc_url( $props['url'] ) . '">' . $text . '</a>';
				return '<h' . $level . $attrs . '>' . $text . '</h' . $level . '>';
			case 'text':
				$tag = in_array( $props['tag'] ?? '', array( 'p', 'div', 'span' ), true ) ? $props['tag'] : 'p';
				return '<' . $tag . $attrs . '>' . wp_kses_post( (string) ( $props['text'] ?? '' ) ) . '</' . $tag . '>';
			case 'button': return self::render_button( $attrs, $props );
			case 'image': return self::render_image( $attrs, $props );
			case 'list': return self::render_list( $attrs, $props );
			case 'divider': return '<hr' . $attrs . '>';
			case 'spacer': return '<div' . $attrs . ' aria-hidden="true"></div>';
			case 'icon': return self::render_icon( $attrs, $props );
			case 'icon-box': return self::render_icon_box( $attrs, $props );
			case 'video': return self::render_video( $attrs, $props );
			case 'gallery': return self::render_gallery( $attrs, $props );
			case 'accordion': return self::render_accordion( $attrs, $props );
			case 'tabs': return self::render_tabs( $attrs, $props );
			case 'testimonial': return self::render_testimonial( $attrs, $props );
			case 'counter': return self::render_counter( $attrs, $props );
			case 'progress': return self::render_progress( $attrs, $props );
			case 'social-icons': return self::render_social( $attrs, $props );
			case 'site-logo': return self::render_site_logo( $attrs, $props );
			case 'site-title': return self::render_site_title( $attrs, $props );
			case 'nav-menu': return self::render_nav_menu( $attrs, $props );
			case 'breadcrumbs': return self::render_breadcrumbs( $attrs, $props, $context_post_id );
			case 'post-title': return self::render_post_title( $attrs, $props, $context_post_id );
			case 'post-excerpt': return self::render_post_excerpt( $attrs, $props, $context_post_id );
			case 'featured-image': return self::render_featured_image( $attrs, $props, $context_post_id );
			case 'post-content': return self::render_post_content( $attrs, $context_post_id );
			case 'dynamic-field': return self::render_dynamic_field( $attrs, $props, $context_post_id );
			case 'loop-grid': return self::render_loop_grid( $attrs, $props );
			case 'form': return self::render_form( $attrs, $props );
			case 'woo-products': return self::render_woo_products( $attrs, $props );
			case 'woo-product-title': return self::render_woo_product_title( $attrs, $props );
			case 'woo-product-price': return self::render_woo_product_price( $attrs );
			case 'woo-product-image': return self::render_woo_product_image( $attrs, $props );
			case 'woo-add-to-cart': return self::render_woo_add_to_cart( $attrs, $props );
		}
		return '';
	}

	private static function attrs( $node ) {
		$type = sanitize_html_class( (string) ( $node['type'] ?? 'widget' ) );
		$id   = (string) ( $node['id'] ?? '' );
		$classes = 'cresco-session-node cresco-builder-widget cresco-widget-' . $type;
		if ( ! empty( $node['meta']['locked'] ) ) $classes .= ' is-locked';
		return ' class="' . esc_attr( $classes ) . '" data-cresco-id="' . esc_attr( $id ) . '" data-cresco-widget="' . esc_attr( $type ) . '"';
	}

	private static function dashicon( $slug, $label = '' ) {
		$slug = sanitize_key( (string) $slug ) ?: 'star-filled';
		$aria = '' === $label ? ' aria-hidden="true"' : ' role="img" aria-label="' . esc_attr( $label ) . '"';
		return '<span class="dashicons dashicons-' . esc_attr( $slug ) . '"' . $aria . '></span>';
	}

	private static function render_button( $attrs, $props ) {
		$target = '_blank' === ( $props['target'] ?? '' ) ? '_blank' : '_self';
		$rels = array_filter( preg_split( '/\s+/', (string) ( $props['rel'] ?? '' ) ) );
		if ( '_blank' === $target ) $rels = array_values( array_unique( array_merge( $rels, array( 'noopener', 'noreferrer' ) ) ) );
		$icon = '' !== (string) ( $props['icon'] ?? '' ) ? self::dashicon( $props['icon'] ) : '';
		$text = '<span data-cresco-part="text">' . esc_html( (string) ( $props['text'] ?? 'Button' ) ) . '</span>';
		$content = 'after' === ( $props['iconPosition'] ?? '' ) ? $text . $icon : $icon . $text;
		return '<a' . $attrs . ' href="' . esc_url( (string) ( $props['url'] ?? '#' ) ) . '" target="' . esc_attr( $target ) . '"' . ( $rels ? ' rel="' . esc_attr( implode( ' ', $rels ) ) . '"' : '' ) . '>' . $content . '</a>';
	}

	private static function render_image( $attrs, $props ) {
		$url = esc_url( (string) ( $props['url'] ?? '' ) );
		$style = '';
		$fit = (string) ( $props['objectFit'] ?? '' );
		if ( in_array( $fit, array( 'cover', 'contain', 'fill', 'none', 'scale-down' ), true ) ) $style .= 'object-fit:' . $fit . ';';
		$ratio = WebsiteBuilder::sanitize_css_value( $props['aspectRatio'] ?? '' );
		if ( '' !== $ratio ) $style .= 'aspect-ratio:' . $ratio . ';';
		$media = $url ? '<img data-cresco-part="media" src="' . $url . '" alt="' . esc_attr( (string) ( $props['alt'] ?? '' ) ) . '" loading="lazy" decoding="async"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>' : '<span class="cresco-widget-image__placeholder" data-cresco-part="media" aria-hidden="true"></span>';
		if ( $url && ! empty( $props['link'] ) ) $media = '<a href="' . esc_url( $props['link'] ) . '">' . $media . '</a>';
		$caption = (string) ( $props['caption'] ?? '' );
		return '<figure' . $attrs . '>' . $media . ( '' !== $caption ? '<figcaption data-cresco-part="caption">' . esc_html( $caption ) . '</figcaption>' : '' ) . '</figure>';
	}

	private static function render_list( $attrs, $props ) {
		$tag = ! empty( $props['ordered'] ) ? 'ol' : 'ul';
		$items = '';
		foreach ( (array) ( $props['items'] ?? array() ) as $item ) $items .= '<li data-cresco-part="item">' . esc_html( (string) $item ) . '</li>';
		return '<' . $tag . $attrs . '>' . $items . '</' . $tag . '>';
	}

	private static function render_icon( $attrs, $props ) {
		$icon = self::dashicon( $props['icon'] ?? '', $props['label'] ?? '' );
		if ( ! empty( $props['url'] ) ) $icon = '<a href="' . esc_url( $props['url'] ) . '">' . $icon . '</a>';
		return '<div' . $attrs . '>' . $icon . '</div>';
	}

	private static function render_icon_box( $attrs, $props ) {
		$position = in_array( $props['position'] ?? '', array( 'top', 'start', 'end' ), true ) ? $props['position'] : 'start';
		$align = in_array( $props['contentAlign'] ?? '', array( 'start', 'center', 'end', 'justify' ), true ) ? $props['contentAlign'] : 'start';
		$gap = WebsiteBuilder::sanitize_css_value( $props['iconGap'] ?? '' );
		$style = '' !== $gap ? ' style="--cresco-icon-box-gap:' . esc_attr( $gap ) . '"' : '';
		$icon = '<div class="cresco-icon-box__icon">' . self::dashicon( $props['icon'] ?? '' ) . '</div>';
		$body = '<div class="cresco-icon-box__body"><h3>' . esc_html( (string) ( $props['title'] ?? '' ) ) . '</h3><p>' . nl2br( esc_html( (string) ( $props['text'] ?? '' ) ) ) . '</p></div>';
		$content = 'end' === $position ? $body . $icon : $icon . $body;
		if ( ! empty( $props['url'] ) ) {
			$layout = '<a class="cresco-icon-box__layout cresco-icon-box__link" href="' . esc_url( $props['url'] ) . '">' . $content . '</a>';
		} else {
			$layout = '<div class="cresco-icon-box__layout">' . $content . '</div>';
		}
		return '<div' . $attrs . ' data-icon-position="' . esc_attr( $position ) . '" data-content-align="' . esc_attr( $align ) . '"' . $style . '>' . $layout . '</div>';
	}

	private static function render_video( $attrs, $props ) {
		$url = esc_url_raw( (string) ( $props['url'] ?? '' ) );
		if ( '' === $url ) return '<div' . $attrs . '><p class="cresco-builder-placeholder">' . esc_html__( 'Add a video URL.', 'cresco-canvas' ) . '</p></div>';
		$html = wp_oembed_get( $url, array( 'width' => 1280 ) );
		if ( ! $html ) return '<div' . $attrs . '><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></div>';
		$allowed = array( 'iframe' => array( 'src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'allow' => true, 'allowfullscreen' => true, 'title' => true, 'loading' => true, 'referrerpolicy' => true ), 'video' => array( 'src' => true, 'controls' => true, 'preload' => true, 'poster' => true ), 'source' => array( 'src' => true, 'type' => true ) );
		$caption = (string) ( $props['caption'] ?? '' );
		return '<figure' . $attrs . '><div class="cresco-video__embed">' . wp_kses( $html, $allowed ) . '</div>' . ( '' !== $caption ? '<figcaption>' . esc_html( $caption ) . '</figcaption>' : '' ) . '</figure>';
	}

	private static function render_gallery( $attrs, $props ) {
		$columns = min( 8, max( 1, absint( $props['columns'] ?? 3 ) ) );
		$gap = WebsiteBuilder::sanitize_css_value( $props['gap'] ?? '' );
		$ratio = WebsiteBuilder::sanitize_css_value( $props['aspectRatio'] ?? '' );
		$fit = in_array( $props['objectFit'] ?? '', array( 'cover', 'contain', 'fill', 'none', 'scale-down' ), true ) ? $props['objectFit'] : 'cover';
		$caption_align = in_array( $props['captionAlign'] ?? '', array( 'start', 'center', 'end', 'justify' ), true ) ? $props['captionAlign'] : 'center';
		$style = '--cresco-gallery-columns:' . $columns . ';--cresco-gallery-fit:' . $fit . ';--cresco-gallery-caption-align:' . $caption_align . ';';
		if ( '' !== $gap ) $style .= '--cresco-gallery-gap:' . $gap . ';';
		if ( '' !== $ratio ) $style .= '--cresco-gallery-ratio:' . $ratio . ';';
		$items = '';
		foreach ( (array) ( $props['images'] ?? array() ) as $index => $image ) {
			$url = esc_url( (string) ( $image['url'] ?? '' ) );
			if ( '' === $url ) continue;
			$img = '<img src="' . $url . '" alt="' . esc_attr( (string) ( $image['alt'] ?? '' ) ) . '" loading="lazy" decoding="async">';
			if ( ! empty( $props['lightbox'] ) ) $img = '<a href="' . $url . '" data-cresco-lightbox="gallery" data-cresco-lightbox-index="' . esc_attr( (string) $index ) . '">' . $img . '</a>';
			$caption = ! empty( $props['showCaptions'] ) && ! empty( $image['caption'] ) ? '<figcaption>' . esc_html( $image['caption'] ) . '</figcaption>' : '';
			$items .= '<figure class="cresco-gallery__item">' . $img . $caption . '</figure>';
		}
		return '<div' . $attrs . ' style="' . esc_attr( $style ) . '">' . $items . '</div>';
	}

	private static function render_accordion( $attrs, $props ) {
		$items = '';
		$widget_id = preg_replace( '/[^a-z0-9_-]/i', '-', self::attr_value( $attrs, 'data-cresco-id' ) );
		$title_tag = in_array( $props['titleTag'] ?? '', array( 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $props['titleTag'] : 'h3';
		$icon_position = 'start' === ( $props['iconPosition'] ?? '' ) ? 'start' : 'end';
		$expand_icon = sanitize_key( (string) ( $props['expandIcon'] ?? 'plus-alt2' ) ) ?: 'plus-alt2';
		$collapse_icon = sanitize_key( (string) ( $props['collapseIcon'] ?? 'minus' ) ) ?: 'minus';
		foreach ( (array) ( $props['items'] ?? array() ) as $index => $item ) {
			$button_id = $widget_id . '-trigger-' . $index;
			$panel_id  = $widget_id . '-panel-' . $index;
			$open = ! empty( $item['open'] );
			$icon = '<span class="cresco-accordion__icon" aria-hidden="true" data-expand-icon="' . esc_attr( $expand_icon ) . '" data-collapse-icon="' . esc_attr( $collapse_icon ) . '">' . self::dashicon( $open ? $collapse_icon : $expand_icon ) . '</span>';
			$title = '<span class="cresco-accordion__title">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</span>';
			$content = 'start' === $icon_position ? $icon . $title : $title . $icon;
			$items .= '<div class="cresco-accordion__item"><' . $title_tag . ' class="cresco-accordion__heading"><button type="button" id="' . esc_attr( $button_id ) . '" class="cresco-accordion__trigger" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '">' . $content . '</button></' . $title_tag . '><div id="' . esc_attr( $panel_id ) . '" class="cresco-accordion__panel" role="region" aria-labelledby="' . esc_attr( $button_id ) . '"' . ( $open ? '' : ' hidden' ) . '>' . wp_kses_post( (string) ( $item['content'] ?? '' ) ) . '</div></div>';
		}
		return '<div' . $attrs . ' data-cresco-accordion data-allow-multi="' . ( ! empty( $props['allowMulti'] ) ? '1' : '0' ) . '" data-icon-position="' . esc_attr( $icon_position ) . '">' . $items . '</div>';
	}

	private static function render_tabs( $attrs, $props ) {
		$items = (array) ( $props['items'] ?? array() );
		$widget_id = preg_replace( '/[^a-z0-9_-]/i', '-', self::attr_value( $attrs, 'data-cresco-id' ) );
		$direction = in_array( $props['direction'] ?? '', array( 'top', 'bottom', 'start', 'end' ), true ) ? $props['direction'] : 'top';
		$justify = in_array( $props['justify'] ?? '', array( 'start', 'center', 'end', 'stretch' ), true ) ? $props['justify'] : 'start';
		$title_align = in_array( $props['titleAlign'] ?? '', array( 'start', 'center', 'end' ), true ) ? $props['titleAlign'] : 'center';
		$side_width = WebsiteBuilder::sanitize_css_value( $props['sideWidth'] ?? '240px' ) ?: '240px';
		$tab_gap = WebsiteBuilder::sanitize_css_value( $props['tabGap'] ?? '.25rem' ) ?: '.25rem';
		$scroll = ! empty( $props['horizontalScroll'] ) ? '1' : '0';
		$tabs = ''; $panels = '';
		foreach ( $items as $index => $item ) {
			$tab_id = $widget_id . '-tab-' . $index; $panel_id = $widget_id . '-tabpanel-' . $index; $selected = 0 === $index;
			$tabs .= '<button type="button" role="tab" id="' . esc_attr( $tab_id ) . '" aria-selected="' . ( $selected ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '" tabindex="' . ( $selected ? '0' : '-1' ) . '">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</button>';
			$panels .= '<div role="tabpanel" id="' . esc_attr( $panel_id ) . '" aria-labelledby="' . esc_attr( $tab_id ) . '"' . ( $selected ? '' : ' hidden' ) . '>' . wp_kses_post( (string) ( $item['content'] ?? '' ) ) . '</div>';
		}
		$style = '--cresco-tabs-side-width:' . $side_width . ';--cresco-tabs-gap:' . $tab_gap . ';';
		return '<div' . $attrs . ' data-cresco-tabs data-direction="' . esc_attr( $direction ) . '" data-justify="' . esc_attr( $justify ) . '" data-title-align="' . esc_attr( $title_align ) . '" data-horizontal-scroll="' . esc_attr( $scroll ) . '" style="' . esc_attr( $style ) . '"><div class="cresco-tabs__list" role="tablist" aria-orientation="' . ( in_array( $direction, array( 'start', 'end' ), true ) ? 'vertical' : 'horizontal' ) . '">' . $tabs . '</div><div class="cresco-tabs__panels">' . $panels . '</div></div>';
	}

	private static function render_testimonial( $attrs, $props ) {
		$avatar = ! empty( $props['avatar'] ) ? '<img src="' . esc_url( $props['avatar'] ) . '" alt="" loading="lazy" decoding="async">' : '';
		return '<figure' . $attrs . '><blockquote>' . nl2br( esc_html( (string) ( $props['quote'] ?? '' ) ) ) . '</blockquote><figcaption>' . $avatar . '<span><strong>' . esc_html( (string) ( $props['name'] ?? '' ) ) . '</strong>' . ( ! empty( $props['role'] ) ? '<small>' . esc_html( $props['role'] ) . '</small>' : '' ) . '</span></figcaption></figure>';
	}

	private static function render_counter( $attrs, $props ) {
		$value = (float) ( $props['value'] ?? 0 );
		return '<div' . $attrs . ' data-cresco-counter data-value="' . esc_attr( (string) $value ) . '" data-duration="' . esc_attr( (string) absint( $props['duration'] ?? 1200 ) ) . '"><span class="cresco-counter__prefix">' . esc_html( (string) ( $props['prefix'] ?? '' ) ) . '</span><span class="cresco-counter__value">' . esc_html( (string) $value ) . '</span><span class="cresco-counter__suffix">' . esc_html( (string) ( $props['suffix'] ?? '' ) ) . '</span></div>';
	}

	private static function render_progress( $attrs, $props ) {
		$value = min( 100, max( 0, absint( $props['value'] ?? 0 ) ) );
		return '<div' . $attrs . '><div class="cresco-progress__heading"><span>' . esc_html( (string) ( $props['label'] ?? '' ) ) . '</span>' . ( ! empty( $props['showValue'] ) ? '<span>' . esc_html( $value . '%' ) . '</span>' : '' ) . '</div><div class="cresco-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $value ) . '"><span class="cresco-progress__bar" style="width:' . esc_attr( $value . '%' ) . '"></span></div></div>';
	}

	private static function render_social( $attrs, $props ) {
		$items = '';
		foreach ( (array) ( $props['items'] ?? array() ) as $item ) {
			$items .= '<a href="' . esc_url( (string) ( $item['url'] ?? '#' ) ) . '" aria-label="' . esc_attr( (string) ( $item['label'] ?? 'Link' ) ) . '" rel="noopener noreferrer">' . self::dashicon( $item['icon'] ?? '', '' ) . '<span class="screen-reader-text">' . esc_html( (string) ( $item['label'] ?? 'Link' ) ) . '</span></a>';
		}
		return '<div' . $attrs . '>' . $items . '</div>';
	}

	private static function render_site_logo( $attrs, $props ) {
		$logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( ! $logo_id ) return '<div' . $attrs . '><span class="cresco-builder-placeholder">' . esc_html( get_bloginfo( 'name' ) ) . '</span></div>';
		$width = WebsiteBuilder::sanitize_css_value( $props['width'] ?? '160px' );
		$image = wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'cresco-site-logo__image', 'style' => $width ? 'width:' . $width . ';height:auto' : '' ) );
		if ( ! empty( $props['linkHome'] ) ) $image = '<a href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . $image . '</a>';
		return '<div' . $attrs . '>' . $image . '</div>';
	}

	private static function render_site_title( $attrs, $props ) {
		$tag = in_array( $props['tag'] ?? '', array( 'div', 'p', 'h1', 'h2', 'h3' ), true ) ? $props['tag'] : 'div';
		$title = esc_html( get_bloginfo( 'name' ) );
		if ( ! empty( $props['linkHome'] ) ) $title = '<a href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . $title . '</a>';
		return '<' . $tag . $attrs . '>' . $title . '</' . $tag . '>';
	}

	private static function render_nav_menu( $attrs, $props ) {
		$menu_id = absint( $props['menu'] ?? 0 );
		if ( ! $menu_id ) return '<nav' . $attrs . ' aria-label="' . esc_attr__( 'Navigation', 'cresco-canvas' ) . '"><span class="cresco-builder-placeholder">' . esc_html__( 'Choose a WordPress menu.', 'cresco-canvas' ) . '</span></nav>';
		$menu = wp_nav_menu( array( 'menu' => $menu_id, 'container' => false, 'echo' => false, 'fallback_cb' => false, 'depth' => min( 5, max( 1, absint( $props['depth'] ?? 2 ) ) ), 'menu_class' => 'cresco-nav-menu__list' ) );
		return '<nav' . $attrs . ' aria-label="' . esc_attr__( 'Navigation', 'cresco-canvas' ) . '" data-orientation="' . esc_attr( (string) ( $props['orientation'] ?? 'horizontal' ) ) . '">' . ( $menu ?: '' ) . '</nav>';
	}

	private static function render_breadcrumbs( $attrs, $props, $post_id ) {
		$separator = esc_html( (string) ( $props['separator'] ?? '/' ) );
		$parts = array();
		if ( ! empty( $props['showHome'] ) ) $parts[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'cresco-canvas' ) . '</a>';
		$ancestors = array_reverse( get_post_ancestors( $post_id ) );
		foreach ( $ancestors as $ancestor_id ) $parts[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
		if ( $post_id ) $parts[] = '<span aria-current="page">' . esc_html( get_the_title( $post_id ) ) . '</span>';
		return '<nav' . $attrs . ' aria-label="' . esc_attr__( 'Breadcrumbs', 'cresco-canvas' ) . '"><ol><li>' . implode( '</li><li class="cresco-breadcrumbs__separator" aria-hidden="true">' . $separator . '</li><li>', $parts ) . '</li></ol></nav>';
	}

	private static function render_post_title( $attrs, $props, $post_id ) {
		$tag = in_array( $props['tag'] ?? '', array( 'h1', 'h2', 'h3', 'div' ), true ) ? $props['tag'] : 'h1';
		$title = esc_html( get_the_title( $post_id ) );
		if ( ! empty( $props['link'] ) ) $title = '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . $title . '</a>';
		return '<' . $tag . $attrs . '>' . $title . '</' . $tag . '>';
	}

	private static function render_post_excerpt( $attrs, $props, $post_id ) {
		$post = get_post( $post_id );
		$excerpt = $post ? get_the_excerpt( $post ) : '';
		return '<div' . $attrs . '>' . esc_html( wp_trim_words( $excerpt, min( 100, max( 5, absint( $props['words'] ?? 30 ) ) ) ) ) . '</div>';
	}

	private static function render_featured_image( $attrs, $props, $post_id ) {
		$size = in_array( $props['size'] ?? '', array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $props['size'] : 'large';
		$image = get_the_post_thumbnail( $post_id, $size, array( 'loading' => 'lazy', 'decoding' => 'async' ) );
		if ( ! $image ) return '<div' . $attrs . '></div>';
		if ( ! empty( $props['link'] ) ) $image = '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . $image . '</a>';
		return '<figure' . $attrs . '>' . $image . '</figure>';
	}

	private static function render_post_content( $attrs, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) return '<div' . $attrs . '></div>';
		$content = do_blocks( (string) $post->post_content );
		$content = wpautop( $content );
		return '<div' . $attrs . '>' . wp_kses_post( $content ) . '</div>';
	}

	private static function render_dynamic_field( $attrs, $props, $post_id ) {
		$source = (string) ( $props['source'] ?? 'meta' );
		$key = (string) ( $props['key'] ?? '' );
		$value = null;
		if ( '' !== $key && '_' !== substr( $key, 0, 1 ) ) {
			if ( 'acf' === $source && function_exists( 'get_field' ) ) $value = get_field( $key, $post_id, false );
			elseif ( 'meta' === $source ) $value = get_post_meta( $post_id, $key, true );
			elseif ( 'site' === $source && in_array( $key, array( 'name', 'description', 'url', 'language' ), true ) ) $value = 'language' === $key ? get_bloginfo( 'language' ) : get_bloginfo( $key );
			elseif ( 'user' === $source ) {
				$post = get_post( $post_id ); $user = $post ? get_userdata( (int) $post->post_author ) : false;
				if ( $user && in_array( $key, array( 'display_name', 'description', 'user_url' ), true ) ) $value = $user->{$key};
			}
		}
		if ( is_array( $value ) || is_object( $value ) || null === $value || '' === (string) $value ) $value = (string) ( $props['fallback'] ?? '' );
		$format = (string) ( $props['format'] ?? 'text' );
		if ( 'richtext' === $format ) $rendered = wp_kses_post( (string) $value );
		elseif ( 'url' === $format ) $rendered = '<a href="' . esc_url( (string) $value ) . '">' . esc_html( (string) $value ) . '</a>';
		elseif ( 'number' === $format ) $rendered = esc_html( is_numeric( $value ) ? (string) $value : '0' );
		else $rendered = esc_html( (string) $value );
		return '<div' . $attrs . '>' . esc_html( (string) ( $props['before'] ?? '' ) ) . $rendered . esc_html( (string) ( $props['after'] ?? '' ) ) . '</div>';
	}

	private static function render_loop_grid( $attrs, $props ) {
		$post_type = sanitize_key( (string) ( $props['postType'] ?? 'post' ) );
		$obj = get_post_type_object( $post_type );
		if ( ! $obj || empty( $obj->public ) ) $post_type = 'post';
		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => min( 24, max( 1, absint( $props['perPage'] ?? 6 ) ) ),
			'orderby'             => in_array( $props['orderBy'] ?? '', array( 'date', 'title', 'menu_order', 'modified', 'rand' ), true ) ? $props['orderBy'] : 'date',
			'order'               => 'ASC' === ( $props['order'] ?? '' ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$taxonomy = sanitize_key( (string) ( $props['taxonomy'] ?? '' ) );
		$term = sanitize_title( (string) ( $props['term'] ?? '' ) );
		$tax_obj = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		if ( $taxonomy && $term && $tax_obj && ! empty( $tax_obj->public ) && in_array( $post_type, (array) $tax_obj->object_type, true ) ) {
			$args['tax_query'] = array( array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => array( $term ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- User-configured bounded builder query.
		}
		$query = new WP_Query( $args );
		$cards = '';
		foreach ( $query->posts as $post ) {
			$image = ! empty( $props['showImage'] ) ? get_the_post_thumbnail( $post->ID, 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async' ) ) : '';
			$date = ! empty( $props['showDate'] ) ? '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>' : '';
			$excerpt = ! empty( $props['showExcerpt'] ) ? '<p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 26 ) ) . '</p>' : '';
			$button = '' !== (string) ( $props['buttonLabel'] ?? '' ) ? '<a class="cresco-loop-card__button" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $props['buttonLabel'] ) . '</a>' : '';
			$cards .= '<article class="cresco-loop-card">' . ( $image ? '<a class="cresco-loop-card__image" href="' . esc_url( get_permalink( $post ) ) . '">' . $image . '</a>' : '' ) . '<div class="cresco-loop-card__body">' . $date . '<h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3>' . $excerpt . $button . '</div></article>';
		}
		return '<div' . $attrs . ' style="--cresco-loop-columns:' . esc_attr( (string) min( 6, max( 1, absint( $props['columns'] ?? 3 ) ) ) ) . '">' . $cards . '</div>';
	}

	private static function render_form( $attrs, $props ) {
		$fields = array();
		foreach ( (array) ( $props['fields'] ?? array() ) as $field ) {
			$field_attrs = array_filter( array(
				'name' => (string) ( $field['name'] ?? '' ), 'label' => (string) ( $field['label'] ?? '' ), 'type' => (string) ( $field['type'] ?? 'text' ),
				'required' => ! empty( $field['required'] ), 'placeholder' => (string) ( $field['placeholder'] ?? '' ), 'options' => (string) ( $field['options'] ?? '' ),
				'min' => $field['min'] ?? null, 'max' => $field['max'] ?? null,
			), static function ( $value ) { return null !== $value && '' !== $value; } );
			$fields[] = array( 'blockName' => 'cresco/form-field', 'attrs' => $field_attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		}
		if ( empty( $fields ) ) return '<div' . $attrs . '><p class="cresco-builder-placeholder">' . esc_html__( 'Add at least one form field.', 'cresco-canvas' ) . '</p></div>';
		$form_attrs = array(
			'formId' => sanitize_key( (string) ( $props['formId'] ?? 'contact' ) ), 'submitLabel' => (string) ( $props['submitLabel'] ?? 'Submit' ),
			'successMessage' => (string) ( $props['successMessage'] ?? 'Thank you.' ), 'emailTo' => sanitize_email( (string) ( $props['emailTo'] ?? '' ) ),
			'storeSubmissions' => ! empty( $props['storeSubmissions'] ), 'redirectUrl' => esc_url_raw( (string) ( $props['redirectUrl'] ?? '' ) ),
			'retentionDays' => min( 365, max( 1, absint( $props['retentionDays'] ?? 30 ) ) ),
		);
		$block = array( 'blockName' => 'cresco/form', 'attrs' => $form_attrs, 'innerBlocks' => $fields, 'innerHTML' => '', 'innerContent' => array() );
		return '<div' . $attrs . '>' . do_blocks( serialize_blocks( array( $block ) ) ) . '</div>';
	}

	private static function render_woo_products( $attrs, $props ) {
		if ( ! class_exists( 'WooCommerce' ) || ! shortcode_exists( 'products' ) ) return '<div' . $attrs . '><p class="cresco-builder-placeholder">' . esc_html__( 'WooCommerce is not active.', 'cresco-canvas' ) . '</p></div>';
		$shortcode = sprintf( '[products limit="%d" columns="%d" orderby="%s" order="%s"%s]', min( 24, max( 1, absint( $props['limit'] ?? 8 ) ) ), min( 6, max( 1, absint( $props['columns'] ?? 4 ) ) ), esc_attr( (string) ( $props['orderby'] ?? 'date' ) ), 'ASC' === ( $props['order'] ?? '' ) ? 'ASC' : 'DESC', ! empty( $props['category'] ) ? ' category="' . esc_attr( sanitize_title( $props['category'] ) ) . '"' : '' );
		return '<div' . $attrs . '>' . do_shortcode( $shortcode ) . '</div>';
	}

	private static function current_product() {
		return function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : false;
	}

	private static function render_woo_product_title( $attrs, $props ) {
		$product = self::current_product(); if ( ! $product ) return '<div' . $attrs . '></div>';
		$tag = in_array( $props['tag'] ?? '', array( 'h1', 'h2', 'h3', 'div' ), true ) ? $props['tag'] : 'h1';
		return '<' . $tag . $attrs . '>' . esc_html( $product->get_name() ) . '</' . $tag . '>';
	}

	private static function render_woo_product_price( $attrs ) {
		$product = self::current_product(); return '<div' . $attrs . '>' . ( $product ? wp_kses_post( $product->get_price_html() ) : '' ) . '</div>';
	}

	private static function render_woo_product_image( $attrs, $props ) {
		$product = self::current_product(); if ( ! $product ) return '<figure' . $attrs . '></figure>';
		$size = in_array( $props['size'] ?? '', array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $props['size'] : 'large';
		return '<figure' . $attrs . '>' . wp_get_attachment_image( $product->get_image_id(), $size, false, array( 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</figure>';
	}

	private static function render_woo_add_to_cart( $attrs, $props ) {
		$product = self::current_product(); if ( ! $product ) return '<div' . $attrs . '></div>';
		if ( $product->is_purchasable() && $product->is_in_stock() ) {
			$url = add_query_arg( 'add-to-cart', $product->get_id(), wc_get_cart_url() );
			return '<div' . $attrs . '><a class="button add_to_cart_button" href="' . esc_url( $url ) . '" data-product_id="' . esc_attr( (string) $product->get_id() ) . '">' . esc_html( $product->add_to_cart_text() ?: (string) ( $props['label'] ?? 'Add to cart' ) ) . '</a></div>';
		}
		return '<div' . $attrs . '></div>';
	}

	private static function attr_value( $attrs, $name ) {
		if ( preg_match( '/' . preg_quote( $name, '/' ) . '=\"([^\"]*)\"/', $attrs, $matches ) ) return html_entity_decode( $matches[1], ENT_QUOTES );
		return '';
	}

	private function __construct() {}
}
