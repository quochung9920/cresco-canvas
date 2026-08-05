<?php
/**
 * Tests for final 0.9 form workflow normalization.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Forms\FormCompletion;
use PHPUnit\Framework\TestCase;

final class FormCompletionTest extends TestCase {
	public function test_formula_accepts_bounded_arithmetic_grammar() {
		$this->assertSame( 'quantity * price + tax', FormCompletion::sanitize_formula( 'quantity   * price + tax' ) );
	}

	public function test_formula_rejects_executable_syntax() {
		$this->assertSame( '', FormCompletion::sanitize_formula( 'alert(1);' ) );
		$this->assertSame( '', FormCompletion::sanitize_formula( 'system("id")' ) );
	}

	public function test_provider_is_allow_listed() {
		$this->assertSame( 'hcaptcha', FormCompletion::sanitize_provider( 'hcaptcha' ) );
		$this->assertSame( 'turnstile', FormCompletion::sanitize_provider( 'unknown' ) );
	}

	public function test_condition_operator_is_allow_listed() {
		$this->assertSame( 'contains', FormCompletion::sanitize_operator( 'contains' ) );
		$this->assertSame( 'equals', FormCompletion::sanitize_operator( 'regex' ) );
	}
}
