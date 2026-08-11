<?php
/**
 * Runtime guard for the standalone Website Builder.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderRuntimeGuard {
	/** Register late runtime hardening after the editor assets exist. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'harden_editor_assets' ), 123 );
	}

	/**
	 * Force content-addressed asset versions and install an independent recovery
	 * watchdog. This prevents a stale cached editor bundle from leaving the UI on
	 * the initial loading screen forever.
	 */
	public function harden_editor_assets() {
		if ( ! $this->is_editor_request() ) return;

		$this->refresh_script_version( 'cresco-canvas-website-builder', CRESCO_CANVAS_PATH . 'build/website-builder-editor.js' );
		$this->refresh_script_version( 'cresco-canvas-website-builder-bootstrap', CRESCO_CANVAS_PATH . 'build/website-builder-bootstrap.js' );
		$this->refresh_style_version( 'cresco-canvas-website-builder', CRESCO_CANVAS_PATH . 'assets/css/website-builder.css' );

		if ( ! wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) return;

		$guard = <<<'JS'
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var started=Date.now();
function ready(){return !!root.querySelector('.cc-builder-app')}
function recovery(){
 if(ready()||root.querySelector('[data-cresco-runtime-guard]'))return;
 var loading=root.querySelector('.cc-builder-loading');
 if(!loading)return;
 while(root.firstChild)root.removeChild(root.firstChild);
 var panel=document.createElement('div');panel.className='cc-builder-loading cc-builder-bootstrap-recovery';panel.setAttribute('data-cresco-runtime-guard','1');panel.setAttribute('role','alert');
 var title=document.createElement('strong');title.textContent='Cresco Website Builder could not finish loading.';panel.appendChild(title);
 var message=document.createElement('p');message.textContent='The editor startup exceeded the safe loading window. Cached runtime files or a stalled REST request may be responsible. Your saved document has not been changed.';panel.appendChild(message);
 var actions=document.createElement('div');actions.className='cc-builder-ai-actions';
 var retry=document.createElement('button');retry.type='button';retry.className='cc-builder-primary';retry.textContent='Reload fresh';retry.onclick=function(){var u=new URL(window.location.href);u.searchParams.set('cresco-runtime',String(Date.now()));window.location.replace(u.toString())};actions.appendChild(retry);
 var copy=document.createElement('button');copy.type='button';copy.className='cc-builder-secondary';copy.textContent='Copy diagnostics';copy.onclick=function(){var boot=window.crescoWebsiteBuilderBootstrap||{},editor=window.crescoWebsiteBuilderEditorBoot||{},value=JSON.stringify({elapsedMs:Date.now()-started,ready:ready(),editorBoot:editor,bootstrap:boot,settingsPresent:!!window.crescoWebsiteBuilderSettings,wpApiFetch:!!(window.wp&&window.wp.apiFetch)},null,2);if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(value)};actions.appendChild(copy);panel.appendChild(actions);root.appendChild(panel);
}
window.setTimeout(recovery,12000);
})(window,document);
JS;
		wp_add_inline_script( 'cresco-canvas-website-builder', $guard, 'after' );
	}

	private function refresh_script_version( $handle, $path ) {
		global $wp_scripts;
		if ( ! $wp_scripts || ! isset( $wp_scripts->registered[ $handle ] ) || ! is_readable( $path ) ) return;
		$wp_scripts->registered[ $handle ]->ver = $this->asset_version( $path );
	}

	private function refresh_style_version( $handle, $path ) {
		global $wp_styles;
		if ( ! $wp_styles || ! isset( $wp_styles->registered[ $handle ] ) || ! is_readable( $path ) ) return;
		$wp_styles->registered[ $handle ]->ver = $this->asset_version( $path );
	}

	private function asset_version( $path ) {
		$hash = hash_file( 'sha256', $path );
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}

	private function is_editor_request() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return VisualEditor::PAGE_SLUG === $page;
	}
}
