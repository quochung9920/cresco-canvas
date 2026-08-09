<?php
use CrescoCanvas\Forms\FormBuilder;
use CrescoCanvas\Forms\FormCompletion;
use PHPUnit\Framework\TestCase;

final class FormsSecurityTest extends TestCase {
	protected function setUp(): void { $GLOBALS['cresco_test_filters']=array(); }
	public function test_oversized_scalar_field_is_rejected(): void {
		$result = FormBuilder::validate( array('name'=>str_repeat('a',2049)), array('name'=>array('type'=>'text','required'=>true)) );
		self::assertArrayHasKey( 'name', $result['errors'] );
	}
	public function test_number_minimum_and_maximum_are_enforced(): void {
		$schema=array('qty'=>array('type'=>'number','min'=>1,'max'=>10));
		self::assertArrayHasKey('qty',FormBuilder::validate(array('qty'=>'0'),$schema)['errors']);
		self::assertArrayHasKey('qty',FormBuilder::validate(array('qty'=>'11'),$schema)['errors']);
		self::assertSame(array(),FormBuilder::validate(array('qty'=>'5'),$schema)['errors']);
	}
	public function test_array_input_is_rejected_for_scalar_fields(): void {
		$result=FormBuilder::validate( array( 'email' => array( 'a@example.test' ) ), array( 'email' => array( 'type' => 'email' ) ) );
		self::assertArrayHasKey('email',$result['errors']);
	}
	public function test_captcha_token_is_bounded_and_submit_boundary_uses_adapter(): void {
		$result=FormCompletion::verify_token('turnstile',str_repeat('x',4097));
		self::assertInstanceOf(WP_Error::class,$result);
		self::assertSame('cresco_captcha_too_large',$result->get_error_code());
		add_filter('cresco_canvas_verify_captcha', static function(){ return true; });
		self::assertTrue(FormBuilder::verify_submission_captcha(array('captcha'=>array('provider'=>'turnstile','action'=>'contact')),'token'));
	}
}
