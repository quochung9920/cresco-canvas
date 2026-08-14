<?php
/**
 * Feature flag isolation tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Support\FeatureFlags;
use PHPUnit\Framework\TestCase;

final class FeatureFlagsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['cresco_test_options'][ FeatureFlags::OPTION_NAME ] = array();
		$GLOBALS['cresco_test_filters']['cresco_canvas_feature_flags'] = array();
	}

	public function test_existing_builder_layers_remain_enabled_by_default(): void {
		$flags = FeatureFlags::all();
		foreach ( array(
			'builderCompatibilityLayer',
			'builderRendererParity',
			'builderPresentationLayers',
			'builderArchitectureV2',
			'builderWorkflowExtensions',
			'builderCorePlatformV2',
		) as $name ) {
			self::assertArrayHasKey( $name, $flags );
			self::assertTrue( $flags[ $name ], $name . ' must preserve current production behavior by default.' );
		}
		self::assertFalse( $flags['experimentalEditorTools'] );
	}

	public function test_known_layers_can_be_disabled_independently(): void {
		$GLOBALS['cresco_test_options'][ FeatureFlags::OPTION_NAME ] = array(
			'builderRendererParity' => false,
			'builderPresentationLayers' => '0',
			'builderCorePlatformV2' => true,
			'unknownFutureFlag' => true,
		);
		$flags = FeatureFlags::all();
		self::assertFalse( $flags['builderRendererParity'] );
		self::assertFalse( $flags['builderPresentationLayers'] );
		self::assertTrue( $flags['builderCorePlatformV2'] );
		self::assertArrayNotHasKey( 'unknownFutureFlag', $flags );
		self::assertTrue( FeatureFlags::is_known( 'builderRendererParity' ) );
		self::assertFalse( FeatureFlags::is_known( 'unknownFutureFlag' ) );
	}
}
