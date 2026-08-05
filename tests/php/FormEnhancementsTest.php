<?php
/**
 * Advanced form boundary tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Forms\FormBuilder;
use PHPUnit\Framework\TestCase;

final class FormEnhancementsTest extends TestCase {
	public function test_signed_form_payload_detects_tampering(): void {
		$payload   = FormBuilder::encode( array( 'formId' => 'contact', 'schema' => array( 'email' => array( 'type' => 'email' ) ) ) );
		$signature = FormBuilder::sign( $payload );

		$this->assertTrue( FormBuilder::verify( $payload, $signature ) );
		$this->assertFalse( FormBuilder::verify( $payload . 'x', $signature ) );
	}

	public function test_field_names_are_bounded_and_sanitized(): void {
		$this->assertSame( 'customer_email', FormBuilder::field_name( 'Customer Email' ) );
		$this->assertLessThanOrEqual( 48, strlen( FormBuilder::field_name( str_repeat( 'a', 80 ) ) ) );
	}

	public function test_file_is_an_allowed_field_type(): void {
		$this->assertSame( 'file', FormBuilder::field_type( 'file' ) );
		$this->assertSame( 'text', FormBuilder::field_type( 'executable' ) );
	}

	public function test_options_are_bounded_and_sanitized(): void {
		$options = FormBuilder::options( "Starter|starter\nPremium Plan|premium-plan" );
		$this->assertSame( 'starter', $options[0]['value'] );
		$this->assertSame( 'premium-plan', $options[1]['value'] );
	}
}
