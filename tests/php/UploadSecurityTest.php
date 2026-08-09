<?php
use CrescoCanvas\Security\UploadSecurity;
use PHPUnit\Framework\TestCase;

final class UploadSecurityTest extends TestCase {
	private $files = array();
	protected function tearDown(): void { foreach($this->files as $file) @unlink($file); }
	private function upload( string $name, string $content ): array { $path=tempnam(sys_get_temp_dir(),'cresco-upload-'); file_put_contents($path,$content); $this->files[]=$path; return array('name'=>$name,'tmp_name'=>$path,'size'=>filesize($path),'error'=>UPLOAD_ERR_OK); }

	public function test_private_storage_rejects_path_under_document_root(): void {
		$previous = $_SERVER['DOCUMENT_ROOT'] ?? null;
		$_SERVER['DOCUMENT_ROOT'] = dirname( CRESCO_CANVAS_PATH );
		$result = UploadSecurity::private_root();
		if ( null === $previous ) unset( $_SERVER['DOCUMENT_ROOT'] ); else $_SERVER['DOCUMENT_ROOT'] = $previous;
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_upload_storage_public', $result->get_error_code() );
	}

	public function test_double_executable_extension_is_rejected(): void {
		self::assertTrue( UploadSecurity::has_dangerous_extension( 'invoice.php.jpg' ) );
		self::assertTrue( UploadSecurity::has_dangerous_extension( 'payload.phar.pdf' ) );
		self::assertFalse( UploadSecurity::has_dangerous_extension( 'invoice.final.pdf' ) );
	}

	public function test_mime_spoof_is_rejected(): void {
		$gif = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true );
		$result = UploadSecurity::validate_file( $this->upload( 'document.pdf', $gif ) );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_upload_mime_mismatch', $result->get_error_code() );
	}

	public function test_php_like_payload_is_rejected_even_in_text_allowlist(): void {
		$result = UploadSecurity::validate_file( $this->upload( 'notes.txt', "hello\n<?php echo 'x'; ?>" ) );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_upload_executable_payload', $result->get_error_code() );
	}

	public function test_binary_control_bytes_are_rejected_for_text_uploads(): void {
		$result = UploadSecurity::validate_file( $this->upload( 'notes.txt', "abc\x01def" ) );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_upload_binary_text', $result->get_error_code() );
	}

	public function test_active_pdf_actions_are_rejected(): void {
		$pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /OpenAction 2 0 R /JavaScript (alert) >>\nendobj\n%%EOF\n";
		$result = UploadSecurity::validate_file( $this->upload( 'active.pdf', $pdf ) );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_upload_active_pdf', $result->get_error_code() );
	}

	public function test_image_polyglot_trailing_data_is_rejected(): void {
		$gif = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true ) . 'TRAILING';
		$result = UploadSecurity::validate_file( $this->upload( 'pixel.gif', $gif ) );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cresco_upload_image_polyglot', $result->get_error_code() );
	}
}
