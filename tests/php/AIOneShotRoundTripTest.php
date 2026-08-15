<?php
/**
 * One-Shot round trip: export, AI-shaped patch, validate, render.
 *
 * This is the regression that matters most. Each stage passing alone proves
 * nothing about whether a user can actually finish a design in one exchange;
 * only the whole loop does.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\AIResultNormalizer;
use CrescoCanvas\AI\ContextBuilderV2;
use CrescoCanvas\AI\PatchValidator;
use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use CrescoCanvas\Builder\WebsiteRenderer;
use CrescoCanvas\Styles\ScopedCss;
use PHPUnit\Framework\TestCase;

final class AIOneShotRoundTripTest extends TestCase {
	private const MARQUEE = "@keyframes marquee {\n  from { transform: translate3d(0,0,0); }\n  to { transform: translate3d(-50%,0,0); }\n}\n\n& {\n  animation: marquee 32s linear infinite;\n}";

	/** One empty Container — the case v2 exists to make workable. */
	private function session(): array {
		$session = WebsiteBuilderSessionSanitizer::sanitize_session(
			array(
				'documentId' => 'one-shot',
				'nodes'      => array(
					array( 'id' => 'one-shot-root', 'type' => 'container', 'props' => array(), 'children' => array() ),
				),
			)
		);
		self::assertFalse( is_wp_error( $session ) );
		return $session;
	}

	private function package(): array {
		$package = ContextBuilderV2::build( 0, $this->session(), 'subtree', array( 'nodeId' => 'one-shot-root' ), 'redesign', 'optimized', array(), true );
		self::assertFalse( is_wp_error( $package ) );
		return $package;
	}

	/** A response built only from what the package declared. */
	private function patch( array $package ): array {
		return array(
			'schema'     => 'cresco-patch/v1',
			'target'     => $package['scopePackage']['target'],
			'operations' => array(
				array(
					'op'     => 'replaceSubtree',
					'nodeId' => 'one-shot-root',
					'node'   => array(
						'id'         => 'one-shot-root',
						'type'       => 'container',
						'props'      => array( 'contentWidth' => 'full', 'layout' => 'flex', 'direction' => 'row' ),
						'style'      => array( 'paddingTop' => '32px', 'gap' => '24px', 'alignItems' => 'center' ),
						'responsive' => array( 'mobile' => array( 'paddingTop' => '16px' ) ),
						'customCSS'  => array( 'base' => self::MARQUEE ),
						'children'   => array(
							array( 'id' => 'strip-title', 'type' => 'heading', 'props' => array( 'text' => 'Trusted by teams', 'level' => '2' ) ),
							array( 'id' => 'strip-copy', 'type' => 'text', 'props' => array( 'text' => 'Shipping with Cresco.' ) ),
						),
					),
				),
			),
		);
	}

	public function test_creation_catalog_offers_the_widgets_the_patch_uses(): void {
		$catalog = array_keys( $this->package()['scopePackage']['contracts']['creationCatalog'] );
		foreach ( array( 'container', 'heading', 'text' ) as $type ) {
			self::assertContains( $type, $catalog );
		}
	}

	public function test_full_round_trip_preserves_the_target_root_id(): void {
		$session = $this->session();
		$package = $this->package();

		$normalized = AIResultNormalizer::normalize( "```json\n" . wp_json_encode( $this->patch( $package ) ) . "\n```" );
		self::assertIsArray( $normalized );
		self::assertArrayNotHasKey( 'baseChecksum', $normalized );

		$validated = PatchValidator::validate( $session, $normalized );
		self::assertFalse( is_wp_error( $validated ), is_wp_error( $validated ) ? $validated->get_error_message() : '' );

		$candidate = $validated['session'];
		$root      = $candidate['nodes'][0];

		self::assertSame( 'one-shot-root', $root['id'] );
		self::assertCount( 2, $root['children'] );

		$sanitized = WebsiteBuilderSessionSanitizer::sanitize_session( $candidate );
		self::assertFalse( is_wp_error( $sanitized ) );

		$html = WebsiteRenderer::render_document( $sanitized, 0 );
		self::assertStringContainsString( 'one-shot-root', $html );
		self::assertStringContainsString( 'Trusted by teams', $html );

		self::assertNotSame( '', WebsiteRenderer::compile_css( $sanitized ) );
	}

	public function test_legacy_checksum_field_is_ignored(): void {
		$patch                 = $this->patch( $this->package() );
		$patch['baseChecksum'] = str_repeat( '0', 64 );

		$validated = PatchValidator::validate( $this->session(), $patch );
		self::assertFalse( is_wp_error( $validated ) );
		self::assertArrayNotHasKey( 'baseChecksum', $validated );
		self::assertArrayNotHasKey( 'stale', $validated );
	}

	/**
	 * What the package promises about Custom CSS must be what the compiler
	 * actually does. Drift here is invisible until a user's animation silently
	 * stops working.
	 */
	public function test_declared_keyframe_capability_matches_compiler_behaviour(): void {
		$capabilities = $this->package()['scopePackage']['capabilities']['customCss'];
		self::assertNotEmpty( $capabilities['localKeyframes'] );
		self::assertTrue( $capabilities['keyframeNamesScoped'] );

		$compiled = ScopedCss::compile( self::MARQUEE, '.x', 'scope1' );
		self::assertIsString( $compiled );

		self::assertStringNotContainsString( '@keyframes marquee{', $compiled );

		self::assertSame( 1, preg_match( '/@(?:-webkit-)?keyframes\s+([A-Za-z0-9_-]+)/', $compiled, $matches ) );
		self::assertStringContainsString( $matches[1], $compiled );
		self::assertStringContainsString( 'cresco-kf-', $matches[1] );
	}
}
