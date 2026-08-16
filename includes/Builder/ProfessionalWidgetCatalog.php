<?php
/**
 * Professional widget catalog expansion for Cresco Canvas.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProfessionalWidgetCatalog {
	/** Extend the canonical catalog without changing the saved session schema. */
	public static function extend( $widgets ) {
		$widgets = is_array( $widgets ) ? $widgets : array();
		$layout  = (array) ( $widgets['container']['style'] ?? array() );
		$text    = (array) ( $widgets['text']['style'] ?? $layout );
		$card    = (array) ( $widgets['icon-box']['style'] ?? $layout );
		$media   = (array) ( $widgets['image']['style'] ?? $layout );

		// Upgrade the existing Gallery instead of creating another basic gallery.
		if ( isset( $widgets['gallery']['props'] ) ) {
			$widgets['gallery']['props']['layoutMode'] = self::enum( array( 'grid', 'masonry' ), 'grid', 'Layout mode', array( 'group' => 'Layout' ) );
			$widgets['gallery']['props']['tabletColumns'] = self::integer( 1, 6, 2, 'Tablet columns', array( 'group' => 'Responsive' ) );
			$widgets['gallery']['props']['mobileColumns'] = self::integer( 1, 3, 1, 'Mobile columns', array( 'group' => 'Responsive' ) );
			$widgets['gallery']['props']['hoverZoom'] = self::boolean( false, 'Zoom image on hover', array( 'group' => 'Behavior' ) );
			$widgets['gallery']['props']['lightboxNavigation'] = self::boolean( true, 'Lightbox navigation', array( 'group' => 'Behavior' ) );
		}

		$widgets['nested-card'] = self::widget( 'Nested Card', 'content', 'index-card', true, array(
			'url'       => self::url( '', 'Card link', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target'    => self::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
			'rel'       => self::string( '', 'Rel', array( 'group' => 'Link' ) ),
			'ariaLabel' => self::string( '', 'Accessible label', array( 'group' => 'Accessibility' ) ),
			'hoverLift' => self::boolean( true, 'Lift on hover', array( 'group' => 'Interaction' ) ),
		), $card, array( 'hover', 'focus' ), array(
			'root' => self::part( 'Card', '&' ),
			'body' => self::part( 'Content', '& > *' ),
		) );

		$widgets['clickable-container'] = self::widget( 'Clickable Container', 'layout', 'external', true, array(
			'url'       => self::url( '#', 'Destination', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target'    => self::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
			'rel'       => self::string( '', 'Rel', array( 'group' => 'Link' ) ),
			'ariaLabel' => self::string( '', 'Accessible label', array( 'group' => 'Accessibility' ) ),
		), $layout, array( 'hover', 'focus' ) );

		$widgets['cta'] = self::widget( 'CTA / Banner', 'content', 'megaphone', true, array(
			'layout'  => self::enum( array( 'row', 'column' ), 'row', 'Content direction', array( 'group' => 'Layout' ) ),
			'align'   => self::enum( array( 'start', 'center', 'end', 'stretch' ), 'center', 'Align items', array( 'group' => 'Layout' ) ),
			'justify' => self::enum( array( 'start', 'center', 'end', 'space-between' ), 'space-between', 'Justify content', array( 'group' => 'Layout' ) ),
			'gap'     => self::css( '24px', 'Content gap', array( 'group' => 'Layout' ) ),
			'stackAt' => self::enum( array( 'never', 'tablet', 'mobile' ), 'mobile', 'Stack at', array( 'group' => 'Responsive' ) ),
		), $card, array( 'hover' ) );

		$widgets['icon-list'] = self::widget( 'Icon List / Feature List', 'content', 'editor-ul', false, array(
			'items' => self::json( array(
				array( 'icon' => 'yes-alt', 'title' => 'Feature', 'text' => 'Describe the benefit.', 'url' => '' ),
				array( 'icon' => 'yes-alt', 'title' => 'Feature', 'text' => 'Describe the benefit.', 'url' => '' ),
			), 'Items', 'icon_list', array( 'group' => 'Items', 'control' => 'repeater' ) ),
			'iconPosition' => self::enum( array( 'start', 'end' ), 'start', 'Icon position', array( 'group' => 'Layout' ) ),
			'gap'          => self::css( '12px', 'Item gap', array( 'group' => 'Layout' ) ),
			'divider'      => self::boolean( false, 'Divider between items', array( 'group' => 'Appearance' ) ),
		), $text, array( 'hover' ), array(
			'root'  => self::part( 'List', '&' ),
			'item'  => self::part( 'Item', '& .cresco-pro-icon-list__item' ),
			'icon'  => self::part( 'Icon', '& .cresco-pro-icon-list__icon' ),
			'title' => self::part( 'Title', '& .cresco-pro-icon-list__title' ),
			'text'  => self::part( 'Text', '& .cresco-pro-icon-list__text' ),
		) );

		$widgets['badge'] = self::widget( 'Badge / Tag / Pill', 'content', 'tag', false, array(
			'text'         => self::string( 'Badge', 'Text', array( 'group' => 'Content' ) ),
			'icon'         => self::string( '', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'iconPosition' => self::enum( array( 'before', 'after' ), 'before', 'Icon position', array( 'group' => 'Icon' ) ),
			'url'          => self::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target'       => self::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
		), $text, array( 'hover', 'focus' ), array( 'root' => self::part( 'Badge', '&' ), 'icon' => self::part( 'Icon', '& .dashicons' ) ) );

		$widgets['advanced-divider'] = self::widget( 'Advanced Divider / Shape', 'content', 'minus', false, array(
			'lineStyle' => self::enum( array( 'solid', 'dashed', 'dotted', 'double' ), 'solid', 'Line style', array( 'group' => 'Line' ) ),
			'thickness' => self::css( '1px', 'Thickness', array( 'group' => 'Line' ) ),
			'length'    => self::css( '100%', 'Length', array( 'group' => 'Line' ) ),
			'align'     => self::enum( array( 'start', 'center', 'end' ), 'center', 'Alignment', array( 'group' => 'Layout' ) ),
			'text'      => self::string( '', 'Center text', array( 'group' => 'Content' ) ),
			'icon'      => self::string( '', 'Center icon', array( 'group' => 'Content', 'control' => 'icon' ) ),
		), $text, array(), array( 'root' => self::part( 'Divider', '&' ), 'line' => self::part( 'Line', '& .cresco-pro-divider__line' ), 'content' => self::part( 'Center content', '& .cresco-pro-divider__content' ) ) );

		$widgets['team-member'] = self::widget( 'Team Member / Profile Card', 'content', 'admin-users', false, array(
			'image'    => self::url( '', 'Photo', array( 'group' => 'Media', 'control' => 'media', 'mediaType' => 'image' ) ),
			'alt'      => self::string( '', 'Photo alt text', array( 'group' => 'Accessibility' ) ),
			'name'     => self::string( 'Team member', 'Name', array( 'group' => 'Content' ) ),
			'role'     => self::string( 'Role', 'Role', array( 'group' => 'Content' ) ),
			'bio'      => self::textarea( '', 'Biography', array( 'group' => 'Content' ) ),
			'socials'  => self::json( array(), 'Social links', 'social', array( 'group' => 'Social', 'control' => 'repeater' ) ),
			'imagePosition' => self::enum( array( 'top', 'start', 'end' ), 'top', 'Photo position', array( 'group' => 'Layout' ) ),
			'imageSize' => self::css( '96px', 'Photo size', array( 'group' => 'Layout' ) ),
		), $card, array( 'hover' ), array(
			'root' => self::part( 'Profile', '&' ), 'image' => self::part( 'Photo', '& .cresco-pro-team__image' ),
			'name' => self::part( 'Name', '& .cresco-pro-team__name' ), 'role' => self::part( 'Role', '& .cresco-pro-team__role' ),
			'bio' => self::part( 'Biography', '& .cresco-pro-team__bio' ), 'socials' => self::part( 'Social links', '& .cresco-pro-team__socials' ),
		) );

		$widgets['faq'] = self::widget( 'FAQ', 'interactive', 'editor-help', false, array(
			'items' => self::json( array(
				array( 'title' => 'Frequently asked question', 'content' => 'Add the answer here.', 'open' => true ),
			), 'Questions', 'accordion', array( 'group' => 'Items', 'control' => 'repeater' ) ),
			'allowMulti'   => self::boolean( false, 'Allow multiple open', array( 'group' => 'Behavior' ) ),
			'faqSchema'    => self::boolean( true, 'Output FAQ schema', array( 'group' => 'SEO' ) ),
			'expandIcon'   => self::string( 'plus-alt2', 'Expand icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'collapseIcon' => self::string( 'minus', 'Collapse icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
		), $card, array( 'hover', 'focus' ) );

		$widgets['data-table'] = self::widget( 'Data Table', 'content', 'editor-table', false, array(
			'rows' => self::string_list( array( 'Name | Value | Notes', 'Alpha | 10 | Example', 'Beta | 20 | Example' ), 'Rows', array( 'group' => 'Table', 'help' => 'Separate cells with |.' ) ),
			'caption'           => self::string( '', 'Caption', array( 'group' => 'Table' ) ),
			'firstRowHeader'    => self::boolean( true, 'First row is header', array( 'group' => 'Semantics' ) ),
			'firstColumnHeader' => self::boolean( false, 'First column is header', array( 'group' => 'Semantics' ) ),
			'striped'           => self::boolean( true, 'Striped rows', array( 'group' => 'Appearance' ) ),
			'hoverRows'         => self::boolean( true, 'Highlight row on hover', array( 'group' => 'Appearance' ) ),
			'mobileMode'        => self::enum( array( 'scroll', 'stack' ), 'scroll', 'Mobile behavior', array( 'group' => 'Responsive' ) ),
		), $card, array( 'hover' ) );

		$widgets['table-of-contents'] = self::widget( 'Table of Contents', 'site', 'list-view', false, array(
			'title'        => self::string( 'On this page', 'Title', array( 'group' => 'Content' ) ),
			'levels'       => self::string( '2,3', 'Heading levels', array( 'group' => 'Content', 'help' => 'Comma separated, e.g. 2,3,4.' ) ),
			'ordered'      => self::boolean( false, 'Numbered list', array( 'group' => 'Appearance' ) ),
			'smoothScroll' => self::boolean( true, 'Smooth scroll', array( 'group' => 'Behavior' ) ),
			'collapsible'  => self::boolean( false, 'Collapsible', array( 'group' => 'Behavior' ) ),
			'offset'       => self::css( '80px', 'Scroll offset', array( 'group' => 'Behavior' ) ),
		), $card, array( 'hover', 'focus' ) );

		$widgets['video-popup'] = self::widget( 'Video Popup', 'media', 'video-alt3', false, array(
			'videoUrl'     => self::url( '', 'Video URL', array( 'group' => 'Video', 'control' => 'link' ) ),
			'poster'       => self::url( '', 'Poster image', array( 'group' => 'Media', 'control' => 'media', 'mediaType' => 'image' ) ),
			'triggerLabel' => self::string( 'Play video', 'Trigger label', array( 'group' => 'Trigger' ) ),
			'closeLabel'   => self::string( 'Close video', 'Close label', array( 'group' => 'Accessibility' ) ),
			'autoplay'     => self::boolean( true, 'Autoplay after open', array( 'group' => 'Behavior' ) ),
			'aspectRatio'  => self::css( '16 / 9', 'Aspect ratio', array( 'group' => 'Layout' ) ),
			'maxWidth'     => self::css( '960px', 'Popup max width', array( 'group' => 'Layout' ) ),
		), $media, array( 'hover', 'focus' ) );

		$widgets['stats-card'] = self::widget( 'Stats Card', 'content', 'chart-bar', false, array(
			'value'       => self::number( -1000000000, 1000000000, 98, 'Value', array( 'group' => 'Value' ) ),
			'prefix'      => self::string( '', 'Prefix', array( 'group' => 'Value' ) ),
			'suffix'      => self::string( '%', 'Suffix', array( 'group' => 'Value' ) ),
			'decimals'    => self::integer( 0, 4, 0, 'Decimals', array( 'group' => 'Value' ) ),
			'title'       => self::string( 'Success rate', 'Title', array( 'group' => 'Content' ) ),
			'description' => self::textarea( '', 'Description', array( 'group' => 'Content' ) ),
			'icon'        => self::string( 'chart-bar', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'duration'    => self::integer( 0, 10000, 1200, 'Animation duration (ms)', array( 'group' => 'Animation' ) ),
		), $card, array( 'hover' ) );

		$widgets['advanced-icon'] = self::widget( 'Advanced Icon / SVG', 'media', 'star-filled', false, array(
			'source' => self::enum( array( 'dashicon', 'image', 'svg' ), 'dashicon', 'Source', array( 'group' => 'Icon' ) ),
			'icon'   => self::string( 'star-filled', 'Dashicon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'media'  => self::url( '', 'Image or SVG file', array( 'group' => 'Icon', 'control' => 'media' ) ),
			'alt'    => self::string( '', 'Accessible label', array( 'group' => 'Accessibility' ) ),
			'url'    => self::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target' => self::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
			'size'   => self::css( '32px', 'Icon size', array( 'group' => 'Layout' ) ),
		), $media, array( 'hover', 'focus' ) );

		$widgets['logo-grid'] = self::widget( 'Logo Grid', 'media', 'screenoptions', true, array(
			'columns'       => self::integer( 1, 8, 5, 'Columns', array( 'group' => 'Layout' ) ),
			'tabletColumns' => self::integer( 1, 6, 3, 'Tablet columns', array( 'group' => 'Responsive' ) ),
			'mobileColumns' => self::integer( 1, 3, 2, 'Mobile columns', array( 'group' => 'Responsive' ) ),
			'gap'           => self::css( '24px', 'Gap', array( 'group' => 'Layout' ) ),
			'grayscale'     => self::boolean( false, 'Grayscale logos', array( 'group' => 'Appearance' ) ),
		), $layout, array( 'hover' ) );

		$widgets['steps'] = self::widget( 'Progress Steps / Process', 'interactive', 'editor-ol', true, array(
			'orientation' => self::enum( array( 'horizontal', 'vertical' ), 'horizontal', 'Orientation', array( 'group' => 'Layout' ) ),
			'gap'         => self::css( '24px', 'Step gap', array( 'group' => 'Layout' ) ),
			'numbered'    => self::boolean( true, 'Show step numbers', array( 'group' => 'Appearance' ) ),
			'startNumber' => self::integer( 0, 99, 1, 'Start number', array( 'group' => 'Appearance' ) ),
			'showLine'    => self::boolean( true, 'Show connector', array( 'group' => 'Appearance' ) ),
		), $layout, array( 'hover' ) );

		$widgets['alert'] = self::widget( 'Alert / Notice', 'content', 'warning', false, array(
			'tone'        => self::enum( array( 'neutral', 'info', 'success', 'warning', 'error' ), 'info', 'Tone', array( 'group' => 'Appearance' ) ),
			'title'       => self::string( 'Notice', 'Title', array( 'group' => 'Content' ) ),
			'text'        => self::richtext( 'Add an important message.', 'Message', array( 'group' => 'Content' ) ),
			'icon'        => self::string( 'info-outline', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'dismissible' => self::boolean( false, 'Dismissible', array( 'group' => 'Behavior' ) ),
			'closeLabel'  => self::string( 'Dismiss', 'Close label', array( 'group' => 'Accessibility' ) ),
		), $card, array() );

		$widgets['blockquote'] = self::widget( 'Quote / Blockquote', 'content', 'format-quote', false, array(
			'quote'     => self::richtext( 'A memorable quote.', 'Quote', array( 'group' => 'Content' ) ),
			'cite'      => self::string( 'Author', 'Citation', array( 'group' => 'Citation' ) ),
			'role'      => self::string( '', 'Role / source', array( 'group' => 'Citation' ) ),
			'url'       => self::url( '', 'Citation link', array( 'group' => 'Citation', 'control' => 'link' ) ),
			'quoteMark' => self::boolean( true, 'Show quote mark', array( 'group' => 'Appearance' ) ),
		), $text, array() );

		$widgets['mega-menu'] = self::widget( 'Mega Menu', 'site', 'menu', false, array(
			'menu' => self::integer( 0, PHP_INT_MAX, 0, 'WordPress menu', array( 'group' => 'Menu', 'control' => 'option-select', 'optionsSource' => 'menus', 'optionValue' => 'id', 'optionLabel' => 'label', 'emptyLabel' => 'Choose a WordPress menu' ) ),
			'depth'            => self::integer( 1, 5, 3, 'Depth', array( 'group' => 'Menu' ) ),
			'openOn'           => self::enum( array( 'hover', 'click' ), 'hover', 'Open submenu on', array( 'group' => 'Behavior' ) ),
			'panelWidth'       => self::css( 'min(960px, 90vw)', 'Mega panel width', array( 'group' => 'Layout' ) ),
			'fullWidth'        => self::boolean( false, 'Full-width panels', array( 'group' => 'Layout' ) ),
			'mobileDrawer'     => self::boolean( true, 'Use mobile drawer', array( 'group' => 'Responsive' ) ),
			'mobileBreakpoint' => self::integer( 480, 1280, 768, 'Mobile breakpoint (px)', array( 'group' => 'Responsive' ) ),
		), $card, array( 'hover', 'focus' ) );

		$widgets['nested-tabs'] = self::widget( 'Nested Tabs', 'interactive', 'index-card', true, array(
			'direction'         => self::enum( array( 'top', 'bottom', 'start', 'end' ), 'top', 'Direction', array( 'group' => 'Layout' ) ),
			'justify'           => self::enum( array( 'start', 'center', 'end', 'stretch' ), 'start', 'Justify tabs', array( 'group' => 'Layout' ) ),
			'tabGap'            => self::css( '4px', 'Tab gap', array( 'group' => 'Layout' ) ),
			'activation'        => self::enum( array( 'automatic', 'manual' ), 'automatic', 'Keyboard activation', array( 'group' => 'Accessibility' ) ),
			'deepLink'          => self::boolean( false, 'Update URL hash', array( 'group' => 'Behavior' ) ),
			'mobileMode'        => self::enum( array( 'tabs', 'accordion' ), 'accordion', 'Mobile mode', array( 'group' => 'Responsive' ) ),
			'animationDuration' => self::integer( 0, 1500, 200, 'Animation duration (ms)', array( 'group' => 'Motion' ) ),
		), $card, array( 'hover', 'focus', 'active' ), array( 'root' => self::part( 'Tabs', '&' ), 'list' => self::part( 'Tab list', '& .cresco-pro-nested-tabs__list' ), 'tab' => self::part( 'Tab', '& [role="tab"]' ), 'panel' => self::part( 'Panel', '& [role="tabpanel"]' ) ) );

		$widgets['tab-panel'] = self::widget( 'Tab Panel', 'interactive', 'excerpt-view', true, array(
			'title'    => self::string( 'Tab', 'Tab title', array( 'group' => 'Content' ) ),
			'icon'     => self::string( '', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'disabled' => self::boolean( false, 'Disabled', array( 'group' => 'Behavior' ) ),
			'slug'     => self::string( '', 'URL slug', array( 'group' => 'Behavior' ) ),
		), $card, array() );

		$widgets['nested-accordion'] = self::widget( 'Nested Accordion', 'interactive', 'menu-alt3', true, array(
			'allowMulti'        => self::boolean( false, 'Allow multiple open', array( 'group' => 'Behavior' ) ),
			'iconPosition'      => self::enum( array( 'start', 'end' ), 'end', 'Icon position', array( 'group' => 'Icon' ) ),
			'expandIcon'        => self::string( 'plus-alt2', 'Expand icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'collapseIcon'      => self::string( 'minus', 'Collapse icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'deepLink'          => self::boolean( false, 'Update URL hash', array( 'group' => 'Behavior' ) ),
			'animationDuration' => self::integer( 0, 1500, 200, 'Animation duration (ms)', array( 'group' => 'Motion' ) ),
		), $card, array( 'hover', 'focus', 'active' ) );

		$widgets['accordion-item'] = self::widget( 'Accordion Item', 'interactive', 'excerpt-view', true, array(
			'title'      => self::string( 'Accordion item', 'Title', array( 'group' => 'Content' ) ),
			'open'       => self::boolean( false, 'Open by default', array( 'group' => 'Behavior' ) ),
			'headingTag' => self::enum( array( 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3', 'Heading tag', array( 'group' => 'Semantics' ) ),
			'slug'       => self::string( '', 'URL slug', array( 'group' => 'Behavior' ) ),
		), $card, array() );

		$widgets['filterable-grid'] = self::widget( 'Filterable Grid / Portfolio', 'interactive', 'grid-view', true, array(
			'allLabel'    => self::string( 'All', 'All filter label', array( 'group' => 'Filters' ) ),
			'filterAlign' => self::enum( array( 'start', 'center', 'end' ), 'center', 'Filter alignment', array( 'group' => 'Filters' ) ),
			'columns'     => self::integer( 1, 6, 3, 'Columns', array( 'group' => 'Layout' ) ),
			'tabletColumns' => self::integer( 1, 4, 2, 'Tablet columns', array( 'group' => 'Responsive' ) ),
			'mobileColumns' => self::integer( 1, 2, 1, 'Mobile columns', array( 'group' => 'Responsive' ) ),
			'gap'         => self::css( '24px', 'Grid gap', array( 'group' => 'Layout' ) ),
			'animationDuration' => self::integer( 0, 1200, 220, 'Filter animation (ms)', array( 'group' => 'Motion' ) ),
		), $layout, array( 'hover', 'focus' ) );

		$widgets['filter-item'] = self::widget( 'Filter Item', 'interactive', 'excerpt-view', true, array(
			'category' => self::string( 'general', 'Category slug', array( 'group' => 'Filter' ) ),
			'categoryLabel' => self::string( 'General', 'Category label', array( 'group' => 'Filter' ) ),
		), $card, array( 'hover' ) );

		return $widgets;
	}

	private static function widget( $label, $category, $icon, $allows_children, $props, $style, $states = array( 'hover' ), $parts = array() ) {
		return array(
			'label'          => $label,
			'category'       => $category,
			'icon'           => $icon,
			'allowsChildren' => (bool) $allows_children,
			'props'          => $props,
			'style'          => array_values( array_unique( (array) $style ) ),
			'responsive'     => true,
			'states'         => array_values( array_unique( (array) $states ) ),
			'parts'          => $parts ?: array( 'root' => self::part( 'Root', '&' ) ),
			'description'    => $label . ' professional widget.',
		);
	}

	private static function part( $label, $selector ) { return array( 'label' => $label, 'selector' => $selector ); }
	private static function schema( $type, $default, $label, $extra = array() ) { return array_merge( array( 'type' => $type, 'default' => $default, 'label' => $label ), $extra ); }
	private static function string( $default, $label, $extra = array() ) { return self::schema( 'string', $default, $label, $extra ); }
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
