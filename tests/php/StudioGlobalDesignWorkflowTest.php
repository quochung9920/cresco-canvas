<?php
/** Global Design actionable workflow regression tests. */

use CrescoCanvas\Builder\StudioGlobalDesignPro;
use PHPUnit\Framework\TestCase;

final class StudioGlobalDesignWorkflowTest extends TestCase {
	public function test_workflow_assets_are_registered_as_part_of_global_design(): void {
		self::assertSame( 'cresco-canvas-studio-global-design-workflows-guard', StudioGlobalDesignPro::WORKFLOW_GUARD_HANDLE );
		self::assertSame( 'build/studio-global-design-workflows-guard.js', StudioGlobalDesignPro::WORKFLOW_GUARD_SCRIPT );
		self::assertSame( 'cresco-canvas-studio-global-design-workflows', StudioGlobalDesignPro::WORKFLOW_HANDLE );
		self::assertSame( 'build/studio-global-design-workflows.js', StudioGlobalDesignPro::WORKFLOW_SCRIPT );
		self::assertSame( 'assets/css/studio-global-design-workflows.css', StudioGlobalDesignPro::WORKFLOW_STYLE );
		self::assertFileExists( CRESCO_CANVAS_PATH . StudioGlobalDesignPro::WORKFLOW_GUARD_SCRIPT );
		self::assertFileExists( CRESCO_CANVAS_PATH . StudioGlobalDesignPro::WORKFLOW_SCRIPT );
		self::assertFileExists( CRESCO_CANVAS_PATH . StudioGlobalDesignPro::WORKFLOW_STYLE );
	}

	public function test_workflow_prelude_loads_before_the_main_global_design_runtime(): void {
		$php = (string) file_get_contents( CRESCO_CANVAS_PATH . 'includes/Builder/StudioGlobalDesignPro.php' );
		$guard = strpos( $php, 'WebsiteBuilderAsset::url( self::WORKFLOW_GUARD_SCRIPT )' );
		$workflow = strpos( $php, 'WebsiteBuilderAsset::url( self::WORKFLOW_SCRIPT )' );
		$main = strrpos( $php, 'WebsiteBuilderAsset::url( self::SCRIPT )' );
		self::assertNotFalse( $guard );
		self::assertNotFalse( $workflow );
		self::assertNotFalse( $main );
		self::assertLessThan( $workflow, $guard );
		self::assertLessThan( $main, $workflow );
		self::assertStringContainsString( 'self::WORKFLOW_GUARD_HANDLE', $php );
		self::assertStringContainsString( 'self::WORKFLOW_HANDLE', $php );
	}

	public function test_workflow_keeps_canvas_css_isolated(): void {
		$css = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioGlobalDesignPro::WORKFLOW_STYLE );
		self::assertStringContainsString( '.cc-global-design-pro .cc-gdw-action-center', $css );
		self::assertStringContainsString( '.cc-gdw-modal', $css );
		self::assertStringNotContainsString( '.cresco-session-root', $css );
		self::assertStringNotContainsString( '.cresco-website-builder-root', $css );
		self::assertStringNotContainsString( '.cc-studio-canvas button', $css );
		self::assertStringNotContainsString( '.cc-studio-app button', $css );
	}

	public function test_workflow_contains_safety_and_recovery_contracts(): void {
		$script = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioGlobalDesignPro::WORKFLOW_SCRIPT );
		$guard = (string) file_get_contents( CRESCO_CANVAS_PATH . StudioGlobalDesignPro::WORKFLOW_GUARD_SCRIPT );
		foreach ( array(
			'impactDialog(save)',
			"confirm:'Apply globally'",
			'validateBreakpoints',
			'SETTINGS_UNDO_KEY',
			'SESSION_UNDO_KEY',
			'node.click()',
			'setBySegments',
			'Optimize Design System',
			'This color is still in use',
		) as $contract ) {
			self::assertStringContainsString( $contract, $script );
		}
		self::assertStringContainsString( 'Apply or discard Global Design changes before normalizing the page.', $guard );
		self::assertStringContainsString( "e.key==='Escape'", $guard );
	}
}
