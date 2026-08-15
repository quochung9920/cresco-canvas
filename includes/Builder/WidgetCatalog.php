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
		$base = array(
			'allowsChildren' => false,
			'style'          => self::style_properties(),
			'responsive'     => true,
			'states'         => array( 'hover' ),
			'parts'          => array( 'root' => self::part( 'Root', '&' ) ),
		);

		$layout_styles = self::styles( array( 'size', 'spacing', 'background', 'border', 'effects', 'position', 'overflow', 'flex-container', 'flex-item', 'grid-container', 'grid-item', 'interaction' ) );
		$text_styles   = self::styles( array( 'size', 'typography', 'spacing', 'background', 'border', 'effects', 'position', 'overflow', 'flex-item', 'grid-item', 'interaction' ) );
		$card_styles   = self::styles( array( 'size', 'typography', 'spacing', 'background', 'border', 'effects', 'position', 'overflow', 'flex-container', 'flex-item', 'grid-container', 'grid-item', 'interaction' ) );
		$media_styles  = self::styles( array( 'size', 'spacing', 'background', 'border', 'effects', 'position', 'overflow', 'flex-item', 'grid-item', 'media', 'interaction' ) );
		$small_styles  = self::styles( array( 'size', 'spacing', 'border', 'effects', 'position', 'flex-item', 'grid-item', 'interaction' ) );

		$widgets = array(
			'container' => self::widget( $base, 'Container', 'layout', 'layout', true, array(
				'contentWidth' => self::enum( array( 'full', 'boxed' ), 'full', 'Content width', array( 'group' => 'Layout', 'help' => 'Full spans the available width. Boxed constrains content to the global container width.' ) ),
				'layout'       => self::enum( array( 'block', 'flex', 'grid' ), 'flex', 'Layout', array( 'group' => 'Layout', 'panel' => 'layout', 'styleKey' => 'display' ) ),
				'direction'    => self::enum( array( 'row', 'column' ), 'column', 'Direction', array( 'group' => 'Layout', 'panel' => 'layout', 'styleKey' => 'flexDirection', 'condition' => array( 'key' => 'layout', 'equals' => 'flex' ) ) ),
				'wrap'         => self::enum( array( 'nowrap', 'wrap', 'wrap-reverse' ), 'nowrap', 'Wrap', array( 'group' => 'Layout', 'panel' => 'layout', 'styleKey' => 'flexWrap', 'condition' => array( 'key' => 'layout', 'equals' => 'flex' ) ) ),
				'align'        => self::enum( array( 'stretch', 'flex-start', 'center', 'flex-end', 'baseline' ), 'stretch', 'Align items', array( 'group' => 'Layout', 'panel' => 'layout', 'styleKey' => 'alignItems', 'condition' => array( 'key' => 'layout', 'equals' => 'flex' ) ) ),
				'justify'      => self::enum( array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ), 'flex-start', 'Justify content', array( 'group' => 'Layout', 'panel' => 'layout', 'styleKey' => 'justifyContent', 'condition' => array( 'key' => 'layout', 'equals' => 'flex' ) ) ),
				'columns'      => self::integer( 1, 12, 2, 'Grid columns', array( 'group' => 'Layout', 'panel' => 'layout', 'styleKey' => 'gridTemplateColumns', 'condition' => array( 'key' => 'layout', 'equals' => 'grid' ) ) ),
				'gridTemplate' => self::text( 'repeat(2, minmax(0, 1fr))', 'Grid template', array( 'group' => 'Layout', 'panel' => 'layout', 'styleKey' => 'gridTemplateColumns', 'condition' => array( 'key' => 'layout', 'equals' => 'grid' ) ) ),
				'tag'          => self::enum( array( 'div', 'section', 'main', 'header', 'footer', 'aside', 'nav' ), 'div', 'HTML tag', array( 'group' => 'Semantics' ) ),
				'ariaLabel'    => self::string( '', 'ARIA label', array( 'group' => 'Accessibility' ) ),
			), array( 'description' => 'Structural wrapper for page sections and responsive layouts.', 'style' => self::styles( array( 'size', 'typography', 'spacing', 'background', 'border', 'effects', 'position', 'overflow', 'flex-container', 'flex-item', 'grid-container', 'grid-item', 'interaction' ) ) ) ),
			'columns' => self::widget( $base, 'Columns', 'layout', 'columns', true, array(
				'columns'    => self::integer( 1, 12, 2, 'Columns', array( 'group' => 'Layout' ) ),
				'collapseAt' => self::enum( array( 'never', 'tablet', 'mobile' ), 'tablet', 'Stack at', array( 'group' => 'Responsive' ) ),
			), array( 'description' => 'Quick responsive column layout that can contain child widgets.', 'style' => $layout_styles ) ),
			'heading' => self::widget( $base, 'Heading', 'content', 'heading', false, array(
				'text'  => self::textarea( 'Heading', 'Text', array( 'group' => 'Content' ) ),
				'level' => self::enum( array( '1', '2', '3', '4', '5', '6' ), '2', 'HTML heading', array( 'group' => 'Semantics', 'valueLabels' => array( '1' => 'H1', '2' => 'H2', '3' => 'H3', '4' => 'H4', '5' => 'H5', '6' => 'H6' ) ) ),
				'url'   => self::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
			), array( 'description' => 'Semantic heading with optional link.', 'style' => $text_styles, 'parts' => array( 'root' => self::part( 'Heading', '&' ), 'link' => self::part( 'Link', '& > a' ) ) ) ),
			'text' => self::widget( $base, 'Text', 'content', 'editor-paragraph', false, array(
				'text' => self::richtext( 'Add your text.', 'Text', array( 'group' => 'Content', 'control' => 'richtext' ) ),
				'tag'  => self::enum( array( 'p', 'div', 'span' ), 'p', 'HTML tag', array( 'group' => 'Semantics' ) ),
			), array( 'description' => 'Rich text content with a selectable semantic wrapper.', 'style' => $text_styles ) ),
			'button' => self::widget( $base, 'Button', 'content', 'button', false, array(
				'text'         => self::string( 'Button', 'Label', array( 'group' => 'Content' ) ),
				'url'          => self::url( '#', 'URL', array( 'group' => 'Link', 'control' => 'link' ) ),
				'target'       => self::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
				'rel'          => self::string( '', 'Rel', array( 'group' => 'Link' ) ),
				'icon'         => self::string( '', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
				'iconPosition' => self::enum( array( 'before', 'after' ), 'before', 'Icon position', array( 'group' => 'Icon' ) ),
			), array( 'description' => 'Linked call-to-action with optional Dashicon.', 'style' => $card_styles, 'states' => array( 'hover', 'focus', 'active' ), 'parts' => array( 'root' => self::part( 'Button', '&' ), 'text' => self::part( 'Text', '& [data-cresco-part="text"]' ), 'icon' => self::part( 'Icon', '& .dashicons' ) ) ) ),
			'image' => self::widget( $base, 'Image', 'media', 'format-image', false, array(
				'url'         => self::url( '', 'Image', array( 'group' => 'Image', 'control' => 'media', 'mediaType' => 'image' ) ),
				'alt'         => self::string( '', 'Alternative text', array( 'group' => 'Accessibility' ) ),
				'caption'     => self::textarea( '', 'Caption', array( 'group' => 'Content' ) ),
				'link'        => self::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
				'objectFit'   => self::enum( array( 'cover', 'contain', 'fill', 'none', 'scale-down' ), 'cover', 'Object fit', array( 'group' => 'Image' ) ),
				'aspectRatio' => self::css( '', 'Aspect ratio', array( 'group' => 'Image', 'placeholder' => '16 / 9' ) ),
			), array( 'description' => 'Responsive image with alt text, caption, link, fit, and aspect ratio.', 'style' => $media_styles, 'parts' => array( 'root' => self::part( 'Figure', '&' ), 'media' => self::part( 'Image', '& [data-cresco-part="media"]' ), 'caption' => self::part( 'Caption', '& [data-cresco-part="caption"]' ), 'link' => self::part( 'Link', '& > a' ) ) ) ),
			'list' => self::widget( $base, 'List', 'content', 'editor-ul', false, array(
				'items'   => self::string_list( array( 'First item', 'Second item' ), 'Items', array( 'group' => 'Items', 'control' => 'repeater', 'shape' => 'string_list' ) ),
				'ordered' => self::boolean( false, 'Ordered list', array( 'group' => 'Semantics' ) ),
			), array( 'description' => 'Semantic ordered or unordered list.', 'style' => $text_styles, 'parts' => array( 'root' => self::part( 'List', '&' ), 'item' => self::part( 'Items', '& > [data-cresco-part="item"]' ) ) ) ),
			'divider' => self::widget( $base, 'Divider', 'content', 'minus', false, array(), array( 'description' => 'Semantic horizontal divider.', 'style' => $small_styles, 'states' => array() ) ),
			'spacer' => self::widget( $base, 'Spacer', 'layout', 'image-flip-vertical', false, array(
				'height' => self::css( '48px', 'Height', array( 'group' => 'Size', 'placeholder' => '48px' ) ),
			), array( 'description' => 'Purpose-built vertical spacing element.', 'style' => self::styles( array( 'size', 'spacing', 'position', 'flex-item', 'grid-item', 'interaction' ) ), 'states' => array() ) ),
			'icon' => self::widget( $base, 'Icon', 'content', 'star-filled', false, array(
				'icon'  => self::string( 'star-filled', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
				'label' => self::string( '', 'Accessible label', array( 'group' => 'Accessibility' ) ),
				'url'   => self::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
			), array( 'description' => 'Dashicon with optional accessible label and link.', 'style' => $text_styles, 'parts' => array( 'root' => self::part( 'Wrapper', '&' ), 'icon' => self::part( 'Icon', '& .dashicons' ), 'link' => self::part( 'Link', '& > a' ) ) ) ),
			'icon-box' => self::widget( $base, 'Icon Box', 'content', 'info-outline', false, array(
				'icon'         => self::string( 'lightbulb', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
				'title'        => self::string( 'Feature', 'Title', array( 'group' => 'Content' ) ),
				'text'         => self::textarea( 'Describe this feature.', 'Text', array( 'group' => 'Content' ) ),
				'url'          => self::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
				'position'     => self::enum( array( 'top', 'start', 'end' ), 'start', 'Icon position', array( 'group' => 'Layout' ) ),
				'contentAlign' => self::enum( array( 'start', 'center', 'end', 'justify' ), 'start', 'Content alignment', array( 'group' => 'Layout' ) ),
				'iconGap'      => self::css( '', 'Icon spacing', array( 'group' => 'Layout', 'placeholder' => '15px' ) ),
			), array( 'description' => 'Feature card composed of icon, title, description, and optional link.', 'style' => $card_styles, 'parts' => array( 'root' => self::part( 'Box', '&' ), 'icon' => self::part( 'Icon', '& .cresco-icon-box__icon' ), 'title' => self::part( 'Title', '& .cresco-icon-box__body h3' ), 'text' => self::part( 'Text', '& .cresco-icon-box__body p' ), 'link' => self::part( 'Link', '& .cresco-icon-box__link' ) ) ) ),
			'video' => self::widget( $base, 'Video', 'media', 'video-alt3', false, array(
				'url'     => self::url( '', 'Video URL', array( 'group' => 'Video', 'control' => 'link' ) ),
				'caption' => self::textarea( '', 'Caption', array( 'group' => 'Content' ) ),
			), array( 'description' => 'WordPress oEmbed video with optional caption.', 'style' => $media_styles, 'parts' => array( 'root' => self::part( 'Figure', '&' ), 'embed' => self::part( 'Embed', '& .cresco-video__embed' ), 'caption' => self::part( 'Caption', '& figcaption' ) ) ) ),
			'gallery' => self::widget( $base, 'Gallery', 'media', 'format-gallery', false, array(
				'images'       => self::json( array(), 'Images', 'gallery', array( 'group' => 'Images', 'control' => 'repeater' ) ),
				'columns'      => self::integer( 1, 8, 3, 'Columns', array( 'group' => 'Layout' ) ),
				'gap'          => self::css( '', 'Image gap', array( 'group' => 'Layout', 'placeholder' => '15px' ) ),
				'aspectRatio'  => self::css( '', 'Image aspect ratio', array( 'group' => 'Images', 'placeholder' => '1 / 1' ) ),
				'objectFit'    => self::enum( array( 'cover', 'contain', 'fill', 'none', 'scale-down' ), 'cover', 'Image fit', array( 'group' => 'Images' ) ),
				'showCaptions' => self::boolean( true, 'Show captions', array( 'group' => 'Caption' ) ),
				'captionAlign' => self::enum( array( 'start', 'center', 'end', 'justify' ), 'start', 'Caption alignment', array( 'group' => 'Caption' ) ),
				'lightbox'     => self::boolean( true, 'Lightbox', array( 'group' => 'Behavior' ) ),
			), array( 'description' => 'Image gallery with configurable columns, spacing, media fit, captions, and optional lightbox links.', 'style' => $layout_styles, 'parts' => array( 'root' => self::part( 'Gallery', '&' ), 'item' => self::part( 'Item', '& .cresco-gallery__item' ), 'image' => self::part( 'Image', '& .cresco-gallery__item img' ), 'caption' => self::part( 'Caption', '& .cresco-gallery__item figcaption' ) ) ) ),
			'accordion' => self::widget( $base, 'Accordion', 'interactive', 'menu-alt3', false, array(
				'items'        => self::json( array( array( 'title' => 'Question', 'content' => 'Answer', 'open' => true ) ), 'Items', 'accordion', array( 'group' => 'Items', 'control' => 'repeater' ) ),
				'allowMulti'   => self::boolean( false, 'Allow multiple open', array( 'group' => 'Behavior' ) ),
				'titleTag'     => self::enum( array( 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3', 'Title HTML tag', array( 'group' => 'Semantics' ) ),
				'iconPosition' => self::enum( array( 'start', 'end' ), 'end', 'Icon position', array( 'group' => 'Icon' ) ),
				'expandIcon'   => self::string( 'plus-alt2', 'Expand icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
				'collapseIcon' => self::string( 'minus', 'Collapse icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			), array( 'description' => 'Accessible disclosure list with editable items and semantic title tags.', 'style' => $card_styles, 'parts' => array( 'root' => self::part( 'Accordion', '&' ), 'item' => self::part( 'Item', '& .cresco-accordion__item' ), 'trigger' => self::part( 'Trigger', '& .cresco-accordion__trigger' ), 'icon' => self::part( 'Icon', '& .cresco-accordion__icon' ), 'panel' => self::part( 'Panel', '& .cresco-accordion__panel' ) ) ) ),
			'tabs' => self::widget( $base, 'Tabs', 'interactive', 'index-card', false, array(
				'items'            => self::json( array( array( 'title' => 'Tab 1', 'content' => 'Tab content' ) ), 'Items', 'tabs', array( 'group' => 'Items', 'control' => 'repeater' ) ),
				'direction'        => self::enum( array( 'top', 'bottom', 'start', 'end' ), 'top', 'Direction', array( 'group' => 'Layout' ) ),
				'justify'          => self::enum( array( 'start', 'center', 'end', 'stretch' ), 'start', 'Justify tabs', array( 'group' => 'Layout' ) ),
				'titleAlign'       => self::enum( array( 'start', 'center', 'end' ), 'center', 'Title alignment', array( 'group' => 'Layout' ) ),
				'horizontalScroll' => self::boolean( true, 'Horizontal scroll', array( 'group' => 'Responsive' ) ),
				'sideWidth'        => self::css( '240px', 'Side tab width', array( 'group' => 'Layout' ) ),
				'tabGap'           => self::css( '.25rem', 'Gap between tabs', array( 'group' => 'Layout' ) ),
			), array( 'description' => 'Accessible tab list with editable items and layout controls.', 'style' => $card_styles, 'parts' => array( 'root' => self::part( 'Tabs', '&' ), 'list' => self::part( 'Tab list', '& .cresco-tabs__list' ), 'tab' => self::part( 'Tab', '& [role="tab"]' ), 'activeTab' => self::part( 'Active tab', '& [role="tab"][aria-selected="true"]' ), 'panels' => self::part( 'Panels', '& .cresco-tabs__panels' ), 'panel' => self::part( 'Panel', '& [role="tabpanel"]' ) ) ) ),
			'testimonial' => self::widget( $base, 'Testimonial', 'content', 'format-quote', false, array(
				'quote'  => self::textarea( 'A great experience.', 'Quote', array( 'group' => 'Content' ) ),
				'name'   => self::string( 'Customer', 'Name', array( 'group' => 'Author' ) ),
				'role'   => self::string( '', 'Role', array( 'group' => 'Author' ) ),
				'avatar' => self::url( '', 'Avatar', array( 'group' => 'Author', 'control' => 'media', 'mediaType' => 'image' ) ),
			), array( 'description' => 'Customer quote with author identity and optional avatar.', 'style' => $card_styles, 'parts' => array( 'root' => self::part( 'Testimonial', '&' ), 'quote' => self::part( 'Quote', '& blockquote' ), 'avatar' => self::part( 'Avatar', '& figcaption img' ), 'name' => self::part( 'Name', '& figcaption strong' ), 'role' => self::part( 'Role', '& figcaption small' ) ) ) ),
			'counter' => self::widget( $base, 'Counter', 'interactive', 'chart-bar', false, array(
				'value'    => self::number( -1000000000, 1000000000, 100, 'Value', array( 'group' => 'Value' ) ),
				'prefix'   => self::string( '', 'Prefix', array( 'group' => 'Value' ) ),
				'suffix'   => self::string( '+', 'Suffix', array( 'group' => 'Value' ) ),
				'duration' => self::integer( 0, 10000, 1200, 'Duration (ms)', array( 'group' => 'Animation' ) ),
			), array( 'description' => 'Animated numeric value with prefix, suffix, and duration.', 'style' => $text_styles, 'parts' => array( 'root' => self::part( 'Counter', '&' ), 'prefix' => self::part( 'Prefix', '& .cresco-counter__prefix' ), 'value' => self::part( 'Value', '& .cresco-counter__value' ), 'suffix' => self::part( 'Suffix', '& .cresco-counter__suffix' ) ) ) ),
			'progress' => self::widget( $base, 'Progress', 'interactive', 'chart-line', false, array(
				'label'     => self::string( 'Progress', 'Label', array( 'group' => 'Content' ) ),
				'value'     => self::integer( 0, 100, 75, 'Value', array( 'group' => 'Progress' ) ),
				'showValue' => self::boolean( true, 'Show value', array( 'group' => 'Progress' ) ),
			), array( 'description' => 'Accessible progress indicator from 0 to 100 percent.', 'style' => $card_styles, 'parts' => array( 'root' => self::part( 'Progress', '&' ), 'heading' => self::part( 'Heading', '& .cresco-progress__heading' ), 'track' => self::part( 'Track', '& .cresco-progress__track' ), 'bar' => self::part( 'Bar', '& .cresco-progress__bar' ) ) ) ),
			'social-icons' => self::widget( $base, 'Social Icons', 'content', 'share', false, array(
				'items' => self::json( array( array( 'label' => 'Website', 'url' => '#', 'icon' => 'admin-site' ) ), 'Networks', 'social', array( 'group' => 'Networks', 'control' => 'repeater' ) ),
			), array( 'description' => 'Accessible social or external links represented by Dashicons.', 'style' => $text_styles, 'parts' => array( 'root' => self::part( 'Social links', '&' ), 'link' => self::part( 'Links', '& > a' ), 'icon' => self::part( 'Icons', '& .dashicons' ) ) ) ),
			'site-logo' => self::widget( $base, 'Site Logo', 'site', 'format-image', false, array(
				'linkHome' => self::boolean( true, 'Link to home', array( 'group' => 'Behavior' ) ),
				'width'    => self::css( '160px', 'Logo width', array( 'group' => 'Logo' ) ),
			), array( 'description' => 'WordPress custom logo with optional home link.', 'style' => $media_styles, 'parts' => array( 'root' => self::part( 'Logo wrapper', '&' ), 'image' => self::part( 'Logo image', '& .cresco-site-logo__image' ), 'link' => self::part( 'Home link', '& > a' ) ) ) ),
			'site-title' => self::widget( $base, 'Site Title', 'site', 'admin-site-alt3', false, array(
				'tag'      => self::enum( array( 'div', 'p', 'h1', 'h2', 'h3' ), 'div', 'HTML tag', array( 'group' => 'Semantics' ) ),
				'linkHome' => self::boolean( true, 'Link to home', array( 'group' => 'Behavior' ) ),
			), array( 'description' => 'Dynamic WordPress site title with semantic tag and optional home link.', 'style' => $text_styles, 'parts' => array( 'root' => self::part( 'Title', '&' ), 'link' => self::part( 'Home link', '& > a' ) ) ) ),
			'nav-menu' => self::widget( $base, 'Navigation Menu', 'site', 'menu', false, array(
				'menu'        => self::integer( 0, PHP_INT_MAX, 0, 'Menu', array( 'group' => 'Menu', 'control' => 'option-select', 'optionsSource' => 'menus', 'optionValue' => 'id', 'optionLabel' => 'label', 'emptyLabel' => 'Choose a WordPress menu' ) ),
				'orientation' => self::enum( array( 'horizontal', 'vertical' ), 'horizontal', 'Orientation', array( 'group' => 'Layout' ) ),
				'depth'       => self::integer( 1, 5, 2, 'Depth', array( 'group' => 'Menu' ) ),
			), array( 'description' => 'WordPress navigation menu with orientation and depth controls.', 'style' => $card_styles, 'parts' => array( 'root' => self::part( 'Navigation', '&' ), 'list' => self::part( 'Menu list', '& .cresco-nav-menu__list' ), 'link' => self::part( 'Links', '& a' ), 'submenu' => self::part( 'Submenus', '& .sub-menu' ) ) ) ),
			'breadcrumbs' => self::widget( $base, 'Breadcrumbs', 'site', 'arrow-right-alt2', false, array(
				'separator' => self::string( '/', 'Separator', array( 'group' => 'Display' ) ),
				'showHome'  => self::boolean( true, 'Show home', array( 'group' => 'Display' ) ),
			), array( 'description' => 'Context-aware breadcrumb trail with optional home item.', 'style' => $text_styles ) ),
			'post-title' => self::widget( $base, 'Post Title', 'dynamic', 'heading', false, array(
				'tag'  => self::enum( array( 'h1', 'h2', 'h3', 'div' ), 'h1', 'HTML tag', array( 'group' => 'Semantics' ) ),
				'link' => self::boolean( false, 'Link to post', array( 'group' => 'Behavior' ) ),
			), array( 'description' => 'Current post title for templates and dynamic layouts.', 'style' => $text_styles, 'parts' => array( 'root' => self::part( 'Title', '&' ), 'link' => self::part( 'Post link', '& > a' ) ) ) ),
			'post-excerpt' => self::widget( $base, 'Post Excerpt', 'dynamic', 'editor-paragraph', false, array(
				'words' => self::integer( 5, 100, 30, 'Words', array( 'group' => 'Excerpt' ) ),
			), array( 'description' => 'Trimmed excerpt from the current post.', 'style' => $text_styles ) ),
			'featured-image' => self::widget( $base, 'Featured Image', 'dynamic', 'format-image', false, array(
				'size' => self::enum( array( 'thumbnail', 'medium', 'large', 'full' ), 'large', 'Image size', array( 'group' => 'Image' ) ),
				'link' => self::boolean( false, 'Link to post', array( 'group' => 'Behavior' ) ),
			), array( 'description' => 'Featured image from the current post.', 'style' => $media_styles, 'parts' => array( 'root' => self::part( 'Figure', '&' ), 'image' => self::part( 'Image', '& img' ), 'link' => self::part( 'Post link', '& > a' ) ) ) ),
			'post-content' => self::widget( $base, 'Post Content', 'dynamic', 'text-page', false, array(), array( 'description' => 'Rendered content of the current post.', 'style' => $text_styles, 'states' => array() ) ),
			'dynamic-field' => self::widget( $base, 'Dynamic Field', 'dynamic', 'database', false, array(
				'source'   => self::enum( array( 'meta', 'acf', 'site', 'user' ), 'meta', 'Source', array( 'group' => 'Source' ) ),
				'key'      => self::string( '', 'Field key', array( 'group' => 'Source' ) ),
				'fallback' => self::textarea( '', 'Fallback', array( 'group' => 'Fallback' ) ),
				'before'   => self::string( '', 'Before', array( 'group' => 'Formatting' ) ),
				'after'    => self::string( '', 'After', array( 'group' => 'Formatting' ) ),
				'format'   => self::enum( array( 'text', 'richtext', 'url', 'number' ), 'text', 'Format', array( 'group' => 'Formatting' ) ),
			), array( 'description' => 'Safe dynamic value from post meta, ACF, site, or author data.', 'style' => $text_styles ) ),
			'loop-grid' => self::widget( $base, 'Loop Grid', 'dynamic', 'grid-view', false, self::loop_query_props(), array(
				'description' => 'Bounded WordPress query rendered as a reusable post-card grid.',
				'style'       => $layout_styles,
				'parts'       => array( 'root' => self::part( 'Grid', '&' ), 'card' => self::part( 'Card', '& .cresco-loop-card' ), 'image' => self::part( 'Image', '& .cresco-loop-card__image img' ), 'body' => self::part( 'Body', '& .cresco-loop-card__body' ), 'title' => self::part( 'Title', '& .cresco-loop-card__body h3' ), 'excerpt' => self::part( 'Excerpt', '& .cresco-loop-card__body p' ), 'button' => self::part( 'Button', '& .cresco-loop-card__button' ) ),
			) ),
			'form' => self::widget( $base, 'Form', 'forms', 'feedback', false, array(
				'formId'           => self::string( 'contact', 'Form ID', array( 'group' => 'Form' ) ),
				'fields'           => self::json( array( array( 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ) ), 'Fields', 'form_fields', array( 'group' => 'Fields', 'control' => 'repeater' ) ),
				'submitLabel'      => self::string( 'Submit', 'Submit label', array( 'group' => 'Submit' ) ),
				'successMessage'   => self::string( 'Thank you.', 'Success message', array( 'group' => 'After submit' ) ),
				'emailTo'          => self::string( '', 'Notification email', array( 'group' => 'Notifications', 'control' => 'email' ) ),
				'storeSubmissions' => self::boolean( true, 'Store submissions', array( 'group' => 'Storage' ) ),
				'redirectUrl'      => self::url( '', 'Redirect URL', array( 'group' => 'After submit', 'control' => 'link' ) ),
				'retentionDays'    => self::integer( 1, 365, 30, 'Retention days', array( 'group' => 'Storage' ) ),
			), array( 'description' => 'Native Cresco form with visual field definitions, notifications, storage, and redirect behavior.', 'style' => $card_styles ) ),
		);

		$widgets = array_merge( $widgets, self::professional_widgets( $base, $layout_styles, $text_styles, $card_styles, $media_styles ) );

		$widgets['woo-products'] = self::widget( $base, 'Woo Products', 'woocommerce', 'products', false, array(
			'limit'    => self::integer( 1, 24, 8, 'Products', array( 'group' => 'Query' ) ),
			'columns'  => self::integer( 1, 6, 4, 'Columns', array( 'group' => 'Layout' ) ),
			'orderby'  => self::enum( array( 'date', 'title', 'price', 'popularity', 'rating', 'rand', 'menu_order' ), 'date', 'Order by', array( 'group' => 'Query' ) ),
			'order'    => self::enum( array( 'ASC', 'DESC' ), 'DESC', 'Order', array( 'group' => 'Query' ) ),
			'category' => self::string( '', 'Category slug', array( 'group' => 'Filter' ) ),
		), array( 'description' => 'WooCommerce product collection backed by the native products shortcode.', 'style' => $layout_styles, 'parts' => array( 'root' => self::part( 'Products', '&' ), 'list' => self::part( 'Product list', '& .products' ), 'product' => self::part( 'Product card', '& .product' ), 'title' => self::part( 'Title', '& .woocommerce-loop-product__title' ), 'price' => self::part( 'Price', '& .price' ), 'button' => self::part( 'Button', '& .button' ) ) ) );
		$widgets['woo-product-title'] = self::widget( $base, 'Product Title', 'woocommerce', 'heading', false, array(
			'tag' => self::enum( array( 'h1', 'h2', 'h3', 'div' ), 'h1', 'HTML tag', array( 'group' => 'Semantics' ) ),
		), array( 'description' => 'Title of the current WooCommerce product.', 'style' => $text_styles ) );
		$widgets['woo-product-price'] = self::widget( $base, 'Product Price', 'woocommerce', 'money-alt', false, array(), array( 'description' => 'Formatted price of the current WooCommerce product.', 'style' => $text_styles, 'states' => array(), 'parts' => array( 'root' => self::part( 'Price', '&' ), 'amount' => self::part( 'Amount', '& .amount' ) ) ) );
		$widgets['woo-product-image'] = self::widget( $base, 'Product Image', 'woocommerce', 'format-image', false, array(
			'size' => self::enum( array( 'thumbnail', 'medium', 'large', 'full' ), 'large', 'Image size', array( 'group' => 'Image' ) ),
		), array( 'description' => 'Primary image of the current WooCommerce product.', 'style' => $media_styles, 'parts' => array( 'root' => self::part( 'Figure', '&' ), 'image' => self::part( 'Image', '& img' ) ) ) );
		$widgets['woo-add-to-cart'] = self::widget( $base, 'Add to Cart', 'woocommerce', 'cart', false, array(
			'label' => self::string( 'Add to cart', 'Fallback label', array( 'group' => 'Content' ) ),
		), array( 'description' => 'Purchase action for the current in-stock WooCommerce product.', 'style' => $card_styles, 'parts' => array( 'root' => self::part( 'Wrapper', '&' ), 'button' => self::part( 'Button', '& .add_to_cart_button' ) ) ) );

		return self::with_blueprints( $widgets );
	}

	/** Allow-list of style keys understood by the editor and frontend compiler. */
	public static function style_properties() {
		return self::styles( array( 'size', 'typography', 'spacing', 'background', 'border', 'effects', 'position', 'overflow', 'flex-container', 'flex-item', 'grid-container', 'grid-item', 'media', 'interaction' ) );
	}

	/** Named style groups used to expose only relevant controls for each widget. */
	private static function style_groups() {
		return array(
			'size'           => array( 'display', 'width', 'maxWidth', 'minWidth', 'height', 'maxHeight', 'minHeight', 'aspectRatio' ),
			'typography'     => array( 'color', 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'lineHeight', 'letterSpacing', 'textAlign', 'textTransform', 'textDecoration' ),
			'spacing'        => array( 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'gap', 'columnGap', 'rowGap' ),
			'background'     => array( 'background', 'backgroundColor' ),
			'border'         => array( 'border', 'borderColor', 'borderWidth', 'borderStyle', 'borderRadius' ),
			'effects'        => array( 'boxShadow', 'opacity', 'transform', 'filter', 'transition' ),
			'position'       => array( 'position', 'top', 'right', 'bottom', 'left', 'inset', 'zIndex' ),
			'overflow'       => array( 'overflow', 'overflowX', 'overflowY' ),
			'flex-container' => array( 'alignItems', 'justifyContent', 'flexDirection', 'flexWrap' ),
			'flex-item'      => array( 'alignSelf', 'flexGrow', 'flexShrink', 'flexBasis', 'order' ),
			'grid-container' => array( 'gridTemplateColumns', 'gridTemplateRows', 'placeItems', 'placeContent' ),
			'grid-item'      => array( 'justifySelf', 'gridColumn', 'gridRow' ),
			'media'          => array( 'objectFit', 'objectPosition' ),
			'interaction'    => array( 'cursor', 'visibility' ),
		);
	}

	/** Core query props shared by Loop Grid and Loop Carousel. */
	private static function loop_query_props() {
		return array(
			'postType'    => self::string( 'post', 'Post type', array( 'group' => 'Query', 'control' => 'option-select', 'optionsSource' => 'postTypes', 'optionValue' => 'slug', 'optionLabel' => 'label' ) ),
			'perPage'     => self::integer( 1, 24, 6, 'Items', array( 'group' => 'Query' ) ),
			'columns'     => self::integer( 1, 6, 3, 'Columns', array( 'group' => 'Layout' ) ),
			'order'       => self::enum( array( 'ASC', 'DESC' ), 'DESC', 'Order', array( 'group' => 'Query' ) ),
			'orderBy'     => self::enum( array( 'date', 'title', 'menu_order', 'modified', 'rand' ), 'date', 'Order by', array( 'group' => 'Query' ) ),
			'taxonomy'    => self::string( '', 'Taxonomy', array( 'group' => 'Filter', 'control' => 'option-select', 'optionsSource' => 'taxonomies', 'optionValue' => 'slug', 'optionLabel' => 'label', 'emptyLabel' => 'No taxonomy filter' ) ),
			'term'        => self::string( '', 'Term slug', array( 'group' => 'Filter' ) ),
			'showImage'   => self::boolean( true, 'Show image', array( 'group' => 'Card' ) ),
			'showExcerpt' => self::boolean( true, 'Show excerpt', array( 'group' => 'Card' ) ),
			'showDate'    => self::boolean( false, 'Show date', array( 'group' => 'Card' ) ),
			'buttonLabel' => self::string( 'Read more', 'Button label', array( 'group' => 'Card' ) ),
		);
	}

	/** Shared behavior for every horizontal carousel engine. */
	private static function carousel_behavior_props( $slides = 3 ) {
		return array(
			'slidesPerView'       => self::integer( 1, 8, $slides, 'Slides per view', array( 'group' => 'Layout' ) ),
			'tabletSlidesPerView' => self::integer( 1, 6, min( 2, $slides ), 'Tablet slides', array( 'group' => 'Responsive' ) ),
			'mobileSlidesPerView' => self::integer( 1, 3, 1, 'Mobile slides', array( 'group' => 'Responsive' ) ),
			'gap'                 => self::css( '24px', 'Gap', array( 'group' => 'Layout' ) ),
			'loop'                => self::boolean( true, 'Loop', array( 'group' => 'Behavior' ) ),
			'autoplay'            => self::boolean( false, 'Autoplay', array( 'group' => 'Behavior' ) ),
			'autoplayDelay'       => self::integer( 1000, 30000, 4500, 'Autoplay delay (ms)', array( 'group' => 'Behavior' ) ),
			'speed'               => self::integer( 100, 3000, 550, 'Transition speed (ms)', array( 'group' => 'Behavior' ) ),
			'pauseOnHover'        => self::boolean( true, 'Pause on hover', array( 'group' => 'Behavior' ) ),
			'navigation'          => self::boolean( true, 'Navigation arrows', array( 'group' => 'Navigation' ) ),
			'pagination'          => self::enum( array( 'none', 'dots', 'fraction' ), 'dots', 'Pagination', array( 'group' => 'Navigation' ) ),
			'centered'            => self::boolean( false, 'Centered slides', array( 'group' => 'Layout' ) ),
			'adaptiveHeight'      => self::boolean( false, 'Adaptive height', array( 'group' => 'Layout' ) ),
			'keyboard'            => self::boolean( true, 'Keyboard navigation', array( 'group' => 'Accessibility' ) ),
		);
	}

	/** Professional widget suite built on shared layout and interaction engines. */
	private static function professional_widgets( $base, $layout_styles, $text_styles, $card_styles, $media_styles ) {
		$carousel = self::carousel_behavior_props( 3 );
		$single   = self::carousel_behavior_props( 1 );
		$widgets  = array();

		$widgets['carousel'] = self::widget( $base, 'Carousel', 'interactive', 'slides', true, $carousel, array(
			'description' => 'Nested-content carousel. Each direct child is one slide and can contain any Cresco widget.',
			'style'       => $layout_styles,
			'parts'       => array( 'root' => self::part( 'Carousel', '&' ), 'track' => self::part( 'Track', '& .cresco-pro-carousel__track' ), 'item' => self::part( 'Slide', '& .cresco-pro-carousel__item' ), 'navigation' => self::part( 'Navigation', '& .cresco-pro-carousel__nav' ), 'pagination' => self::part( 'Pagination', '& .cresco-pro-carousel__pagination' ) ),
		) );
		$widgets['slides'] = self::widget( $base, 'Slides / Hero Slider', 'interactive', 'images-alt2', true, array_merge( $single, array(
			'height'     => self::css( '620px', 'Slider height', array( 'group' => 'Layout' ) ),
			'transition' => self::enum( array( 'slide', 'fade' ), 'slide', 'Transition', array( 'group' => 'Behavior' ) ),
		) ), array( 'description' => 'Hero slider with nested slide content, autoplay, navigation and responsive controls.', 'style' => $layout_styles ) );
		$widgets['loop-carousel'] = self::widget( $base, 'Loop Carousel', 'dynamic', 'images-alt', false, array_merge( self::loop_query_props(), $carousel ), array(
			'description' => 'Dynamic post/CPT query rendered with the shared Carousel Engine.',
			'style'       => $layout_styles,
			'parts'       => array( 'root' => self::part( 'Carousel', '&' ), 'card' => self::part( 'Card', '& .cresco-loop-card' ), 'image' => self::part( 'Image', '& .cresco-loop-card__image img' ), 'title' => self::part( 'Title', '& .cresco-loop-card__body h3' ) ),
		) );
		$widgets['marquee'] = self::widget( $base, 'Infinite Marquee', 'interactive', 'controls-repeat', true, array(
			'direction'    => self::enum( array( 'left', 'right', 'up', 'down' ), 'left', 'Direction', array( 'group' => 'Motion' ) ),
			'duration'     => self::integer( 4, 180, 32, 'Loop duration (seconds)', array( 'group' => 'Motion' ) ),
			'gap'          => self::css( '24px', 'Item gap', array( 'group' => 'Layout' ) ),
			'pauseOnHover' => self::boolean( true, 'Pause on hover', array( 'group' => 'Behavior' ) ),
			'pauseOnFocus' => self::boolean( true, 'Pause on focus', array( 'group' => 'Accessibility' ) ),
			'edgeFade'     => self::boolean( false, 'Fade edges', array( 'group' => 'Appearance' ) ),
		), array( 'description' => 'Native seamless horizontal or vertical loop. Direct children are duplicated automatically.', 'style' => $layout_styles ) );
		$widgets['image-carousel'] = self::widget( $base, 'Image Carousel', 'media', 'format-gallery', false, array_merge( array(
			'images'       => self::json( array(), 'Images', 'gallery', array( 'group' => 'Images', 'control' => 'repeater' ) ),
			'aspectRatio'  => self::css( '16 / 9', 'Image aspect ratio', array( 'group' => 'Images' ) ),
			'objectFit'    => self::enum( array( 'cover', 'contain', 'fill', 'none', 'scale-down' ), 'cover', 'Image fit', array( 'group' => 'Images' ) ),
			'showCaptions' => self::boolean( false, 'Show captions', array( 'group' => 'Caption' ) ),
		), $carousel ), array( 'description' => 'Media-library image carousel powered by the shared Carousel Engine.', 'style' => $media_styles ) );
		$widgets['testimonial-carousel'] = self::widget( $base, 'Testimonial Carousel', 'interactive', 'format-quote', true, $carousel, array( 'description' => 'Carousel optimized for nested Testimonial widgets.', 'style' => $card_styles ) );
		$widgets['logo-carousel'] = self::widget( $base, 'Logo Carousel', 'media', 'screenoptions', true, array_merge( $carousel, array(
			'grayscale' => self::boolean( false, 'Grayscale logos', array( 'group' => 'Appearance' ) ),
		) ), array( 'description' => 'Partner/client logo carousel. Add Image or Site Logo children.', 'style' => $layout_styles ) );
		$widgets['media-carousel'] = self::widget( $base, 'Media Carousel', 'media', 'format-video', true, $carousel, array( 'description' => 'Mixed image, video and content carousel using nested widgets.', 'style' => $layout_styles ) );
		$widgets['before-after'] = self::widget( $base, 'Before / After', 'interactive', 'image-filter', false, array(
			'beforeImage' => self::url( '', 'Before image', array( 'group' => 'Images', 'control' => 'media', 'mediaType' => 'image' ) ),
			'afterImage'  => self::url( '', 'After image', array( 'group' => 'Images', 'control' => 'media', 'mediaType' => 'image' ) ),
			'beforeLabel' => self::string( 'Before', 'Before label', array( 'group' => 'Labels' ) ),
			'afterLabel'  => self::string( 'After', 'After label', array( 'group' => 'Labels' ) ),
			'position'    => self::integer( 0, 100, 50, 'Initial split', array( 'group' => 'Behavior' ) ),
			'aspectRatio' => self::css( '16 / 9', 'Aspect ratio', array( 'group' => 'Layout' ) ),
		), array( 'description' => 'Accessible draggable before/after image comparison.', 'style' => $media_styles ) );
		$widgets['timeline'] = self::widget( $base, 'Timeline', 'interactive', 'editor-ol', true, array(
			'orientation' => self::enum( array( 'vertical', 'horizontal' ), 'vertical', 'Orientation', array( 'group' => 'Layout' ) ),
			'gap'         => self::css( '24px', 'Item gap', array( 'group' => 'Layout' ) ),
			'showLine'    => self::boolean( true, 'Show connecting line', array( 'group' => 'Appearance' ) ),
		), array( 'description' => 'Nested-content process or history timeline.', 'style' => $layout_styles ) );
		$widgets['pricing-table'] = self::widget( $base, 'Pricing Table', 'content', 'tickets-alt', true, array(
			'columns'      => self::integer( 1, 6, 3, 'Columns', array( 'group' => 'Layout' ) ),
			'highlightGap' => self::boolean( true, 'Emphasize featured card', array( 'group' => 'Appearance' ) ),
		), array( 'description' => 'Responsive pricing-card grid using nested content.', 'style' => $layout_styles ) );
		$widgets['countdown'] = self::widget( $base, 'Countdown', 'interactive', 'clock', false, array(
			'targetDate'  => self::string( '', 'Target date/time', array( 'group' => 'Countdown', 'help' => 'ISO 8601 or browser-readable date/time.' ) ),
			'expiredText' => self::string( 'Expired', 'Expired message', array( 'group' => 'Countdown' ) ),
			'showDays'    => self::boolean( true, 'Show days', array( 'group' => 'Units' ) ),
			'showSeconds' => self::boolean( true, 'Show seconds', array( 'group' => 'Units' ) ),
		), array( 'description' => 'Live accessible countdown timer.', 'style' => $text_styles, 'states' => array() ) );
		$widgets['modal'] = self::widget( $base, 'Modal / Popup', 'interactive', 'welcome-view-site', true, array(
			'triggerLabel' => self::string( 'Open popup', 'Trigger label', array( 'group' => 'Trigger' ) ),
			'closeLabel'   => self::string( 'Close', 'Close label', array( 'group' => 'Accessibility' ) ),
			'closeOnEsc'   => self::boolean( true, 'Close on Escape', array( 'group' => 'Behavior' ) ),
			'closeBackdrop'=> self::boolean( true, 'Close on backdrop', array( 'group' => 'Behavior' ) ),
			'maxWidth'     => self::css( '720px', 'Panel max width', array( 'group' => 'Layout' ) ),
		), array( 'description' => 'Accessible modal shell containing arbitrary nested Cresco content.', 'style' => $card_styles ) );
		$widgets['off-canvas'] = self::widget( $base, 'Off Canvas', 'interactive', 'align-right', true, array(
			'triggerLabel' => self::string( 'Open panel', 'Trigger label', array( 'group' => 'Trigger' ) ),
			'side'         => self::enum( array( 'left', 'right', 'top', 'bottom' ), 'right', 'Panel side', array( 'group' => 'Layout' ) ),
			'panelSize'    => self::css( '380px', 'Panel size', array( 'group' => 'Layout' ) ),
			'closeOnEsc'   => self::boolean( true, 'Close on Escape', array( 'group' => 'Behavior' ) ),
		), array( 'description' => 'Drawer/off-canvas panel containing nested widgets.', 'style' => $card_styles ) );
		$widgets['comparison-table'] = self::widget( $base, 'Comparison Table', 'content', 'editor-table', false, array(
			'rows'        => self::string_list( array( 'Feature | Basic | Pro', 'Support | Email | Priority', 'Price | £49 | £99' ), 'Rows', array( 'group' => 'Table', 'help' => 'Separate cells with |. The first row is treated as the table header.' ) ),
			'striped'     => self::boolean( true, 'Striped rows', array( 'group' => 'Appearance' ) ),
			'firstColumn' => self::boolean( true, 'Emphasize first column', array( 'group' => 'Appearance' ) ),
		), array( 'description' => 'Responsive feature or pricing comparison table.', 'style' => $card_styles ) );
		$widgets['hotspot-image'] = self::widget( $base, 'Hotspot Image', 'interactive', 'location-alt', true, array(
			'image'       => self::url( '', 'Background image', array( 'group' => 'Image', 'control' => 'media', 'mediaType' => 'image' ) ),
			'alt'         => self::string( '', 'Alternative text', array( 'group' => 'Accessibility' ) ),
			'aspectRatio' => self::css( '16 / 9', 'Aspect ratio', array( 'group' => 'Image' ) ),
		), array( 'description' => 'Image canvas for absolutely positioned nested hotspot widgets.', 'style' => $media_styles ) );
		$widgets['flip-card'] = self::widget( $base, 'Flip Card', 'interactive', 'image-rotate', true, array(
			'trigger'  => self::enum( array( 'hover', 'click', 'focus' ), 'hover', 'Flip trigger', array( 'group' => 'Behavior' ) ),
			'duration' => self::integer( 100, 2000, 600, 'Duration (ms)', array( 'group' => 'Motion' ) ),
			'axis'     => self::enum( array( 'x', 'y' ), 'y', 'Flip axis', array( 'group' => 'Motion' ) ),
		), array( 'description' => 'Two-sided card. First child is front, second child is back.', 'style' => $card_styles ) );
		$widgets['animated-headline'] = self::widget( $base, 'Animated Headline', 'interactive', 'editor-textcolor', false, array(
			'prefix'   => self::string( '', 'Prefix', array( 'group' => 'Content' ) ),
			'words'    => self::string_list( array( 'Better', 'Faster', 'Clearer' ), 'Animated words', array( 'group' => 'Content' ) ),
			'suffix'   => self::string( '', 'Suffix', array( 'group' => 'Content' ) ),
			'effect'   => self::enum( array( 'fade', 'slide-up', 'type' ), 'fade', 'Effect', array( 'group' => 'Motion' ) ),
			'interval' => self::integer( 500, 10000, 2200, 'Interval (ms)', array( 'group' => 'Motion' ) ),
		), array( 'description' => 'Rotating headline words with reduced-motion fallback.', 'style' => $text_styles ) );
		$widgets['progress-circle'] = self::widget( $base, 'Progress Circle', 'interactive', 'chart-pie', false, array(
			'value'     => self::integer( 0, 100, 75, 'Value', array( 'group' => 'Progress' ) ),
			'label'     => self::string( 'Progress', 'Label', array( 'group' => 'Content' ) ),
			'showValue' => self::boolean( true, 'Show value', array( 'group' => 'Progress' ) ),
			'size'      => self::css( '140px', 'Size', array( 'group' => 'Layout' ) ),
			'thickness' => self::css( '10px', 'Ring thickness', array( 'group' => 'Layout' ) ),
		), array( 'description' => 'Circular progress indicator with accessible numeric value.', 'style' => $card_styles ) );
		$widgets['rating'] = self::widget( $base, 'Rating', 'content', 'star-filled', false, array(
			'value' => self::number( 0, 5, 4.9, 'Rating', array( 'group' => 'Rating' ) ),
			'max'   => self::integer( 1, 10, 5, 'Maximum', array( 'group' => 'Rating' ) ),
			'label' => self::string( 'Customer rating', 'Accessible label', array( 'group' => 'Accessibility' ) ),
		), array( 'description' => 'Star rating with accessible text.', 'style' => $text_styles, 'states' => array() ) );
		$widgets['site-search'] = self::widget( $base, 'Site Search', 'site', 'search', false, array(
			'placeholder' => self::string( 'Search…', 'Placeholder', array( 'group' => 'Search' ) ),
			'buttonLabel' => self::string( 'Search', 'Button label', array( 'group' => 'Search' ) ),
			'ariaLabel'   => self::string( 'Search site', 'ARIA label', array( 'group' => 'Accessibility' ) ),
		), array( 'description' => 'Native WordPress site search form.', 'style' => $card_styles ) );
		$widgets['advanced-breadcrumbs'] = self::widget( $base, 'Advanced Breadcrumbs', 'site', 'arrow-right-alt2', false, array(
			'separator'   => self::string( '/', 'Separator', array( 'group' => 'Display' ) ),
			'showHome'    => self::boolean( true, 'Show home', array( 'group' => 'Display' ) ),
			'homeLabel'   => self::string( 'Home', 'Home label', array( 'group' => 'Display' ) ),
			'showCurrent' => self::boolean( true, 'Show current page', array( 'group' => 'Display' ) ),
		), array( 'description' => 'Breadcrumb preset with additional display semantics.', 'style' => $text_styles ) );
		$widgets['map'] = self::widget( $base, 'Map', 'media', 'location', false, array(
			'address'   => self::string( '', 'Address', array( 'group' => 'Location' ) ),
			'zoom'      => self::integer( 1, 20, 14, 'Zoom', array( 'group' => 'Location' ) ),
			'height'    => self::css( '420px', 'Height', array( 'group' => 'Layout' ) ),
			'title'     => self::string( 'Map location', 'Accessible title', array( 'group' => 'Accessibility' ) ),
		), array( 'description' => 'Location map using the constrained Google Maps embed endpoint.', 'style' => $media_styles, 'states' => array() ) );

		return $widgets;
	}

	private static function styles( $groups ) {
		$available = self::style_groups();
		$output    = array();
		foreach ( (array) $groups as $group ) {
			if ( ! isset( $available[ $group ] ) ) continue;
			foreach ( $available[ $group ] as $key ) {
				if ( ! in_array( $key, $output, true ) ) $output[] = $key;
			}
		}
		return $output;
	}

	/** Add normalized AI-friendly blueprints without changing the saved node schema. */
	private static function with_blueprints( $widgets ) {
		$references = array(
			'button'    => array( 'e-button' ),
			'image'     => array( 'image' ),
			'icon-box'  => array( 'icon-box' ),
			'gallery'   => array( 'image-gallery' ),
			'accordion' => array( 'nested-accordion' ),
			'tabs'      => array( 'nested-tabs' ),
			'counter'   => array( 'counter' ),
			'progress'  => array( 'progress' ),
			'carousel'  => array( 'nested-carousel', 'loop-carousel' ),
			'slides'    => array( 'slides' ),
		);
		$part_states = array(
			'button'    => array( 'root' => array( 'normal', 'hover', 'focus', 'active' ) ),
			'icon-box'  => array( 'title' => array( 'normal', 'hover', 'focus' ) ),
			'accordion' => array( 'trigger' => array( 'normal', 'hover', 'focus', 'active' ) ),
			'tabs'      => array( 'tab' => array( 'normal', 'hover', 'focus' ), 'activeTab' => array( 'active' ) ),
		);
		$style_shorthands = array(
			'borderWidth' => array( 'order' => array( 'top', 'right', 'bottom', 'left' ), 'css' => 'border-width', 'linkedByDefault' => true ),
			'borderStyle' => array( 'order' => array( 'top', 'right', 'bottom', 'left' ), 'css' => 'border-style', 'linkedByDefault' => true ),
			'borderColor' => array( 'order' => array( 'top', 'right', 'bottom', 'left' ), 'css' => 'border-color', 'linkedByDefault' => true ),
			'borderRadius'=> array( 'order' => array( 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ), 'css' => 'border-radius', 'linkedByDefault' => true ),
		);

		foreach ( $widgets as $type => &$widget ) {
			$groups = array();
			foreach ( (array) ( $widget['props'] ?? array() ) as $key => $schema ) {
				$group = (string) ( $schema['group'] ?? 'General' );
				if ( ! isset( $groups[ $group ] ) ) $groups[ $group ] = array();
				$groups[ $group ][] = $key;
			}
			$blueprint = array(
				'schema'          => 'cresco-widget-blueprint/v1',
				'controlGroups'   => $groups,
				'styleTargets'    => array_keys( (array) ( $widget['parts'] ?? array() ) ),
				'states'          => array_values( array_unique( array_merge( array( 'normal' ), (array) ( $widget['states'] ?? array() ) ) ) ),
				'responsive'      => ! empty( $widget['responsive'] ),
				'styleShorthands' => $style_shorthands,
			);
			if ( isset( $part_states[ $type ] ) ) $blueprint['partStates'] = $part_states[ $type ];
			if ( isset( $references[ $type ] ) ) $blueprint['reference'] = array( 'source' => 'elementor-control-catalog', 'widgets' => $references[ $type ], 'mode' => 'capability-reference' );
			if ( in_array( $type, array( 'carousel', 'slides', 'testimonial-carousel', 'logo-carousel', 'media-carousel', 'marquee', 'timeline', 'pricing-table', 'hotspot-image', 'flip-card', 'modal', 'off-canvas' ), true ) ) {
				$blueprint['nestedItemModel'] = array( 'mode' => 'direct-children', 'editableInStructure' => true );
			}
			$widget['blueprint'] = $blueprint;
		}
		unset( $widget );
		return $widgets;
	}

	private static function widget( $base, $label, $category, $icon, $allows_children, $props, $extra = array() ) {
		return array_merge( $base, $extra, array( 'label' => $label, 'category' => $category, 'icon' => $icon, 'allowsChildren' => (bool) $allows_children, 'props' => $props ) );
	}

	private static function part( $label, $selector ) { return array( 'label' => $label, 'selector' => $selector ); }
	private static function schema( $type, $default, $label, $extra = array() ) { return array_merge( array( 'type' => $type, 'default' => $default, 'label' => $label ), $extra ); }
	private static function string( $default, $label, $extra = array() ) { return self::schema( 'string', $default, $label, $extra ); }
	private static function text( $default, $label, $extra = array() ) { return self::schema( 'text', $default, $label, $extra ); }
	private static function textarea( $default, $label, $extra = array() ) { return self::schema( 'text', $default, $label, array_merge( array( 'control' => 'textarea' ), $extra ) ); }
	private static function richtext( $default, $label, $extra = array() ) { return self::schema( 'richtext', $default, $label, array_merge( array( 'control' => 'textarea' ), $extra ) ); }
	private static function url( $default, $label, $extra = array() ) { return self::schema( 'url', $default, $label, $extra ); }
	private static function css( $default, $label, $extra = array() ) { return self::schema( 'css', $default, $label, $extra ); }
	private static function boolean( $default, $label, $extra = array() ) { return self::schema( 'bool', (bool) $default, $label, array_merge( array( 'control' => 'toggle' ), $extra ) ); }
	private static function integer( $min, $max, $default, $label, $extra = array() ) { return self::schema( 'int', $default, $label, array_merge( array( 'min' => $min, 'max' => $max, 'control' => 'number' ), $extra ) ); }
	private static function number( $min, $max, $default, $label, $extra = array() ) { return self::schema( 'number', $default, $label, array_merge( array( 'min' => $min, 'max' => $max, 'control' => 'number' ), $extra ) ); }
	private static function enum( $values, $default, $label, $extra = array() ) { return self::schema( 'enum', $default, $label, array_merge( array( 'values' => $values, 'control' => 'select' ), $extra ) ); }
	private static function string_list( $default, $label, $extra = array() ) { return self::schema( 'string_list', $default, $label, array_merge( array( 'control' => 'textarea' ), $extra ) ); }
	private static function json( $default, $label, $shape, $extra = array() ) { return self::schema( 'json', $default, $label, array_merge( array( 'control' => 'json', 'shape' => $shape ), $extra ) ); }

	private function __construct() {}
}
