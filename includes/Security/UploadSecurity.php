<?php
/**
 * Private storage and validation for Cresco form uploads.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Security;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UploadSecurity {
	const POST_TYPE        = 'cresco_upload';
	const MAX_UPLOAD_BYTES = 5242880;
	const MAX_UPLOADS      = 5;
	const DEFAULT_RETENTION_DAYS = 30;

	/** Register the private ownership record and protected download endpoint. */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ), 9 );
		add_action( 'admin_post_cresco_download_form_upload', array( $this, 'download' ) );
		add_action( 'cresco_canvas_daily_cleanup', array( $this, 'cleanup_expired' ), 20 );
	}

	/** Register an internal post type used only to track Cresco-owned files. */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array( 'name' => __( 'Cresco Uploads', 'cresco-canvas' ) ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
			)
		);
	}

	/** Return the explicit allowlist used for extension and WordPress type checks. */
	public static function allowed_mimes() {
		return array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'gif'      => 'image/gif',
			'webp'     => 'image/webp',
			'pdf'      => 'application/pdf',
			'txt'      => 'text/plain',
			'csv'      => 'text/csv',
		);
	}

	/**
	 * Validate an upload using filename policy, WordPress real-type checks,
	 * fileinfo/image inspection, and bounded content/polyglot checks.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function validate_file( $file ) {
		$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		$tmp  = (string) ( $file['tmp_name'] ?? '' );
		$size = absint( $file['size'] ?? 0 );
		$error = absint( $file['error'] ?? 0 );

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_file( $tmp ) ) {
			return new WP_Error( 'cresco_upload_invalid', __( 'The uploaded file is not valid.', 'cresco-canvas' ) );
		}
		if ( 0 === $size || $size > self::MAX_UPLOAD_BYTES || $size !== (int) filesize( $tmp ) ) {
			return new WP_Error( 'cresco_upload_too_large', __( 'The file is empty or exceeds the 5 MB limit.', 'cresco-canvas' ) );
		}
		if ( '' === $name || self::has_dangerous_extension( $name ) ) {
			return new WP_Error( 'cresco_upload_dangerous_name', __( 'The file name contains a blocked executable extension.', 'cresco-canvas' ) );
		}

		$extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$allowed_exts = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv' );
		if ( ! in_array( $extension, $allowed_exts, true ) ) {
			return new WP_Error( 'cresco_upload_invalid_extension', __( 'This file extension is not allowed.', 'cresco-canvas' ) );
		}

		$checked = wp_check_filetype_and_ext( $tmp, $name, self::allowed_mimes() );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error( 'cresco_upload_invalid_type', __( 'The file contents do not match an allowed file type.', 'cresco-canvas' ) );
		}
		$checked_ext = strtolower( (string) $checked['ext'] );
		if ( 'jpeg' === $checked_ext ) {
			$checked_ext = 'jpg';
		}
		$normalized_ext = 'jpeg' === $extension ? 'jpg' : $extension;
		if ( $checked_ext !== $normalized_ext ) {
			return new WP_Error( 'cresco_upload_extension_mismatch', __( 'The file extension does not match its contents.', 'cresco-canvas' ) );
		}

		$actual_mime = self::actual_mime( $tmp, $extension );
		if ( is_wp_error( $actual_mime ) ) {
			return $actual_mime;
		}
		if ( ! self::mime_matches_extension( $extension, $actual_mime ) ) {
			return new WP_Error( 'cresco_upload_mime_mismatch', __( 'The detected file type is not allowed for this extension.', 'cresco-canvas' ) );
		}

		$content_check = self::validate_content( $tmp, $extension );
		if ( is_wp_error( $content_check ) ) {
			return $content_check;
		}

		return array(
			'name' => $name,
			'extension' => $extension,
			'mime' => $actual_mime,
			'size' => $size,
		);
	}

	/** Move an accepted upload outside the web root and create a private ownership record. */
	public static function store( $file, $form_id = '', $retention_days = self::DEFAULT_RETENTION_DAYS ) {
		$validated = self::validate_file( $file );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$root = self::private_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$site_dir = trailingslashit( $root ) . 'site-' . absint( get_current_blog_id() );
		if ( ! wp_mkdir_p( $site_dir ) || ! is_dir( $site_dir ) || ! is_writable( $site_dir ) ) {
			return new WP_Error( 'cresco_upload_storage_unavailable', __( 'Private upload storage is not writable.', 'cresco-canvas' ) );
		}

		$extension = $validated['extension'];
		$random = strtolower( wp_generate_password( 32, false, false ) );
		$target = trailingslashit( $site_dir ) . $random . '.' . $extension;
		if ( ! self::path_is_within( $target, $root ) ) {
			return new WP_Error( 'cresco_upload_storage_path', __( 'Private upload storage path is invalid.', 'cresco-canvas' ) );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) || ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			return new WP_Error( 'cresco_upload_move_failed', __( 'The upload could not be moved into private storage.', 'cresco-canvas' ) );
		}
		@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$retention_days = min( 365, max( 1, absint( $retention_days ) ) );
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $validated['name'],
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			@unlink( $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $post_id;
		}

		update_post_meta( $post_id, '_cresco_upload_path', $target );
		update_post_meta( $post_id, '_cresco_upload_name', $validated['name'] );
		update_post_meta( $post_id, '_cresco_upload_mime', $validated['mime'] );
		update_post_meta( $post_id, '_cresco_upload_size', $validated['size'] );
		update_post_meta( $post_id, '_cresco_upload_form_id', sanitize_key( (string) $form_id ) );
		update_post_meta( $post_id, '_cresco_upload_delete_after', time() + ( $retention_days * DAY_IN_SECONDS ) );

		return array(
			'uploadId' => (int) $post_id,
			'name'     => $validated['name'],
			'mime'     => $validated['mime'],
			'size'     => (int) $validated['size'],
		);
	}

	/** Link one or more private uploads to a stored submission. */
	public static function attach_to_submission( $upload_ids, $submission_id, $retention_days = self::DEFAULT_RETENTION_DAYS ) {
		$submission_id = absint( $submission_id );
		$retention_days = min( 365, max( 1, absint( $retention_days ) ) );
		foreach ( array_slice( array_map( 'absint', (array) $upload_ids ), 0, self::MAX_UPLOADS ) as $upload_id ) {
			if ( self::POST_TYPE !== get_post_type( $upload_id ) ) {
				continue;
			}
			update_post_meta( $upload_id, '_cresco_submission_id', $submission_id );
			update_post_meta( $upload_id, '_cresco_upload_delete_after', time() + ( $retention_days * DAY_IN_SECONDS ) );
		}
	}

	/** Delete all private uploads owned by one submission. */
	public static function delete_for_submission( $submission_id ) {
		$submission_id = absint( $submission_id );
		do {
			$ids = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'meta_key'       => '_cresco_submission_id',
					'meta_value'     => $submission_id,
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $id ) {
				self::delete_upload( $id );
			}
		} while ( count( $ids ) === 100 );
	}

	/** Delete expired private uploads in bounded batches. */
	public function cleanup_expired() {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_cresco_upload_delete_after',
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
				'no_found_rows' => true,
			)
		);
		foreach ( $ids as $id ) {
			self::delete_upload( $id );
		}
	}

	/** Serve a private file only after capability and nonce checks. */
	public function download() {
		$upload_id = isset( $_GET['upload'] ) ? absint( wp_unslash( $_GET['upload'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below.
		if ( ! $upload_id || self::POST_TYPE !== get_post_type( $upload_id ) ) {
			wp_die( esc_html__( 'Upload not found.', 'cresco-canvas' ), '', array( 'response' => 404 ) );
		}
		check_admin_referer( 'cresco_download_upload_' . $upload_id );
		$submission_id = absint( get_post_meta( $upload_id, '_cresco_submission_id', true ) );
		if ( ! current_user_can( 'manage_options' ) && ( ! $submission_id || ! current_user_can( 'edit_post', $submission_id ) ) ) {
			wp_die( esc_html__( 'You are not allowed to download this file.', 'cresco-canvas' ), '', array( 'response' => 403 ) );
		}
		$path = (string) get_post_meta( $upload_id, '_cresco_upload_path', true );
		$root = self::private_root();
		if ( is_wp_error( $root ) || ! is_file( $path ) || ! self::path_is_within( $path, $root ) ) {
			wp_die( esc_html__( 'Upload file is unavailable.', 'cresco-canvas' ), '', array( 'response' => 404 ) );
		}
		$name = sanitize_file_name( (string) get_post_meta( $upload_id, '_cresco_upload_name', true ) );
		$mime = sanitize_mime_type( (string) get_post_meta( $upload_id, '_cresco_upload_mime', true ) );
		nocache_headers();
		header( 'Content-Type: ' . ( $mime ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $name ?: 'download' ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: sandbox; default-src 'none'" );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- protected local private file stream.
		exit;
	}

	/** Delete one owned file and its internal ownership record. */
	public static function delete_upload( $upload_id ) {
		$upload_id = absint( $upload_id );
		if ( self::POST_TYPE !== get_post_type( $upload_id ) ) {
			return false;
		}
		$path = (string) get_post_meta( $upload_id, '_cresco_upload_path', true );
		$root = self::private_root();
		if ( ! is_wp_error( $root ) && $path && is_file( $path ) && self::path_is_within( $path, $root ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return false !== wp_delete_post( $upload_id, true );
	}

	/** Return a storage root outside ABSPATH or fail closed. */
	public static function private_root() {
		$configured = defined( 'CRESCO_CANVAS_PRIVATE_UPLOAD_DIR' ) ? (string) CRESCO_CANVAS_PRIVATE_UPLOAD_DIR : dirname( rtrim( ABSPATH, '/\\' ) ) . '/.cresco-canvas-private';
		$root = wp_normalize_path( $configured );
		$is_absolute = str_starts_with( $root, '/' ) || (bool) preg_match( '/^[A-Za-z]:\//', $root );
		if ( ! $is_absolute ) {
			return new WP_Error( 'cresco_upload_storage_relative', __( 'Private upload storage must use an absolute filesystem path.', 'cresco-canvas' ) );
		}
		$parent = realpath( dirname( $root ) );
		if ( false !== $parent ) {
			$root = trailingslashit( wp_normalize_path( $parent ) ) . basename( $root );
		}
		$wordpress_root = wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH );
		$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) && '' !== trim( (string) $_SERVER['DOCUMENT_ROOT'] )
			? wp_normalize_path( realpath( (string) $_SERVER['DOCUMENT_ROOT'] ) ?: (string) $_SERVER['DOCUMENT_ROOT'] )
			: '';
		if ( '' === trim( $root ) || self::path_is_within( $root, $wordpress_root ) || ( $document_root && self::path_is_within( $root, $document_root ) ) ) {
			return new WP_Error( 'cresco_upload_storage_public', __( 'Private upload storage must be outside the web document root.', 'cresco-canvas' ) );
		}
		return untrailingslashit( $root );
	}

	/** Reject double extensions containing executable/server-config suffixes. */
	public static function has_dangerous_extension( $name ) {
		$parts = explode( '.', strtolower( sanitize_file_name( (string) $name ) ) );
		array_shift( $parts );
		$dangerous = array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'jspx', 'shtml', 'htaccess', 'htpasswd', 'user', 'ini' );
		foreach ( $parts as $part ) {
			if ( in_array( $part, $dangerous, true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function actual_mime( $path, $extension ) {
		if ( in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			$mime = wp_get_image_mime( $path );
			return $mime ? $mime : new WP_Error( 'cresco_upload_invalid_image', __( 'The image contents are invalid.', 'cresco-canvas' ) );
		}
		$mime = '';
		if ( class_exists( 'finfo' ) ) {
			$finfo = new \finfo( FILEINFO_MIME_TYPE );
			$mime = (string) $finfo->file( $path );
		}
		return sanitize_mime_type( $mime ?: ( 'pdf' === $extension ? 'application/pdf' : 'text/plain' ) );
	}

	private static function mime_matches_extension( $extension, $mime ) {
		$map = array(
			'jpg'  => array( 'image/jpeg' ),
			'jpeg' => array( 'image/jpeg' ),
			'png'  => array( 'image/png' ),
			'gif'  => array( 'image/gif' ),
			'webp' => array( 'image/webp' ),
			'pdf'  => array( 'application/pdf' ),
			'txt'  => array( 'text/plain', 'text/x-plain', 'application/octet-stream' ),
			'csv'  => array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream' ),
		);
		return in_array( strtolower( (string) $mime ), $map[ $extension ] ?? array(), true );
	}

	private static function validate_content( $path, $extension ) {
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return new WP_Error( 'cresco_upload_unreadable', __( 'The uploaded file could not be inspected.', 'cresco-canvas' ) );
		}
		if ( preg_match( '/<\?(?:php|=)|<%|__halt_compiler\s*\(|phar:\/\//i', $content ) ) {
			return new WP_Error( 'cresco_upload_executable_payload', __( 'Executable content is not allowed in uploads.', 'cresco-canvas' ) );
		}
		if ( in_array( $extension, array( 'txt', 'csv' ), true ) ) {
			if ( false !== strpos( $content, "\0" ) || preg_match( '/[\x01-\x08\x0B\x0C\x0E-\x1F]/', $content ) ) {
				return new WP_Error( 'cresco_upload_binary_text', __( 'Text uploads cannot contain binary control bytes.', 'cresco-canvas' ) );
			}
		}
		if ( 'pdf' === $extension ) {
			if ( 0 !== strpos( $content, '%PDF-' ) || ! preg_match( '/%%EOF\s*\z/s', $content ) ) {
				return new WP_Error( 'cresco_upload_invalid_pdf', __( 'The PDF structure is invalid.', 'cresco-canvas' ) );
			}
			if ( preg_match( '/\/(?:JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b/i', $content ) ) {
				return new WP_Error( 'cresco_upload_active_pdf', __( 'Active or embedded PDF content is not allowed.', 'cresco-canvas' ) );
			}
		}
		if ( in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) && ! self::image_has_clean_ending( $content, $extension ) ) {
			return new WP_Error( 'cresco_upload_image_polyglot', __( 'The image contains unexpected trailing data.', 'cresco-canvas' ) );
		}
		return true;
	}

	private static function image_has_clean_ending( $content, $extension ) {
		if ( in_array( $extension, array( 'jpg', 'jpeg' ), true ) ) {
			return str_ends_with( $content, "\xFF\xD9" );
		}
		if ( 'png' === $extension ) {
			return str_ends_with( $content, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82" );
		}
		if ( 'gif' === $extension ) {
			return str_ends_with( $content, ';' );
		}
		if ( 'webp' === $extension && strlen( $content ) >= 12 && 'RIFF' === substr( $content, 0, 4 ) && 'WEBP' === substr( $content, 8, 4 ) ) {
			$declared = unpack( 'Vsize', substr( $content, 4, 4 ) );
			return isset( $declared['size'] ) && (int) $declared['size'] + 8 === strlen( $content );
		}
		return false;
	}

	private static function path_is_within( $path, $root ) {
		$path = untrailingslashit( wp_normalize_path( (string) $path ) );
		$root = untrailingslashit( wp_normalize_path( (string) $root ) );
		return '' !== $root && ( $path === $root || str_starts_with( $path, $root . '/' ) );
	}
}
