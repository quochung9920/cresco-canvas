( function () {
	'use strict';

	var SUPPRESSED_CLASS = 'cresco-canvas-native-preview-suppressed';
	var TOOL_HOST_CLASS = 'cresco-canvas-header-tools-left';
	var MOVED_TOOL_CLASS = 'cresco-canvas-header-tool-moved';
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
	var scheduled = false;

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
		if ( TOOL_CLASS_SELECTORS.some( function ( selector ) { return control.matches( selector ); } ) ) {
			return true;
		}
		var label = controlLabel( control );
		if ( /(^|\s)(cresco canvas|cresco preview|global design|settings|setting|styles|global styles|design system|cài đặt|cai dat|kiểu dáng|kieu dang|thiết kế toàn cục|thiet ke toan cuc)(\s|$)/i.test( label ) ) {
			return true;
		}
		/*
		 * Gutenberg versions do not always expose a stable class or accessible
		 * label for utility buttons. Any remaining icon-only utility inside the
		 * right settings group is moved left, while protected save/publish/menu
		 * actions above always stay in place.
		 */
		return isRightToolbarIcon( control );
	}

	function suppressNode( node ) {
		if ( ! node || node.closest( EXCLUDED_SELECTOR ) ) {
			return;
		}
		var wrapper = node.closest( '.components-dropdown, .components-dropdown-menu, .editor-post-preview, .editor-preview-dropdown, .editor-post-preview__dropdown' ) || node;
		if ( wrapper.classList.contains( SUPPRESSED_CLASS ) ) {
			return;
		}
		wrapper.classList.add( SUPPRESSED_CLASS );
		wrapper.hidden = true;
		wrapper.setAttribute( 'aria-hidden', 'true' );
		wrapper.querySelectorAll( 'button, a, [tabindex]' ).forEach( function ( control ) {
			control.tabIndex = -1;
		} );
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
		if ( dropdown && dropdown.querySelectorAll( 'button, a, [role="button"]' ).length === 1 ) {
			return dropdown;
		}
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

	function applyHeaderAdapter() {
		suppressNativePreview();
		relocateHeaderTools();
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
		var observer = new MutationObserver( schedule );
		observer.observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'aria-label', 'title' ] } );
		window.addEventListener( 'resize', schedule, { passive: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
