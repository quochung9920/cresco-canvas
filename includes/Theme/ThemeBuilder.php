<?php
/**
 * Native Theme Builder templates and display-condition resolution.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Theme;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemeBuilder {
	const POST_TYPE = 'cresco_template';
	const META_TYPE = '_cresco_template_type';
	const META_PRIORITY = '_cresco_template_priority';
	const META_CONDITIONS = '_cresco_template_conditions';
	const TYPES = array( 'header', 'footer', 'single', 'page', 'archive', 'search', '404' );

	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_body_open', array( $this, 'render_header' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_footer' ), 5 );
		add_filter( 'template_include', array( $this, 'resolve_document_template' ), 99 );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Cresco Theme Templates', 'cresco-canvas' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-layout',
				'supports'     => array( 'title', 'editor', 'revisions' ),
				'capability_type' => 'page',
				'map_meta_cap' => true,
			)
		);

		register_post_meta( self::POST_TYPE, self::META_TYPE, array(
			'single' => true, 'type' => 'string', 'show_in_rest' => true,
			'sanitize_callback' => array( self::class, 'sanitize_type' ),
			'auth_callback' => static function () { return current_user_can( 'edit_pages' ); },
		) );
		register_post_meta( self::POST_TYPE, self::META_PRIORITY, array(
			'single' => true, 'type' => 'integer', 'default' => 10, 'show_in_rest' => true,
			'sanitize_callback' => static function ( $value ) { return min( 1000, max( 0, (int) $value ) ); },
			'auth_callback' => static function () { return current_user_can( 'edit_pages' ); },
		) );
		register_post_meta( self::POST_TYPE, self::META_CONDITIONS, array(
			'single' => true, 'type' => 'array', 'default' => array(),
			'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ),
			'sanitize_callback' => array( self::class, 'sanitize_conditions' ),
			'auth_callback' => static function () { return current_user_can( 'edit_pages' ); },
		) );
	}

	public function register_routes() {
		register_rest_route( 'cresco-canvas/v1', '/theme-templates', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'list_templates' ), 'permission_callback' => array( $this, 'can_edit' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_template' ), 'permission_callback' => array( $this, 'can_edit' ) ),
		) );
		register_rest_route( 'cresco-canvas/v1', '/theme-templates/(?P<id>\d+)', array(
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_template' ), 'permission_callback' => array( $this, 'can_edit_item' ) ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_template' ), 'permission_callback' => array( $this, 'can_edit_item' ) ),
		) );
		register_rest_route( 'cresco-canvas/v1', '/theme-builder/options', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_options' ),
			'permission_callback' => array( $this, 'can_edit' ),
		) );
	}

	public function can_edit() { return current_user_can( 'edit_pages' ); }
	public function can_edit_item( WP_REST_Request $request ) { return current_user_can( 'edit_post', absint( $request['id'] ) ); }

	public function get_options() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		return new WP_REST_Response( array(
			'types' => self::TYPES,
			'operators' => array( 'include', 'exclude' ),
			'rules' => array( 'entire_site', 'front_page', 'blog_home', 'singular', 'post_type', 'post_id', 'archive', 'taxonomy', 'search', '404', 'logged_in', 'logged_out' ),
			'postTypes' => array_values( array_map( static function ( $item ) { return array( 'slug' => $item->name, 'label' => $item->labels->singular_name ); }, $post_types ) ),
		) );
	}

	public function list_templates() {
		$posts = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 100, 'orderby' => 'modified', 'order' => 'DESC' ) );
		return new WP_REST_Response( array_values( array_map( array( $this, 'present' ), $posts ) ) );
	}

	public function create_template( WP_REST_Request $request ) {
		return $this->save_template( 0, $request );
	}

	public function update_template( WP_REST_Request $request ) {
		return $this->save_template( absint( $request['id'] ), $request );
	}

	private function save_template( $id, WP_REST_Request $request ) {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$type = self::sanitize_type( $request->get_param( 'type' ) );
		$content = (string) $request->get_param( 'content' );
		$status = 'publish' === $request->get_param( 'status' ) ? 'publish' : 'draft';
		if ( '' === $title || '' === $type || '' === trim( $content ) ) {
			return new WP_Error( 'cresco_theme_template_invalid', __( 'Title, template type, and block content are required.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) || ! self::blocks_are_safe( $blocks ) ) {
			return new WP_Error( 'cresco_theme_template_unsafe', __( 'Theme templates may contain only supported Core and Cresco blocks.', 'cresco-canvas' ), array( 'status' => 400 ) );
		}
		$postarr = array( 'ID' => $id, 'post_type' => self::POST_TYPE, 'post_title' => $title, 'post_content' => serialize_blocks( $blocks ), 'post_status' => $status );
		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) { return $post_id; }
		update_post_meta( $post_id, self::META_TYPE, $type );
		update_post_meta( $post_id, self::META_PRIORITY, min( 1000, max( 0, (int) $request->get_param( 'priority' ) ) ) );
		update_post_meta( $post_id, self::META_CONDITIONS, self::sanitize_conditions( $request->get_param( 'conditions' ) ) );
		return new WP_REST_Response( $this->present( get_post( $post_id ) ), $id ? 200 : 201 );
	}

	public function delete_template( WP_REST_Request $request ) {
		$result = wp_trash_post( absint( $request['id'] ) );
		return $result ? new WP_REST_Response( array( 'deleted' => true ) ) : new WP_Error( 'cresco_theme_template_delete_failed', __( 'Template could not be moved to Trash.', 'cresco-canvas' ), array( 'status' => 500 ) );
	}

	private function present( $post ) {
		return array(
			'id' => (int) $post->ID,
			'title' => get_the_title( $post ),
			'type' => (string) get_post_meta( $post->ID, self::META_TYPE, true ),
			'priority' => (int) get_post_meta( $post->ID, self::META_PRIORITY, true ),
			'conditions' => (array) get_post_meta( $post->ID, self::META_CONDITIONS, true ),
			'content' => $post->post_content,
			'status' => $post->post_status,
			'editUrl' => get_edit_post_link( $post->ID, 'raw' ),
		);
	}

	public function render_header() { $this->render_slot( 'header' ); }
	public function render_footer() { $this->render_slot( 'footer' ); }
	private function render_slot( $type ) {
		$template = $this->resolve( $type );
		if ( $template ) {
			echo '<div class="cresco-theme-template cresco-theme-template--' . esc_attr( $type ) . '">' . do_blocks( $template->post_content ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native block rendering escapes at block level.
		}
	}

	public function resolve_document_template( $template ) {
		$type = $this->document_type();
		if ( ! $type || ! $this->resolve( $type ) ) { return $template; }
		$GLOBALS['cresco_canvas_resolved_theme_template'] = $this->resolve( $type );
		return CRESCO_CANVAS_PATH . 'includes/Theme/renderer.php';
	}

	private function document_type() {
		if ( is_404() ) { return '404'; }
		if ( is_search() ) { return 'search'; }
		if ( is_archive() || is_home() ) { return 'archive'; }
		if ( is_page() ) { return 'page'; }
		if ( is_singular() ) { return 'single'; }
		return '';
	}

	public function resolve( $type ) {
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 100, 'meta_key' => self::META_TYPE, 'meta_value' => $type, 'orderby' => array( 'meta_value_num' => 'DESC', 'modified' => 'DESC' ) ) );
		$matches = array();
		foreach ( $query->posts as $post ) {
			if ( self::conditions_match( (array) get_post_meta( $post->ID, self::META_CONDITIONS, true ) ) ) { $matches[] = $post; }
		}
		usort( $matches, static function ( $a, $b ) { return (int) get_post_meta( $b->ID, self::META_PRIORITY, true ) <=> (int) get_post_meta( $a->ID, self::META_PRIORITY, true ); } );
		return $matches ? $matches[0] : null;
	}

	public static function sanitize_type( $value ) { $value = sanitize_key( (string) $value ); return in_array( $value, self::TYPES, true ) ? $value : ''; }
	public static function sanitize_conditions( $conditions ) {
		$allowed_rules = array( 'entire_site', 'front_page', 'blog_home', 'singular', 'post_type', 'post_id', 'archive', 'taxonomy', 'search', '404', 'logged_in', 'logged_out' );
		$output = array();
		foreach ( is_array( $conditions ) ? $conditions : array() as $condition ) {
			if ( ! is_array( $condition ) ) { continue; }
			$rule = sanitize_key( $condition['rule'] ?? '' );
			$operator = 'exclude' === ( $condition['operator'] ?? '' ) ? 'exclude' : 'include';
			if ( ! in_array( $rule, $allowed_rules, true ) ) { continue; }
			$output[] = array( 'operator' => $operator, 'rule' => $rule, 'value' => sanitize_text_field( (string) ( $condition['value'] ?? '' ) ) );
			if ( count( $output ) >= 24 ) { break; }
		}
		return $output;
	}

	public static function conditions_match( $conditions ) {
		if ( empty( $conditions ) ) { return true; }
		$include = false; $has_include = false;
		foreach ( $conditions as $condition ) {
			$matched = self::condition_matches( $condition );
			if ( 'exclude' === ( $condition['operator'] ?? '' ) && $matched ) { return false; }
			if ( 'include' === ( $condition['operator'] ?? '' ) ) { $has_include = true; $include = $include || $matched; }
		}
		return $has_include ? $include : true;
	}

	private static function condition_matches( $condition ) {
		$rule = $condition['rule'] ?? ''; $value = $condition['value'] ?? '';
		switch ( $rule ) {
			case 'entire_site': return true;
			case 'front_page': return is_front_page();
			case 'blog_home': return is_home();
			case 'singular': return is_singular();
			case 'post_type': return is_singular( sanitize_key( $value ) );
			case 'post_id': return is_singular() && get_queried_object_id() === absint( $value );
			case 'archive': return is_archive() || is_home();
			case 'taxonomy': return is_tax( sanitize_key( $value ) ) || is_category() || is_tag();
			case 'search': return is_search();
			case '404': return is_404();
			case 'logged_in': return is_user_logged_in();
			case 'logged_out': return ! is_user_logged_in();
		}
		return false;
	}

	private static function blocks_are_safe( $blocks ) {
		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( '' !== $name && 0 !== strpos( $name, 'core/' ) && 0 !== strpos( $name, 'cresco/' ) ) { return false; }
			if ( in_array( $name, array( 'core/html', 'core/shortcode', 'core/freeform' ), true ) ) { return false; }
			if ( ! empty( $block['innerBlocks'] ) && ! self::blocks_are_safe( $block['innerBlocks'] ) ) { return false; }
		}
		return true;
	}
}
