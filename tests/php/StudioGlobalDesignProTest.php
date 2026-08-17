<?php
/** Global Design Pro regression tests. */

use CrescoCanvas\Builder\StudioGlobalDesignPro;
use PHPUnit\Framework\TestCase;

final class StudioGlobalDesignProTest extends TestCase {
	public function test_asset_contract_is_stable(): void {
		self::assertSame( 'cresco-canvas-studio-global-design-pro', StudioGlobalDesignPro::HANDLE );
		self::assertSame( 'build/studio-global-design-pro.js', StudioGlobalDesignPro::SCRIPT );
		self::assertSame( 'assets/css/studio-global-design-pro.css', StudioGlobalDesignPro::STYLE );
	}

	public function test_global_design_script_exposes_compact_user_facing_workspace_sections(): void {
		$path = CRESCO_CANVAS_PATH . StudioGlobalDesignPro::SCRIPT;
		self::assertFileExists( $path );
		$script = (string) file_get_contents( $path );

		foreach ( array( 'Overview', 'Colors', 'Typography', 'Buttons', 'Layout', 'More' ) as $label ) {
			self::assertStringContainsString( $label, $script );
		}
		self::assertStringContainsString( 'Design System Health', $script );
		self::assertStringContainsString( 'Save globally', $script );
		self::assertStringContainsString( 'Semantic aliases', $script );
		self::assertStringContainsString( 'Token Explorer', $script );
		self::assertStringContainsString( 'Global button', $script );
		self::assertStringContainsString( 'button.background', $script );
		self::assertStringContainsString( 'button.hoverBackground', $script );
		self::assertStringContainsString( 'button.paddingInline', $script );
		self::assertStringContainsString( 'button.fontWeight', $script );
		self::assertStringNotContainsString( '<strong>Radius</strong><small>Shape personality', $script );
		self::assertStringContainsString( 'cresco:studio-session-change', $script );
		self::assertStringContainsString( 'cresco-global-design-pro/v1', $script );
	}

	public function test_global_design_styles_are_scoped_away_from_rendered_document(): void {
		$path = CRESCO_CANVAS_PATH . StudioGlobalDesignPro::STYLE;
		self::assertFileExists( $path );
		$css = (string) file_get_contents( $path );

		self::assertStringContainsString( '.cc-global-design-pro', $css );
		self::assertStringContainsString( '.cc-global-design-pro-host', $css );
		self::assertStringNotContainsString( '.cresco-session-root', $css );
		self::assertStringNotContainsString( '.cresco-website-builder-root', $css );
		self::assertStringNotContainsString( '.cc-studio-canvas ', $css );
	}

	public function test_workspace_reuses_canonical_settings_and_token_apis(): void {
		$php = (string) file_get_contents( CRESCO_CANVAS_PATH . 'includes/Builder/StudioGlobalDesignPro.php' );
		self::assertStringContainsString( "'/cresco-canvas/v1/settings'", $php );
		self::assertStringContainsString( "'/cresco-canvas/v1/design-tokens'", $php );
		self::assertStringContainsString( "'/cresco-canvas/v1/settings/reset'", $php );
	}
}
