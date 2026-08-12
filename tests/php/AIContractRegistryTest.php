<?php
/**
 * AI contract registry regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\AI\ContractRegistry;
use PHPUnit\Framework\TestCase;

final class AIContractRegistryTest extends TestCase {
	public function test_contract_registry_exposes_requested_widget_contracts(): void {
		$contracts = ContractRegistry::for_types( array( 'heading' ) );
		self::assertArrayHasKey( 'heading', $contracts );
	}

	public function test_invalid_enum_value_returns_a_validation_error(): void {
		$result = ContractRegistry::validate_node(
			array(
				'id'         => 'heading-contract-test',
				'type'       => 'heading',
				'props'      => array( 'level' => '9' ),
				'style'      => array(),
				'responsive' => array(),
				'states'     => array(),
				'customCSS'  => array(),
				'meta'       => array(),
				'children'   => array(),
			)
		);

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_ai_prop_value', $result->get_error_code() );
	}
}
