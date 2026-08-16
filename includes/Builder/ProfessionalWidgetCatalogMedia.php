<?php
/** Media, data, and navigation professional widget definitions. */
namespace CrescoCanvas\Builder;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProfessionalWidgetCatalogMedia {
	public static function extend( $widgets, $styles ) {
		$f = ProfessionalWidgetCatalog::class;
		$layout = $styles['layout']; $card = $styles['card']; $media = $styles['media'];

		if ( isset( $widgets['gallery']['props'] ) ) {
			$widgets['gallery']['props']['layoutMode'] = $f::enum( array( 'grid', 'masonry' ), 'grid', 'Layout mode', array( 'group' => 'Layout' ) );
			$widgets['gallery']['props']['tabletColumns'] = $f::integer( 1, 6, 2, 'Tablet columns', array( 'group' => 'Responsive' ) );
			$widgets['gallery']['props']['mobileColumns'] = $f::integer( 1, 3, 1, 'Mobile columns', array( 'group' => 'Responsive' ) );
			$widgets['gallery']['props']['hoverZoom'] = $f::boolean( false, 'Zoom image on hover', array( 'group' => 'Behavior' ) );
			$widgets['gallery']['props']['lightboxNavigation'] = $f::boolean( true, 'Lightbox navigation', array( 'group' => 'Behavior' ) );
		}

		$widgets['data-table'] = $f::widget( 'Data Table', 'content', 'editor-table', false, array(
			'rows' => $f::string_list( array( 'Name | Value | Notes', 'Alpha | 10 | Example', 'Beta | 20 | Example' ), 'Rows', array( 'group' => 'Table', 'help' => 'Separate cells with |.' ) ),
			'caption' => $f::string( '', 'Caption', array( 'group' => 'Table' ) ),
			'firstRowHeader' => $f::boolean( true, 'First row is header', array( 'group' => 'Semantics' ) ),
			'firstColumnHeader' => $f::boolean( false, 'First column is header', array( 'group' => 'Semantics' ) ),
			'striped' => $f::boolean( true, 'Striped rows', array( 'group' => 'Appearance' ) ),
			'hoverRows' => $f::boolean( true, 'Highlight row on hover', array( 'group' => 'Appearance' ) ),
			'mobileMode' => $f::enum( array( 'scroll', 'stack' ), 'scroll', 'Mobile behavior', array( 'group' => 'Responsive' ) ),
		), $card );

		$widgets['table-of-contents'] = $f::widget( 'Table of Contents', 'site', 'list-view', false, array(
			'title' => $f::string( 'On this page', 'Title', array( 'group' => 'Content' ) ),
			'levels' => $f::string( '2,3', 'Heading levels', array( 'group' => 'Content', 'help' => 'Comma separated, e.g. 2,3,4.' ) ),
			'ordered' => $f::boolean( false, 'Numbered list', array( 'group' => 'Appearance' ) ),
			'smoothScroll' => $f::boolean( true, 'Smooth scroll', array( 'group' => 'Behavior' ) ),
			'collapsible' => $f::boolean( false, 'Collapsible', array( 'group' => 'Behavior' ) ),
			'offset' => $f::css( '80px', 'Scroll offset', array( 'group' => 'Behavior' ) ),
		), $card, array( 'hover', 'focus' ) );

		$widgets['video-popup'] = $f::widget( 'Video Popup', 'media', 'video-alt3', false, array(
			'videoUrl' => $f::url( '', 'Video URL', array( 'group' => 'Video', 'control' => 'link' ) ),
			'poster' => $f::url( '', 'Poster image', array( 'group' => 'Media', 'control' => 'media', 'mediaType' => 'image' ) ),
			'triggerLabel' => $f::string( 'Play video', 'Trigger label', array( 'group' => 'Trigger' ) ),
			'closeLabel' => $f::string( 'Close video', 'Close label', array( 'group' => 'Accessibility' ) ),
			'autoplay' => $f::boolean( true, 'Autoplay after open', array( 'group' => 'Behavior' ) ),
			'aspectRatio' => $f::css( '16 / 9', 'Aspect ratio', array( 'group' => 'Layout' ) ),
			'maxWidth' => $f::css( '960px', 'Popup max width', array( 'group' => 'Layout' ) ),
		), $media, array( 'hover', 'focus' ) );

		$widgets['advanced-icon'] = $f::widget( 'Advanced Icon / SVG', 'media', 'star-filled', false, array(
			'source' => $f::enum( array( 'dashicon', 'image', 'svg' ), 'dashicon', 'Source', array( 'group' => 'Icon' ) ),
			'icon' => $f::string( 'star-filled', 'Dashicon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'media' => $f::url( '', 'Image or SVG file', array( 'group' => 'Icon', 'control' => 'media' ) ),
			'alt' => $f::string( '', 'Accessible label', array( 'group' => 'Accessibility' ) ),
			'url' => $f::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target' => $f::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
			'size' => $f::css( '32px', 'Icon size', array( 'group' => 'Layout' ) ),
		), $media, array( 'hover', 'focus' ) );

		$widgets['logo-grid'] = $f::widget( 'Logo Grid', 'media', 'screenoptions', true, array(
			'columns' => $f::integer( 1, 8, 5, 'Columns', array( 'group' => 'Layout' ) ),
			'tabletColumns' => $f::integer( 1, 6, 3, 'Tablet columns', array( 'group' => 'Responsive' ) ),
			'mobileColumns' => $f::integer( 1, 3, 2, 'Mobile columns', array( 'group' => 'Responsive' ) ),
			'gap' => $f::css( '24px', 'Gap', array( 'group' => 'Layout' ) ),
			'grayscale' => $f::boolean( false, 'Grayscale logos', array( 'group' => 'Appearance' ) ),
		), $layout );

		$widgets['mega-menu'] = $f::widget( 'Mega Menu', 'site', 'menu', false, array(
			'menu' => $f::integer( 0, PHP_INT_MAX, 0, 'WordPress menu', array( 'group' => 'Menu', 'control' => 'option-select', 'optionsSource' => 'menus', 'optionValue' => 'id', 'optionLabel' => 'label', 'emptyLabel' => 'Choose a WordPress menu' ) ),
			'depth' => $f::integer( 1, 5, 3, 'Depth', array( 'group' => 'Menu' ) ),
			'openOn' => $f::enum( array( 'hover', 'click' ), 'hover', 'Open submenu on', array( 'group' => 'Behavior' ) ),
			'panelWidth' => $f::css( 'min(960px, 90vw)', 'Mega panel width', array( 'group' => 'Layout' ) ),
			'fullWidth' => $f::boolean( false, 'Full-width panels', array( 'group' => 'Layout' ) ),
			'mobileDrawer' => $f::boolean( true, 'Use mobile drawer', array( 'group' => 'Responsive' ) ),
			'mobileBreakpoint' => $f::integer( 480, 1280, 768, 'Mobile breakpoint (px)', array( 'group' => 'Responsive' ) ),
		), $card, array( 'hover', 'focus' ) );

		return $widgets;
	}
	private function __construct() {}
}
