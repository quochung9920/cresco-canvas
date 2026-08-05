<?php
/**
 * Accessible server-rendered interactive components.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Interactions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InteractiveComponents {
	/** Register blocks and frontend assets. */
	public function register() {
		add_action( 'init', array( $this, 'register_assets' ), 20 );
		add_action( 'init', array( $this, 'register_blocks' ), 40 );
	}

	/** Register checked-in frontend assets. */
	public function register_assets() {
		$asset_file = CRESCO_CANVAS_PATH . 'build/interactions-frontend.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_register_script(
				'cresco-canvas-interactions-frontend',
				CRESCO_CANVAS_URL . 'build/interactions-frontend.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}
		wp_register_style(
			'cresco-canvas-interactions',
			CRESCO_CANVAS_URL . 'assets/css/interactions.css',
			array(),
			CRESCO_CANVAS_VERSION
		);
	}

	/** Register interaction blocks. */
	public function register_blocks() {
		$blocks = array(
			'cresco/tabs' => array(
				'attributes' => array(
					'activeIndex' => array( 'type' => 'number', 'default' => 0 ),
					'orientation' => array( 'type' => 'string', 'default' => 'horizontal' ),
					'instanceId'  => array( 'type' => 'string', 'default' => '' ),
				),
				'callback' => 'render_tabs',
			),
			'cresco/tab' => array(
				'attributes' => array( 'label' => array( 'type' => 'string', 'default' => 'Tab' ) ),
				'callback' => 'render_passthrough',
			),
			'cresco/accordion' => array(
				'attributes' => array(
					'multiple'   => array( 'type' => 'boolean', 'default' => false ),
					'instanceId' => array( 'type' => 'string', 'default' => '' ),
				),
				'callback' => 'render_accordion',
			),
			'cresco/accordion-item' => array(
				'attributes' => array(
					'label'    => array( 'type' => 'string', 'default' => 'Section' ),
					'open'     => array( 'type' => 'boolean', 'default' => false ),
				),
				'callback' => 'render_accordion_item',
			),
			'cresco/modal' => array(
				'attributes' => array(
					'triggerLabel' => array( 'type' => 'string', 'default' => 'Open dialog' ),
					'closeLabel'   => array( 'type' => 'string', 'default' => 'Close dialog' ),
					'instanceId'   => array( 'type' => 'string', 'default' => '' ),
				),
				'callback' => 'render_modal',
			),
			'cresco/slider' => array(
				'attributes' => array(
					'perView'    => array( 'type' => 'number', 'default' => 1 ),
					'loop'       => array( 'type' => 'boolean', 'default' => false ),
					'autoplay'   => array( 'type' => 'boolean', 'default' => false ),
					'interval'   => array( 'type' => 'number', 'default' => 5000 ),
					'instanceId' => array( 'type' => 'string', 'default' => '' ),
				),
				'callback' => 'render_slider',
			),
			'cresco/slide' => array(
				'attributes' => array(),
				'callback' => 'render_passthrough',
			),
			'cresco/offcanvas' => array(
				'attributes' => array(
					'triggerLabel' => array( 'type' => 'string', 'default' => 'Open panel' ),
					'closeLabel'   => array( 'type' => 'string', 'default' => 'Close panel' ),
					'position'     => array( 'type' => 'string', 'default' => 'right' ),
					'instanceId'   => array( 'type' => 'string', 'default' => '' ),
				),
				'callback' => 'render_offcanvas',
			),
			'cresco/disclosure' => array(
				'attributes' => array(
					'label' => array( 'type' => 'string', 'default' => 'Show details' ),
					'open'  => array( 'type' => 'boolean', 'default' => false ),
				),
				'callback' => 'render_disclosure',
			),
			'cresco/tooltip' => array(
				'attributes' => array(
					'label'   => array( 'type' => 'string', 'default' => 'More information' ),
					'content' => array( 'type' => 'string', 'default' => '' ),
				),
				'callback' => 'render_tooltip',
			),
			'cresco/sticky' => array(
				'attributes' => array(
					'offset' => array( 'type' => 'number', 'default' => 0 ),
				),
				'callback' => 'render_sticky',
			),
			'cresco/conditional' => array(
				'attributes' => array(
					'condition' => array( 'type' => 'string', 'default' => 'always' ),
					'value'     => array( 'type' => 'string', 'default' => '' ),
				),
				'callback' => 'render_conditional',
			),
		);

		foreach ( $blocks as $name => $definition ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'attributes'      => $definition['attributes'],
					'render_callback' => array( $this, $definition['callback'] ),
					'supports'        => array( 'html' => false, 'className' => true, 'align' => array( 'wide', 'full' ), 'spacing' => true ),
				)
			);
		}
	}

	/** Render tabs from child tab blocks. */
	public function render_tabs( $attributes, $content, $block ) {
		$tabs = array();
		foreach ( (array) ( $block->parsed_block['innerBlocks'] ?? array() ) as $child ) {
			if ( 'cresco/tab' !== ( $child['blockName'] ?? '' ) ) {
				continue;
			}
			$tabs[] = array(
				'label' => sanitize_text_field( (string) ( $child['attrs']['label'] ?? 'Tab' ) ),
				'html'  => do_blocks( serialize_blocks( array( $child ) ) ),
			);
		}
		if ( ! $tabs ) {
			return '';
		}
		$id = self::instance_id( $attributes['instanceId'] ?? '', 'tabs' );
		$active = min( count( $tabs ) - 1, max( 0, absint( $attributes['activeIndex'] ?? 0 ) ) );
		$orientation = 'vertical' === ( $attributes['orientation'] ?? '' ) ? 'vertical' : 'horizontal';
		$buttons = '';
		$panels = '';
		foreach ( $tabs as $index => $tab ) {
			$selected = $index === $active;
			$buttons .= '<button role="tab" id="' . esc_attr( $id . '-tab-' . $index ) . '" aria-controls="' . esc_attr( $id . '-panel-' . $index ) . '" aria-selected="' . ( $selected ? 'true' : 'false' ) . '" tabindex="' . ( $selected ? '0' : '-1' ) . '">' . esc_html( $tab['label'] ) . '</button>';
			$panels .= '<div role="tabpanel" id="' . esc_attr( $id . '-panel-' . $index ) . '" aria-labelledby="' . esc_attr( $id . '-tab-' . $index ) . '"' . ( $selected ? '' : ' hidden' ) . '>' . $tab['html'] . '</div>';
		}
		return $this->enhance( '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-tabs', 'data-cresco-tabs' => $id ) ) . '><div role="tablist" aria-orientation="' . esc_attr( $orientation ) . '">' . $buttons . '</div>' . $panels . '</div>' );
	}

	/** Render accordion container. */
	public function render_accordion( $attributes, $content ) {
		$id = self::instance_id( $attributes['instanceId'] ?? '', 'accordion' );
		return $this->enhance( '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-accordion', 'data-cresco-accordion' => ! empty( $attributes['multiple'] ) ? 'multiple' : 'single', 'data-instance' => $id ) ) . '>' . $content . '</div>' );
	}

	/** Render one accordion item. */
	public function render_accordion_item( $attributes, $content ) {
		$id = wp_unique_id( 'cresco-accordion-' );
		$open = ! empty( $attributes['open'] );
		return '<section class="cresco-accordion__item"><h3><button type="button" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $id ) . '">' . esc_html( sanitize_text_field( (string) ( $attributes['label'] ?? 'Section' ) ) ) . '</button></h3><div id="' . esc_attr( $id ) . '" class="cresco-accordion__panel"' . ( $open ? '' : ' hidden' ) . '>' . $content . '</div></section>';
	}

	/** Render modal dialog. */
	public function render_modal( $attributes, $content ) {
		$id = self::instance_id( $attributes['instanceId'] ?? '', 'modal' );
		return $this->enhance( '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-modal', 'data-cresco-modal' => $id ) ) . '><button type="button" data-cresco-open="' . esc_attr( $id ) . '">' . esc_html( sanitize_text_field( (string) ( $attributes['triggerLabel'] ?? 'Open dialog' ) ) ) . '</button><div class="cresco-modal__backdrop" hidden><div class="cresco-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $id . '-title' ) . '" tabindex="-1"><button type="button" class="cresco-modal__close" data-cresco-close>' . esc_html( sanitize_text_field( (string) ( $attributes['closeLabel'] ?? 'Close dialog' ) ) ) . '</button><div id="' . esc_attr( $id . '-title' ) . '" class="screen-reader-text">' . esc_html__( 'Dialog', 'cresco-canvas' ) . '</div>' . $content . '</div></div></div>' );
	}

	/** Render slider. */
	public function render_slider( $attributes, $content ) {
		$id = self::instance_id( $attributes['instanceId'] ?? '', 'slider' );
		$per_view = min( 6, max( 1, absint( $attributes['perView'] ?? 1 ) ) );
		$interval = min( 30000, max( 2000, absint( $attributes['interval'] ?? 5000 ) ) );
		return $this->enhance( '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-slider', 'data-cresco-slider' => $id, 'data-loop' => ! empty( $attributes['loop'] ) ? '1' : '0', 'data-autoplay' => ! empty( $attributes['autoplay'] ) ? '1' : '0', 'data-interval' => (string) $interval, 'style' => '--cresco-slides-per-view:' . $per_view . ';' ) ) . '><div class="cresco-slider__viewport"><div class="cresco-slider__track">' . $content . '</div></div><div class="cresco-slider__controls"><button type="button" data-cresco-prev aria-label="' . esc_attr__( 'Previous slide', 'cresco-canvas' ) . '">‹</button><span aria-live="polite" data-cresco-status></span><button type="button" data-cresco-next aria-label="' . esc_attr__( 'Next slide', 'cresco-canvas' ) . '">›</button></div></div>' );
	}

	/** Render off-canvas panel. */
	public function render_offcanvas( $attributes, $content ) {
		$id = self::instance_id( $attributes['instanceId'] ?? '', 'offcanvas' );
		$position = in_array( $attributes['position'] ?? '', array( 'left', 'right' ), true ) ? $attributes['position'] : 'right';
		return $this->enhance( '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-offcanvas cresco-offcanvas--' . $position, 'data-cresco-offcanvas' => $id ) ) . '><button type="button" data-cresco-open="' . esc_attr( $id ) . '">' . esc_html( sanitize_text_field( (string) ( $attributes['triggerLabel'] ?? 'Open panel' ) ) ) . '</button><div class="cresco-offcanvas__backdrop" hidden><aside class="cresco-offcanvas__panel" role="dialog" aria-modal="true" tabindex="-1"><button type="button" data-cresco-close>' . esc_html( sanitize_text_field( (string) ( $attributes['closeLabel'] ?? 'Close panel' ) ) ) . '</button>' . $content . '</aside></div></div>' );
	}

	/** Render disclosure. */
	public function render_disclosure( $attributes, $content ) {
		$id = wp_unique_id( 'cresco-disclosure-' );
		$open = ! empty( $attributes['open'] );
		return $this->enhance( '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-disclosure' ) ) . '><button type="button" data-cresco-disclosure aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $id ) . '">' . esc_html( sanitize_text_field( (string) ( $attributes['label'] ?? 'Show details' ) ) ) . '</button><div id="' . esc_attr( $id ) . '"' . ( $open ? '' : ' hidden' ) . '>' . $content . '</div></div>' );
	}

	/** Render tooltip. */
	public function render_tooltip( $attributes ) {
		$id = wp_unique_id( 'cresco-tooltip-' );
		return $this->enhance( '<span ' . get_block_wrapper_attributes( array( 'class' => 'cresco-tooltip' ) ) . '><button type="button" aria-describedby="' . esc_attr( $id ) . '">' . esc_html( sanitize_text_field( (string) ( $attributes['label'] ?? 'More information' ) ) ) . '</button><span role="tooltip" id="' . esc_attr( $id ) . '" hidden>' . esc_html( sanitize_text_field( (string) ( $attributes['content'] ?? '' ) ) ) . '</span></span>' );
	}

	/** Render sticky wrapper. */
	public function render_sticky( $attributes, $content ) {
		$offset = min( 500, max( 0, absint( $attributes['offset'] ?? 0 ) ) );
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'cresco-sticky', 'style' => '--cresco-sticky-offset:' . $offset . 'px;' ) ) . '>' . $content . '</div>';
	}

	/** Render bounded visibility conditions. */
	public function render_conditional( $attributes, $content ) {
		$condition = sanitize_key( (string) ( $attributes['condition'] ?? 'always' ) );
		$value     = sanitize_text_field( (string) ( $attributes['value'] ?? '' ) );
		$visible   = true;
		switch ( $condition ) {
			case 'logged_in':
				$visible = is_user_logged_in();
				break;
			case 'logged_out':
				$visible = ! is_user_logged_in();
				break;
			case 'role':
				$user = wp_get_current_user();
				$visible = $value && in_array( sanitize_key( $value ), (array) $user->roles, true );
				break;
			case 'query_param':
				$parts = array_map( 'sanitize_key', explode( '=', $value, 2 ) );
				$key = $parts[0] ?? '';
				$expected = $parts[1] ?? '';
				$actual = $key && isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display condition.
				$visible = $key && $actual === $expected;
				break;
			case 'always':
			default:
				$visible = true;
		}
		return $visible ? $content : '';
	}

	/** Pass through child content. */
	public function render_passthrough( $attributes, $content ) {
		unset( $attributes );
		return $content;
	}

	/** Enqueue runtime and style only when needed. */
	private function enhance( $html ) {
		wp_enqueue_script( 'cresco-canvas-interactions-frontend' );
		wp_enqueue_style( 'cresco-canvas-interactions' );
		return $html;
	}

	/** Build a safe stable instance id. */
	private static function instance_id( $value, $prefix ) {
		$value = sanitize_key( (string) $value );
		return $value ? substr( $value, 0, 40 ) : wp_unique_id( 'cresco-' . $prefix . '-' );
	}
}
