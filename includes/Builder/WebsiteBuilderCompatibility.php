<?php
/**
 * Compatibility boundary between the unified Website Builder and retired standalone UI layers.
 *
 * @package CrescoCanvas
 */

namespace CrescoCanvas\Builder;

use CrescoCanvas\Admin\VisualEditor;
use CrescoCanvas\Session\SessionManager;
use CrescoCanvas\Styles\GlobalStyles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebsiteBuilderCompatibility {
	const BUILDER_HANDLE = 'cresco-canvas-website-builder';

	/** Keep the Website Builder as the only UI runtime and normalize legacy contracts. */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'remove_legacy_editor_assets' ), 999 );
		add_action( 'admin_footer', array( $this, 'render_editor_bootstrap_watchdog' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'replace_frontend_compiled_styles' ), 999 );
	}

	public function remove_legacy_editor_assets() {
		$post_id = $this->requested_editor_post_id();
		if ( ! $post_id ) return;

		$this->harden_editor_bootstrap( $post_id );
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
				array( self::BUILDER_HANDLE ),
				$this->asset_version( $control_style )
			);
		}
		if ( is_readable( $control_script ) ) {
			wp_enqueue_script(
				'cresco-canvas-website-builder-controls',
				CRESCO_CANVAS_URL . 'build/website-builder-controls.js',
				array( self::BUILDER_HANDLE ),
				$this->asset_version( $control_script ),
				true
			);
		}
	}

	/**
	 * Make editor startup independent from stale browser cache and from the old
	 * standalone asset graph. This also provides a fallback enqueue if the main
	 * WebsiteBuilder service returned early because one presentation asset was
	 * missing when admin_enqueue_scripts first ran.
	 */
	private function harden_editor_bootstrap( $post_id ) {
		$script_path = CRESCO_CANVAS_PATH . 'build/website-builder-editor.js';
		$style_path  = CRESCO_CANVAS_PATH . 'assets/css/website-builder.css';
		if ( ! is_readable( $script_path ) ) return;

		$dependencies = array( 'wp-element', 'wp-api-fetch', 'wp-i18n' );
		foreach ( $dependencies as $dependency ) wp_enqueue_script( $dependency );

		$scripts = wp_scripts();
		if ( ! isset( $scripts->registered[ self::BUILDER_HANDLE ] ) ) {
			wp_register_script(
				self::BUILDER_HANDLE,
				CRESCO_CANVAS_URL . 'build/website-builder-editor.js',
				$dependencies,
				$this->asset_version( $script_path ),
				true
			);
		}
		if ( isset( $scripts->registered[ self::BUILDER_HANDLE ] ) ) {
			$scripts->registered[ self::BUILDER_HANDLE ]->deps = $dependencies;
			$scripts->registered[ self::BUILDER_HANDLE ]->ver  = $this->asset_version( $script_path );
		}
		wp_enqueue_script( self::BUILDER_HANDLE );

		if ( is_readable( $style_path ) ) {
			$styles = wp_styles();
			$style_was_registered = isset( $styles->registered[ self::BUILDER_HANDLE ] );
			if ( ! $style_was_registered ) {
				wp_register_style(
					self::BUILDER_HANDLE,
					CRESCO_CANVAS_URL . 'assets/css/website-builder.css',
					array( 'wp-components' ),
					$this->asset_version( $style_path )
				);
			}
			if ( isset( $styles->registered[ self::BUILDER_HANDLE ] ) ) {
				$styles->registered[ self::BUILDER_HANDLE ]->ver = $this->asset_version( $style_path );
			}
			wp_enqueue_style( 'wp-components' );
			wp_enqueue_style( self::BUILDER_HANDLE );
			if ( ! $style_was_registered ) {
				wp_add_inline_style( self::BUILDER_HANDLE, GlobalStyles::css( '.cc-builder-canvas' ) . GlobalStyles::visual_css( '.cc-builder-canvas' ) );
			}
		}

		wp_enqueue_media( array( 'post' => $post_id ) );
		$settings = $this->editor_settings( $post_id );
		if ( $settings ) {
			// wp_localize_script is intentionally used here. WordPress prints its
			// data separately from the compatibility "before" script, so a future
			// compatibility syntax failure cannot erase the boot settings as well.
			wp_localize_script( self::BUILDER_HANDLE, 'crescoWebsiteBuilderSettings', $settings );
		}
		wp_set_script_translations( self::BUILDER_HANDLE, 'cresco-canvas' );
	}

	/** Return the same editor settings contract as WebsiteBuilder::enqueue_editor(). */
	private function editor_settings( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) return null;
		return array(
			'postId'              => (int) $post_id,
			'postTitle'           => (string) $post->post_title,
			'sessionPath'         => '/cresco-canvas/v1/website-builder/session/' . $post_id,
			'validatePath'        => '/cresco-canvas/v1/website-builder/session/validate',
			'contextPath'         => '/cresco-canvas/v1/website-builder/context/' . $post_id,
			'optionsPath'         => '/cresco-canvas/v1/website-builder/options',
			'componentsPath'      => '/cresco-canvas/v1/website-builder/components',
			'pageSettingsPath'    => '/cresco-canvas/v1/page-settings/' . $post_id,
			'settingsPath'        => '/cresco-canvas/v1/settings',
			'historyPath'         => '/cresco-canvas/v1/history/' . $post_id,
			'themeTemplatesPath'  => '/cresco-canvas/v1/theme-templates',
			'themeOptionsPath'    => '/cresco-canvas/v1/theme-builder/options',
			'previewUrl'          => get_preview_post_link( $post_id ),
			'adminPagesUrl'       => admin_url( 'edit.php?post_type=page' ),
			'widgetCatalog'       => WidgetCatalog::all(),
			'previewWidths'       => array( 'wide' => 1920, 'desktop' => 1440, 'laptop' => 1366, 'tablet' => 768, 'mobile' => 390 ),
			'canManageGlobal'     => current_user_can( 'edit_theme_options' ),
			'canManageComponents' => current_user_can( 'edit_pages' ),
			'builderVersion'      => WebsiteBuilder::BUILDER_VERSION,
			'pluginVersion'       => CRESCO_CANVAS_VERSION,
		);
	}

	/** Build a content-derived asset version so changing a runtime always busts caches. */
	private function asset_version( $path ) {
		$hash = is_readable( $path ) ? hash_file( 'sha256', $path ) : false;
		return CRESCO_CANVAS_VERSION . ( is_string( $hash ) && '' !== $hash ? '-' . substr( $hash, 0, 12 ) : '' );
	}

	/**
	 * Last-resort browser bootstrap recovery. A healthy Website Builder replaces
	 * the legacy loading shell immediately, before REST requests finish. If that
	 * never happens, retry the self-contained runtime once with a unique URL and
	 * replace the endless spinner with an actionable diagnostic on failure.
	 */
	public function render_editor_bootstrap_watchdog() {
		$post_id = $this->requested_editor_post_id();
		if ( ! $post_id ) return;
		$script_path = CRESCO_CANVAS_PATH . 'build/website-builder-editor.js';
		$settings    = $this->editor_settings( $post_id );
		$settings_js = wp_json_encode( $settings ?: array() );
		if ( ! is_readable( $script_path ) || ! is_string( $settings_js ) ) {
			$message = ! is_readable( $script_path )
				? __( 'Website Builder runtime is missing. Reinstall or rebuild Cresco Canvas.', 'cresco-canvas' )
				: __( 'Website Builder settings could not be encoded.', 'cresco-canvas' );
			$this->print_bootstrap_failure_script( $message );
			return;
		}

		$runtime_url = add_query_arg(
			array(
				'ver'          => $this->asset_version( $script_path ),
				'cresco-retry' => '1',
			),
			CRESCO_CANVAS_URL . 'build/website-builder-editor.js'
		);
		$runtime_js = wp_json_encode( esc_url_raw( $runtime_url ) );
		$script = <<<JS
(function(window,document){
'use strict';
var root=document.getElementById('cresco-canvas-standalone-editor');
if(!root)return;
var fallbackSettings={$settings_js};
if(!window.crescoWebsiteBuilderSettings||!window.crescoWebsiteBuilderSettings.postId){window.crescoWebsiteBuilderSettings=fallbackSettings;}
var runtimeUrl={$runtime_js};
function mounted(){return !!root.querySelector('.cc-builder-app,.cc-builder-loading');}
function detail(){var missing=[];if(!window.wp)missing.push('window.wp');if(!window.wp||!window.wp.element)missing.push('wp.element');if(!window.wp||!window.wp.apiFetch)missing.push('wp.apiFetch');if(!window.crescoWebsiteBuilderSettings||!window.crescoWebsiteBuilderSettings.postId)missing.push('builder settings');return missing.length?' Missing: '+missing.join(', ')+'.':'';}
function fail(message){if(mounted())return;root.innerHTML='';var box=document.createElement('div');box.className='cc-builder-loading cc-builder-bootstrap-error';box.setAttribute('role','alert');var strong=document.createElement('strong');strong.textContent='Cresco Website Builder could not start.';var copy=document.createElement('span');copy.textContent=message+detail();box.appendChild(strong);box.appendChild(copy);root.appendChild(box);}
function retry(){if(mounted())return;if(root.dataset.crescoBuilderRetry==='1'){fail('The Website Builder runtime loaded but did not mount. Open DevTools Console for the JavaScript error.');return;}root.dataset.crescoBuilderRetry='1';if(!window.wp||!window.wp.element||!window.wp.apiFetch){fail('Required WordPress JavaScript dependencies were not loaded.');return;}var script=document.createElement('script');script.src=runtimeUrl;script.async=false;script.dataset.crescoBuilderRetry='1';script.onload=function(){window.setTimeout(function(){if(!mounted())fail('The Website Builder runtime loaded but did not mount. Open DevTools Console for the JavaScript error.');},800);};script.onerror=function(){fail('The Website Builder runtime request failed: '+runtimeUrl);};document.body.appendChild(script);}
window.setTimeout(retry,1800);
})(window,document);
JS;
		wp_print_inline_script_tag( $script, array( 'id' => 'cresco-website-builder-bootstrap-watchdog' ) );
	}

	private function print_bootstrap_failure_script( $message ) {
		$message_js = wp_json_encode( (string) $message );
		if ( ! is_string( $message_js ) ) return;
		$script = <<<JS
(function(document){var root=document.getElementById('cresco-canvas-standalone-editor');if(!root)return;root.innerHTML='';var box=document.createElement('div');box.className='cc-builder-loading cc-builder-bootstrap-error';box.setAttribute('role','alert');var strong=document.createElement('strong');strong.textContent='Cresco Website Builder could not start.';var copy=document.createElement('span');copy.textContent={$message_js};box.appendChild(strong);box.appendChild(copy);root.appendChild(box);})(document);
JS;
		wp_print_inline_script_tag( $script, array( 'id' => 'cresco-website-builder-bootstrap-failure' ) );
	}

	private function requested_editor_post_id() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		if ( VisualEditor::PAGE_SLUG !== $page || ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return 0;
		return $post_id;
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
		if ( ! wp_script_is( self::BUILDER_HANDLE, 'enqueued' ) ) return;
		$script = <<<'JS'
(function(window,document){
'use strict';
var apiFetch=window.wp&&window.wp.apiFetch;
if(apiFetch&&typeof apiFetch.use==='function'){
	apiFetch.use(function(options,next){
		var path=String(options&&options.path||'');
		var method=String(options&&options.method||'GET').toUpperCase();
		var pageSettings=/\/cresco-canvas\/v1\/page-settings\/\d+$/.test(path);
		var builderContext=/\/cresco-canvas\/v1\/website-builder\/context\/\d+$/.test(path);
		var bootRequest=method==='GET'&&(
			/\/cresco-canvas\/v1\/website-builder\/(session\/\d+|context\/\d+|options|components)$/.test(path)||
			/\/cresco-canvas\/v1\/(page-settings\/\d+|theme-templates|settings)$/.test(path)
		);
		if(pageSettings&&options&&options.data&&options.data.settings){
			var settings=Object.assign({},options.data.settings);
			if(Object.prototype.hasOwnProperty.call(settings,'customCss'))settings.customCSS=settings.customCss;
			delete settings.customCss;
			options=Object.assign({},options,{data:Object.assign({},options.data,{settings:settings})});
		}
		var pending=next(options);
		if(bootRequest){
			pending=Promise.race([pending,new Promise(function(resolve,reject){window.setTimeout(function(){reject(new Error('Website Builder request timed out: '+path));},12000);})]);
		}
		return pending.catch(function(error){
			if(method==='GET'&&/\/cresco-canvas\/v1\/website-builder\/options$/.test(path))return{};
			if(method==='GET'&&/\/cresco-canvas\/v1\/website-builder\/components$/.test(path))return[];
			if(method==='GET'&&/\/cresco-canvas\/v1\/page-settings\/\d+$/.test(path))return{settings:{}};
			if(method==='GET'&&/\/cresco-canvas\/v1\/theme-templates$/.test(path))return[];
			throw error;
		}).then(function(response){
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
		wp_add_inline_script( self::BUILDER_HANDLE, $script, 'before' );
	}
}
