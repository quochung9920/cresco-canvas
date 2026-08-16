<?php
/** Studio color harmony regression tests. */

use CrescoCanvas\Builder\StudioColorHarmony;
use PHPUnit\Framework\TestCase;

final class StudioColorHarmonyTest extends TestCase {
	public function test_color_harmony_asset_contract_is_stable(): void {
		self::assertSame( 'cresco-canvas-studio-color-harmony', StudioColorHarmony::HANDLE );
		self::assertSame( 'assets/css/studio-color-harmony.css', StudioColorHarmony::STYLE );
		self::assertSame( 'cresco-canvas-studio-light-first', StudioColorHarmony::LIGHT_HANDLE );
		self::assertSame( 'assets/css/studio-light-first.css', StudioColorHarmony::LIGHT_STYLE );
		self::assertSame( 'build/studio-light-first.js', StudioColorHarmony::LIGHT_SCRIPT );
	}

	public function test_stylesheet_defines_both_supported_editor_themes(): void {
		$path = CRESCO_CANVAS_PATH . StudioColorHarmony::STYLE;
		self::assertFileExists( $path );
		$css = (string) file_get_contents( $path );

		self::assertStringContainsString( 'html[data-cc-theme="dark"]', $css );
		self::assertStringContainsString( 'html[data-cc-theme="light"]', $css );
		self::assertStringContainsString( '--cc-color-accent: #7c83ff', $css );
		self::assertStringContainsString( '.cc-studio-stage', $css );
		self::assertStringContainsString( '.cc-studio-tree-row.is-selected', $css );
	}

	public function test_light_first_layer_is_white_neutral_and_canvas_focused(): void {
		$path = CRESCO_CANVAS_PATH . StudioColorHarmony::LIGHT_STYLE;
		self::assertFileExists( $path );
		$css = (string) file_get_contents( $path );

		self::assertStringContainsString( '--cc-color-surface: #ffffff', $css );
		self::assertStringContainsString( '--cc-color-canvas: #f4f6f8', $css );
		self::assertStringContainsString( '--cc-color-accent: #5b5cf6', $css );
		self::assertStringContainsString( 'background-color: #eef1f5', $css );
		self::assertStringContainsString( '.cc-studio-rail button.is-active', $css );
	}

	public function test_light_first_runtime_migrates_legacy_system_default_without_removing_theme_choice(): void {
		$path = CRESCO_CANVAS_PATH . StudioColorHarmony::LIGHT_SCRIPT;
		self::assertFileExists( $path );
		$script = (string) file_get_contents( $path );

		self::assertStringContainsString( "MIGRATION_KEY='cresco-studio-light-first-v1'", $script );
		self::assertStringContainsString( "theme==='system'", $script );
		self::assertStringContainsString( "theme='light'", $script );
		self::assertStringContainsString( "theme==='dark'", $script );
	}

	public function test_stylesheet_does_not_style_rendered_document_content(): void {
		$css = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioColorHarmony::STYLE );
		$light_css = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioColorHarmony::LIGHT_STYLE );
		self::assertStringNotContainsString( '.cresco-session-root', $css . $light_css );
		self::assertStringNotContainsString( '.cresco-website-builder-root', $css . $light_css );
	}
}
