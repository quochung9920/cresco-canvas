<?php
/**
 * Final runtime policy and recovery boundary for Website Builder editors.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderRuntimeGuard {
	/** Register cache hardening first and deterministic module policy last. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'harden_editor_assets' ), 123 );
		add_action( 'admin_enqueue_scripts', array( $this, 'apply_module_policy' ), 1400 );
	}

	/**
	 * Refresh required runtime versions and install the only user-facing startup
	 * recovery panel. Other resilience services publish state but do not compete
	 * for recovery UI ownership.
	 */
	public function harden_editor_assets() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context ) return;

		$this->refresh_registered_assets();
		if ( ! wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) return;

		$canonical_config = WebsiteBuilderEditorConfig::for_context( $context );
		if ( $canonical_config ) {
			wp_add_inline_script(
				'cresco-canvas-website-builder',
				'window.crescoWebsiteBuilderSettings=Object.assign({},window.crescoWebsiteBuilderSettings||{},' . wp_json_encode( $canonical_config ) . ');',
				'before'
			);
		}

		$post_id = wp_json_encode( $context->post_id() );
		$guard   = <<<JS
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var started=Date.now(),postId={$post_id};
function ready(){return !!root.querySelector('.cc-studio-app')}
function runtimeState(){return window.crescoRuntimeState||window.crescoWebsiteBuilderBootstrap||{};}
function diagnostics(){var editor=window.crescoWebsiteBuilderEditorBoot||{},state=runtimeState(),diag=window.crescoDiagnostics&&window.crescoDiagnostics.snapshot?window.crescoDiagnostics.snapshot():(window.crescoStudioDiagnostics||null);return{postId:postId,elapsedMs:Date.now()-started,ready:ready(),state:state,editorBoot:editor,diagnostics:diag,runtimeOwner:window.crescoCanonicalRuntimeOwner||null,settingsPresent:!!window.crescoWebsiteBuilderSettings,wpElement:!!(window.wp&&window.wp.element),wpApiFetch:!!(window.wp&&window.wp.apiFetch),isolationMode:window.crescoRuntimeIsolationMode||'normal',architectureQuarantined:!!window.crescoArchitectureQuarantined};}
function copyText(value){if(navigator.clipboard&&navigator.clipboard.writeText)return navigator.clipboard.writeText(value);var area=document.createElement('textarea');area.value=value;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();try{document.execCommand('copy')}catch(e){}area.remove();return Promise.resolve();}
function recover(){
 if(ready()||root.querySelector('[data-cresco-runtime-guard]'))return;
 var loading=root.querySelector('.cc-builder-loading,.cc-studio-loading,.cc-standalone-loading');
 if(!loading)return;
 while(root.firstChild)root.removeChild(root.firstChild);
 var panel=document.createElement('div');panel.className='cc-builder-loading cc-builder-bootstrap-recovery';panel.setAttribute('data-cresco-runtime-guard','1');panel.setAttribute('role','alert');
 var title=document.createElement('strong');title.textContent='Cresco Studio could not finish loading.';panel.appendChild(title);
 var message=document.createElement('p');message.textContent='The canonical Studio runtime did not mount inside the safe loading window. The retired Website Builder runtime will not be used as a fallback, and the saved document was not changed. Use Cresco Diagnostics to identify the failing module or request.';panel.appendChild(message);
 var actions=document.createElement('div');actions.className='cc-builder-ai-actions';
 var retry=document.createElement('button');retry.type='button';retry.className='cc-builder-primary';retry.textContent='Reload fresh';retry.onclick=function(){var u=new URL(window.location.href);u.searchParams.set('cresco-runtime',String(Date.now()));window.location.replace(u.toString())};actions.appendChild(retry);
 var copy=document.createElement('button');copy.type='button';copy.className='cc-builder-secondary';copy.textContent='Copy diagnostics';copy.onclick=function(){copyText(JSON.stringify(diagnostics(),null,2))};actions.appendChild(copy);panel.appendChild(actions);
 var details=document.createElement('details'),summary=document.createElement('summary'),pre=document.createElement('pre');summary.textContent='Startup diagnostics';pre.textContent=JSON.stringify(diagnostics(),null,2);details.appendChild(summary);details.appendChild(pre);panel.appendChild(details);root.appendChild(panel);
}
window.setTimeout(recover,12000);
})(window,document);
JS;
		wp_add_inline_script( 'cresco-canvas-website-builder', $guard, 'after' );
	}

	/**
	 * Apply one central module policy after all enhancement services had a chance
	 * to register/enqueue. Normal mode keeps every healthy module except modules
	 * explicitly quarantined by the registry. Isolation modes are deterministic.
	 */
	public function apply_module_policy() {
		$context = WebsiteBuilderRuntimeContext::from_request();
		if ( ! $context ) return;

		$this->refresh_registered_assets();
		$enabled = WebsiteBuilderModuleRegistry::enabled_keys( $context );

		foreach ( WebsiteBuilderModuleRegistry::all() as $key => $module ) {
			if ( ! empty( $module['required'] ) ) continue;
			$keep = in_array( $key, $enabled, true );
			foreach ( $module['scripts'] as $asset ) {
				$handle = $asset['handle'];
				if (
					$keep
					&& ! wp_script_is( $handle, 'registered' )
					&& ! empty( $asset['register'] )
					&& WebsiteBuilderAsset::readable( $asset['file'] )
				) {
					$deps = isset( $asset['deps'] ) && is_array( $asset['deps'] )
						? $asset['deps']
						: array( 'cresco-canvas-website-builder' );
					wp_register_script(
						$handle,
						WebsiteBuilderAsset::url( $asset['file'] ),
						$deps,
						WebsiteBuilderAsset::version( $asset['file'] ),
						true
					);
				}
				if ( $keep && wp_script_is( $handle, 'registered' ) ) wp_enqueue_script( $handle );
				if ( ! $keep ) wp_dequeue_script( $handle );
			}
			foreach ( $module['styles'] as $asset ) {
				$handle = $asset['handle'];
				if ( $keep && wp_style_is( $handle, 'registered' ) ) wp_enqueue_style( $handle );
				if ( ! $keep ) wp_dequeue_style( $handle );
			}
		}

		if ( wp_script_is( 'cresco-canvas-website-builder', 'registered' ) ) {
			$payload = array(
				'isolationMode'     => $context->isolation_mode(),
				'enabledModules'    => $enabled,
				'architectureReady' => in_array( 'architecture', $enabled, true ),
			);
			wp_add_inline_script(
				'cresco-canvas-website-builder',
				'window.crescoRuntimePolicy=' . wp_json_encode( $payload ) . ';window.crescoRuntimeIsolationMode=' . wp_json_encode( $context->isolation_mode() ) . ';window.crescoArchitectureQuarantined=' . ( in_array( 'architecture', $enabled, true ) ? 'false' : 'true' ) . ';',
				'before'
			);
		}
	}

	private function refresh_registered_assets() {
		foreach ( WebsiteBuilderModuleRegistry::all() as $module ) {
			foreach ( $module['scripts'] as $asset ) WebsiteBuilderAsset::refresh_registered_script( $asset['handle'], $asset['file'] );
			foreach ( $module['styles'] as $asset ) WebsiteBuilderAsset::refresh_registered_style( $asset['handle'], $asset['file'] );
		}
	}
}
