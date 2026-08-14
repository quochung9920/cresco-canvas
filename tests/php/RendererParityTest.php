<?php
/**
 * Legacy/V2 renderer parity contract during the deprecation window.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteRenderer;
use CrescoCanvas\Builder\WebsiteRendererV2;
use CrescoCanvas\Builder\WidgetArchitectureV2;
use CrescoCanvas\Rendering\RenderEngine;
use PHPUnit\Framework\TestCase;

final class RendererParityTest extends TestCase {
	private function shared_session(): array {
		return array(
			'schema' => 'cresco-session/v1',
			'version' => 1,
			'documentId' => 'renderer-parity',
			'nodes' => array(
				array(
					'id' => 'hero',
					'type' => 'container',
					'props' => array(
						'layout' => 'flex',
						'direction' => 'column',
						'contentWidth' => 'full',
						'tag' => 'section',
						'ariaLabel' => 'Parity hero',
					),
					'style' => array(),
					'responsive' => array(),
					'states' => array(),
					'customCSS' => array(),
					'meta' => array(),
					'children' => array(
						array(
							'id' => 'hero-title',
							'type' => 'heading',
							'props' => array( 'level' => 2, 'text' => 'Canonical rendering' ),
							'style' => array(), 'responsive' => array(), 'states' => array(), 'customCSS' => array(), 'meta' => array(), 'children' => array(),
						),
						array(
							'id' => 'hero-copy',
							'type' => 'text',
							'props' => array( 'tag' => 'p', 'text' => 'One document, one frontend contract.' ),
							'style' => array(), 'responsive' => array(), 'states' => array(), 'customCSS' => array(), 'meta' => array(), 'children' => array(),
						),
					),
				),
			),
		);
	}

	public function test_shared_markup_is_identical_after_v2_root_marker_is_normalized(): void {
		$session = $this->shared_session();
		$legacy = WebsiteRenderer::render_document( $session );
		$v2 = WebsiteRendererV2::render_document( $session, 0, WidgetArchitectureV2::empty_document() );
		$v2 = str_replace( ' data-cresco-architecture="v2"', '', $v2 );

		self::assertSame( $legacy, $v2 );
	}

	public function test_render_engine_is_the_canonical_v2_owner(): void {
		$result = RenderEngine::render( $this->shared_session() );
		self::assertIsArray( $result );
		self::assertSame( 'RenderEngine/v2', $result['renderOwner'] );
		self::assertStringContainsString( 'data-cresco-architecture="v2"', $result['html'] );
		self::assertStringContainsString( 'data-cresco-id="hero-title"', $result['html'] );
		self::assertStringContainsString( 'Canonical rendering', $result['html'] );
	}

	public function test_legacy_css_drift_is_not_used_by_the_canonical_engine(): void {
		$session = $this->shared_session();
		$legacy = WebsiteRenderer::compile_css( $session );
		$canonical = RenderEngine::css( $session );

		// The legacy compiler still carries historical full-container sizing.
		// This assertion documents why new callers must use RenderEngine while
		// WebsiteRenderer remains only as a compatibility fragment dependency.
		self::assertNotSame( $legacy, $canonical );
		self::assertStringContainsString( 'width:auto;', $canonical );
		self::assertStringContainsString( 'width:100%;', $legacy );
	}
}
