<?php
/**
 * Cresco Session v1 regression tests.
 *
 * @package CrescoCanvas
 */

use CrescoCanvas\Session\SessionManager;
use PHPUnit\Framework\TestCase;

final class SessionManagerTest extends TestCase {
	public function test_valid_session_keeps_only_contract_properties(): void {
		$session = SessionManager::sanitize_session(
			array(
				'schema' => 'cresco-session/v1',
				'version' => 1,
				'documentId' => 'Home Page',
				'nodes' => array(
					array(
						'id' => 'hero',
						'type' => 'container',
						'props' => array( 'layout' => 'flex', 'direction' => 'column', 'columns' => 99, 'invented' => 'ignored' ),
						'style' => array( 'paddingTop' => '{spacing.xl}', 'fontSize' => '48px', 'unknownProperty' => 'discard-me' ),
						'responsive' => array( 'mobile' => array( 'paddingTop' => '24px' ), 'watch' => array( 'width' => '1px' ) ),
						'customCSS' => array( 'base' => '&:hover { transform: translateY(-3px); }' ),
						'children' => array(
							array( 'id' => 'gap', 'type' => 'spacer', 'props' => array( 'height' => '32px' ) ),
						),
					),
				),
			)
		);

		self::assertFalse( is_wp_error( $session ) );
		self::assertSame( 'homepage', $session['documentId'] );
		self::assertSame( 12, $session['nodes'][0]['props']['columns'] );
		self::assertArrayNotHasKey( 'invented', $session['nodes'][0]['props'] );
		self::assertArrayNotHasKey( 'unknownProperty', $session['nodes'][0]['style'] );
		self::assertArrayHasKey( 'mobile', $session['nodes'][0]['responsive'] );
		self::assertArrayNotHasKey( 'watch', $session['nodes'][0]['responsive'] );
		self::assertSame( 'spacer', $session['nodes'][0]['children'][0]['type'] );
	}

	public function test_unknown_widget_and_duplicate_ids_are_rejected(): void {
		$unknown = SessionManager::sanitize_session(
			array( 'nodes' => array( array( 'id' => 'one', 'type' => 'invented-widget' ) ) )
		);
		self::assertTrue( is_wp_error( $unknown ) );
		self::assertSame( 'cresco_session_widget', $unknown->get_error_code() );

		$duplicate = SessionManager::sanitize_session(
			array(
				'nodes' => array(
					array( 'id' => 'same', 'type' => 'divider' ),
					array( 'id' => 'same', 'type' => 'spacer' ),
				),
			)
		);
		self::assertTrue( is_wp_error( $duplicate ) );
		self::assertSame( 'cresco_session_duplicate_id', $duplicate->get_error_code() );
	}

	public function test_leaf_widgets_cannot_receive_children(): void {
		$result = SessionManager::sanitize_session(
			array(
				'nodes' => array(
					array(
						'id' => 'divider-one',
						'type' => 'divider',
						'children' => array( array( 'id' => 'nested', 'type' => 'spacer' ) ),
					),
				),
			)
		);

		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'cresco_session_children', $result->get_error_code() );
	}

	public function test_custom_css_requires_widget_scope_and_blocks_external_constructs(): void {
		$valid = SessionManager::sanitize_custom_css( '& { opacity: .9; } &:hover { transform: translateY(-3px); }' );
		self::assertIsString( $valid );

		$global = SessionManager::sanitize_custom_css( 'body { display: none; }' );
		self::assertTrue( is_wp_error( $global ) );
		self::assertSame( 'cresco_session_css_scope', $global->get_error_code() );

		$external = SessionManager::sanitize_custom_css( '& { background-image: url(https://example.test/a.png); }' );
		self::assertTrue( is_wp_error( $external ) );
		self::assertSame( 'cresco_session_css_forbidden', $external->get_error_code() );
	}

	public function test_compiler_scopes_css_and_uses_global_token_variables(): void {
		$session = SessionManager::sanitize_session(
			array(
				'documentId' => 'demo',
				'nodes' => array(
					array(
						'id' => 'hero',
						'type' => 'container',
						'style' => array( 'paddingTop' => '{spacing.xl}', 'color' => '{colors.text}' ),
						'responsive' => array( 'mobile' => array( 'paddingTop' => '20px' ) ),
						'customCSS' => array( 'base' => '&:hover { opacity: .9; }' ),
					),
				),
			)
		);
		self::assertFalse( is_wp_error( $session ) );

		$css = SessionManager::compile_session_css( $session );
		self::assertStringContainsString( '[data-cresco-id="hero"]{', $css );
		self::assertStringContainsString( 'padding-top:var(--cc-space-xl);', $css );
		self::assertStringContainsString( 'color:var(--cc-text);', $css );
		self::assertStringContainsString( '[data-cresco-id="hero"]:hover { opacity: .9; }', $css );
		self::assertStringContainsString( '@media (max-width:767px)', $css );
	}
}
