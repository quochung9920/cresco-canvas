<?php
/**
 * Theme Builder diagnostics regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Theme\ThemeDiagnostics;
use PHPUnit\Framework\TestCase;

final class ThemeDiagnosticsTest extends TestCase {
	public function test_reports_invalid_type_and_empty_content(): void {
		$issues = ThemeDiagnostics::analyze_records(
			array(
				array(
					'id'         => 10,
					'type'       => 'not-valid',
					'priority'   => 10,
					'conditions' => array(),
					'content'    => '',
					'status'     => 'draft',
				),
			)
		);

		self::assertSame( array( 'invalid_type', 'empty_content' ), array_column( $issues, 'code' ) );
	}

	public function test_reports_exclude_only_conditions(): void {
		$issues = ThemeDiagnostics::analyze_records(
			array(
				array(
					'id'         => 11,
					'type'       => 'header',
					'priority'   => 20,
					'conditions' => array(
						array( 'operator' => 'exclude', 'rule' => 'front_page', 'value' => '' ),
					),
					'content'    => '<!-- wp:paragraph --><p>Header</p><!-- /wp:paragraph -->',
					'status'     => 'publish',
				),
			)
		);

		self::assertSame( 'exclude_only', $issues[0]['code'] );
		self::assertSame( 'warning', $issues[0]['severity'] );
	}

	public function test_reports_ambiguous_published_templates_regardless_of_condition_order(): void {
		$shared = array(
			array( 'operator' => 'include', 'rule' => 'entire_site', 'value' => '' ),
			array( 'operator' => 'exclude', 'rule' => 'logged_out', 'value' => '' ),
		);
		$issues = ThemeDiagnostics::analyze_records(
			array(
				array(
					'id' => 21, 'type' => 'footer', 'priority' => 50, 'conditions' => $shared,
					'content' => '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->', 'status' => 'publish',
				),
				array(
					'id' => 22, 'type' => 'footer', 'priority' => 50, 'conditions' => array_reverse( $shared ),
					'content' => '<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph -->', 'status' => 'publish',
				),
			)
		);

		self::assertSame( 'ambiguous_priority', $issues[0]['code'] );
		self::assertSame( 22, $issues[0]['templateId'] );
		self::assertSame( 21, $issues[0]['relatedId'] );
	}

	public function test_drafts_do_not_create_priority_conflicts(): void {
		$issues = ThemeDiagnostics::analyze_records(
			array(
				array(
					'id' => 31, 'type' => 'single', 'priority' => 10, 'conditions' => array(),
					'content' => '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->', 'status' => 'draft',
				),
				array(
					'id' => 32, 'type' => 'single', 'priority' => 10, 'conditions' => array(),
					'content' => '<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph -->', 'status' => 'draft',
				),
			)
		);

		self::assertSame( array(), $issues );
	}
}
