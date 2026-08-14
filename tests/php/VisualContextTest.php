<?php
/**
 * Rendered appearance payload for AI context envelopes.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\VisualContext;
use PHPUnit\Framework\TestCase;

final class VisualContextTest extends TestCase {
	private function nodes(): array {
		return array(
			array( 'id' => 'hero', 'type' => 'container', 'props' => array(), 'children' => array() ),
			array( 'id' => 'rule', 'type' => 'divider', 'props' => array(), 'children' => array() ),
		);
	}

	public function test_page_scope_content_yields_the_whole_session(): void {
		$session = array( 'documentId' => 'doc', 'nodes' => $this->nodes() );
		$result  = VisualContext::session_from_content( array( 'session' => $session ), array() );
		self::assertSame( $session, $result );
	}

	public function test_node_list_scope_renders_only_the_selected_nodes(): void {
		$session = array( 'documentId' => 'doc', 'nodes' => $this->nodes() );
		$only    = array( $this->nodes()[1] );
		$result  = VisualContext::session_from_content( array( 'nodes' => $only ), $session );

		self::assertCount( 1, $result['nodes'] );
		self::assertSame( 'rule', $result['nodes'][0]['id'] );
		// Session-level fields survive so the render keeps its document context.
		self::assertSame( 'doc', $result['documentId'] );
	}

	public function test_single_widget_scope_is_wrapped_into_a_node_list(): void {
		$session = array( 'documentId' => 'doc', 'nodes' => $this->nodes() );
		$result  = VisualContext::session_from_content( array( 'node' => $this->nodes()[0] ), $session );

		self::assertCount( 1, $result['nodes'] );
		self::assertSame( 'hero', $result['nodes'][0]['id'] );
	}

	public function test_empty_scope_yields_no_nodes(): void {
		$result = VisualContext::session_from_content( array(), array( 'documentId' => 'doc' ) );
		self::assertSame( array(), $result['nodes'] );
	}

	public function test_document_is_a_standalone_html_page(): void {
		$html = VisualContext::document(
			array( 'html' => '<div data-cresco-id="hero">Hi</div>', 'css' => '.x{color:red}' ),
			'My page'
		);

		self::assertStringStartsWith( '<!doctype html>', $html );
		self::assertStringContainsString( '<meta charset="utf-8">', $html );
		self::assertStringContainsString( '<title>My page</title>', $html );
		self::assertStringContainsString( '<div data-cresco-id="hero">Hi</div>', $html );
		self::assertStringContainsString( '.x{color:red}', $html );
		self::assertStringEndsWith( "</html>\n", $html );
	}

	public function test_document_carries_a_reset_so_browser_defaults_do_not_read_as_design(): void {
		$html = VisualContext::document( array( 'html' => '<p>x</p>', 'css' => '' ), 'T' );
		self::assertStringContainsString( 'box-sizing:border-box', $html );
		self::assertStringContainsString( 'body{margin:0', $html );
	}

	public function test_document_escapes_the_title(): void {
		$html = VisualContext::document( array( 'html' => '', 'css' => '' ), '<script>alert(1)</script>' );
		self::assertStringNotContainsString( '<script>alert(1)</script>', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_document_tolerates_a_payload_with_no_markup(): void {
		$html = VisualContext::document( array(), 'Empty' );
		self::assertStringContainsString( '<title>Empty</title>', $html );
		self::assertStringEndsWith( "</html>\n", $html );
	}
}
