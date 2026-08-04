<?php
/**
 * Theme Builder diagnostics and administration helpers.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Theme;

use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemeDiagnostics {
	/** Register diagnostics and list-table integration. */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'manage_' . ThemeBuilder::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . ThemeBuilder::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/** Register a read-only diagnostics endpoint. */
	public function register_routes() {
		register_rest_route(
			'cresco-canvas/v1',
			'/theme-builder/diagnostics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_diagnostics' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
			)
		);
	}

	/** Return diagnostics for all non-trashed Theme Builder templates. */
	public function get_diagnostics() {
		$posts   = get_posts(
			array(
				'post_type'      => ThemeBuilder::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$records = array();

		foreach ( $posts as $post ) {
			$records[] = array(
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'status'     => $post->post_status,
				'type'       => (string) get_post_meta( $post->ID, ThemeBuilder::META_TYPE, true ),
				'priority'   => (int) get_post_meta( $post->ID, ThemeBuilder::META_PRIORITY, true ),
				'conditions' => (array) get_post_meta( $post->ID, ThemeBuilder::META_CONDITIONS, true ),
				'content'    => $post->post_content,
				'editUrl'    => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		$issues = self::analyze_records( $records );

		return new WP_REST_Response(
			array(
				'schemaVersion' => 1,
				'summary'       => array(
					'templates' => count( $records ),
					'errors'    => count( array_filter( $issues, static function ( $issue ) { return 'error' === $issue['severity']; } ) ),
					'warnings'  => count( array_filter( $issues, static function ( $issue ) { return 'warning' === $issue['severity']; } ) ),
				),
				'issues'        => $issues,
			)
		);
	}

	/**
	 * Analyze normalized template records without mutating content.
	 *
	 * @param array<int, array<string, mixed>> $records Template records.
	 * @return array<int, array<string, mixed>>
	 */
	public static function analyze_records( $records ) {
		$issues     = array();
		$signatures = array();

		foreach ( $records as $record ) {
			$id         = absint( $record['id'] ?? 0 );
			$type       = ThemeBuilder::sanitize_type( $record['type'] ?? '' );
			$priority   = min( 1000, max( 0, (int) ( $record['priority'] ?? 0 ) ) );
			$conditions = ThemeBuilder::sanitize_conditions( $record['conditions'] ?? array() );
			$status     = sanitize_key( (string) ( $record['status'] ?? '' ) );
			$content    = trim( (string) ( $record['content'] ?? '' ) );

			if ( '' === $type ) {
				$issues[] = self::issue( 'error', 'invalid_type', $id, __( 'Template has an invalid or missing type.', 'cresco-canvas' ) );
			}

			if ( '' === $content ) {
				$issues[] = self::issue( 'error', 'empty_content', $id, __( 'Template has no block content.', 'cresco-canvas' ) );
			}

			$has_include = false;
			foreach ( $conditions as $condition ) {
				if ( 'include' === $condition['operator'] ) {
					$has_include = true;
					break;
				}
			}
			if ( ! empty( $conditions ) && ! $has_include ) {
				$issues[] = self::issue( 'warning', 'exclude_only', $id, __( 'Template contains only exclusions and therefore matches every other context.', 'cresco-canvas' ) );
			}

			if ( 'publish' !== $status || '' === $type ) {
				continue;
			}

			$signature = $type . '|' . $priority . '|' . wp_json_encode( self::canonical_conditions( $conditions ) );
			if ( isset( $signatures[ $signature ] ) ) {
				$other_id = $signatures[ $signature ];
				$issues[] = array(
					'severity'   => 'warning',
					'code'       => 'ambiguous_priority',
					'templateId' => $id,
					'relatedId'  => $other_id,
					'message'    => __( 'Published templates share the same type, priority, and conditions. The selected template may depend on modification order.', 'cresco-canvas' ),
				);
			} else {
				$signatures[ $signature ] = $id;
			}
		}

		return $issues;
	}

	/** Return canonical conditions for deterministic comparison. */
	private static function canonical_conditions( $conditions ) {
		usort(
			$conditions,
			static function ( $left, $right ) {
				return strcmp(
					$left['operator'] . '|' . $left['rule'] . '|' . $left['value'],
					$right['operator'] . '|' . $right['rule'] . '|' . $right['value']
				);
			}
		);
		return $conditions;
	}

	/** Create a normalized issue. */
	private static function issue( $severity, $code, $template_id, $message ) {
		return array(
			'severity'   => $severity,
			'code'       => $code,
			'templateId' => $template_id,
			'message'    => $message,
		);
	}

	/** Add useful Theme Builder list-table columns. */
	public function add_columns( $columns ) {
		$columns['cresco_type']       = __( 'Template type', 'cresco-canvas' );
		$columns['cresco_priority']   = __( 'Priority', 'cresco-canvas' );
		$columns['cresco_conditions'] = __( 'Display conditions', 'cresco-canvas' );
		return $columns;
	}

	/** Render Theme Builder list-table values. */
	public function render_column( $column, $post_id ) {
		if ( 'cresco_type' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, ThemeBuilder::META_TYPE, true ) );
			return;
		}
		if ( 'cresco_priority' === $column ) {
			echo esc_html( (string) (int) get_post_meta( $post_id, ThemeBuilder::META_PRIORITY, true ) );
			return;
		}
		if ( 'cresco_conditions' === $column ) {
			$conditions = ThemeBuilder::sanitize_conditions( get_post_meta( $post_id, ThemeBuilder::META_CONDITIONS, true ) );
			if ( empty( $conditions ) ) {
				echo esc_html__( 'Entire site', 'cresco-canvas' );
				return;
			}
			$labels = array_map(
				static function ( $condition ) {
					$value = '' !== $condition['value'] ? ':' . $condition['value'] : '';
					return $condition['operator'] . ' ' . $condition['rule'] . $value;
				},
				$conditions
			);
			echo esc_html( implode( ', ', $labels ) );
		}
	}
}
