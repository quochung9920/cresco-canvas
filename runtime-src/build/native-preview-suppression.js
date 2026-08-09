( function () {
	'use strict';

	var wp = window.wp || {};
	var SUPPRESSED_CLASS = 'cresco-canvas-native-preview-suppressed';
	var TOOL_HOST_CLASS = 'cresco-canvas-header-tools-left';
	var MOVED_TOOL_CLASS = 'cresco-canvas-header-tool-moved';
	var MODE_NAV_CLASS = 'cc-builder-mode-nav';
	var EXPERIENCE_CLASS = 'cresco-canvas-elementor-experience';
	var DRAGGING_CLASS = 'cc-elementor-dragging';
	var DROP_TARGET_CLASS = 'cc-elementor-drop-target';
	var OVERLAY_CLASS = 'cc-elementor-drop-overlay';
	var IFRAME_STYLE_ID = 'cresco-canvas-elementor-frame-styles';
	var DRAG_MIME = 'application/x-cresco-canvas-element';
	var HEADER_SELECTOR = [
		'.edit-post-header__settings',
		'.editor-header__settings',
		'.edit-site-header__actions',
		'.interface-interface-skeleton__header',
	].join( ',' );
	var RIGHT_TOOLBAR_SELECTOR = [
		'.edit-post-header__settings',
		'.editor-header__settings',
		'.edit-site-header__actions',
	].join( ',' );
	var LEFT_TOOLBAR_SELECTORS = [
		'.editor-header__toolbar',
		'.edit-post-header__toolbar',
		'.edit-site-header__toolbar',
		'.interface-interface-skeleton__header .editor-header__toolbar',
	];
	var DIRECT_PREVIEW_SELECTORS = [
		'.editor-post-preview',
		'.editor-preview-dropdown',
		'.editor-post-preview__dropdown',
	].join( ',' );
	var EXCLUDED_SELECTOR = [
		'.cc-canvas-stage-toolbar-root',
		'.cresco-canvas-preview-sidebar',
		'.cresco-canvas-sidebar',
	].join( ',' );
	var TOOL_CLASS_SELECTORS = [
		'.edit-post-global-styles__toggle',
		'.edit-site-global-styles__toggle',
		'.editor-global-styles__toggle',
		'.edit-post-header__settings-sidebar-toggle',
		'.editor-header__settings-sidebar-toggle',
	];
	var PROTECTED_ACTION_SELECTORS = [
		'.editor-post-save-draft',
		'.editor-post-publish-button',
		'.editor-post-publish-panel__toggle',
		'.editor-post-switch-to-draft',
		'.editor-post-saved-state',
		'.editor-header__save-button',
		'.editor-header__publish-button',
		'.edit-site-save-button',
		'.edit-site-save-button__button',
		'.interface-pinned-items .components-dropdown-menu__toggle',
	].join( ',' );
	var MODES = [
		{ id: 'widgets', label: 'Widgets', icon: 'screenoptions', target: 'cresco-canvas/cresco-canvas-settings' },
		{ id: 'edit', label: 'Edit', icon: 'edit', target: 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector' },
		{ id: 'global', label: 'Global', icon: 'admin-appearance', target: 'cresco-canvas-design-system/cresco-canvas-design-system' },
	];
	var scheduled = false;
	var lastSelectedClientId = null;
	var lastActiveSidebar = '';
	var iframeDocuments = new Map();
	var draggingLabel = '';

	function controlLabel( control ) {
		return [
			control.getAttribute( 'aria-label' ),
			control.getAttribute( 'title' ),
			control.getAttribute( 'data-tooltip' ),
			control.textContent,
		]
			.filter( Boolean )
			.join( ' ' )
			.trim()
			.toLocaleLowerCase();
	}

	function isVisible( control ) {
		return Boolean( control && control.getClientRects().length && control.getAttribute( 'aria-hidden' ) !== 'true' );
	}

	function isPreviewControl( control ) {
		if ( ! control || ! control.closest( HEADER_SELECTOR ) || control.closest( EXCLUDED_SELECTOR ) ) {
			return false;
		}
		return /(^|\s)(preview|xem trước|xem truoc|xem thử|xem thu)(\s|$)/i.test( controlLabel( control ) );
	}

	function isProtectedAction( control ) {
		if ( ! control ) return true;
		if ( control.matches( PROTECTED_ACTION_SELECTORS ) || control.closest( PROTECTED_ACTION_SELECTORS ) ) return true;
		var label = controlLabel( control );
		return /(^|\s)(saved|saving|save|publish|published|update|submit|switch to draft|more|more options|options|preferences|command palette|đã lưu|dang lưu|đang lưu|lưu|xuất bản|xuat ban|đăng|dang|cập nhật|cap nhat|tùy chọn|tuy chon)(\s|$)/i.test( label );
	}

	function isRightToolbarIcon( control ) {
		if ( ! control || ! control.closest( RIGHT_TOOLBAR_SELECTOR ) || ! isVisible( control ) ) return false;
		if ( isProtectedAction( control ) || isPreviewControl( control ) ) return false;
		return Boolean( control.querySelector( 'svg, .dashicons, [class*="icon"]' ) );
	}

	function isMovableTool( control ) {
		if ( ! control || control.closest( '.' + TOOL_HOST_CLASS ) || control.closest( EXCLUDED_SELECTOR ) ) {
			return false;
		}
		if ( isProtectedAction( control ) ) return false;
		if ( TOOL_CLASS_SELECTORS.some( function ( selector ) { return control.matches( selector ); } ) ) return true;
		var label = controlLabel( control );
		if ( /(^|\s)(cresco canvas|cresco preview|global design|settings|setting|styles|global styles|design system|cài đặt|cai dat|kiểu dáng|kieu dang|thiết kế toàn cục|thiet ke toan cuc)(\s|$)/i.test( label ) ) return true;
		return isRightToolbarIcon( control );
	}

	function suppressNode( node ) {
		if ( ! node || node.closest( EXCLUDED_SELECTOR ) ) return;
		var wrapper = node.closest( '.components-dropdown, .components-dropdown-menu, .editor-post-preview, .editor-preview-dropdown, .editor-post-preview__dropdown' ) || node;
		if ( wrapper.classList.contains( SUPPRESSED_CLASS ) ) return;
		wrapper.classList.add( SUPPRESSED_CLASS );
		wrapper.hidden = true;
		wrapper.setAttribute( 'aria-hidden', 'true' );
		wrapper.querySelectorAll( 'button, a, [tabindex]' ).forEach( function ( control ) { control.tabIndex = -1; } );
	}

	function suppressNativePreview() {
		document.querySelectorAll( DIRECT_PREVIEW_SELECTORS ).forEach( suppressNode );
		document.querySelectorAll( HEADER_SELECTOR ).forEach( function ( header ) {
			header.querySelectorAll( 'button, a' ).forEach( function ( control ) {
				if ( isPreviewControl( control ) ) suppressNode( control );
			} );
		} );
	}

	function findLeftToolbar() {
		for ( var index = 0; index < LEFT_TOOLBAR_SELECTORS.length; index += 1 ) {
			var toolbar = document.querySelector( LEFT_TOOLBAR_SELECTORS[ index ] );
			if ( toolbar ) return toolbar;
		}
		return null;
	}

	function ensureToolHost() {
		var toolbar = findLeftToolbar();
		if ( ! toolbar ) return null;
		var host = toolbar.querySelector( ':scope > .' + TOOL_HOST_CLASS );
		if ( host ) return host;
		host = document.createElement( 'div' );
		host.className = TOOL_HOST_CLASS;
		host.setAttribute( 'role', 'group' );
		host.setAttribute( 'aria-label', 'Cresco editor tools' );
		toolbar.appendChild( host );
		return host;
	}

	function movableWrapper( control ) {
		var dropdown = control.closest( '.components-dropdown' );
		if ( dropdown && dropdown.querySelectorAll( 'button, a, [role="button"]' ).length === 1 ) return dropdown;
		return control;
	}

	function relocateHeaderTools() {
		var host = ensureToolHost();
		if ( ! host ) return;
		var candidates = [];
		document.querySelectorAll( HEADER_SELECTOR ).forEach( function ( header ) {
			header.querySelectorAll( 'button, a, [role="button"]' ).forEach( function ( control ) {
				if ( isMovableTool( control ) ) candidates.push( control );
			} );
		} );
		TOOL_CLASS_SELECTORS.forEach( function ( selector ) {
			document.querySelectorAll( selector ).forEach( function ( control ) {
				if ( isMovableTool( control ) ) candidates.push( control );
			} );
		} );
		var seen = new Set();
		candidates.forEach( function ( control ) {
			var node = movableWrapper( control );
			if ( ! node || seen.has( node ) || node.closest( '.' + TOOL_HOST_CLASS ) ) return;
			seen.add( node );
			node.classList.add( MOVED_TOOL_CLASS );
			host.appendChild( node );
		} );
	}

	function activeSidebarName() {
		if ( ! wp.data || ! wp.data.select ) return '';
		var store = wp.data.select( 'core/edit-post' );
		if ( store && typeof store.getActiveGeneralSidebarName === 'function' ) return store.getActiveGeneralSidebarName() || '';
		store = wp.data.select( 'core/edit-site' );
		return store && typeof store.getActiveComplementaryArea === 'function' ? store.getActiveComplementaryArea() || '' : '';
	}

	function openSidebar( target ) {
		if ( ! wp.data || ! wp.data.dispatch ) return;
		var dispatch = wp.data.dispatch( 'core/edit-post' );
		if ( dispatch && typeof dispatch.openGeneralSidebar === 'function' ) {
			dispatch.openGeneralSidebar( target );
			return;
		}
		dispatch = wp.data.dispatch( 'core/edit-site' );
		if ( dispatch && typeof dispatch.openGeneralSidebar === 'function' ) dispatch.openGeneralSidebar( target );
	}

	function modeFromSidebar( value ) {
		if ( value.indexOf( 'widget-inspector' ) !== -1 ) return 'edit';
		if ( value.indexOf( 'design-system' ) !== -1 ) return 'global';
		return 'widgets';
	}

	function findVisibleCrescoArea() {
		var areas = document.querySelectorAll( '.interface-complementary-area' );
		for ( var index = 0; index < areas.length; index += 1 ) {
			var area = areas[ index ];
			if ( ! isVisible( area ) ) continue;
			if ( area.querySelector( '.cresco-canvas-sidebar, .cresco-canvas-widget-inspector, .cresco-canvas-design-system, [class*="cresco-canvas-"]' ) ) return area;
		}
		return null;
	}

	function ensureModeNav() {
		var area = findVisibleCrescoArea();
		if ( ! area ) return;
		var nav = area.querySelector( ':scope > .' + MODE_NAV_CLASS );
		if ( ! nav ) {
			nav = document.createElement( 'nav' );
			nav.className = MODE_NAV_CLASS;
			nav.setAttribute( 'aria-label', 'Cresco builder modes' );
			MODES.forEach( function ( mode ) {
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'cc-builder-mode-nav__button';
				button.dataset.ccBuilderMode = mode.id;
				button.innerHTML = '<span class="dashicons dashicons-' + mode.icon + '" aria-hidden="true"></span><span>' + mode.label + '</span>';
				button.addEventListener( 'click', function () { openSidebar( mode.target ); } );
				nav.appendChild( button );
			} );
			var panel = area.querySelector( ':scope > .components-panel' );
			if ( panel ) area.insertBefore( nav, panel );
			else area.appendChild( nav );
		}
		var activeMode = modeFromSidebar( activeSidebarName() );
		nav.querySelectorAll( '[data-cc-builder-mode]' ).forEach( function ( button ) {
			var active = button.dataset.ccBuilderMode === activeMode;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
	}

	function selectedClientId() {
		if ( ! wp.data || ! wp.data.select ) return null;
		var store = wp.data.select( 'core/block-editor' );
		return store && typeof store.getSelectedBlockClientId === 'function' ? store.getSelectedBlockClientId() : null;
	}

	function selectedBlockTitle( clientId ) {
		if ( ! clientId || ! wp.data || ! wp.data.select ) return '';
		var store = wp.data.select( 'core/block-editor' );
		var block = store && typeof store.getBlock === 'function' ? store.getBlock( clientId ) : null;
		if ( ! block ) return '';
		var type = wp.blocks && typeof wp.blocks.getBlockType === 'function' ? wp.blocks.getBlockType( block.name ) : null;
		return type && type.title ? String( type.title ) : String( block.name || 'Widget' );
	}

	function cssEscape( value ) {
		if ( window.CSS && typeof window.CSS.escape === 'function' ) return window.CSS.escape( value );
		return String( value ).replace( /["\\]/g, '\\$&' );
	}

	function frameStyles() {
		return [
			'html.cc-elementor-canvas-document .block-editor-block-list__block[data-cc-widget-selected="true"]{position:relative;outline:2px solid #6c5ce7!important;outline-offset:2px;box-shadow:0 0 0 1px rgba(255,255,255,.9)!important}',
			'html.cc-elementor-canvas-document .block-editor-block-list__block[data-cc-widget-selected="true"]:before{content:attr(data-cc-widget-label);position:absolute;z-index:9999;inset-block-start:-24px;inset-inline-start:-2px;display:block;max-width:220px;padding:4px 8px;border-radius:5px 5px 0 0;background:#6c5ce7;color:#fff;font:600 11px/1.2 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none}',
			'html.cc-elementor-canvas-document .block-editor-block-list__block.' + DROP_TARGET_CLASS + '{outline:2px dashed #6c5ce7!important;outline-offset:4px;background:rgba(108,92,231,.045)!important}',
			'html.cc-elementor-canvas-document .block-editor-block-list__layout{padding-block-start:22px}',
		].join( '' );
	}

	function injectFrameStyles( targetDocument ) {
		if ( ! targetDocument || ! targetDocument.head ) return;
		targetDocument.documentElement.classList.add( 'cc-elementor-canvas-document' );
		if ( targetDocument.getElementById( IFRAME_STYLE_ID ) ) return;
		var style = targetDocument.createElement( 'style' );
		style.id = IFRAME_STYLE_ID;
		style.textContent = frameStyles();
		targetDocument.head.appendChild( style );
	}

	function editorDocuments() {
		var documents = [ document ];
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) {
			try {
				if ( iframe.contentDocument ) documents.push( iframe.contentDocument );
			} catch ( error ) {}
		} );
		return documents;
	}

	function annotateSelection() {
		var clientId = selectedClientId();
		var label = selectedBlockTitle( clientId );
		editorDocuments().forEach( function ( targetDocument ) {
			injectFrameStyles( targetDocument );
			targetDocument.querySelectorAll( '[data-cc-widget-selected="true"]' ).forEach( function ( node ) {
				node.removeAttribute( 'data-cc-widget-selected' );
				node.removeAttribute( 'data-cc-widget-label' );
			} );
			if ( ! clientId ) return;
			var node = targetDocument.querySelector( '[data-block="' + cssEscape( clientId ) + '"]' );
			if ( node ) {
				node.setAttribute( 'data-cc-widget-selected', 'true' );
				node.setAttribute( 'data-cc-widget-label', label || 'Widget' );
			}
		} );
	}

	function syncBuilderState() {
		var currentClientId = selectedClientId();
		var currentSidebar = activeSidebarName();
		if ( currentClientId !== lastSelectedClientId ) {
			lastSelectedClientId = currentClientId;
			if ( currentClientId && currentSidebar.indexOf( 'design-system' ) === -1 ) {
				openSidebar( 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector' );
				currentSidebar = 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector';
			}
		}
		lastActiveSidebar = currentSidebar;
		ensureModeNav();
		annotateSelection();
	}

	function ensureDropOverlay() {
		var stage = document.querySelector( '.cresco-canvas-stage-host' );
		if ( ! stage ) return null;
		var overlay = stage.querySelector( ':scope > .' + OVERLAY_CLASS );
		if ( overlay ) return overlay;
		overlay = document.createElement( 'div' );
		overlay.className = OVERLAY_CLASS;
		overlay.setAttribute( 'aria-hidden', 'true' );
		stage.appendChild( overlay );
		return overlay;
	}

	function clearDropTargets() {
		editorDocuments().forEach( function ( targetDocument ) {
			targetDocument.querySelectorAll( '.' + DROP_TARGET_CLASS ).forEach( function ( node ) { node.classList.remove( DROP_TARGET_CLASS ); } );
		} );
	}

	function startElementDrag( event, card ) {
		draggingLabel = '';
		var labelNode = card.querySelector( '.cc-element-card__insert span:last-child' );
		draggingLabel = labelNode ? labelNode.textContent.trim() : 'Widget';
		document.body.classList.add( DRAGGING_CLASS );
		card.classList.add( 'is-dragging' );
		var overlay = ensureDropOverlay();
		if ( overlay ) overlay.textContent = 'Drop ' + draggingLabel + ' anywhere on the canvas';
		if ( event.dataTransfer ) {
			var ghost = card.cloneNode( true );
			ghost.className += ' cc-elementor-drag-ghost';
			document.body.appendChild( ghost );
			try { event.dataTransfer.setDragImage( ghost, 42, 42 ); } catch ( error ) {}
			window.setTimeout( function () { if ( ghost.parentNode ) ghost.parentNode.removeChild( ghost ); }, 0 );
		}
	}

	function finishElementDrag() {
		document.body.classList.remove( DRAGGING_CLASS );
		document.querySelectorAll( '.cc-element-card.is-dragging' ).forEach( function ( card ) { card.classList.remove( 'is-dragging' ); } );
		clearDropTargets();
		draggingLabel = '';
	}

	function markDropTarget( event ) {
		if ( ! document.body.classList.contains( DRAGGING_CLASS ) ) return;
		clearDropTargets();
		var target = event.target && event.target.closest ? event.target.closest( '[data-block]' ) : null;
		if ( target ) target.classList.add( DROP_TARGET_CLASS );
	}

	function attachDocumentDragListeners( targetDocument ) {
		if ( ! targetDocument || iframeDocuments.has( targetDocument ) ) return;
		iframeDocuments.set( targetDocument, true );
		injectFrameStyles( targetDocument );
		targetDocument.addEventListener( 'dragover', markDropTarget, true );
		targetDocument.addEventListener( 'drop', finishElementDrag, true );
	}

	function scanIframes() {
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) {
			function attach() {
				try { attachDocumentDragListeners( iframe.contentDocument ); annotateSelection(); } catch ( error ) {}
			}
			iframe.removeEventListener( 'load', attach );
			iframe.addEventListener( 'load', attach );
			attach();
		} );
	}

	function applyHeaderAdapter() {
		document.body.classList.add( EXPERIENCE_CLASS );
		suppressNativePreview();
		relocateHeaderTools();
		ensureModeNav();
		scanIframes();
		annotateSelection();
	}

	function schedule() {
		if ( scheduled ) return;
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			applyHeaderAdapter();
		} );
	}

	function start() {
		applyHeaderAdapter();
		attachDocumentDragListeners( document );
		document.addEventListener( 'dragstart', function ( event ) {
			var card = event.target && event.target.closest ? event.target.closest( '.cc-element-card' ) : null;
			if ( ! card ) return;
			if ( event.dataTransfer && Array.from( event.dataTransfer.types || [] ).indexOf( DRAG_MIME ) === -1 ) return;
			startElementDrag( event, card );
		}, true );
		document.addEventListener( 'dragend', finishElementDrag, true );
		document.addEventListener( 'drop', finishElementDrag, true );
		var observer = new MutationObserver( schedule );
		observer.observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'aria-label', 'title', 'aria-hidden' ] } );
		window.addEventListener( 'resize', schedule, { passive: true } );
		if ( wp.data && typeof wp.data.subscribe === 'function' ) wp.data.subscribe( syncBuilderState );
		syncBuilderState();
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )();