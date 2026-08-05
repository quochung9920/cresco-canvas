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

	function isPreviewControl( control ) {
		if ( ! control || ! control.closest( HEADER_SELECTOR ) || control.closest( EXCLUDED_SELECTOR ) ) {
			return false;
		}
		return /(^|\s)(preview|xem trước|xem truoc|xem thử|xem thu)(\s|$)/i.test( controlLabel( control ) );
	}

	function isMovableTool( control ) {
		if ( ! control || control.closest( '.' + TOOL_HOST_CLASS ) || control.closest( EXCLUDED_SELECTOR ) ) {
			return false;
		}
		if ( TOOL_CLASS_SELECTORS.some( function ( selector ) { return control.matches( selector ); } ) ) {
			return true;
		}
		var label = controlLabel( control );
		return /(^|\s)(cresco canvas|cresco preview|settings|setting|styles|global styles|design system|cài đặt|cai dat|kiểu dáng|kieu dang)(\s|$)/i.test( label );
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
