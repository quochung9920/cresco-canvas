<?php
/**
 * Form submission administration, export, retention, and privacy helpers.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Forms;

use CrescoCanvas\Security\UploadSecurity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FormAdministration {
	const MAX_EXPORT_ROWS = 2000;
	const MAX_CELL_BYTES  = 32768;
	const PRIVACY_PAGE_SIZE = 100;
	const ERASE_MARKER_META = '_cresco_privacy_erase_pending';

	/** Register administration and privacy hooks. */
	public function register() {
		add_filter( 'manage_' . FormBuilder::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . FormBuilder::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'admin_post_cresco_export_submissions', array( $this, 'export_csv' ) );
		add_action( 'cresco_canvas_daily_retention', array( $this, 'purge_expired' ) );
		add_action( 'init', array( $this, 'schedule_retention' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'privacy_eraser' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
	}

	public function columns( $columns ) {
		$columns['cresco_form']  = __( 'Form', 'cresco-canvas' );
		$columns['cresco_email'] = __( 'Email', 'cresco-canvas' );
		return $columns;
	}

	public function column( $column, $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		$data = (array) get_post_meta( $post_id, '_cresco_submission_data', true );
		if ( 'cresco_form' === $column ) echo esc_html( (string) get_post_meta( $post_id, '_cresco_form_id', true ) );
		if ( 'cresco_email' === $column ) {
			$email = self::first_email( $data );
			if ( $email ) echo esc_html( $email );
		}
	}

	/** Stream a bounded CSV export for administrators. */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to export submissions.', 'cresco-canvas' ), 403 );
		check_admin_referer( 'cresco_export_submissions' );
		$form_id = isset( $_GET['form_id'] ) ? FormBuilder::form_id( wp_unslash( $_GET['form_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above.
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
		$rows    = array();
		$headers = array( 'submission_id', 'submitted_at', 'form_id' );
		foreach ( array_slice( (array) $query->posts, 0, self::MAX_EXPORT_ROWS ) as $post ) {
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
						if ( is_array( $value ) || is_object( $value ) ) $value = wp_json_encode( $value );
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
		if ( strlen( $value ) > self::MAX_CELL_BYTES ) $value = substr( $value, 0, self::MAX_CELL_BYTES );
		if ( preg_match( '/^[\x00-\x20]*[=+\-@]/', $value ) ) $value = "'" . $value;
		return $value;
	}

	public function schedule_retention() {
		if ( ! wp_next_scheduled( 'cresco_canvas_daily_retention' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cresco_canvas_daily_retention' );
	}

	/** Purge a bounded batch of submissions whose retention date has passed. */
	public function purge_expired() {
		$ids = get_posts( array( 'post_type' => FormBuilder::POST_TYPE, 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 200, 'meta_key' => '_cresco_delete_after', 'meta_value' => time(), 'meta_compare' => '<=', 'meta_type' => 'NUMERIC', 'no_found_rows' => true ) );
		foreach ( $ids as $id ) $this->delete_submission( $id );
	}

	public function privacy_exporter( $exporters ) {
		$exporters['cresco-canvas-submissions'] = array( 'exporter_friendly_name' => __( 'Cresco Canvas form submissions', 'cresco-canvas' ), 'callback' => array( $this, 'export_personal_data' ) );
		return $exporters;
	}

	public function privacy_eraser( $erasers ) {
		$erasers['cresco-canvas-submissions'] = array( 'eraser_friendly_name' => __( 'Cresco Canvas form submissions', 'cresco-canvas' ), 'callback' => array( $this, 'erase_personal_data' ) );
		return $erasers;
	}

	/** Export one bounded page of records that recursively contain the requested email. */
	public function export_personal_data( $email, $page = 1 ) {
		$email = sanitize_email( $email );
		if ( ! $email ) return array( 'data' => array(), 'done' => true );
		$query = $this->privacy_query( $page );
		$output = array();
		foreach ( $query->posts as $post ) {
			$data = (array) get_post_meta( $post->ID, '_cresco_submission_data', true );
			if ( ! self::contains_email( $data, $email ) ) continue;
			$output[] = array(
				'group_id' => 'cresco-canvas-submissions',
				'group_label' => __( 'Form submissions', 'cresco-canvas' ),
				'item_id' => 'submission-' . $post->ID,
				'data' => array_map(
					static function ( $key, $value ) {
						return array( 'name' => sanitize_text_field( (string) $key ), 'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
					},
					array_keys( $data ),
					array_values( $data )
				),
			);
		}
		return array( 'data' => $output, 'done' => max( 1, absint( $page ) ) >= max( 1, (int) $query->max_num_pages ) );
	}

	/**
	 * Mark matches while paging without changing pagination, then delete the marked
	 * Cresco-owned submissions and uploads only after the final scan page.
	 */
	public function erase_personal_data( $email, $page = 1 ) {
		$email = sanitize_email( $email );
		if ( ! $email ) return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		$page = max( 1, absint( $page ) );
		$query = $this->privacy_query( $page );
		$marker = substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) ), 0, 40 );
		foreach ( $query->posts as $post ) {
			$data = (array) get_post_meta( $post->ID, '_cresco_submission_data', true );
			if ( self::contains_email( $data, $email ) ) update_post_meta( $post->ID, self::ERASE_MARKER_META, $marker );
		}
		$done = $page >= max( 1, (int) $query->max_num_pages );
		$removed = 0;
		if ( $done ) {
			do {
				$ids = get_posts( array( 'post_type' => FormBuilder::POST_TYPE, 'post_status' => 'private', 'fields' => 'ids', 'posts_per_page' => 100, 'meta_key' => self::ERASE_MARKER_META, 'meta_value' => $marker, 'no_found_rows' => true ) );
				foreach ( $ids as $id ) {
					if ( $this->delete_submission( $id ) ) ++$removed;
				}
			} while ( count( $ids ) === 100 );
		}
		return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => array(), 'done' => $done );
	}

	/** Add a concise policy disclosure to WordPress privacy guidance. */
	public function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) return;
		wp_add_privacy_policy_content(
			'Cresco Canvas',
			wp_kses_post( __( '<p>Cresco Canvas can store form submissions as private WordPress records when a form is configured to do so. A form may also store accepted uploads in private Cresco-owned storage outside the WordPress web root. Site administrators control retention, email delivery, and optional webhook delivery. Temporary webhook retry state expires automatically. WordPress personal-data export and erasure requests include Cresco form submissions matched by email.</p>', 'cresco-canvas' ) )
		);
	}

	private function privacy_query( $page ) {
		return new \WP_Query( array( 'post_type' => FormBuilder::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => self::PRIVACY_PAGE_SIZE, 'paged' => max( 1, absint( $page ) ), 'orderby' => 'ID', 'order' => 'ASC' ) );
	}

	private function delete_submission( $post_id ) {
		$post_id = absint( $post_id );
		if ( FormBuilder::POST_TYPE !== get_post_type( $post_id ) ) return false;
		UploadSecurity::delete_for_submission( $post_id );
		$legacy = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => 100, 'meta_key' => '_cresco_submission_id', 'meta_value' => $post_id, 'no_found_rows' => true ) );
		foreach ( $legacy as $attachment_id ) {
			if ( get_post_meta( $attachment_id, '_cresco_form_upload', true ) ) wp_delete_attachment( $attachment_id, true );
		}
		return false !== wp_delete_post( $post_id, true );
	}

	private static function contains_email( $value, $email ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) if ( self::contains_email( $item, $email ) ) return true;
			return false;
		}
		return is_string( $value ) && 0 === strcasecmp( trim( $value ), $email );
	}

	private static function first_email( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$found = self::first_email( $item );
				if ( $found ) return $found;
			}
			return '';
		}
		return is_string( $value ) && is_email( $value ) ? sanitize_email( $value ) : '';
	}
}
