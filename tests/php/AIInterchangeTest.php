<?php
/**
 * Cresco AI Interchange v1 contract, scope, patch, and security tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\AIInterchange;
use CrescoCanvas\AI\ContextBuilder;
use CrescoCanvas\AI\ContextSanitizer;
use CrescoCanvas\AI\PatchValidator;
use CrescoCanvas\Session\SessionManager;
use PHPUnit\Framework\TestCase;

final class AIInterchangeTest extends TestCase {
	private function session(): array {
		$session = SessionManager::sanitize_session(
			array(
				'documentId' => 'ai-test',
				'nodes' => array(
					array(
						'id' => 'hero',
						'type' => 'container',
						'props' => array( 'contentWidth' => 'boxed', 'layout' => 'flex', 'direction' => 'column' ),
						'style' => array( 'paddingTop' => '{spacing.xl}' ),
						'children' => array(
							array( 'id' => 'hero-gap', 'type' => 'spacer', 'props' => array( 'height' => '32px' ) ),
							array( 'id' => 'hero-rule', 'type' => 'divider' ),
						),
					),
					array( 'id' => 'outside', 'type' => 'divider' ),
				),
			)
		);
		self::assertFalse( is_wp_error( $session ) );
		return $session;
	}

	private function resources(): array {
		return array(
			'designSystem' => array(
				'schemaVersion' => 3,
				'colors' => array( 'primary' => '#635bff', 'text' => '#111827' ),
				'spacing' => array( 'xl' => '64px', 'md' => '24px' ),
				'radius' => array( 'md' => '12px' ),
				'breakpoints' => array( 'mobile' => 0, 'tablet' => 768, 'laptop' => 1025, 'desktop' => 1440, 'wide' => 1920 ),
			),
			'pageSettings' => array( 'layout' => 'full-width', 'privateApiKey' => 'must-not-export' ),
			'pageSettingsEffective' => array( 'layout' => 'full-width', 'contentRoot' => 'viewport' ),
			'postTitle' => 'AI Test',
		);
	}

	public function test_full_page_context_export(): void {
		$context = ContextBuilder::build( 10, $this->session(), 'page', array(), 'full', $this->resources() );
		self::assertFalse( is_wp_error( $context ) );
		self::assertSame( 'cresco-ai-context/v1', $context['schema'] );
		self::assertSame( 'page', $context['scope'] );
		self::assertSame( 'cresco-session/v1', $context['content']['session']['schema'] );
		self::assertCount( 9, $context['contracts'] );
		self::assertArrayNotHasKey( 'privateApiKey', $context['pageSettings']['settings'] );
		self::assertArrayNotHasKey( 'baseChecksum', $context );
	}

	public function test_widget_export_contains_only_selected_widget_content(): void {
		$context = ContextBuilder::build( 10, $this->session(), 'widget', array( 'nodeId' => 'hero-rule' ), 'optimized', $this->resources() );
		self::assertFalse( is_wp_error( $context ) );
		self::assertSame( 'widget', $context['scope'] );
		self::assertSame( 'hero-rule', $context['content']['node']['id'] );
		self::assertSame( array(), $context['content']['node']['children'] );
		self::assertArrayHasKey( 'divider', $context['contracts'] );
	}

	public function test_subtree_export_keeps_descendants_and_minimal_ancestry(): void {
		$context = ContextBuilder::build( 10, $this->session(), 'subtree', array( 'nodeId' => 'hero' ), 'optimized', $this->resources() );
		self::assertFalse( is_wp_error( $context ) );
		self::assertSame( 'hero', $context['content']['node']['id'] );
		self::assertCount( 2, $context['content']['node']['children'] );
		self::assertSame( array(), $context['content']['ancestry'] );
	}

	public function test_selection_protocol_exports_only_explicit_selected_nodes(): void {
		$context = ContextBuilder::build( 10, $this->session(), 'selection', array( 'nodeIds' => array( 'hero', 'outside' ) ), 'optimized', $this->resources() );
		self::assertFalse( is_wp_error( $context ) );
		self::assertSame( 'selection', $context['scope'] );
		self::assertSame( array( 'hero', 'outside' ), $context['target']['nodeIds'] );
		self::assertCount( 2, $context['content']['nodes'] );
		self::assertSame( array(), $context['content']['nodes'][0]['children'] );
	}

	public function test_optimized_context_omits_unrelated_widget_contracts_and_tokens(): void {
		$context = ContextBuilder::build( 10, $this->session(), 'widget', array( 'nodeId' => 'hero-gap' ), 'optimized', $this->resources() );
		self::assertFalse( is_wp_error( $context ) );
		self::assertArrayHasKey( 'spacer', $context['contracts'] );
		self::assertArrayHasKey( 'container', $context['contracts'] );
		self::assertArrayNotHasKey( 'button', $context['contracts'] );
		self::assertArrayNotHasKey( 'colors', $context['designSystem'] );
	}

	public function test_context_sanitizer_never_exports_secret_keys(): void {
		$clean = ContextSanitizer::sanitize(
			array(
				'design' => array( 'primary' => '#fff', 'apiKey' => 'secret', 'license_key' => 'secret-2' ),
				'nonce' => 'abc',
				'authorizationHeader' => 'Bearer nope',
				'content' => array( 'text' => 'safe' ),
			)
		);
		$json = json_encode( $clean );
		self::assertStringNotContainsString( 'secret', $json );
		self::assertStringNotContainsString( 'Bearer', $json );
		self::assertStringNotContainsString( 'nonce', $json );
		self::assertSame( 'safe', $clean['content']['text'] );
	}

	public function test_valid_patch_is_accepted_and_produces_valid_session_and_diff(): void {
		$base = $this->session();
		$patch = array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array(
				array( 'op' => 'setStyle', 'nodeId' => 'hero', 'style' => array( 'paddingTop' => '112px' ) ),
				array( 'op' => 'setResponsive', 'nodeId' => 'hero', 'responsive' => array( 'mobile' => array( 'paddingTop' => '48px' ) ) ),
			),
		);
		$result = PatchValidator::validate( $base, $patch );
		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( '112px', $result['session']['nodes'][0]['style']['paddingTop'] );
		self::assertSame( '48px', $result['session']['nodes'][0]['responsive']['mobile']['paddingTop'] );
		self::assertGreaterThan( 0, $result['diff']['summary']['total'] );
		self::assertFalse( is_wp_error( SessionManager::sanitize_session( $result['session'] ) ) );
	}

	public function test_unsupported_widget_is_rejected(): void {
		$base = $this->session();
		$result = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array( array( 'op' => 'insertNode', 'parentId' => 'hero', 'node' => array( 'id' => 'bad', 'type' => 'script-widget' ) ) ),
		) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_widget_unsupported', $result->get_error_code() );
	}

	public function test_unsupported_property_is_rejected_instead_of_silently_dropped(): void {
		$base = $this->session();
		$result = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array( array( 'op' => 'setProps', 'nodeId' => 'hero', 'props' => array( 'inventedProperty' => 'nope' ) ) ),
		) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_patch_operation', $result->get_error_code() );
	}

	public function test_unsupported_structured_style_property_is_rejected(): void {
		$base = $this->session();
		$result = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array( array( 'op' => 'setStyle', 'nodeId' => 'hero', 'style' => array( 'backdropFilter' => 'blur(5px)' ) ) ),
		) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_style_unsupported', $result->get_error_code() );
	}

	public function test_duplicate_insert_id_is_remapped_and_followup_reference_is_rewritten(): void {
		$base = $this->session();
		$result = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array(
				array( 'op' => 'insertNode', 'parentId' => 'hero', 'node' => array( 'id' => 'hero', 'type' => 'spacer', 'props' => array( 'height' => '20px' ) ) ),
				array( 'op' => 'setProps', 'nodeId' => 'hero', 'props' => array( 'height' => '36px' ) ),
			),
		) );
		self::assertFalse( is_wp_error( $result ) );
		self::assertArrayHasKey( 'hero', $result['idMap'] );
		$mapped = $result['idMap']['hero'];
		$found = null;
		foreach ( $result['session']['nodes'][0]['children'] as $child ) if ( $child['id'] === $mapped ) $found = $child;
		self::assertNotNull( $found );
		self::assertSame( '36px', $found['props']['height'] );
	}

	public function test_structural_patch_operations_move_remove_and_replace_are_supported(): void {
		$base = $this->session();
		$result = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'page' ),
			'operations' => array(
				array( 'op' => 'moveNode', 'nodeId' => 'hero-rule', 'parentId' => 'hero', 'index' => 0 ),
				array( 'op' => 'removeNode', 'nodeId' => 'hero-gap' ),
				array( 'op' => 'replaceSubtree', 'nodeId' => 'outside', 'node' => array( 'id' => 'replacement', 'type' => 'spacer', 'props' => array( 'height' => '44px' ) ) ),
			),
		) );
		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( 'hero-rule', $result['session']['nodes'][0]['children'][0]['id'] );
		self::assertCount( 1, $result['session']['nodes'][0]['children'] );
		self::assertSame( 'outside', $result['session']['nodes'][1]['id'] );
		self::assertSame( 'spacer', $result['session']['nodes'][1]['type'] );
		self::assertSame( '44px', $result['session']['nodes'][1]['props']['height'] );
	}

	public function test_legacy_checksum_field_is_ignored(): void {
		$base = $this->session();
		$result = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'baseChecksum' => str_repeat( '0', 64 ),
			'target' => array( 'scope' => 'page' ),
			'operations' => array(),
		) );
		self::assertFalse( is_wp_error( $result ) );
		self::assertArrayNotHasKey( 'baseChecksum', $result );
		self::assertArrayNotHasKey( 'stale', $result );
	}

	public function test_patch_cannot_escape_subtree_target(): void {
		$base = $this->session();
		$result = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array( array( 'op' => 'setStyle', 'nodeId' => 'outside', 'style' => array( 'opacity' => '.5' ) ) ),
		) );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_patch_scope_escape', $result->get_error_code() );
	}

	public function test_custom_css_still_uses_session_sanitizer(): void {
		$base = $this->session();
		$valid = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array( array( 'op' => 'setCustomCSS', 'nodeId' => 'hero', 'customCSS' => array( 'base' => '&:hover { opacity: .9; }' ) ) ),
		) );
		self::assertFalse( is_wp_error( $valid ) );

		$invalid = PatchValidator::validate( $base, array(
			'schema' => 'cresco-patch/v1',
			'target' => array( 'scope' => 'subtree', 'nodeId' => 'hero' ),
			'operations' => array( array( 'op' => 'setCustomCSS', 'nodeId' => 'hero', 'customCSS' => array( 'base' => 'body { display:none; }' ) ) ),
		) );
		self::assertTrue( is_wp_error( $invalid ) );
		self::assertSame( 'cresco_session_css_scope', $invalid->get_error_code() );
	}

	public function test_full_session_import_compatibility_remains_available(): void {
		$base = $this->session();
		$candidate = $base;
		$candidate['nodes'][] = array( 'id' => 'new-rule', 'type' => 'divider' );
		$controller = new AIInterchange();
		$response = $controller->rest_validate_result( new WP_REST_Request( array( 'postId' => 10, 'currentSession' => $base, 'result' => $candidate ) ) );
		self::assertFalse( is_wp_error( $response ) );
		$data = $response->get_data();
		self::assertSame( 'session', $data['resultType'] );
		self::assertSame( 'cresco-session/v1', $data['session']['schema'] );
		self::assertArrayNotHasKey( 'baseChecksum', $data );
		self::assertArrayNotHasKey( 'checksum', $data );
		self::assertArrayNotHasKey( 'stale', $data );
		self::assertSame( 3, $data['diff']['summary']['total'] > 0 ? count( $data['session']['nodes'] ) : 0 );
	}
}
