( function () {
	'use strict';

	var ROOT_CLASS = 'cresco-canvas-three-pane';
	var RESIZING_CLASS = 'cresco-canvas-workspace-resizing';
	var LEFT_CLASS = 'cresco-canvas-workspace-left';
	var CENTER_CLASS = 'cresco-canvas-workspace-center';
	var RIGHT_CLASS = 'cresco-canvas-workspace-right';
	var HANDLE_CLASS = 'cresco-canvas-workspace-resize-handle';
	var STORAGE_KEY = 'crescoCanvas.workspaceLeftWidth';
	var WIDTH_PROPERTY = '--cc-workspace-left-width';
	var ACTIVE_BREAKPOINT = 1180;
	var DEFAULT_WIDTH = 320;
	var MIN_WIDTH = 280;
	var MAX_WIDTH = 520;

	function clamp( value, minimum, maximum ) {
		return Math.min( maximum, Math.max( minimum, Number( value ) || minimum ) );
	}

	function maximumWidth() {
		return Math.max( MIN_WIDTH, Math.min( MAX_WIDTH, window.innerWidth - 640 ) );
	}

	function readWidth() {
		try {
			return clamp( window.localStorage.getItem( STORAGE_KEY ), MIN_WIDTH, maximumWidth() );
		} catch ( error ) {
			return DEFAULT_WIDTH;
		}
	}

	function persistWidth( width ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, String( width ) );
		} catch ( error ) {
			// Storage can be unavailable in hardened browser contexts.
		}
	}

	function applyWidth( width, handle ) {
		var next = clamp( width, MIN_WIDTH, maximumWidth() );
		document.body.style.setProperty( WIDTH_PROPERTY, next + 'px' );
		if ( handle ) {
			handle.setAttribute( 'aria-valuenow', String( next ) );
			handle.setAttribute( 'aria-valuemax', String( maximumWidth() ) );
			handle.title = 'Resize Cresco Canvas tools (' + next + 'px). Double-click to reset.';
		}
		return next;
	}

	function directChildOf( node, parent ) {
		var current = node;
		while ( current && current.parentElement && current.parentElement !== parent ) {
			current = current.parentElement;
		}
		return current && current.parentElement === parent ? current : null;
	}

	function clearClasses() {
		document.body.classList.remove( ROOT_CLASS, RESIZING_CLASS );
		document.querySelectorAll( '.' + LEFT_CLASS + ', .' + CENTER_CLASS + ', .' + RIGHT_CLASS ).forEach( function ( node ) {
			node.classList.remove( LEFT_CLASS, CENTER_CLASS, RIGHT_CLASS );
		} );
	}

	function removeHandles( except ) {
		document.querySelectorAll( '.' + HANDLE_CLASS ).forEach( function ( handle ) {
			if ( ! except || ! except.contains( handle ) ) {
				handle.remove();
			}
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

	function ensureResizeHandle( left ) {
		removeHandles( left );
		var handle = left.querySelector( ':scope > .' + HANDLE_CLASS );
		if ( handle ) {
			applyWidth( readWidth(), handle );
			return;
		}

		handle = document.createElement( 'div' );
		handle.className = HANDLE_CLASS;
		handle.setAttribute( 'role', 'separator' );
		handle.setAttribute( 'aria-label', 'Resize Cresco Canvas tools' );
		handle.setAttribute( 'aria-orientation', 'vertical' );
		handle.setAttribute( 'aria-valuemin', String( MIN_WIDTH ) );
		handle.setAttribute( 'aria-valuemax', String( maximumWidth() ) );
		handle.tabIndex = 0;
		left.appendChild( handle );
		applyWidth( readWidth(), handle );

		function reset() {
			var width = applyWidth( DEFAULT_WIDTH, handle );
			persistWidth( width );
		}

		handle.addEventListener( 'dblclick', reset );
		handle.addEventListener( 'keydown', function ( event ) {
			var current = parseInt( handle.getAttribute( 'aria-valuenow' ), 10 ) || readWidth();
			var step = event.shiftKey ? 20 : 10;
			if ( event.key === 'ArrowLeft' ) current -= step;
			else if ( event.key === 'ArrowRight' ) current += step;
			else if ( event.key === 'Home' ) current = DEFAULT_WIDTH;
			else return;
			event.preventDefault();
			current = applyWidth( current, handle );
			persistWidth( current );
		} );

		handle.addEventListener( 'pointerdown', function ( event ) {
			if ( event.button !== 0 ) return;
			event.preventDefault();
			var startX = event.clientX;
			var startWidth = parseInt( handle.getAttribute( 'aria-valuenow' ), 10 ) || readWidth();
			document.body.classList.add( RESIZING_CLASS );
			handle.setPointerCapture( event.pointerId );

			function move( moveEvent ) {
				applyWidth( startWidth + moveEvent.clientX - startX, handle );
			}

			function finish() {
				var width = parseInt( handle.getAttribute( 'aria-valuenow' ), 10 ) || DEFAULT_WIDTH;
				persistWidth( width );
				document.body.classList.remove( RESIZING_CLASS );
				handle.removeEventListener( 'pointermove', move );
				handle.removeEventListener( 'pointerup', finish );
				handle.removeEventListener( 'pointercancel', finish );
			}

			handle.addEventListener( 'pointermove', move );
			handle.addEventListener( 'pointerup', finish );
			handle.addEventListener( 'pointercancel', finish );
		} );
	}

	function applyLayout() {
		clearClasses();
		if ( window.innerWidth < ACTIVE_BREAKPOINT ) {
			removeHandles();
			return;
		}

		var bodyShell = document.querySelector( '.interface-interface-skeleton__body' );
		var crescoPanel = document.querySelector( '.cresco-canvas-sidebar, .cresco-canvas-hub' );
		var editorContent = document.querySelector( '.interface-interface-skeleton__content' );
		var listView = findListView();

		if ( ! bodyShell || ! crescoPanel || ! editorContent ) {
			removeHandles();
			return;
		}

		var left = directChildOf( crescoPanel, bodyShell );
		var center = directChildOf( editorContent, bodyShell );
		var right = listView ? directChildOf( listView, bodyShell ) : null;

		if ( ! left || ! center || left === center ) {
			removeHandles();
			return;
		}

		document.body.classList.add( ROOT_CLASS );
		left.classList.add( LEFT_CLASS );
		center.classList.add( CENTER_CLASS );
		ensureResizeHandle( left );

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
		applyWidth( readWidth() );
		scheduleLayout();
		var observer = new MutationObserver( scheduleLayout );
		observer.observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'aria-hidden' ] } );
		window.addEventListener( 'resize', function () {
			applyWidth( readWidth(), document.querySelector( '.' + HANDLE_CLASS ) );
			scheduleLayout();
		}, { passive: true } );
		window.addEventListener( 'orientationchange', scheduleLayout, { passive: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
} )();
