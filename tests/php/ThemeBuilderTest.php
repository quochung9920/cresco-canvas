<?php
/**
 * Theme Builder regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Theme\ThemeBuilder;
use PHPUnit\Framework\TestCase;

final class ThemeBuilderTest extends TestCase {
	public function test_template_types_are_allow_listed(): void {
		self::assertSame( 'header', ThemeBuilder::sanitize_type( 'header' ) );
		self::assertSame( '404', ThemeBuilder::sanitize_type( '404' ) );
		self::assertSame( '', ThemeBuilder::sanitize_type( 'php-template' ) );
	}

	public function test_conditions_are_sanitized_and_bounded(): void {
		$conditions = ThemeBuilder::sanitize_conditions(
			array(
				array( 'operator' => 'exclude', 'rule' => 'post_type', 'value' => 'post<script>' ),
				array( 'operator' => 'invalid', 'rule' => 'entire_site', 'value' => '' ),
				array( 'operator' => 'include', 'rule' => 'execute_php', 'value' => 'phpinfo()' ),
			)
		);

		self::assertCount( 2, $conditions );
		self::assertSame( 'exclude', $conditions[0]['operator'] );
		self::assertSame( 'post_type', $conditions[0]['rule'] );
		self::assertSame( 'include', $conditions[1]['operator'] );
		self::assertSame( 'entire_site', $conditions[1]['rule'] );
	}

	public function test_no_conditions_match_by_default(): void {
		self::assertTrue( ThemeBuilder::conditions_match( array() ) );
	}
}
