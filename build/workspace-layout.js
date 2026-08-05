( function () {
	'use strict';

	var ROOT_CLASS = 'cresco-canvas-three-pane';
	var LEFT_CLASS = 'cresco-canvas-workspace-left';
	var CENTER_CLASS = 'cresco-canvas-workspace-center';
	var RIGHT_CLASS = 'cresco-canvas-workspace-right';
	var LEGACY_RESIZING_CLASS = 'cresco-canvas-workspace-resizing';
	var LEGACY_HANDLE_CLASS = 'cresco-canvas-workspace-resize-handle';
	var LEGACY_STORAGE_KEY = 'crescoCanvas.workspaceLeftWidth';
	var WIDTH_PROPERTY = '--cc-workspace-left-width';
	var ACTIVE_BREAKPOINT = 1180;

	function cleanupLegacyResize() {
		document.body.classList.remove( LEGACY_RESIZING_CLASS );
		document.body.style.removeProperty( WIDTH_PROPERTY );
		document.querySelectorAll( '.' + LEGACY_HANDLE_CLASS ).forEach( function ( handle ) {
			handle.remove();
		} );
		try {
			window.localStorage.removeItem( LEGACY_STORAGE_KEY );
		} catch ( error ) {
			// Storage can be unavailable in hardened browser contexts.
		}
	}

	function directChildOf( node, parent ) {
		var current = node;
		while ( current && current.parentElement && current.parentElement !== parent ) {
			current = current.parentElement;
		}
		return current && current.parentElement === parent ? current : null;
	}

	function removeClassFromOtherNodes( className, activeNode ) {
		document.querySelectorAll( '.' + className ).forEach( function ( node ) {
			if ( node !== activeNode ) node.classList.remove( className );
		} );
	}

	function deactivateLayout() {
		document.body.classList.remove( ROOT_CLASS );
		removeClassFromOtherNodes( LEFT_CLASS, null );
		removeClassFromOtherNodes( CENTER_CLASS, null );
		removeClassFromOtherNodes( RIGHT_CLASS, null );
	}

	function reconcileLayout( left, center, right ) {
		removeClassFromOtherNodes( LEFT_CLASS, left );
		removeClassFromOtherNodes( CENTER_CLASS, center );
		removeClassFromOtherNodes( RIGHT_CLASS, right );
		document.body.classList.add( ROOT_CLASS );
		left.classList.add( LEFT_CLASS );
		center.classList.add( CENTER_CLASS );
		if ( right ) right.classList.add( RIGHT_CLASS );
	}

	function findListView() {
		var selectors = [
			'.edit-post-editor__list-view-container',
			'.editor-list-view-sidebar',
			'.edit-site-editor__list-view-panel',
			'.interface-interface-skeleton__secondary-sidebar',
			'[aria-label="List View"]',
			'[aria-label="List view"]'
		];
		for ( var index = 0; index < selectors.length; index += 1 ) {
			var node = document.querySelector( selectors[ index ] );
			if ( node && node.offsetParent !== null ) return node;
		}
		return null;
	}

	function applyLayout() {
		cleanupLegacyResize();
		if ( window.innerWidth < ACTIVE_BREAKPOINT ) {
			deactivateLayout();
			return;
		}

		var bodyShell = document.querySelector( '.interface-interface-skeleton__body' );
		var crescoPanel = document.querySelector( '.cresco-canvas-sidebar, .cresco-canvas-hub' );
		var editorContent = document.querySelector( '.interface-interface-skeleton__content' );
		var listView = findListView();

		if ( ! bodyShell || ! crescoPanel || ! editorContent ) {
			deactivateLayout();
			return;
		}

		var left = directChildOf( crescoPanel, bodyShell );
		var center = directChildOf( editorContent, bodyShell );
		var right = listView ? directChildOf( listView, bodyShell ) : null;
		if ( right === left || right === center ) right = null;

		if ( ! left || ! center || left === center ) {
			deactivateLayout();
			return;
		}

		reconcileLayout( left, center, right );
	}

	var scheduled = false;
	function scheduleLayout() {
		if ( scheduled ) return;
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			applyLayout();
		} );
	}

	function start() {
		cleanupLegacyResize();
		scheduleLayout();
		var observer = new MutationObserver( scheduleLayout );
		observer.observe( document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class', 'aria-hidden' ]
		} );
		window.addEventListener( 'resize', scheduleLayout, { passive: true } );
		window.addEventListener( 'orientationchange', scheduleLayout, { passive: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
