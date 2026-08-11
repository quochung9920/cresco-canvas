<?php
/**
 * Workflow extensions for dependency mapping and WooCommerce Theme templates.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Theme\ThemeBuilder;
use CrescoCanvas\Theme\ThemeSessionBridge;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderWorkflowExtensions {
	const HANDLE = 'cresco-canvas-website-builder-workflow-extensions';

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 35 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ), 1060 );
	}

	public function register_routes() {
		$route = array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_woo_single_template' ),
			'permission_callback' => static function () { return current_user_can( 'edit_pages' ); },
		);
		register_rest_route( 'cresco-canvas/v1', '/website-builder/woocommerce/templates/single', $route );
		// Compatibility alias. New code must use the stable feature route above.
		register_rest_route( 'cresco-canvas/v1', '/website-builder/v3/woo-single-template', $route );
	}

	public function enqueue_editor() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context || ! WebsiteBuilderModuleRegistry::is_enabled( 'workflow', $context ) ) return;
		if ( ! WebsiteBuilderAsset::readable( 'build/website-builder-workflow-extensions.js' ) ) return;

		wp_enqueue_script(
			self::HANDLE,
			WebsiteBuilderAsset::url( 'build/website-builder-workflow-extensions.js' ),
			array( 'cresco-canvas-website-builder', 'wp-api-fetch' ),
			WebsiteBuilderAsset::version( 'build/website-builder-workflow-extensions.js' ),
			true
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.crescoWebsiteBuilderWorkflowSettings=' . wp_json_encode(
				array(
					'wooTemplatePath' => '/cresco-canvas/v1/website-builder/woocommerce/templates/single',
					'woocommerce'    => WebsiteBuilderComprehensiveV3::has_woocommerce(),
				)
			) . ';',
			'before'
		);
	}

	public function rest_woo_single_template() {
		if ( ! WebsiteBuilderComprehensiveV3::has_woocommerce() ) {
			return new WP_Error( 'cresco_woo_inactive', __( 'Activate WooCommerce before creating a Single Product template.', 'cresco-canvas' ), array( 'status' => 409 ) );
		}
		$existing = $this->find_product_template();
		if ( $existing ) return new WP_REST_Response( $this->present_template( $existing, false ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => ThemeBuilder::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => __( 'Single Product — Cresco', 'cresco-canvas' ),
				'post_content' => '<!-- wp:paragraph --><p>' . esc_html__( 'Open this template with Cresco Canvas.', 'cresco-canvas' ) . '</p><!-- /wp:paragraph -->',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) return $post_id;
		$post_id = absint( $post_id );
		update_post_meta( $post_id, ThemeBuilder::META_TYPE, 'single' );
		update_post_meta( $post_id, ThemeBuilder::META_PRIORITY, 40 );
		update_post_meta( $post_id, ThemeBuilder::META_CONDITIONS, array( array( 'operator' => 'include', 'rule' => 'post_type', 'value' => 'product' ) ) );

		$session = WebsiteBuilder::sanitize_session( $this->default_product_session( $post_id ) );
		if ( is_wp_error( $session ) ) {
			wp_delete_post( $post_id, true );
			return $session;
		}
		$json = wp_json_encode( $session, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			wp_delete_post( $post_id, true );
			return new WP_Error( 'cresco_woo_template_encode', __( 'The Single Product template could not be encoded.', 'cresco-canvas' ), array( 'status' => 500 ) );
		}
		update_post_meta( $post_id, SessionManager::META_KEY, $json );
		update_post_meta( $post_id, WebsiteBuilder::BUILDER_META, WebsiteBuilder::BUILDER_VERSION );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => ThemeSessionBridge::block_markup( $post_id ) ) );
		return new WP_REST_Response( $this->present_template( $post_id, true ), 201 );
	}

	private function find_product_template() {
		$ids = get_posts(
			array(
				'post_type'      => ThemeBuilder::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_key'       => ThemeBuilder::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small Theme Builder template collection.
				'meta_value'     => 'single', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small Theme Builder template collection.
			)
		);
		foreach ( $ids as $id ) {
			foreach ( (array) get_post_meta( $id, ThemeBuilder::META_CONDITIONS, true ) as $condition ) {
				if ( 'include' === ( $condition['operator'] ?? '' ) && 'post_type' === ( $condition['rule'] ?? '' ) && 'product' === ( $condition['value'] ?? '' ) ) return absint( $id );
			}
		}
		return 0;
	}

	private function present_template( $post_id, $created ) {
		return array(
			'id'        => absint( $post_id ),
			'created'   => (bool) $created,
			'title'     => get_the_title( $post_id ),
			'editUrl'   => add_query_arg( array( 'page' => ThemeSessionBridge::PAGE_SLUG, 'post' => absint( $post_id ) ), admin_url( 'admin.php' ) ),
			'status'    => get_post_status( $post_id ),
			'condition' => 'post_type:product',
		);
	}

	private function default_product_session( $post_id ) {
		return array(
			'schema'     => SessionManager::SCHEMA,
			'version'    => SessionManager::VERSION,
			'documentId' => 'theme-' . absint( $post_id ),
			'nodes'      => array(
				$this->node(
					'product-shell',
					'container',
					array( 'contentWidth' => 'boxed', 'layout' => 'grid', 'columns' => 2, 'gridTemplate' => 'minmax(0,1fr) minmax(0,1fr)', 'tag' => 'main', 'ariaLabel' => 'Product' ),
					array( 'paddingTop' => '{spacing.xl}', 'paddingRight' => '{spacing.containerGutter}', 'paddingBottom' => '{spacing.xl}', 'paddingLeft' => '{spacing.containerGutter}', 'gap' => '{spacing.xl}', 'alignItems' => 'start' ),
					array( 'tablet' => array( 'gridTemplateColumns' => '1fr' ), 'mobile' => array( 'gridTemplateColumns' => '1fr', 'gap' => '{spacing.lg}' ) ),
					array(
						$this->node(
							'product-media',
							'container',
							array( 'contentWidth' => 'full', 'layout' => 'flex', 'direction' => 'column', 'wrap' => 'nowrap', 'align' => 'stretch', 'justify' => 'flex-start', 'columns' => 1, 'gridTemplate' => '1fr', 'tag' => 'section', 'ariaLabel' => 'Product gallery' ),
							array(),
							array(),
							array( $this->node( 'product-image', 'woo-product-image', array( 'size' => 'large' ), array( 'width' => '100%', 'borderRadius' => '{radius.md}', 'overflow' => 'hidden' ) ) )
						),
						$this->node(
							'product-summary',
							'container',
							array( 'contentWidth' => 'full', 'layout' => 'flex', 'direction' => 'column', 'wrap' => 'nowrap', 'align' => 'stretch', 'justify' => 'flex-start', 'columns' => 1, 'gridTemplate' => '1fr', 'tag' => 'section', 'ariaLabel' => 'Product summary' ),
							array( 'gap' => '{spacing.md}' ),
							array(),
							array(
								$this->node( 'product-title', 'woo-product-title', array( 'tag' => 'h1' ), array( 'fontSize' => '{typography.sizes.h2}', 'lineHeight' => '1.08', 'marginTop' => '0', 'marginBottom' => '0' ) ),
								$this->node( 'product-price', 'woo-product-price', array(), array( 'fontSize' => '{typography.sizes.xl}', 'fontWeight' => '700' ) ),
								$this->node( 'product-cart', 'woo-add-to-cart', array( 'label' => 'Add to cart' ), array( 'marginTop' => '{spacing.sm}' ) ),
							)
						),
					)
				),
			),
		);
	}

	private function node( $id, $type, $props = array(), $style = array(), $responsive = array(), $children = array() ) {
		return array( 'id' => $id, 'type' => $type, 'props' => $props, 'style' => $style, 'responsive' => $responsive, 'states' => array(), 'customCSS' => array(), 'meta' => array( 'label' => '', 'componentId' => 0, 'locked' => false, 'hidden' => false ), 'children' => $children );
	}
}
