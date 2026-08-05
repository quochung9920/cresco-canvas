( function () {
	'use strict';

	var SUPPRESSED_CLASS = 'cresco-canvas-native-preview-suppressed';
	var HEADER_SELECTOR = [
		'.edit-post-header__settings',
		'.editor-header__settings',
		'.edit-site-header__actions',
		'.interface-interface-skeleton__header',
	].join( ',' );
	var DIRECT_SELECTORS = [
		'.editor-post-preview',
		'.editor-preview-dropdown',
		'.editor-post-preview__dropdown',
	].join( ',' );
	var EXCLUDED_SELECTOR = [
		'.cc-canvas-stage-toolbar-root',
		'.cresco-canvas-preview-sidebar',
		'.cresco-canvas-sidebar',
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

	function isPreviewControl( control ) {
		if ( ! control || ! control.closest( HEADER_SELECTOR ) || control.closest( EXCLUDED_SELECTOR ) ) {
			return false;
		}
		var label = controlLabel( control );
		return /(^|\s)(preview|xem trước|xem truoc|xem thử|xem thu)(\s|$)/i.test( label );
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
		document.querySelectorAll( DIRECT_SELECTORS ).forEach( suppressNode );
		document.querySelectorAll( HEADER_SELECTOR ).forEach( function ( header ) {
			header.querySelectorAll( 'button, a' ).forEach( function ( control ) {
				if ( isPreviewControl( control ) ) {
					suppressNode( control );
				}
			} );
		} );
	}

	function schedule() {
		if ( scheduled ) {
			return;
		}
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			suppressNativePreview();
		} );
	}

	function start() {
		suppressNativePreview();
		var observer = new MutationObserver( schedule );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
