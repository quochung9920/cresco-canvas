<?php
/**
 * Resilient bootstrap for the standalone Website Builder.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderBootstrapResilience {
	const HANDLE = 'cresco-canvas-website-builder-bootstrap';

	/** Register the bootstrap before the main builder and attach a post-runtime stall watchdog. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 119 );
		add_action( 'admin_enqueue_scripts', array( $this, 'attach_editor_watchdog' ), 122 );
	}

	/**
	 * Load the request timeout middleware before WebsiteBuilder enqueues its main
	 * runtime at priority 120. Queue order now guarantees that API middleware is
	 * installed before the React App starts its initial REST requests.
	 */
	public function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( VisualEditor::PAGE_SLUG !== $page ) return;

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) return;

		$script = CRESCO_CANVAS_PATH . 'build/website-builder-bootstrap.js';
		if ( ! is_readable( $script ) ) return;

		wp_enqueue_script(
			self::HANDLE,
			CRESCO_CANVAS_URL . 'build/website-builder-bootstrap.js',
			array( 'wp-api-fetch' ),
			$this->asset_version( $script ),
			true
		);

		$settings = array(
			'postId'            => $post_id,
			'postTitle'         => (string) $post->post_title,
			'builderVersion'    => WebsiteBuilder::BUILDER_VERSION,
			'optionalTimeoutMs' => 6500,
			'criticalTimeoutMs' => 10000,
			'watchdogMs'        => 13000,
			'paths'             => array(
				'session'        => '/cresco-canvas/v1/website-builder/session/' . $post_id,
				'context'        => '/cresco-canvas/v1/website-builder/context/' . $post_id,
				'options'        => '/cresco-canvas/v1/website-builder/options',
				'components'     => '/cresco-canvas/v1/website-builder/components',
				'pageSettings'   => '/cresco-canvas/v1/page-settings/' . $post_id,
			
'themeTemplates' => '/cresco-canvas/v1/theme-templates',
				'globalSettings' => '/cresco-canvas/v1/settings',
			),
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.crescoWebsiteBuilderBootstrapSettings=' . wp_json_encode( $settings ) . ';',
			'before'
		);
	}

	/**
	 * Attach a watchdog directly to the main runtime handle.
	 *
	 * This is deliberately independent of the bootstrap asset. If the React
	 * runtime mounts its loading shell but an initial request never settles, or
	 * if another compatibility layer changes dependency ordering, the editor is
	 * still guaranteed to leave the spinner and show an actionable recovery UI.
	 */
	public function attach_editor_watchdog() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( VisualEditor::PAGE_SLUG !== $page ) return;

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( ! wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) return;

		$post_id_json = wp_json_encode( $post_id );
		$watchdog = <<<JS
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var startedAt=Date.now();
function ready(){return !!root.querySelector('.cc-builder-app');}
function diagnostics(){
 var bootstrap=window.crescoWebsiteBuilderBootstrap||{};
 return {
  postId:{$post_id_json},
  elapsedMs:Date.now()-startedAt,
  ready:ready(),
  loading:!!root.querySelector('.cc-builder-loading'),
  settingsPresent:!!(window.crescoWebsiteBuilderSettings&&window.crescoWebsiteBuilderSettings.postId),
  wpElement:!!(window.wp&&window.wp.element),
  wpApiFetch:!!(window.wp&&window.wp.apiFetch),
  bootstrap:bootstrap,
  lastError:bootstrap.lastError||null
 };
}
function copyText(value){
 if(navigator.clipboard&&navigator.clipboard.writeText)return navigator.clipboard.writeText(value);
 var area=document.createElement('textarea');area.value=value;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();try{document.execCommand('copy');}catch(e){}area.remove();return Promise.resolve();
}
function recover(){
 if(ready()||root.querySelector('[data-cresco-stall-recovery]'))return;
 var hadLoading=!!root.querySelector('.cc-builder-loading');
 while(root.firstChild)root.removeChild(root.firstChild);
 var panel=document.createElement('div');panel.className='cc-builder-loading cc-builder-bootstrap-recovery';panel.setAttribute('data-cresco-stall-recovery','1');panel.setAttribute('role','alert');
 var strong=document.createElement('strong');strong.textContent='Cresco Website Builder could not finish loading.';panel.appendChild(strong);
 var message=document.createElement('p');message.textContent=hadLoading?'The editor started, but one or more startup requests did not finish. Your saved document has not been changed.':'The editor runtime did not mount correctly. Your saved document has not been changed.';panel.appendChild(message);
 var actions=document.createElement('div');actions.className='cc-builder-ai-actions';
 var retry=document.createElement('button');retry.type='button';retry.className='cc-builder-primary';retry.textContent='Retry';retry.addEventListener('click',function(){var url=new URL(window.location.href);url.searchParams.set('cresco-retry',String(Date.now()));window.location.href=url.toString();});actions.appendChild(retry);
 var copy=document.createElement('button');copy.type='button';copy.className='cc-builder-secondary';copy.textContent='Copy diagnostics';copy.addEventListener('click',function(){copyText(JSON.stringify(diagnostics(),null,2));});actions.appendChild(copy);panel.appendChild(actions);
 var details=document.createElement('details');var summary=document.createElement('summary');summary.textContent='Diagnostics';details.appendChild(summary);var pre=document.createElement('pre');pre.textContent=JSON.stringify(diagnostics(),null,2);details.appendChild(pre);panel.appendChild(details);root.appendChild(panel);
 try{window.dispatchEvent(new CustomEvent('cresco:builder-stall-recovery',{detail:diagnostics()}));}catch(e){}
}
window.setTimeout(recover,13500);
})(window,document);
JS;

		wp_add_inline_script( 'cresco-canvas-website-builder', $watchdog, 'after' );
	}

	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}
}
