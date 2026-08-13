<?php
/**
 * Studio Inspector label-hygiene regression tests.
 *
 * @package CrescoCanvas
 */

use PHPUnit\Framework\TestCase;

final class StudioInspectorLabelHygieneTest extends TestCase {
	public function test_ui_correction_hides_only_labels_without_available_controls(): void {
		$root   = dirname( __DIR__, 2 );
		$script = file_get_contents( $root . '/build/website-builder-ui-correction.js' );

		self::assertIsString( $script );
		self::assertStringContainsString( 'function sanitizeInspectorLabels()', $script );
		self::assertStringContainsString( "tab!=='content'||!contentGroupHasAvailableField(label)", $script );
		self::assertStringContainsString( '!accordionGroupHasAvailableMember(header)', $script );
		self::assertStringContainsString( "node.dataset.crescoCapabilityHidden!=='1'", $script );
		self::assertStringContainsString( "node.dataset.crescoConditionHidden!=='1'", $script );
		self::assertStringContainsString( '.is-cresco-empty-inspector-label', $script );
	}

	public function test_content_only_helper_is_hidden_outside_content_tab(): void {
		$root   = dirname( __DIR__, 2 );
		$script = file_get_contents( $root . '/build/website-builder-ui-correction.js' );

		self::assertIsString( $script );
		self::assertStringContainsString( ".cc-studio-responsive-layout-note',panel", $script );
		self::assertStringContainsString( "tab!=='content'", $script );
		self::assertStringContainsString( 'is-cresco-context-hidden', $script );
	}

	public function test_build_and_runtime_source_are_identical(): void {
		$root    = dirname( __DIR__, 2 );
		$build   = file_get_contents( $root . '/build/website-builder-ui-correction.js' );
		$runtime = file_get_contents( $root . '/runtime-src/build/website-builder-ui-correction.js' );

		self::assertSame( $build, $runtime );
	}
}
