<?php
/** Content-oriented professional widget definitions. */
namespace CrescoCanvas\Builder;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProfessionalWidgetCatalogContent {
	public static function extend( $widgets, $styles ) {
		$f = ProfessionalWidgetCatalog::class;
		$layout = $styles['layout']; $text = $styles['text']; $card = $styles['card'];

		$widgets['nested-card'] = $f::widget( 'Nested Card', 'content', 'index-card', true, array(
			'url' => $f::url( '', 'Card link', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target' => $f::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
			'rel' => $f::string( '', 'Rel', array( 'group' => 'Link' ) ),
			'ariaLabel' => $f::string( '', 'Accessible label', array( 'group' => 'Accessibility' ) ),
			'hoverLift' => $f::boolean( true, 'Lift on hover', array( 'group' => 'Interaction' ) ),
		), $card, array( 'hover', 'focus' ), array( 'root' => $f::part( 'Card', '&' ), 'body' => $f::part( 'Content', '& > *' ) ) );

		$widgets['clickable-container'] = $f::widget( 'Clickable Container', 'layout', 'external', true, array(
			'url' => $f::url( '#', 'Destination', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target' => $f::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
			'rel' => $f::string( '', 'Rel', array( 'group' => 'Link' ) ),
			'ariaLabel' => $f::string( '', 'Accessible label', array( 'group' => 'Accessibility' ) ),
		), $layout, array( 'hover', 'focus' ) );

		$widgets['cta'] = $f::widget( 'CTA / Banner', 'content', 'megaphone', true, array(
			'layout' => $f::enum( array( 'row', 'column' ), 'row', 'Content direction', array( 'group' => 'Layout' ) ),
			'align' => $f::enum( array( 'start', 'center', 'end', 'stretch' ), 'center', 'Align items', array( 'group' => 'Layout' ) ),
			'justify' => $f::enum( array( 'start', 'center', 'end', 'space-between' ), 'space-between', 'Justify content', array( 'group' => 'Layout' ) ),
			'gap' => $f::css( '24px', 'Content gap', array( 'group' => 'Layout' ) ),
			'stackAt' => $f::enum( array( 'never', 'tablet', 'mobile' ), 'mobile', 'Stack at', array( 'group' => 'Responsive' ) ),
		), $card );

		// Use canonical string_list so WebsiteBuilder sanitizer preserves every item.
		$widgets['icon-list'] = $f::widget( 'Icon List / Feature List', 'content', 'editor-ul', false, array(
			'items' => $f::string_list( array(
				'yes-alt | Feature | Describe the benefit. |',
				'yes-alt | Another feature | Add another benefit. |',
			), 'Items', array( 'group' => 'Items', 'help' => 'One item per line: Icon | Title | Description | URL' ) ),
			'iconPosition' => $f::enum( array( 'start', 'end' ), 'start', 'Icon position', array( 'group' => 'Layout' ) ),
			'gap' => $f::css( '12px', 'Item gap', array( 'group' => 'Layout' ) ),
			'divider' => $f::boolean( false, 'Divider between items', array( 'group' => 'Appearance' ) ),
		), $text, array( 'hover' ), array(
			'root' => $f::part( 'List', '&' ), 'item' => $f::part( 'Item', '& .cresco-pro-icon-list__item' ),
			'icon' => $f::part( 'Icon', '& .cresco-pro-icon-list__icon' ), 'title' => $f::part( 'Title', '& .cresco-pro-icon-list__title' ),
			'text' => $f::part( 'Text', '& .cresco-pro-icon-list__text' ),
		) );

		$widgets['badge'] = $f::widget( 'Badge / Tag / Pill', 'content', 'tag', false, array(
			'text' => $f::string( 'Badge', 'Text', array( 'group' => 'Content' ) ),
			'icon' => $f::string( '', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'iconPosition' => $f::enum( array( 'before', 'after' ), 'before', 'Icon position', array( 'group' => 'Icon' ) ),
			'url' => $f::url( '', 'Link', array( 'group' => 'Link', 'control' => 'link' ) ),
			'target' => $f::enum( array( '_self', '_blank' ), '_self', 'Target', array( 'group' => 'Link' ) ),
		), $text, array( 'hover', 'focus' ), array( 'root' => $f::part( 'Badge', '&' ), 'icon' => $f::part( 'Icon', '& .dashicons' ) ) );

		$widgets['advanced-divider'] = $f::widget( 'Advanced Divider / Shape', 'content', 'minus', false, array(
			'lineStyle' => $f::enum( array( 'solid', 'dashed', 'dotted', 'double' ), 'solid', 'Line style', array( 'group' => 'Line' ) ),
			'thickness' => $f::css( '1px', 'Thickness', array( 'group' => 'Line' ) ),
			'length' => $f::css( '100%', 'Length', array( 'group' => 'Line' ) ),
			'align' => $f::enum( array( 'start', 'center', 'end' ), 'center', 'Alignment', array( 'group' => 'Layout' ) ),
			'text' => $f::string( '', 'Center text', array( 'group' => 'Content' ) ),
			'icon' => $f::string( '', 'Center icon', array( 'group' => 'Content', 'control' => 'icon' ) ),
		), $text, array(), array( 'root' => $f::part( 'Divider', '&' ), 'line' => $f::part( 'Line', '& .cresco-pro-divider__line' ), 'content' => $f::part( 'Center content', '& .cresco-pro-divider__content' ) ) );

		$widgets['team-member'] = $f::widget( 'Team Member / Profile Card', 'content', 'admin-users', false, array(
			'image' => $f::url( '', 'Photo', array( 'group' => 'Media', 'control' => 'media', 'mediaType' => 'image' ) ),
			'alt' => $f::string( '', 'Photo alt text', array( 'group' => 'Accessibility' ) ),
			'name' => $f::string( 'Team member', 'Name', array( 'group' => 'Content' ) ),
			'role' => $f::string( 'Role', 'Role', array( 'group' => 'Content' ) ),
			'bio' => $f::textarea( '', 'Biography', array( 'group' => 'Content' ) ),
			'socials' => $f::json( array(), 'Social links', 'social', array( 'group' => 'Social', 'control' => 'repeater' ) ),
			'imagePosition' => $f::enum( array( 'top', 'start', 'end' ), 'top', 'Photo position', array( 'group' => 'Layout' ) ),
			'imageSize' => $f::css( '96px', 'Photo size', array( 'group' => 'Layout' ) ),
		), $card, array( 'hover' ), array(
			'root' => $f::part( 'Profile', '&' ), 'image' => $f::part( 'Photo', '& .cresco-pro-team__image' ),
			'name' => $f::part( 'Name', '& .cresco-pro-team__name' ), 'role' => $f::part( 'Role', '& .cresco-pro-team__role' ),
			'bio' => $f::part( 'Biography', '& .cresco-pro-team__bio' ), 'socials' => $f::part( 'Social links', '& .cresco-pro-team__socials' ),
		) );

		$widgets['stats-card'] = $f::widget( 'Stats Card', 'content', 'chart-bar', false, array(
			'value' => $f::number( -1000000000, 1000000000, 98, 'Value', array( 'group' => 'Value' ) ),
			'prefix' => $f::string( '', 'Prefix', array( 'group' => 'Value' ) ),
			'suffix' => $f::string( '%', 'Suffix', array( 'group' => 'Value' ) ),
			'decimals' => $f::integer( 0, 4, 0, 'Decimals', array( 'group' => 'Value' ) ),
			'title' => $f::string( 'Success rate', 'Title', array( 'group' => 'Content' ) ),
			'description' => $f::textarea( '', 'Description', array( 'group' => 'Content' ) ),
			'icon' => $f::string( 'chart-bar', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'duration' => $f::integer( 0, 10000, 1200, 'Animation duration (ms)', array( 'group' => 'Animation' ) ),
		), $card );

		$widgets['alert'] = $f::widget( 'Alert / Notice', 'content', 'warning', false, array(
			'tone' => $f::enum( array( 'neutral', 'info', 'success', 'warning', 'error' ), 'info', 'Tone', array( 'group' => 'Appearance' ) ),
			'title' => $f::string( 'Notice', 'Title', array( 'group' => 'Content' ) ),
			'text' => $f::richtext( 'Add an important message.', 'Message', array( 'group' => 'Content' ) ),
			'icon' => $f::string( 'info-outline', 'Icon', array( 'group' => 'Icon', 'control' => 'icon' ) ),
			'dismissible' => $f::boolean( false, 'Dismissible', array( 'group' => 'Behavior' ) ),
			'closeLabel' => $f::string( 'Dismiss', 'Close label', array( 'group' => 'Accessibility' ) ),
		), $card, array() );

		$widgets['blockquote'] = $f::widget( 'Quote / Blockquote', 'content', 'format-quote', false, array(
			'quote' => $f::richtext( 'A memorable quote.', 'Quote', array( 'group' => 'Content' ) ),
			'cite' => $f::string( 'Author', 'Citation', array( 'group' => 'Citation' ) ),
			'role' => $f::string( '', 'Role / source', array( 'group' => 'Citation' ) ),
			'url' => $f::url( '', 'Citation link', array( 'group' => 'Citation', 'control' => 'link' ) ),
			'quoteMark' => $f::boolean( true, 'Show quote mark', array( 'group' => 'Appearance' ) ),
		), $text, array() );

		return $widgets;
	}
	private function __construct() {}
}
