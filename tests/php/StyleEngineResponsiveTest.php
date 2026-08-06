<?php
/** Responsive style engine regression tests. @package CrescoCanvas */

use CrescoCanvas\Styles\StyleEngine;
use PHPUnit\Framework\TestCase;

final class StyleEngineResponsiveTest extends TestCase {
	public function test_base_and_device_values_compile_separately(): void {
		$style = array(
			'dimensions' => array(
				'width' => '1200px',
			),
			'responsive' => array(
				'tablet' => array(
					'dimensions' => array(
						'width' => '90%',
					),
				),
				'mobile' => array(
					'spacing' => array(
						'padding' => array(
							'left'  => '16px',
							'right' => '16px',
						),
					),
				),
			),
		);

		self::assertContains( 'width:1200px', StyleEngine::declarations( $style ) );
		self::assertContains( '--cc-r-tablet-width:90%', StyleEngine::responsive_variables( $style ) );
		self::assertContains( '--cc-r-mobile-padding-left:16px', StyleEngine::responsive_variables( $style ) );
		self::assertContains( '--cc-r-mobile-padding-right:16px', StyleEngine::responsive_variables( $style ) );
	}

	public function test_invalid_responsive_css_is_rejected(): void {
		$style = array(
			'responsive' => array(
				'mobile' => array(
					'dimensions' => array(
						'width' => '100%;display:none',
					),
					'effects' => array(
						'transform' => 'translateY(4px)}body{display:none',
					),
				),
			),
		);

		self::assertSame( array(), StyleEngine::responsive_variables( $style ) );
	}

	public function test_schema_version_is_two(): void {
		self::assertSame( 2, StyleEngine::SCHEMA_VERSION );
	}
}
