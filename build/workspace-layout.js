( function () {
	'use strict';

	var ROOT_CLASS = 'cresco-canvas-three-pane';
	var LEFT_CLASS = 'cresco-canvas-workspace-left';
	var CENTER_CLASS = 'cresco-canvas-workspace-center';
	var RIGHT_CLASS = 'cresco-canvas-workspace-right';
	var ACTIVE_BREAKPOINT = 1180;

	function directChildOf( node, parent ) {
		var current = node;
		while ( current && current.parentElement && current.parentElement !== parent ) {
			current = current.parentElement;
		}
		return current && current.parentElement === parent ? current : null;
	}

	function clearClasses() {
		document.body.classList.remove( ROOT_CLASS );
		document.querySelectorAll( '.' + LEFT_CLASS + ', .' + CENTER_CLASS + ', .' + RIGHT_CLASS ).forEach( function ( node ) {
			node.classList.remove( LEFT_CLASS, CENTER_CLASS, RIGHT_CLASS );
		} );
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
		clearClasses();
		if ( window.innerWidth < ACTIVE_BREAKPOINT ) return;

		var bodyShell = document.querySelector( '.interface-interface-skeleton__body' );
		var crescoPanel = document.querySelector( '.cresco-canvas-sidebar, .cresco-canvas-hub' );
		var editorContent = document.querySelector( '.interface-interface-skeleton__content' );
		var listView = findListView();

		if ( ! bodyShell || ! crescoPanel || ! editorContent ) return;

		var left = directChildOf( crescoPanel, bodyShell );
		var center = directChildOf( editorContent, bodyShell );
		var right = listView ? directChildOf( listView, bodyShell ) : null;

		if ( ! left || ! center || left === center ) return;

		document.body.classList.add( ROOT_CLASS );
		left.classList.add( LEFT_CLASS );
		center.classList.add( CENTER_CLASS );

		if ( right && right !== left && right !== center ) {
			right.classList.add( RIGHT_CLASS );
		}
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
		scheduleLayout();
		var observer = new MutationObserver( scheduleLayout );
		observer.observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'aria-hidden' ] } );
		window.addEventListener( 'resize', scheduleLayout, { passive: true } );
		window.addEventListener( 'orientationchange', scheduleLayout, { passive: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
