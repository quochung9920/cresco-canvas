<?php
/**
 * Semantic design-token regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Styles\DesignTokens;
use CrescoCanvas\Styles\GlobalStyles;
use PHPUnit\Framework\TestCase;

final class DesignTokensTest extends TestCase {
	public function test_catalog_exposes_semantic_token_families_without_removing_legacy_paths(): void {
		$tokens = DesignTokens::catalog( GlobalStyles::defaults() );
		self::assertSame( 4, $tokens['schemaVersion'] );
		self::assertSame( $tokens['spacing']['md'], $tokens['space']['md'] );
		self::assertSame( $tokens['shadows']['md'], $tokens['shadow']['md'] );
		self::assertSame( '#ffffff', $tokens['colors']['surface'] );
		self::assertArrayHasKey( 'body', $tokens['typography'] );
		self::assertArrayHasKey( 'heading-xl', $tokens['typography'] );
		self::assertArrayHasKey( 'display', $tokens['typography'] );
		self::assertArrayHasKey( 'sm', $tokens['containers'] );
		self::assertArrayHasKey( 'lg', $tokens['containers'] );
		self::assertArrayHasKey( 'normal', $tokens['transitions'] );
		self::assertSame( '1000', $tokens['zIndex']['modal'] );
		self::assertArrayHasKey( 'button', $tokens );
		self::assertSame( '#635bff', $tokens['button']['background'] );
		self::assertSame( '#ffffff', $tokens['button']['text'] );
		self::assertSame( '#635bff', $tokens['button']['activeBackground'] );
		self::assertSame( '#ffffff', $tokens['button']['activeText'] );
		self::assertSame( '600', $tokens['button']['fontWeight'] );
		// Existing documents may still resolve legacy radius paths.
		self::assertArrayHasKey( 'radius', $tokens );
	}

	public function test_css_variables_publish_semantic_container_transition_layer_and_button_tokens(): void {
		$css = DesignTokens::css_variables( GlobalStyles::defaults() );
		foreach ( array(
			'--cc-surface:',
			'--cc-container-sm:',
			'--cc-container-lg:',
			'--cc-transition:',
			'--cc-z-modal:',
			'--cc-button-bg:',
			'--cc-button-text:',
			'--cc-button-hover-bg:',
			'--cc-button-active-bg:',
			'--cc-button-active-text:',
			'--cc-button-radius:',
			'--cc-button-font-weight:',
		) as $token ) {
			self::assertStringContainsString( $token, $css );
		}
	}
}