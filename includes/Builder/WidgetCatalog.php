<?php
/**
 * Schema-driven widget catalog for the Cresco Website Builder.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WidgetCatalog {
	/** Return the public widget contract used by REST, the editor, AI, and rendering. */
	public static function all() {
		$style = self::style_properties();
		$base  = array(
			'allowsChildren' => false,
			'style'          => $style,
			'responsive'     => true,
			'states'         => array( 'hover', 'focus', 'active' ),
		);

		$widgets = array(
			'container' => self::widget( $base, 'Container', 'layout', 'layout', true, array(
				'contentWidth' => self::enum( array( 'full', 'boxed' ), 'full', 'Content width' ),
				'layout'       => self::enum( array( 'block', 'flex', 'grid' ), 'flex', 'Layout' ),
				'direction'    => self::enum( array( 'row', 'column' ), 'column', 'Direction' ),
				'wrap'         => self::enum( array( 'nowrap', 'wrap', 'wrap-reverse' ), 'nowrap', 'Wrap' ),
				'align'        => self::enum( array( 'stretch', 'flex-start', 'center', 'flex-end', 'baseline' ), 'stretch', 'Align items' ),
				'justify'      => self::enum( array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ), 'flex-start', 'Justify content' ),
				'columns'      => self::integer( 1, 12, 2, 'Grid columns' ),
				'gridTemplate' => self::text( 'repeat(2, minmax(0, 1fr))', 'Grid template' ),
				'tag'          => self::enum( array( 'div', 'section', 'main', 'header', 'footer', 'aside', 'nav' ), 'div', 'HTML tag' ),
				'ariaLabel'    => self::string( '', 'ARIA label' ),
			) ),
			'columns' => self::widget( $base, 'Columns', 'layout', 'columns', true, array(
				'columns'    => self::integer( 1, 12, 2, 'Columns' ),
				'collapseAt' => self::enum( array( 'never', 'tablet', 'mobile' ), 'tablet', 'Stack at' ),
			) ),
			'heading' => self::widget( $base, 'Heading', 'content', 'heading', false, array(
				'text'  => self::textarea( 'Heading', 'Text' ),
				'level' => self::integer( 1, 6, 2, 'HTML heading' ),
				'url'   => self::url( '', 'Link' ),
			) ),
			'text' => self::widget( $base, 'Text', 'content', 'editor-paragraph', false, array(
				'text' => self::richtext( 'Add your text.', 'Text' ),
				'tag'  => self::enum( array( 'p', 'div', 'span' ), 'p', 'HTML tag' ),
			) ),
			'button' => self::widget( $base, 'Button', 'content', 'button', false, array(
				'text'         => self::string( 'Button', 'Label' ),
				'url'          => self::url( '#', 'URL' ),
				'target'       => self::enum( array( '_self', '_blank' ), '_self', 'Target' ),
				'rel'          => self::string( '', 'Rel' ),
				'icon'         => self::string( '', 'Icon' ),
				'iconPosition' => self::enum( array( 'before', 'after' ), 'before', 'Icon position' ),
			) ),
			'image' => self::widget( $base, 'Image', 'media', 'format-image', false, array(
				'url'         => self::url( '', 'Image URL' ),
				'alt'         => self::string( '', 'Alternative text' ),
				'caption'     => self::textarea( '', 'Caption' ),
				'link'        => self::url( '', 'Link' ),
				'objectFit'   => self::enum( array( 'cover', 'contain', 'fill', 'none', 'scale-down' ), 'cover', 'Object fit' ),
				'aspectRatio' => self::css( '', 'Aspect ratio' ),
			) ),
			'list' => self::widget( $base, 'List', 'content', 'editor-ul', false, array(
				'items'   => self::string_list( array( 'First item', 'Second item' ), 'Items' ),
				'ordered' => self::boolean( false, 'Ordered list' ),
			) ),
			'divider' => self::widget( $base, 'Divider', 'content', 'minus', false, array() ),
			'spacer' => self::widget( $base, 'Spacer', 'layout', 'image-flip-vertical', false, array(
				'height' => self::css( '48px', 'Height' ),
			) ),
			'icon' => self::widget( $base, 'Icon', 'content', 'star-filled', false, array(
				'icon'  => self::string( 'star-filled', 'Dashicon' ),
				'label' => self::string( '', 'Accessible label' ),
				'url'   => self::url( '', 'Link' ),
			) ),
			'icon-box' => self::widget( $base, 'Icon Box', 'content', 'info-outline', false, array(
				'icon'  => self::string( 'lightbulb', 'Dashicon' ),
				'title' => self::string( 'Feature', 'Title' ),
				'text'  => self::textarea( 'Describe this feature.', 'Text' ),
				'url'   => self::url( '', 'Link' ),
			) ),
			'video' => self::widget( $base, 'Video', 'media', 'video-alt3', false, array(
				'url'      => self::url( '', 'Video URL' ),
				'caption'  => self::textarea( '', 'Caption' ),
				'controls' => self::boolean( true, 'Show controls' ),
			) ),
			'gallery' => self::widget( $base, 'Gallery', 'media', 'format-gallery', false, array(
				'images'   => self::json( array(), 'Images JSON', 'gallery' ),
				'columns'  => self::integer( 1, 8, 3, 'Columns' ),
				'lightbox' => self::boolean( true, 'Lightbox' ),
			) ),
			'accordion' => self::widget( $base, 'Accordion', 'interactive', 'menu-alt3', false, array(
				'items'       => self::json( array( array( 'title' => 'Question', 'content' => 'Answer', 'open' => true ) ), 'Items JSON', 'accordion' ),
				'allowMulti'  => self::boolean( false, 'Allow multiple open' ),
			) ),
			'tabs' => self::widget( $base, 'Tabs', 'interactive', 'index-card', false, array(
				'items' => self::json( array( array( 'title' => 'Tab 1', 'content' => 'Tab content' ) ), 'Items JSON', 'tabs' ),
			) ),
			'testimonial' => self::widget( $base, 'Testimonial', 'content', 'format-quote', false, array(
				'quote'  => self::textarea( 'A great experience.', 'Quote' ),
				'name'   => self::string( 'Customer', 'Name' ),
				'role'   => self::string( '', 'Role' ),
				'avatar' => self::url( '', 'Avatar URL' ),
			) ),
			'counter' => self::widget( $base, 'Counter', 'interactive', 'chart-bar', false, array(
				'value'    => self::number( -1000000000, 1000000000, 100, 'Value' ),
				'prefix'   => self::string( '', 'Prefix' ),
				'suffix'   => self::string( '+', 'Suffix' ),
				'duration' => self::integer( 0, 10000, 1200, 'Duration (ms)' ),
			) ),
			'progress' => self::widget( $base, 'Progress', 'interactive', 'chart-line', false, array(
				'label'     => self::string( 'Progress', 'Label' ),
				'value'     => self::integer( 0, 100, 75, 'Value' ),
				'showValue' => self::boolean( true, 'Show value' ),
			) ),
			'social-icons' => self::widget( $base, 'Social Icons', 'content', 'share', false, array(
				'items' => self::json( array( array( 'label' => 'Website', 'url' => '#', 'icon' => 'admin-site' ) ), 'Networks JSON', 'social' ),
			) ),
			'site-logo' => self::widget( $base, 'Site Logo', 'site', 'format-image', false, array(
				'linkHome' => self::boolean( true, 'Link to home' ),
				'width'    => self::css( '160px', 'Logo width' ),
			) ),
			'site-title' => self::widget( $base, 'Site Title', 'site', 'admin-site-alt3', false, array(
				'tag'      => self::enum( array( 'div', 'p', 'h1', 'h2', 'h3' ), 'div', 'HTML tag' ),
				'linkHome' => self::boolean( true, 'Link to home' ),
			) ),
			'nav-menu' => self::widget( $base, 'Navigation Menu', 'site', 'menu', false, array(
				'menu'        => self::integer( 0, PHP_INT_MAX, 0, 'Menu ID' ),
				'orientation' => self::enum( array( 'horizontal', 'vertical' ), 'horizontal', 'Orientation' ),
				'depth'       => self::integer( 1, 5, 2, 'Depth' ),
			) ),
			'breadcrumbs' => self::widget( $base, 'Breadcrumbs', 'site', 'arrow-right-alt2', false, array(
				'separator' => self::string( '/', 'Separator' ),
				'showHome'  => self::boolean( true, 'Show home' ),
			) ),
			'post-title' => self::widget( $base, 'Post Title', 'dynamic', 'heading', false, array(
				'tag'  => self::enum( array( 'h1', 'h2', 'h3', 'div' ), 'h1', 'HTML tag' ),
				'link' => self::boolean( false, 'Link to post' ),
			) ),
			'post-excerpt' => self::widget( $base, 'Post Excerpt', 'dynamic', 'editor-paragraph', false, array(
				'words' => self::integer( 5, 100, 30, 'Words' ),
			) ),
			'featured-image' => self::widget( $base, 'Featured Image', 'dynamic', 'format-image', false, array(
				'size' => self::enum( array( 'thumbnail', 'medium', 'large', 'full' ), 'large', 'Image size' ),
				'link' => self::boolean( false, 'Link to post' ),
			) ),
			'post-content' => self::widget( $base, 'Post Content', 'dynamic', 'text-page', false, array() ),
			'dynamic-field' => self::widget( $base, 'Dynamic Field', 'dynamic', 'database', false, array(
				'source'   => self::enum( array( 'meta', 'acf', 'site', 'user' ), 'meta', 'Source' ),
				'key'      => self::string( '', 'Field key' ),
				'fallback' => self::textarea( '', 'Fallback' ),
				'before'   => self::string( '', 'Before' ),
				'after'    => self::string( '', 'After' ),
				'format'   => self::enum( array( 'text', 'richtext', 'url', 'number' ), 'text', 'Format' ),
			) ),
			'loop-grid' => self::widget( $base, 'Loop Grid', 'dynamic', 'grid-view', false, array(
				'postType'    => self::string( 'post', 'Post type' ),
				'perPage'     => self::integer( 1, 24, 6, 'Items' ),
				'columns'     => self::integer( 1, 6, 3, 'Columns' ),
				'order'       => self::enum( array( 'ASC', 'DESC' ), 'DESC', 'Order' ),
				'orderBy'     => self::enum( array( 'date', 'title', 'menu_order', 'modified', 'rand' ), 'date', 'Order by' ),
				'taxonomy'    => self::string( '', 'Taxonomy' ),
				'term'        => self::string( '', 'Term slug' ),
				'showImage'   => self::boolean( true, 'Show image' ),
				'showExcerpt' => self::boolean( true, 'Show excerpt' ),
				'showDate'    => self::boolean( false, 'Show date' ),
				'buttonLabel' => self::string( 'Read more', 'Button label' ),
			) ),
			'form' => self::widget( $base, 'Form', 'forms', 'feedback', false, array(
				'formId'           => self::string( 'contact', 'Form ID' ),
				'fields'           => self::json( array( array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ) ), 'Fields JSON', 'form_fields' ),
				'submitLabel'      => self::string( 'Submit', 'Submit label' ),
				'successMessage'   => self::string( 'Thank you.', 'Success message' ),
				'emailTo'          => self::string( '', 'Notification email' ),
				'storeSubmissions' => self::boolean( true, 'Store submissions' ),
				'redirectUrl'      => self::url( '', 'Redirect URL' ),
				'retentionDays'    => self::integer( 1, 365, 30, 'Retention days' ),
			) ),
			'woo-products' => self::widget( $base, 'Woo Products', 'woocommerce', 'products', false, array(
				'limit'    => self::integer( 1, 24, 8, 'Products' ),
				'columns'  => self::integer( 1, 6, 4, 'Columns' ),
				'orderby'  => self::enum( array( 'date', 'title', 'price', 'popularity', 'rating', 'rand', 'menu_order' ), 'date', 'Order by' ),
				'order'    => self::enum( array( 'ASC', 'DESC' ), 'DESC', 'Order' ),
				'category' => self::string( '', 'Category slug' ),
			) ),
			'woo-product-title' => self::widget( $base, 'Product Title', 'woocommerce', 'heading', false, array( 'tag' => self::enum( array( 'h1', 'h2', 'h3', 'div' ), 'h1', 'HTML tag' ) ) ),
			'woo-product-price' => self::widget( $base, 'Product Price', 'woocommerce', 'money-alt', false, array() ),
			'woo-product-image' => self::widget( $base, 'Product Image', 'woocommerce', 'format-image', false, array( 'size' => self::enum( array( 'thumbnail', 'medium', 'large', 'full' ), 'large', 'Image size' ) ) ),
			'woo-add-to-cart' => self::widget( $base, 'Add to Cart', 'woocommerce', 'cart', false, array( 'label' => self::string( 'Add to cart', 'Fallback label' ) ) ),
		);

		return $widgets;
	}

	/** Allow-list of style keys understood by the editor and frontend compiler. */
	public static function style_properties() {
		return array(
			'display', 'width', 'maxWidth', 'minWidth', 'height', 'maxHeight', 'minHeight', 'aspectRatio',
			'color', 'background', 'backgroundColor', 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'lineHeight', 'letterSpacing', 'textAlign', 'textTransform', 'textDecoration',
			'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'gap', 'columnGap', 'rowGap',
			'border', 'borderColor', 'borderWidth', 'borderStyle', 'borderRadius', 'boxShadow', 'opacity', 'transform', 'filter', 'transition',
			'position', 'top', 'right', 'bottom', 'left', 'inset', 'zIndex', 'overflow', 'overflowX', 'overflowY',
			'alignItems', 'alignSelf', 'justifyContent', 'justifySelf', 'flexDirection', 'flexWrap', 'flexGrow', 'flexShrink', 'flexBasis', 'order',
			'gridTemplateColumns', 'gridTemplateRows', 'gridColumn', 'gridRow', 'placeItems', 'placeContent',
			'objectFit', 'objectPosition', 'cursor', 'visibility',
		);
	}

	private static function widget( $base, $label, $category, $icon, $allows_children, $props ) {
		return array_merge( $base, array(
			'label'          => $label,
			'category'       => $category,
			'icon'           => $icon,
			'allowsChildren' => (bool) $allows_children,
			'props'          => $props,
		) );
	}

	private static function schema( $type, $default, $label, $extra = array() ) { return array_merge( array( 'type' => $type, 'default' => $default, 'label' => $label ), $extra ); }
	private static function string( $default, $label ) { return self::schema( 'string', $default, $label ); }
	private static function textarea( $default, $label ) { return self::schema( 'text', $default, $label, array( 'control' => 'textarea' ) ); }
	private static function richtext( $default, $label ) { return self::schema( 'richtext', $default, $label, array( 'control' => 'textarea' ) ); }
	private static function url( $default, $label ) { return self::schema( 'url', $default, $label ); }
	private static function css( $default, $label ) { return self::schema( 'css', $default, $label ); }
	private static function boolean( $default, $label ) { return self::schema( 'bool', (bool) $default, $label, array( 'control' => 'toggle' ) ); }
	private static function integer( $min, $max, $default, $label ) { return self::schema( 'int', $default, $label, array( 'min' => $min, 'max' => $max, 'control' => 'number' ) ); }
	private static function number( $min, $max, $default, $label ) { return self::schema( 'number', $default, $label, array( 'min' => $min, 'max' => $max, 'control' => 'number' ) ); }
	private static function enum( $values, $default, $label ) { return self::schema( 'enum', $default, $label, array( 'values' => $values, 'control' => 'select' ) ); }
	private static function string_list( $default, $label ) { return self::schema( 'string_list', $default, $label, array( 'control' => 'textarea' ) ); }
	private static function json( $default, $label, $shape ) { return self::schema( 'json', $default, $label, array( 'control' => 'json', 'shape' => $shape ) ); }

	private function __construct() {}
}
