<?php
/**
 * Document diagnostics regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Core\Diagnostics\DocumentDiagnostics;
use PHPUnit\Framework\TestCase;

final class DocumentDiagnosticsTest extends TestCase {
	private function session( array $nodes ): array {
		$session = WebsiteBuilder::sanitize_session(
			array(
				'schema' => 'cresco-session/v1',
				'version' => 1,
				'documentId' => 'diagnostics-test',
				'nodes' => $nodes,
			)
		);
		self::assertFalse( is_wp_error( $session ) );
		return $session;
	}

	private function codes( array $result ): array {
		return array_values( array_map( static function ( $issue ) { return $issue['code']; }, $result['issues'] ) );
	}

	public function test_heading_hierarchy_reports_multiple_h1_and_level_skip(): void {
		$result = DocumentDiagnostics::analyze(
			$this->session(
				array(
					array( 'id' => 'h1-a', 'type' => 'heading', 'props' => array( 'text' => 'A', 'level' => 1 ) ),
					array( 'id' => 'h3', 'type' => 'heading', 'props' => array( 'text' => 'C', 'level' => 3 ) ),
					array( 'id' => 'h1-b', 'type' => 'heading', 'props' => array( 'text' => 'B', 'level' => 1 ) ),
				)
			)
		);
		self::assertFalse( is_wp_error( $result ) );
		$codes = $this->codes( $result );
		self::assertContains( 'heading.multiple-h1', $codes );
		self::assertContains( 'heading.level-skip', $codes );
	}

	public function test_image_without_alt_and_empty_button_are_locatable_accessibility_issues(): void {
		$result = DocumentDiagnostics::analyze(
			$this->session(
				array(
					array( 'id' => 'hero-image', 'type' => 'image', 'props' => array( 'url' => 'https://example.test/hero.jpg', 'alt' => '' ) ),
					array( 'id' => 'cta', 'type' => 'button', 'props' => array( 'text' => '', 'url' => '#' ) ),
				)
			)
		);
		$by_code = array();
		foreach ( $result['issues'] as $issue ) $by_code[ $issue['code'] ] = $issue;
		self::assertSame( 'hero-image', $by_code['image.missing-alt']['nodeId'] );
		self::assertSame( 'cta', $by_code['button.missing-accessible-name']['nodeId'] );
		self::assertNotSame( '', $by_code['image.missing-alt']['id'] );
	}

	public function test_redundant_responsive_override_is_reported_without_modifying_document(): void {
		$session = $this->session(
			array(
				array(
					'id' => 'title',
					'type' => 'heading',
					'props' => array( 'text' => 'Title', 'level' => 2 ),
					'style' => array( 'fontSize' => '48px' ),
					'responsive' => array( 'desktop' => array( 'fontSize' => '48px' ) ),
				),
			)
		);
		$before = wp_json_encode( $session );
		$result = DocumentDiagnostics::analyze( $session );
		self::assertContains( 'responsive.redundant-override', $this->codes( $result ) );
		self::assertSame( $before, wp_json_encode( $session ) );
	}
}
