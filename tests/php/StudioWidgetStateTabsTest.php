<?php
/** Widget state tabs regression tests. */

use CrescoCanvas\Builder\StudioWidgetStateTabs;
use PHPUnit\Framework\TestCase;

final class StudioWidgetStateTabsTest extends TestCase {
	public function test_asset_contract_is_stable(): void {
		self::assertSame( 'cresco-canvas-studio-widget-state-tabs', StudioWidgetStateTabs::HANDLE );
		self::assertSame( 'build/studio-widget-state-tabs.js', StudioWidgetStateTabs::SCRIPT );
		self::assertSame( 'assets/css/studio-widget-state-tabs.css', StudioWidgetStateTabs::STYLE );
	}

	public function test_assets_are_scoped_to_the_widget_inspector(): void {
		$script = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioWidgetStateTabs::SCRIPT );
		$css = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioWidgetStateTabs::STYLE );

		self::assertStringContainsString( "ORDER=['normal','hover','focus','active']", $script );
		self::assertStringContainsString( 'def.states', $script );
		self::assertStringContainsString( 'cc-studio-state-tabs', $script );
		self::assertStringContainsString( '.cc-studio-left .cc-studio-state-tabs', $css );
		self::assertStringNotContainsString( '.cresco-session-root', $css );
		self::assertStringNotContainsString( '.cresco-website-builder-root', $css );
	}
}
