<?php
/**
 * Regression coverage for AI/import/export hardening.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\AIInterchangeHardening;
use CrescoCanvas\AI\PatchValidator;
use CrescoCanvas\Builder\WebsiteBuilderSessionSanitizer;
use CrescoCanvas\Session\SessionManager;
use PHPUnit\Framework\TestCase;

final class AIInterchangeHardeningTest extends TestCase {
	private function session(): array {
		$session = WebsiteBuilderSessionSanitizer::sanitize_session(
			array(
				'schema'     => SessionManager::SCHEMA,
				'version'    => SessionManager::VERSION,
				'documentId' => 'hardening-test',
				'nodes'      => array(
					array(
						'id'       => 'root',
						'type'     => 'container',
						'props'    => array( 'layout' => 'flex', 'direction' => 'column' ),
						'children' => array(
							array(
								'id'       => 'cta',
								'type'     => 'button',
								'props'    => array( 'text' => 'Go', 'url' => '#' ),
								'children' => array(),
							),
						),
					),
					array( 'id' => 'outside', 'type' => 'divider', 'children' => array() ),
				),
			)
		);
		self::assertFalse( is_wp_error( $session ) );
		return $session;
	}

	public function test_set_states_is_a_first_class_patch_operation(): void {
		$result = PatchValidator::validate(
			$this->session(),
			array(
				'schema'     => PatchValidator::SCHEMA,
				'target'     => array( 'scope' => 'widget', 'nodeId' => 'cta' ),
				'operations' => array(
					array( 'op' => 'setStates', 'nodeId' => 'cta', 'states' => array( 'hover' => array( 'opacity' => '.75' ) ) ),
				),
			)
		);
		self::assertFalse( is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
		self::assertSame( '.75', $result['session']['nodes'][0]['children'][0]['states']['hover']['opacity'] );
	}

	public function test_selection_subtrees_patch_can_edit_descendants_but_not_escape(): void {
		$valid = PatchValidator::validate(
			$this->session(),
			array(
				'schema'     => PatchValidator::SCHEMA,
				'target'     => array( 'scope' => 'selection-subtrees', 'nodeIds' => array( 'root' ) ),
				'operations' => array( array( 'op' => 'setStyle', 'nodeId' => 'cta', 'style' => array( 'opacity' => '.9' ) ) ),
			)
		);
		self::assertFalse( is_wp_error( $valid ), is_wp_error( $valid ) ? $valid->get_error_message() : '' );

		$invalid = PatchValidator::validate(
			$this->session(),
			array(
				'schema'     => PatchValidator::SCHEMA,
				'target'     => array( 'scope' => 'selection-subtrees', 'nodeIds' => array( 'root' ) ),
				'operations' => array( array( 'op' => 'setStyle', 'nodeId' => 'outside', 'style' => array( 'opacity' => '.9' ) ) ),
			)
		);
		self::assertTrue( is_wp_error( $invalid ) );
		self::assertSame( 'cresco_ai_patch_scope_escape', $invalid->get_error_code() );
	}

	public function test_ai_result_requires_an_explicit_schema(): void {
		$result = AIInterchangeHardening::validate_ai_request(
			10,
			array( 'currentSession' => $this->session(), 'result' => array( 'nodes' => array() ) )
		);
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_result_schema', $result->get_error_code() );
	}

	public function test_scoped_export_rejects_full_session_return(): void {
		$result = AIInterchangeHardening::validate_ai_request(
			10,
			array(
				'currentSession' => $this->session(),
				'expectedTarget' => array( 'scope' => 'subtree', 'nodeId' => 'root' ),
				'result'         => $this->session(),
			)
		);
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_scoped_session_forbidden', $result->get_error_code() );
	}

	public function test_patch_target_cannot_change_after_export(): void {
		$result = AIInterchangeHardening::validate_ai_request(
			10,
			array(
				'currentSession' => $this->session(),
				'expectedTarget' => array( 'scope' => 'subtree', 'nodeId' => 'root' ),
				'result'         => array(
					'schema'     => PatchValidator::SCHEMA,
					'target'     => array( 'scope' => 'subtree', 'nodeId' => 'outside' ),
					'operations' => array(),
				),
			)
		);
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_target_mismatch', $result->get_error_code() );
	}

	public function test_non_page_package_cannot_accidentally_replace_page(): void {
		$result = AIInterchangeHardening::validate_interchange_request(
			10,
			array(
				'destination' => 'replace-page',
				'package'     => array(
					'schema'  => 'cresco-interchange/v1',
					'version' => 1,
					'scope'   => 'subtree',
					'target'  => array( 'scope' => 'subtree', 'nodeId' => 'root' ),
					'content' => array( 'node' => $this->session()['nodes'][0] ),
				),
			)
		);
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_interchange_scope_widening', $result->get_error_code() );
	}

	public function test_interchange_rejects_unknown_node_fields_instead_of_dropping_them(): void {
		$node = $this->session()['nodes'][0];
		$node['inventedField'] = 'must-not-be-silently-dropped';
		$result = AIInterchangeHardening::validate_interchange_request(
			10,
			array(
				'destination' => 'replace',
				'package'     => array(
					'schema'  => 'cresco-interchange/v1',
					'version' => 1,
					'scope'   => 'subtree',
					'target'  => array( 'scope' => 'subtree', 'nodeId' => 'root' ),
					'content' => array( 'node' => $node ),
				),
			)
		);
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_node_field', $result->get_error_code() );
	}
}
