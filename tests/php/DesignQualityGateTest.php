<?php
/**
 * Deterministic AI design preflight tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\DesignQualityGate;
use PHPUnit\Framework\TestCase;

final class DesignQualityGateTest extends TestCase {
	public function test_reports_accessibility_hygiene_without_claiming_browser_geometry(): void {
		$session = array(
			'schema' => 'cresco-session/v1',
			'version' => 1,
			'documentId' => 'quality',
			'nodes' => array(
				array(
					'id' => 'root',
					'type' => 'container',
					'props' => array(),
					'children' => array(
						array( 'id' => 'h2', 'type' => 'heading', 'props' => array( 'text' => 'Heading', 'level' => 2 ) ),
						array( 'id' => 'h4', 'type' => 'heading', 'props' => array( 'text' => 'Jump', 'level' => 4 ) ),
						array( 'id' => 'img', 'type' => 'image', 'props' => array( 'url' => 'https://example.test/a.jpg', 'alt' => '' ) ),
						array( 'id' => 'cta', 'type' => 'button', 'props' => array( 'text' => '', 'url' => '' ) ),
					),
				),
			),
		);
		$result = DesignQualityGate::inspect( $session, array( 'scope' => 'subtree', 'nodeId' => 'root' ) );
		self::assertSame( 'warning', $result['status'] );
		$codes = array_column( $result['items'], 'code' );
		self::assertContains( 'heading-level-jump', $codes );
		self::assertContains( 'image-alt-empty', $codes );
		self::assertContains( 'button-label-empty', $codes );
		self::assertContains( 'browserGeometry', $result['notChecked'] );
		self::assertContains( 'horizontalOverflow', $result['notChecked'] );
	}
}
