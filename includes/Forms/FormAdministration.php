<?php
/**
 * Form submission administration, export, retention, and privacy helpers.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FormAdministration {
	const MAX_EXPORT_ROWS = 2000;
	const MAX_CELL_BYTES  = 32768;

	/** Register administration and privacy hooks. */
	public function register() {
		add_filter( 'manage_' . FormBuilder::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . FormBuilder::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'admin_post_cresco_export_submissions', array( $this, 'export_csv' ) );
		add_action( 'cresco_canvas_daily_retention', array( $this, 'purge_expired' ) );
		add_action( 'init', array( $this, 'schedule_retention' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'privacy_eraser' ) );
	}

	public function columns( $columns ) {
		$columns['cresco_form'] = __( 'Form', 'cresco-canvas' );
		$columns['cresco_email'] = __( 'Email', 'cresco-canvas' );
		return $columns;
	}

	public function column( $column, $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		$data = (array) get_post_meta( $post_id, '_cresco_submission_data', true );
		if ( 'cresco_form' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_cresco_form_id', true ) );
		}
		if ( 'cresco_email' === $column ) {
			foreach ( $data as $value ) {
				if ( is_string( $value ) && is_email( $value ) ) {
					echo esc_html( $value );
					break;
				}
		}
	}

	/** Stream a bounded CSV export for administrators. */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export submissions.', 'cresco-canvas' ), 403 );
		}
		check_admin_referer( 'cresco_export_submissions' );
		$form_id = sanitize_key( (string) ( $_GET['form_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query = new \WP_Query(
			array(
				'post_type'      => FormBuilder::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => self::MAX_EXPORT_ROWS,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => $form_id ? array( array( 'key' => '_cresco_form_id', 'value' => $form_id ) ) : array(),
			)
		);
		$rows = array();
		$headers = array( 'submission_id', 'submitted_at', 'form_id' );
		foreach ( $query->posts as $post ) {
			$data = (array) get_post_meta( $post->ID, '_cresco_submission_data', true );
			$headers = array_values( array_unique( array_merge( $headers, array_map( 'sanitize_key', array_keys( $data ) ) ) ) );
			$rows[] = array( 'submission_id' => $post->ID, 'submitted_at' => $post->post_date_gmt, 'form_id' => get_post_meta( $post->ID, '_cresco_form_id', true ) ) + $data;
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cresco-submissions-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'X-Content-Type-Options: nosniff' );
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) wp_die( esc_html__( 'The export stream could not be opened.', 'cresco-canvas' ) );
		fputcsv( $output, array_map( array( self::class, 'safe_csv_cell' ), $headers ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array_map(
					static function ( $key ) use ( $row ) {
						$value = $row[ $key ] ?? '';
						if ( is_array( $value ) ) $value = implode( ', ', array_map( 'strval', $value ) );
						if ( is_object( $value ) ) $value = wp_json_encode( $value );
						return self::safe_csv_cell( $value );
					},
					$headers
				)
			);
		}
		fclose( $output );
		exit;
	}

	/** Neutralize spreadsheet formulas and bound individual cells. */
	public static function safe_csv_cell( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = str_replace( array( "\0", "\r" ), '', $value );
		if ( strlen( $value ) > self::MAX_CELL_BYTES ) {
			$value = substr( $value, 0, self::MAX_CELL_BYTES );
		}
		$trimmed = ltrim( $value );
		if ( '' !== $trimmed && in_array( $trimmed[0], array( '=', '+', '-', '@' ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public function schedule_retention() {
		if ( ! wp_next_scheduled( 'cresco_canvas_daily_retention' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cresco_canvas_daily_retention' );
		}
	}

	/** Purge submissions whose signed retention date has passed. */
	public function purge_expired() {
		$ids = get_posts( array( 'post_type' => FormBuilder::POST_TYPE, 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 500, 'meta_key' => '_cresco_delete_after', 'meta_value' => time(), 'meta_compare' => '<=', 'meta_type' => 'NUMERIC' ) );
		foreach ( $ids as $id ) wp_delete_post( $id, true );
	}

	public function privacy_exporter( $exporters ) {
		$exporters['cresco-canvas-submissions'] = array( 'exporter_friendly_name' => __( 'Cresco Canvas form submissions', 'cresco-canvas' ), 'callback' => array( $this, 'export_personal_data' ) );
		return $exporters;
	}

	public function privacy_eraser( $erasers ) {
		$erasers['cresco-canvas-submissions'] = array( 'eraser_friendly_name' => __( 'Cresco Canvas form submissions', 'cresco-canvas' ), 'callback' => array( $this, 'erase_personal_data' ) );
		return $erasers;
	}

	public function export_personal_data( $email, $page = 1 ) {
		return array( 'data' => $this->personal_records( $email, false, $page ), 'done' => true );
	}

	public function erase_personal_data( $email, $page = 1 ) {
		$records = $this->personal_records( $email, true, $page );
		return array( 'items_removed' => (bool) $records, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}

	private function personal_records( $email, $erase, $page ) {
		$email = sanitize_email( $email );
		if ( ! $email ) return array();
		$query = new \WP_Query( array( 'post_type' => FormBuilder::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 100, 'paged' => max( 1, absint( $page ) ) ) );
		$output = array();
		foreach ( $query->posts as $post ) {
			$data = (array) get_post_meta( $post->ID, '_cresco_submission_data', true );
			if ( ! in_array( $email, array_filter( $data, 'is_string' ), true ) ) continue;
			if ( $erase ) {
				wp_delete_post( $post->ID, true );
			} else {
				$output[] = array( 'group_id' => 'cresco-canvas-submissions', 'group_label' => __( 'Form submissions', 'cresco-canvas' ), 'item_id' => 'submission-' . $post->ID, 'data' => array_map( static function ( $key, $value ) { return array( 'name' => $key, 'value' => is_array( $value ) ? implode( ', ', $value ) : $value ); }, array_keys( $data ), array_values( $data ) ) );
			}
		}
		return $output;
	}
}
