<?php
/** Global Design Pro regression tests. */

use CrescoCanvas\Builder\StudioGlobalDesignPro;
use PHPUnit\Framework\TestCase;

final class StudioGlobalDesignProTest extends TestCase {
	public function test_asset_contract_is_stable(): void {
		self::assertSame( 'cresco-canvas-studio-global-design-pro', StudioGlobalDesignPro::HANDLE );
		self::assertSame( 'build/studio-global-design-pro.js', StudioGlobalDesignPro::SCRIPT );
		self::assertSame( 'assets/css/studio-global-design-pro.css', StudioGlobalDesignPro::STYLE );
		self::assertSame( 'cresco-canvas-studio-global-design-authority', StudioGlobalDesignPro::AUTHORITY_HANDLE );
		self::assertSame( 'build/studio-global-design-authority.js', StudioGlobalDesignPro::AUTHORITY_SCRIPT );
		self::assertSame( 'assets/css/studio-global-design-authority.css', StudioGlobalDesignPro::AUTHORITY_STYLE );
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
		self::assertStringContainsString( 'data-button-state', $script );
		self::assertStringContainsString( "['normal','Normal']", $script );
		self::assertStringContainsString( "['hover','Hover']", $script );
		self::assertStringContainsString( "['active','Active']", $script );
		self::assertStringContainsString( 'button.background', $script );
		self::assertStringContainsString( 'button.hoverBackground', $script );
		self::assertStringContainsString( 'button.activeBackground', $script );
		self::assertStringContainsString( 'button.paddingInline', $script );
		self::assertStringContainsString( 'button.fontWeight', $script );
		self::assertStringNotContainsString( '<strong>Normal</strong>', $script );
		self::assertStringNotContainsString( '<strong>Hover</strong>', $script );
		self::assertStringNotContainsString( '<strong>Radius</strong><small>Shape personality', $script );
		self::assertStringContainsString( 'cresco:studio-session-change', $script );
		self::assertStringContainsString( 'cresco-global-design-pro/v1', $script );
		self::assertStringContainsString( 'boot();', $script );
	}

	public function test_global_design_styles_are_scoped_away_from_rendered_document(): void {
		foreach ( array( StudioGlobalDesignPro::STYLE, StudioGlobalDesignPro::AUTHORITY_STYLE ) as $relative_path ) {
			$path = CRESCO_CANVAS_PATH . $relative_path;
			self::assertFileExists( $path );
			$css = (string) file_get_contents( $path );
			self::assertStringNotContainsString( '.cresco-session-root', $css );
			self::assertStringNotContainsString( '.cresco-website-builder-root', $css );
			self::assertStringNotContainsString( '.cc-studio-canvas ', $css );
		}

		$css = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioGlobalDesignPro::STYLE );
		self::assertStringContainsString( '.cc-global-design-pro', $css );
		self::assertStringContainsString( '.cc-global-design-pro-host', $css );
	}

	public function test_workspace_reuses_canonical_settings_and_token_apis(): void {
		$php = (string) file_get_contents( CRESCO_CANVAS_PATH . 'includes/Builder/StudioGlobalDesignPro.php' );
		self::assertStringContainsString( "'/cresco-canvas/v1/settings'", $php );
		self::assertStringContainsString( "'/cresco-canvas/v1/design-tokens'", $php );
		self::assertStringContainsString( "'/cresco-canvas/v1/settings/reset'", $php );
	}

	public function test_optional_enhancements_cannot_disable_pro_core(): void {
		$php = (string) file_get_contents( CRESCO_CANVAS_PATH . 'includes/Builder/StudioGlobalDesignPro.php' );

		self::assertStringContainsString( 'Global Design Pro core is the only mandatory layer', $php );
		self::assertStringNotContainsString( "if ( ! WebsiteBuilderAsset::readable( self::WORKFLOW_GUARD_SCRIPT ) ) return;", $php );
		self::assertStringNotContainsString( "if ( ! WebsiteBuilderAsset::readable( self::WORKFLOW_SCRIPT ) || ! WebsiteBuilderAsset::readable( self::WORKFLOW_STYLE ) ) return;", $php );
		self::assertStringNotContainsString( "if ( ! WebsiteBuilderAsset::readable( self::COMPACT_SCRIPT ) || ! WebsiteBuilderAsset::readable( self::COMPACT_STYLE ) ) return;", $php );
		self::assertStringNotContainsString( "if ( ! WebsiteBuilderAsset::readable( self::FONT_SEARCH_FIX_STYLE ) ) return;", $php );
		self::assertStringNotContainsString( "if ( ! WebsiteBuilderAsset::readable( self::SHARED_STYLE ) || ! WebsiteBuilderAsset::readable( self::SHARED_SCRIPT ) ) return;", $php );
		self::assertStringContainsString( 'wp_add_inline_script( self::HANDLE, $config_script', $php );
	}

	public function test_authority_bridge_blocks_legacy_ux_without_blank_panel(): void {
		$path = CRESCO_CANVAS_PATH . StudioGlobalDesignPro::AUTHORITY_SCRIPT;
		self::assertFileExists( $path );
		$script = (string) file_get_contents( $path );

		self::assertStringContainsString( "target.dataset.uxEnhanced='1'", $script );
		self::assertStringContainsString( '.cc-studio-ux-token-search', $script );
		self::assertStringContainsString( "if(!target||!target.querySelector('.cc-global-design-pro'))return;", $script );
		self::assertStringContainsString( 'Loading Global Design Pro', $script );
		self::assertStringContainsString( 'Retry Global Design', $script );
		self::assertStringNotContainsString( 'cresco-session-root', $script );
		self::assertStringNotContainsString( 'cresco-website-builder-root', $script );
	}
}
