<?php
/** Studio color harmony regression tests. */

use CrescoCanvas\Builder\StudioColorHarmony;
use PHPUnit\Framework\TestCase;

final class StudioColorHarmonyTest extends TestCase {
	public function test_color_harmony_asset_contract_is_stable(): void {
		self::assertSame( 'cresco-canvas-studio-color-harmony', StudioColorHarmony::HANDLE );
		self::assertSame( 'assets/css/studio-color-harmony.css', StudioColorHarmony::STYLE );
	}

	public function test_stylesheet_defines_both_supported_editor_themes(): void {
		$path = CRESCO_CANVAS_PATH . StudioColorHarmony::STYLE;
		self::assertFileExists( $path );
		$css = (string) file_get_contents( $path );

		self::assertStringContainsString( 'html[data-cc-theme="dark"]', $css );
		self::assertStringContainsString( 'html[data-cc-theme="light"]', $css );
		self::assertStringContainsString( '--cc-color-accent: #7c83ff', $css );
		self::assertStringContainsString( '--cc-color-accent: #5d63e9', $css );
		self::assertStringContainsString( '.cc-studio-stage', $css );
		self::assertStringContainsString( '.cc-studio-tree-row.is-selected', $css );
	}

	public function test_stylesheet_does_not_style_rendered_document_content(): void {
		$css = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioColorHarmony::STYLE );
		self::assertStringNotContainsString( '.cresco-session-root', $css );
		self::assertStringNotContainsString( '.cresco-website-builder-root', $css );
	}
}
