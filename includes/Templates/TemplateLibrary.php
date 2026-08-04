<?php
/**
 * Native template, component, and Site Kit services.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Templates;

use CrescoCanvas\Styles\GlobalStyles;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TemplateLibrary {
	const SITE_KIT_OPTION = 'cresco_canvas_site_kit';
	const CATALOG_VERSION = 1;

	/** Register native patterns and REST endpoints. */
	public function register() {
		add_action( 'init', array( $this, 'register_patterns' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Register Cresco pattern categories and bundled native patterns. */
	public function register_patterns() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		foreach ( self::categories() as $slug => $label ) {
			register_block_pattern_category( 'cresco-' . $slug, array( 'label' => $label ) );
		}

		foreach ( self::catalog() as $template ) {
			register_block_pattern(
				'cresco-canvas/' . $template['id'],
				array(
					'title'       => $template['title'],
					'description' => $template['description'],
					'categories'  => array( 'cresco-' . $template['category'] ),
					'keywords'    => $template['keywords'],
					'content'     => $template['content'],
					'blockTypes'  => $template['blockTypes'],
					'inserter'    => true,
				)
			);
		}
	}

	/** Register template-domain routes. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/templates/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_catalog' ),
				'permission_callback' => array( $this, 'can_edit_templates' ),
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/components',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_components' ),
					'permission_callback' => array( $this, 'can_edit_templates' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_component' ),
					'permission_callback' => array( $this, 'can_edit_templates' ),
				),
			)
		);

		register_rest_route(
			'cresco-canvas/v1',
			'/site-kit',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_site_kit' ),
					'permission_callback' => array( $this, 'can_manage_site_kits' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_site_kit' ),
					'permission_callback' => array( $this, 'can_manage_site_kits' ),
				),
			)
		);
	}

	/** @return bool */
	public function can_edit_templates() {
		return current_user_can( 'edit_pages' );
	}

	/** @return bool */
	public function can_manage_site_kits() {
		return current_user_can( 'edit_theme_options' );
	}

	/** @return WP_REST_Response */
	public function get_catalog() {
		return new WP_REST_Response(
			array(
				'schemaVersion'  => self::CATALOG_VERSION,
				'categories'     => self::categories(),
				'templates'      => array_values( self::catalog() ),
				'componentModel' => 'wp_block',
			)
		);
	}

	/** Return editable synced components. */
	public function get_components() {
		$posts = get_posts(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$items[] = array(
				'id'       => (int) $post->ID,
				'title'    => get_the_title( $post ),
				'modified' => get_post_modified_time( DATE_ATOM, true, $post ),
				'status'   => $post->post_status,
			);
		}

		return new WP_REST_Response( $items );
	}

	/** Create a synced component from safe native block markup. */
	public function create_component( WP_REST_Request $request ) {
		$title   = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$content = (string) $request->get_param( 'content' );

		if ( '' === $title || '' === trim( $content ) ) {
			return new WP_Error( 'cresco_component_missing_data', __( 'A component title and block content are required.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) || ! self::blocks_are_safe( $blocks ) ) {
			return new WP_Error( 'cresco_component_unsafe_blocks', __( 'Components may contain only supported Core and Cresco blocks.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => serialize_blocks( $blocks ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return new WP_REST_Response( array( 'id' => (int) $post_id, 'title' => get_the_title( $post_id ), 'status' => 'publish' ), 201 );
	}

	/** Export a safe, declarative Site Kit. */
	public function export_site_kit() {
		$stored = (array) get_option( self::SITE_KIT_OPTION, array() );
		return new WP_REST_Response(
			array(
				'schemaVersion' => 1,
				'pluginVersion' => CRESCO_CANVAS_VERSION,
				'name'          => sanitize_text_field( $stored['name'] ?? __( 'Cresco Site Kit', 'cresco-canvas' ) ),
				'settings'      => GlobalStyles::get_settings(),
				'templateIds'   => self::sanitize_template_ids( $stored['templateIds'] ?? array_keys( self::catalog() ) ),
			)
		);
	}

	/** Import only allow-listed settings and bundled template references. */
	public function import_site_kit( WP_REST_Request $request ) {
		$payload      = (array) $request->get_json_params();
		$schema       = absint( $payload['schemaVersion'] ?? 0 );
		$template_ids = self::sanitize_template_ids( $payload['templateIds'] ?? array() );

		if ( 1 !== $schema || ! isset( $payload['settings'] ) || ! is_array( $payload['settings'] ) ) {
			return new WP_Error( 'cresco_site_kit_invalid', __( 'The Site Kit JSON is invalid or uses an unsupported schema.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}

		$settings = GlobalStyles::sanitize_settings( $payload['settings'] );
		update_option( 'cresco_canvas_settings', $settings, false );
		update_option(
			self::SITE_KIT_OPTION,
			array(
				'name'        => sanitize_text_field( $payload['name'] ?? __( 'Imported Cresco Site Kit', 'cresco-canvas' ) ),
				'templateIds' => $template_ids,
			),
			false
		);

		return new WP_REST_Response( array( 'name' => sanitize_text_field( $payload['name'] ?? '' ), 'settings' => $settings, 'templateIds' => $template_ids ) );
	}

	/** @return array<string, string> */
	public static function categories() {
		return array(
			'pages'      => __( 'Cresco Pages', 'cresco-canvas' ),
			'hero'       => __( 'Cresco Heroes', 'cresco-canvas' ),
			'features'   => __( 'Cresco Features', 'cresco-canvas' ),
			'cta'        => __( 'Cresco Calls to Action', 'cresco-canvas' ),
			'testimonial'=> __( 'Cresco Testimonials', 'cresco-canvas' ),
			'pricing'    => __( 'Cresco Pricing', 'cresco-canvas' ),
			'contact'    => __( 'Cresco Contact', 'cresco-canvas' ),
		);
	}

	/** @return array<string, array<string, mixed>> */
	public static function catalog() {
		$section_open = '<!-- wp:cresco/container {"align":"full","maxWidth":1200,"paddingTop":72,"paddingBottom":72} -->';
		$section_end  = '<!-- /wp:cresco/container -->';
		return array(
			'hero-centered' => self::template( 'hero-centered', __( 'Centered Hero', 'cresco-canvas' ), __( 'A centered headline, supporting text, and primary action.', 'cresco-canvas' ), 'hero', $section_open . '<!-- wp:heading {"textAlign":"center","level":1} --><h1 class="wp-block-heading has-text-align-center">Build visually. Run natively.</h1><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Create fast WordPress experiences with native blocks and Cresco design tokens.</p><!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Get started</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' . $section_end, array( 'hero', 'headline', 'button' ) ),
			'feature-grid' => self::template( 'feature-grid', __( 'Three Feature Grid', 'cresco-canvas' ), __( 'Three equal feature columns with headings and descriptions.', 'cresco-canvas' ), 'features', $section_open . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Everything you need</h2><!-- /wp:heading --><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Native</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Content remains standard Gutenberg markup.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Responsive</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Design for five logical device sizes.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Reusable</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Save sections as synced WordPress components.</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->' . $section_end, array( 'features', 'columns', 'services' ) ),
			'cta-band' => self::template( 'cta-band', __( 'Call to Action Band', 'cresco-canvas' ), __( 'A concise conversion section with two actions.', 'cresco-canvas' ), 'cta', $section_open . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Ready to launch?</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Start with native blocks and evolve without lock-in.</p><!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Start now</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">Learn more</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' . $section_end, array( 'cta', 'conversion', 'buttons' ) ),
			'testimonial-card' => self::template( 'testimonial-card', __( 'Testimonial Card', 'cresco-canvas' ), __( 'A quote with attribution for social proof.', 'cresco-canvas' ), 'testimonial', $section_open . '<!-- wp:quote --><blockquote class="wp-block-quote"><p>Cresco helped our team ship a native WordPress site without sacrificing design control.</p><cite>Customer name, Company</cite></blockquote><!-- /wp:quote -->' . $section_end, array( 'quote', 'review', 'testimonial' ) ),
			'pricing-three' => self::template( 'pricing-three', __( 'Three Pricing Cards', 'cresco-canvas' ), __( 'A three-tier pricing comparison built from native columns.', 'cresco-canvas' ), 'pricing', $section_open . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Simple pricing</h2><!-- /wp:heading --><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Starter</h3><!-- /wp:heading --><!-- wp:paragraph --><p><strong>$19</strong> / month</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><li>Core layouts</li><li>Design tokens</li></ul><!-- /wp:list --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Pro</h3><!-- /wp:heading --><!-- wp:paragraph --><p><strong>$49</strong> / month</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><li>Templates</li><li>Components</li></ul><!-- /wp:list --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Agency</h3><!-- /wp:heading --><!-- wp:paragraph --><p><strong>$99</strong> / month</p><!-- /wp:paragraph --><!-- wp:list --><ul class="wp-block-list"><li>Site Kits</li><li>Priority support</li></ul><!-- /wp:list --></div><!-- /wp:column --></div><!-- /wp:columns -->' . $section_end, array( 'pricing', 'plans', 'columns' ) ),
			'contact-split' => self::template( 'contact-split', __( 'Contact Split', 'cresco-canvas' ), __( 'Contact details beside a form shortcode placeholder.', 'cresco-canvas' ), 'contact', $section_open . '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading --><h2 class="wp-block-heading">Let’s talk</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Tell us about your project and we will respond shortly.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Insert your preferred WordPress form block here.</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->' . $section_end, array( 'contact', 'form', 'columns' ) ),
			'landing-page' => self::template( 'landing-page', __( 'Landing Page Starter', 'cresco-canvas' ), __( 'A complete starter page composed of hero, features, testimonial, and CTA sections.', 'cresco-canvas' ), 'pages', '<!-- wp:pattern {"slug":"cresco-canvas/hero-centered"} /--><!-- wp:pattern {"slug":"cresco-canvas/feature-grid"} /--><!-- wp:pattern {"slug":"cresco-canvas/testimonial-card"} /--><!-- wp:pattern {"slug":"cresco-canvas/cta-band"} /-->', array( 'page', 'landing', 'starter' ) ),
		);
	}

	/** Build a catalog item. */
	private static function template( $id, $title, $description, $category, $content, $keywords ) {
		return array(
			'id'          => $id,
			'title'       => $title,
			'description' => $description,
			'category'    => $category,
			'keywords'    => $keywords,
			'content'     => $content,
			'blockTypes'  => array( 'core/post-content' ),
			'version'     => 1,
		);
	}

	/** Recursively allow only Core and Cresco blocks, excluding executable blocks. */
	private static function blocks_are_safe( $blocks ) {
		$blocked = array( 'core/html', 'core/shortcode', 'core/freeform' );
		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			if ( in_array( $name, $blocked, true ) || ( 0 !== strpos( $name, 'core/' ) && 0 !== strpos( $name, 'cresco/' ) ) ) {
				return false;
			}
			if ( ! empty( $block['innerBlocks'] ) && ! self::blocks_are_safe( $block['innerBlocks'] ) ) {
				return false;
			}
		}
		return true;
	}

	/** Keep only identifiers present in the bundled catalog. */
	private static function sanitize_template_ids( $ids ) {
		$known = array_keys( self::catalog() );
		$ids   = is_array( $ids ) ? $ids : array();
		return array_values( array_unique( array_intersect( array_map( 'sanitize_key', $ids ), $known ) ) );
	}
}
