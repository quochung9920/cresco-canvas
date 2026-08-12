<?php
/**
 * Studio AI result bridge regression tests.
 *
 * @package CrescoCanvas
 */

use PHPUnit\Framework\TestCase;

final class StudioAiPatchIntegrationTest extends TestCase {
	public function test_studio_runtime_accepts_patch_results_through_the_ai_validator(): void {
		$studio = (string) file_get_contents( CRESCO_CANVAS_PATH . 'includes/Builder/WebsiteBuilderStudio.php' );
		$runtime = (string) file_get_contents( CRESCO_CANVAS_PATH . 'build/website-builder-studio.js' );

		self::assertStringContainsString( "'aiValidateResult'", $studio );
		self::assertStringContainsString( '/cresco-canvas/v1/ai-interchange/', $studio );
		self::assertStringContainsString( "schema==='cresco-session/v1'||schema==='cresco-patch/v1'", $runtime );
		self::assertStringContainsString( "'ai-result-validate'", $runtime );
		self::assertStringContainsString( 'Expected Cresco Session, Patch, or interchange JSON.', $runtime );
	}

	public function test_authoritative_and_built_studio_runtime_stay_identical(): void {
		$built = (string) file_get_contents( CRESCO_CANVAS_PATH . 'build/website-builder-studio.js' );
		$source = (string) file_get_contents( CRESCO_CANVAS_PATH . 'runtime-src/build/website-builder-studio.js' );

		self::assertSame( $source, $built );
	}
}
