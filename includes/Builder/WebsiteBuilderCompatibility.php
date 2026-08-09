<?php
/**
 * Compatibility boundary between the unified Website Builder and retired standalone UI layers.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Session\SessionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderCompatibility {
	/** Keep the Website Builder as the only UI runtime and normalize legacy contracts. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'remove_legacy_editor_assets' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'replace_frontend_compiled_styles' ), 999 );
	}

	public function remove_legacy_editor_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( VisualEditor::PAGE_SLUG !== $page || ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;

		$this->bridge_editor_contracts();

		$scripts = array(
			'cresco-canvas-standalone-ai-bridge',
			'cresco-canvas-editor-experience-v2-tools',
			'cresco-canvas-editor-experience-v2-sync',
			'cresco-canvas-editor-experience-v2',
			'cresco-canvas-standalone-history',
			'cresco-canvas-standalone-page-settings',
			'cresco-canvas-standalone-ui-v3',
			'cresco-canvas-widget-control-enhancements',
			'cresco-canvas-standalone-inspector-v2',
			'cresco-canvas-global-config-import',
			'cresco-canvas-viewport-shell',
			'cresco-canvas-standalone-visual-editor',
		);
		$styles = array(
			'cresco-canvas-standalone-ai-bridge',
			'cresco-canvas-editor-experience-v2-tools',
			'cresco-canvas-editor-experience-v2-polish',
			'cresco-canvas-editor-experience-v2',
			'cresco-canvas-standalone-history',
			'cresco-canvas-standalone-page-settings',
			'cresco-canvas-standalone-ui-v3',
			'cresco-canvas-widget-control-enhancements',
			'cresco-canvas-standalone-inspector-v2',
			'cresco-canvas-global-config-import',
			'cresco-canvas-viewport-shell',
			'cresco-canvas-standalone-visual-editor',
		);

		foreach ( $scripts as $handle ) wp_dequeue_script( $handle );
		foreach ( $styles as $handle ) wp_dequeue_style( $handle );

		$control_script = CRESCO_CANVAS_PATH . 'build/website-builder-controls.js';
		$control_style  = CRESCO_CANVAS_PATH . 'assets/css/website-builder-controls.css';
		if ( is_readable( $control_style ) ) {
			wp_enqueue_style(
				'cresco-canvas-website-builder-controls',
				CRESCO_CANVAS_URL . 'assets/css/website-builder-controls.css',
				array( 'cresco-canvas-website-builder' ),
				CRESCO_CANVAS_VERSION
			);
		}
		if ( is_readable( $control_script ) ) {
			wp_enqueue_script(
				'cresco-canvas-website-builder-controls',
				CRESCO_CANVAS_URL . 'build/website-builder-controls.js',
				array( 'cresco-canvas-website-builder' ),
				CRESCO_CANVAS_VERSION,
				true
			);
		}
	}

	/**
	 * WebsiteRenderer originally interpreted breakpoint starts as max-widths.
	 * Replace only that builder-generated inline fragment with the authoritative
	 * range-aware compiler while leaving third-party inline CSS on the handle.
	 */
	public function replace_frontend_compiled_styles() {
		if ( ! is_singular( 'page' ) ) return;
		$post_id = get_queried_object_id();
		if ( WebsiteBuilder::BUILDER_VERSION !== (string) get_post_meta( $post_id, WebsiteBuilder::BUILDER_META, true ) ) return;
		$raw = (string) get_post_meta( $post_id, SessionManager::META_KEY, true );
		$decoded = $raw ? json_decode( $raw, true ) : null;
		$session = is_array( $decoded ) ? WebsiteBuilder::sanitize_session( $decoded ) : null;
		if ( ! is_array( $session ) ) return;
		$session = $this->normalize_legacy_tokens( $session );

		$handle = 'cresco-canvas-website-builder-frontend';
		$styles = wp_styles();
		if ( ! isset( $styles->registered[ $handle ] ) ) return;
		$registered = $styles->registered[ $handle ];
		$after = isset( $registered->extra['after'] ) && is_array( $registered->extra['after'] ) ? $registered->extra['after'] : array();
		$registered->extra['after'] = array_values(
			array_filter(
				$after,
				static function ( $css ) {
					return false === strpos( (string) $css, '.cresco-website-builder-root [data-cresco-id=' );
				}
			)
		);
		$compiled = WebsiteBuilderCssCompiler::compile( $session );
		if ( '' !== $compiled ) wp_add_inline_style( $handle, $compiled );
	}

	/** Normalize a pre-Website-Builder token alias without mutating stored JSON. */
	private function normalize_legacy_tokens( $value ) {
		if ( is_string( $value ) ) return '{spacing.gridGap}' === $value ? '{layout.gridGap}' : $value;
		if ( ! is_array( $value ) ) return $value;
		foreach ( $value as $key => $item ) $value[ $key ] = $this->normalize_legacy_tokens( $item );
		return $value;
	}

	/**
	 * The Website Builder reuses PageSettings v2 and the existing DesignTokens
	 * catalog. Normalize historical aliases and remove UI choices that do not yet
	 * have distinct server semantics instead of silently coercing user intent.
	 */
	private function bridge_editor_contracts() {
		if ( ! wp_script_is( 'cresco-canvas-website-builder', 'enqueued' ) ) return;
		$script = <<<'JS'
(function(window,document){
'use strict';
var apiFetch=window.wp&&window.wp.apiFetch;
if(apiFetch&&typeof apiFetch.use==='function'){
	apiFetch.use(function(options,next){
		var path=String(options&&options.path||'');
		var pageSettings=/\/cresco-canvas\/v1\/page-settings\/\d+$/.test(path);
		var builderContext=/\/cresco-canvas\/v1\/website-builder\/context\/\d+$/.test(path);
		if(pageSettings&&options&&options.data&&options.data.settings){
			var settings=Object.assign({},options.data.settings);
			if(Object.prototype.hasOwnProperty.call(settings,'customCss'))settings.customCSS=settings.customCss;
			delete settings.customCss;
			options=Object.assign({},options,{data:Object.assign({},options.data,{settings:settings})});
		}
		return next(options).then(function(response){
			if(pageSettings&&response&&response.settings&&Object.prototype.hasOwnProperty.call(response.settings,'customCSS')){
				response=Object.assign({},response,{settings:Object.assign({},response.settings,{customCss:response.settings.customCSS})});
			}
			if(builderContext&&response&&response.global){
				var global=Object.assign({},response.global);
				var spacing=Object.assign({},global.spacing||{});
				if(global.layout&&global.layout.gridGap&&!spacing.gridGap)spacing.gridGap=global.layout.gridGap;
				global.spacing=spacing;
				response=Object.assign({},response,{global:global});
			}
			return response;
		});
	});
}
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var scheduled=false;
function pruneUnsupportedPageOptions(){
	var panels=Array.prototype.slice.call(root.querySelectorAll('.cc-builder-panel'));
	var panel=panels.find(function(item){var head=item.querySelector('.cc-builder-panel-head strong');return head&&head.textContent.trim()==='Page Settings';});
	if(!panel)return;
	Array.prototype.slice.call(panel.querySelectorAll('.cc-builder-field')).forEach(function(field){
		var label=field.querySelector('label'),select=field.querySelector('select');
		if(!label||!select)return;
		var name=label.textContent.trim();
		if(name==='Page title'){var inherit=select.querySelector('option[value="inherit"]');if(inherit)inherit.remove();}
		if(name==='Content root'){var content=select.querySelector('option[value="content"]');if(content)content.remove();}
	});
}
function schedule(){if(scheduled)return;scheduled=true;window.requestAnimationFrame(function(){scheduled=false;pruneUnsupportedPageOptions();});}
schedule();
if(window.MutationObserver)new MutationObserver(schedule).observe(root,{childList:true,subtree:true});
})(window,document);
JS;
		wp_add_inline_script( 'cresco-canvas-website-builder', $script, 'before' );
	}
}
