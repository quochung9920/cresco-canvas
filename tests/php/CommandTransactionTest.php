<?php
/**
 * Canonical command and transaction regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Builder\WebsiteBuilder;
use CrescoCanvas\Core\Command\CommandBus;
use CrescoCanvas\Core\Command\TransactionManager;
use PHPUnit\Framework\TestCase;

final class CommandTransactionTest extends TestCase {
	private function session(): array {
		$session = WebsiteBuilder::sanitize_session(
			array(
				'schema'       => 'cresco-session/v1',
				'version'      => 1,
				'documentId'   => 'command-test',
				'nodes'        => array(
					array(
						'id'       => 'hero',
						'type'     => 'container',
						'props'    => array( 'contentWidth' => 'full', 'layout' => 'flex', 'direction' => 'column' ),
						'children' => array(
							array( 'id' => 'title', 'type' => 'heading', 'props' => array( 'text' => 'Hello', 'level' => 1 ) ),
							array( 'id' => 'copy', 'type' => 'text', 'props' => array( 'text' => 'World', 'tag' => 'p' ) ),
						),
					),
				),
			)
		);
		self::assertFalse( is_wp_error( $session ) );
		return $session;
	}

	private function command( string $name, array $payload ): array {
		return array(
			'schema'  => CommandBus::SCHEMA,
			'command' => $name,
			'source'  => 'inspector',
			'target'  => array( 'scope' => 'page' ),
			'payload' => $payload,
		);
	}

	public function test_node_meta_commands_use_the_canonical_patch_path(): void {
		$result = CommandBus::preview( $this->session(), $this->command( 'node.rename', array( 'nodeId' => 'title', 'label' => 'Hero title' ) ) );
		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( 'Hero title', $result['session']['nodes'][0]['children'][0]['meta']['label'] );

		$locked = CommandBus::preview( $result['session'], $this->command( 'node.lock', array( 'nodeId' => 'title', 'locked' => true ) ) );
		self::assertFalse( is_wp_error( $locked ) );
		self::assertTrue( $locked['session']['nodes'][0]['children'][0]['meta']['locked'] );
	}

	public function test_duplicate_remaps_ids_instead_of_cloning_identity(): void {
		$result = CommandBus::preview( $this->session(), $this->command( 'node.duplicate', array( 'nodeId' => 'title' ) ) );
		self::assertFalse( is_wp_error( $result ) );
		$children = $result['session']['nodes'][0]['children'];
		self::assertCount( 3, $children );
		self::assertSame( 'title', $children[0]['id'] );
		self::assertNotSame( 'title', $children[1]['id'] );
		self::assertSame( 'Hello', $children[1]['props']['text'] );
	}

	public function test_group_is_one_validated_multi_operation_command(): void {
		$result = CommandBus::preview(
			$this->session(),
			$this->command( 'node.group', array( 'nodeIds' => array( 'title', 'copy' ) ) )
		);
		self::assertFalse( is_wp_error( $result ) );
		$hero = $result['session']['nodes'][0];
		self::assertCount( 1, $hero['children'] );
		self::assertSame( 'container', $hero['children'][0]['type'] );
		self::assertCount( 2, $hero['children'][0]['children'] );
	}

	public function test_responsive_reset_removes_only_the_requested_breakpoint(): void {
		$with_responsive = CommandBus::preview(
			$this->session(),
			$this->command( 'responsive.update', array( 'nodeId' => 'title', 'values' => array( 'mobile' => array( 'fontSize' => '28px' ), 'tablet' => array( 'fontSize' => '36px' ) ) ) )
		);
		self::assertFalse( is_wp_error( $with_responsive ) );
		$reset = CommandBus::preview( $with_responsive['session'], $this->command( 'responsive.reset', array( 'nodeId' => 'title', 'breakpoint' => 'mobile' ) ) );
		self::assertFalse( is_wp_error( $reset ) );
		$responsive = $reset['session']['nodes'][0]['children'][0]['responsive'];
		self::assertArrayNotHasKey( 'mobile', $responsive );
		self::assertSame( '36px', $responsive['tablet']['fontSize'] );
	}

	public function test_transaction_returns_one_candidate_and_one_diff_for_many_commands(): void {
		$transaction = array(
			'schema'    => TransactionManager::SCHEMA,
			'id'        => 'typing-title',
			'label'     => 'Edit hero title',
			'source'    => 'inspector',
			'commands'  => array(
				$this->command( 'props.update', array( 'nodeId' => 'title', 'values' => array( 'text' => 'Premium hero' ) ) ),
				$this->command( 'style.update', array( 'nodeId' => 'title', 'values' => array( 'fontSize' => '56px' ) ) ),
			),
		);
		$result = TransactionManager::preview( $this->session(), $transaction );
		self::assertFalse( is_wp_error( $result ) );
		self::assertSame( 'Premium hero', $result['session']['nodes'][0]['children'][0]['props']['text'] );
		self::assertSame( '56px', $result['session']['nodes'][0]['children'][0]['style']['fontSize'] );
		self::assertSame( 'typing-title', $result['transaction']['id'] );
		self::assertCount( 2, $result['transaction']['commands'] );
		self::assertGreaterThan( 0, $result['diff']['summary']['total'] );
	}
}
