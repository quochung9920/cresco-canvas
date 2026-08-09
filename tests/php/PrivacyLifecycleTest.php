<?php
use CrescoCanvas\Forms\FormAdministration;
use CrescoCanvas\Forms\FormBuilder;
use CrescoCanvas\Lifecycle\UninstallPolicy;
use CrescoCanvas\Migration\Migrator;
use PHPUnit\Framework\TestCase;

final class PrivacyLifecycleTest extends TestCase {
	protected function setUp(): void { $GLOBALS['cresco_test_posts']=array(); $GLOBALS['cresco_test_post_meta']=array(); $GLOBALS['cresco_test_options']=array(); $GLOBALS['cresco_test_actions']=array(); }
	private function post($id,$type,$status='private',$content=''){ $GLOBALS['cresco_test_posts'][$id]=(object)array('ID'=>$id,'post_type'=>$type,'post_status'=>$status,'post_title'=>'T','post_content'=>$content,'post_date_gmt'=>'2026-08-01 00:00:00'); }

	public function test_privacy_exporter_finds_nested_email_and_eraser_reports_removal(): void {
		$this->post(1,FormBuilder::POST_TYPE); $GLOBALS['cresco_test_post_meta'][1]['_cresco_submission_data']=array('contact'=>array('email'=>'person@example.test'),'message'=>'hello');
		$admin=new FormAdministration(); $export=$admin->export_personal_data('person@example.test',1);
		self::assertCount(1,$export['data']); self::assertTrue($export['done']);
		$erase=$admin->erase_personal_data('person@example.test',1);
		self::assertTrue($erase['items_removed']); self::assertTrue($erase['done']); self::assertArrayNotHasKey(1,$GLOBALS['cresco_test_posts']);
	}

	public function test_uninstall_default_preserves_data_and_explicit_cleanup_never_deletes_user_page_content(): void {
		$this->post( 99, 'page', 'publish', '<p>User authored body</p>' );
		$this->post( 1, FormBuilder::POST_TYPE, 'private' );
		$GLOBALS['wpdb'] = (object) array();
		$GLOBALS['cresco_test_options']['cresco_canvas_settings'] = array( 'removeDataOnUninstall' => false );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) define( 'WP_UNINSTALL_PLUGIN', true );
		require_once CRESCO_CANVAS_PATH . 'uninstall.php';
		self::assertArrayHasKey( 99, $GLOBALS['cresco_test_posts'] );
		self::assertArrayHasKey( 1, $GLOBALS['cresco_test_posts'] );
		self::assertSame( '<p>User authored body</p>', $GLOBALS['cresco_test_posts'][99]->post_content );

		$GLOBALS['cresco_test_options']['cresco_canvas_settings'] = array( 'removeDataOnUninstall' => true );
		cresco_canvas_uninstall_site_data();
		self::assertArrayHasKey( 99, $GLOBALS['cresco_test_posts'] );
		self::assertArrayNotHasKey( 1, $GLOBALS['cresco_test_posts'] );
		self::assertSame( '<p>User authored body</p>', $GLOBALS['cresco_test_posts'][99]->post_content );
	}

	public function test_uninstall_ownership_policy_never_owns_page_or_post(): void {
		self::assertFalse(UninstallPolicy::owns_post_type('page')); self::assertFalse(UninstallPolicy::owns_post_type('post'));
		self::assertContains('cresco_submission',UninstallPolicy::owned_post_types());
	}

	public function test_failed_migration_retries_from_last_completed_version(): void {
		$GLOBALS['cresco_test_options']['cresco_canvas_settings'] = array( 'primary' => '#123456' );
		$failed_once = false;
		add_action( 'cresco_canvas_before_migration', static function ( $version ) use ( &$failed_once ) {
			if ( 2 === (int) $version && ! $failed_once ) { $failed_once = true; throw new RuntimeException( 'sensitive internal failure' ); }
		} );
		$first = Migrator::run();
		self::assertInstanceOf( WP_Error::class, $first );
		self::assertSame( 'cresco_canvas_migration_failed', $first->get_error_code() );
		self::assertSame( 1, get_option( Migrator::VERSION_OPTION ) );
		self::assertTrue( $first->get_error_data()['retryable'] );
		self::assertFalse( str_contains( wp_json_encode( get_option( Migrator::STATE_OPTION ) ), 'sensitive internal failure' ) );
		$GLOBALS['cresco_test_actions']['cresco_canvas_before_migration'] = array();
		self::assertTrue( Migrator::run() );
		self::assertSame( CRESCO_CANVAS_SCHEMA_VERSION, get_option( Migrator::VERSION_OPTION ) );
	}

	public function test_downgrade_is_detected_and_run_refuses_to_write(): void {
		$GLOBALS['cresco_test_options'][Migrator::VERSION_OPTION]=CRESCO_CANVAS_SCHEMA_VERSION+1;
		self::assertTrue(Migrator::is_downgrade()); $result=Migrator::run(); self::assertInstanceOf(WP_Error::class,$result); self::assertSame('cresco_canvas_schema_newer',$result->get_error_code());
		self::assertSame(CRESCO_CANVAS_SCHEMA_VERSION+1,get_option(Migrator::VERSION_OPTION));
	}
}
