<?php
/** Interactive professional widget definitions. */
namespace CrescoCanvas\Builder;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProfessionalWidgetCatalogInteractive {
	public static function extend( $widgets, $styles ) {
		$f = ProfessionalWidgetCatalog::class;
		$layout = $styles['layout']; $card = $styles['card'];

		$widgets['faq'] = $f::widget( 'FAQ', 'interactive', 'editor-help', false, array(
			'items' => $f::json( array( array( 'title' => 'Frequently asked question', 'content' => 'Add the answer here.', 'open' => true ) ), 'Questions', 'accordion', array( 'group' => 'Items', 'control' => 'repeater' ) ),
			'allowMulti' => $f::boolean( false, 'Allow multiple open', array( 'group' => 'Behavior' ) ),
			'faqSchema' => $f::boolean( true, 'Output FAQ schema', array( 'group' => 'SEO' ) ),
			'expandIcon' => $f::string( 'plus-alt2', 'Expand icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'collapseIcon' => $f::string( 'minus', 'Collapse icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
		), $card, array( 'hover', 'focus' ) );

		$widgets['steps'] = $f::widget( 'Progress Steps / Process', 'interactive', 'editor-ol', true, array(
			'orientation' => $f::enum( array( 'horizontal', 'vertical' ), 'horizontal', 'Orientation', array( 'group' => 'Layout' ) ),
			'gap' => $f::css( '24px', 'Step gap', array( 'group' => 'Layout' ) ),
			'numbered' => $f::boolean( true, 'Show step numbers', array( 'group' => 'Appearance' ) ),
			'startNumber' => $f::integer( 0, 99, 1, 'Start number', array( 'group' => 'Appearance' ) ),
			'showLine' => $f::boolean( true, 'Show connector', array( 'group' => 'Appearance' ) ),
		), $layout );

		$widgets['nested-tabs'] = $f::widget( 'Nested Tabs', 'interactive', 'index-card', true, array(
			'direction' => $f::enum( array( 'top', 'bottom', 'start', 'end' ), 'top', 'Direction', array( 'group' => 'Layout' ) ),
			'justify' => $f::enum( array( 'start', 'center', 'end', 'stretch' ), 'start', 'Justify tabs', array( 'group' => 'Layout' ) ),
			'tabGap' => $f::css( '4px', 'Tab gap', array( 'group' => 'Layout' ) ),
			'activation' => $f::enum( array( 'automatic', 'manual' ), 'automatic', 'Keyboard activation', array( 'group' => 'Accessibility' ) ),
			'deepLink' => $f::boolean( false, 'Update URL hash', array( 'group' => 'Behavior' ) ),
			'mobileMode' => $f::enum( array( 'tabs', 'accordion' ), 'accordion', 'Mobile mode', array( 'group' => 'Responsive' ) ),
			'animationDuration' => $f::integer( 0, 1500, 200, 'Animation duration (ms)', array( 'group' => 'Motion' ) ),
		), $card, array( 'hover', 'focus', 'active' ), array(
			'root' => $f::part( 'Tabs', '&' ), 'list' => $f::part( 'Tab list', '& .cresco-pro-nested-tabs__list' ),
			'tab' => $f::part( 'Tab', '& [role="tab"]' ), 'panel' => $f::part( 'Panel', '& [role="tabpanel"]' ),
		) );

		$widgets['tab-panel'] = $f::widget( 'Tab Panel', 'interactive', 'excerpt-view', true, array(
			'title' => $f::string( 'Tab', 'Tab title', array( 'group' => 'Content' ) ),
			'icon' => $f::string( '', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'disabled' => $f::boolean( false, 'Disabled', array( 'group' => 'Behavior' ) ),
			'slug' => $f::string( '', 'URL slug', array( 'group' => 'Behavior' ) ),
		), $card, array() );

		$widgets['nested-accordion'] = $f::widget( 'Nested Accordion', 'interactive', 'menu-alt3', true, array(
			'allowMulti' => $f::boolean( false, 'Allow multiple open', array( 'group' => 'Behavior' ) ),
			'iconPosition' => $f::enum( array( 'start', 'end' ), 'end', 'Icon position', array( 'group' => 'Icon' ) ),
			'expandIcon' => $f::string( 'plus-alt2', 'Expand icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'collapseIcon' => $f::string( 'minus', 'Collapse icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'deepLink' => $f::boolean( false, 'Update URL hash', array( 'group' => 'Behavior' ) ),
			'animationDuration' => $f::integer( 0, 1500, 200, 'Animation duration (ms)', array( 'group' => 'Motion' ) ),
		), $card, array( 'hover', 'focus', 'active' ) );

		$widgets['accordion-item'] = $f::widget( 'Accordion Item', 'interactive', 'excerpt-view', true, array(
			'title' => $f::string( 'Accordion item', 'Title', array( 'group' => 'Content' ) ),
			'open' => $f::boolean( false, 'Open by default', array( 'group' => 'Behavior' ) ),
			'headingTag' => $f::enum( array( 'div', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h3', 'Heading tag', array( 'group' => 'Semantics' ) ),
			'slug' => $f::string( '', 'URL slug', array( 'group' => 'Behavior' ) ),
		), $card, array() );

		$widgets['filterable-grid'] = $f::widget( 'Filterable Grid / Portfolio', 'interactive', 'grid-view', true, array(
			'allLabel' => $f::string( 'All', 'All filter label', array( 'group' => 'Filters' ) ),
			'filterAlign' => $f::enum( array( 'start', 'center', 'end' ), 'center', 'Filter alignment', array( 'group' => 'Filters' ) ),
			'columns' => $f::integer( 1, 6, 3, 'Columns', array( 'group' => 'Layout' ) ),
			'tabletColumns' => $f::integer( 1, 4, 2, 'Tablet columns', array( 'group' => 'Responsive' ) ),
			'mobileColumns' => $f::integer( 1, 2, 1, 'Mobile columns', array( 'group' => 'Responsive' ) ),
			'gap' => $f::css( '24px', 'Grid gap', array( 'group' => 'Layout' ) ),
			'animationDuration' => $f::integer( 0, 1200, 220, 'Filter animation (ms)', array( 'group' => 'Motion' ) ),
		), $layout, array( 'hover', 'focus' ) );

		$widgets['filter-item'] = $f::widget( 'Filter Item', 'interactive', 'excerpt-view', true, array(
			'category' => $f::string( 'general', 'Category slug', array( 'group' => 'Filter' ) ),
			'categoryLabel' => $f::string( 'General', 'Category label', array( 'group' => 'Filter' ) ),
		), $card );

		return $widgets;
	}
	private function __construct() {}
}
