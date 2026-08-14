<?php
/**
 * Consolidated Website Builder Core Platform regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Core\Responsive\ResponsiveResolver;
use PHPUnit\Framework\TestCase;

final class WebsiteBuilderCorePlatformTest extends TestCase {
	public function test_responsive_resolver_has_one_downward_inheritance_contract(): void {
		self::assertSame( array(), ResponsiveResolver::cascade_for( 'wide' ) );
		self::assertSame( array( 'desktop' ), ResponsiveResolver::cascade_for( 'desktop' ) );
		self::assertSame( array( 'desktop', 'laptop' ), ResponsiveResolver::cascade_for( 'laptop' ) );
		self::assertSame( array( 'desktop', 'laptop', 'tablet' ), ResponsiveResolver::cascade_for( 'tablet' ) );
		self::assertSame( array( 'desktop', 'laptop', 'tablet', 'mobile' ), ResponsiveResolver::cascade_for( 'mobile' ) );

		$effective = ResponsiveResolver::effective_style(
			array( 'width' => '86%', 'gap' => '80px' ),
			array(
				'desktop' => array( 'width' => '92%' ),
				'laptop'  => array( 'gap' => '52px' ),
				'tablet'  => array( 'flexDirection' => 'column' ),
			),
			'tablet'
		);
		self::assertSame( '92%', $effective['width'] );
		self::assertSame( '52px', $effective['gap'] );
		self::assertSame( 'column', $effective['flexDirection'] );
	}

	public function test_core_platform_exposes_manifest_transactions_and_safe_status(): void {
		$root = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Builder/WebsiteBuilderCorePlatform.php' );
		self::assertIsString( $source );
		foreach ( array(
			"SCHEMA = 'cresco-builder-core/v2'",
			"STYLE_CONTRACT = 'authoritative-v5'",
			'/website-builder/session/',
			'/website-builder/theme-session/',
			'/website-builder/components',
			'/website-builder/architecture-v2/',
			'/page-settings/',
			'/website-builder/theme-page-settings/',
			'/website-builder/core/',
			'/website-builder/transactions/',
			'/website-builder/system-status/',
			'TransactionManager::preview',
			'WordPressDocumentRepository',
			'wp_slash( $json )',
			"'verified'  => true",
			"'cresco_page_settings_verify_failed'",
			'ThemeSessionBridge::block_markup',
			'render_theme_preview',
			"'cresco_transaction_conflict'",
			"'privacySafe'   => true",
			'DesignSystemAnalyzer::usage',
			'InspectorSchema::manifest',
			'ResponsiveResolver::manifest',
		) as $token ) self::assertStringContainsString( $token, $source );
	}

	public function test_editor_core_bridge_builds_o1_node_index_and_uses_transactions(): void {
		$root = dirname( __DIR__, 2 );
		$source = file_get_contents( $root . '/includes/Builder/WebsiteBuilderCorePlatform.php' );
		self::assertIsString( $source );
		self::assertStringContainsString( 'index=new Map()', $source );
		self::assertStringContainsString( 'index.set(id,node)', $source );
		self::assertStringContainsString( 'getNode:function(id){return index.get', $source );
		self::assertStringContainsString( 'effectiveStyle:effectiveStyle', $source );
		self::assertStringContainsString( 'previewTransaction:previewTransaction', $source );
		self::assertStringContainsString( 'commitTransaction:commitTransaction', $source );
	}

	public function test_inspector_and_design_system_are_catalog_driven(): void {
		$root = dirname( __DIR__, 2 );
		$inspector = file_get_contents( $root . '/includes/Core/UI/InspectorSchema.php' );
		$design = file_get_contents( $root . '/includes/Core/Design/DesignSystemAnalyzer.php' );
		self::assertIsString( $inspector );
		self::assertIsString( $design );
		self::assertStringContainsString( 'WidgetCatalog::all()', $inspector );
		self::assertStringContainsString( "'schema' => 'cresco-inspector/v2'", $inspector );
		self::assertStringContainsString( "'schema' => 'cresco-design-system/v2'", $design );
		self::assertStringContainsString( 'DesignTokens::catalog', $design );
		self::assertStringContainsString( "'usage'", $design );
	}
}
