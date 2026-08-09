( function ( window, document ) {
	'use strict';

	function selectedDevice( toolbar ) {
		var active = toolbar ? toolbar.querySelector( '.cc-standalone-devices button.is-active' ) : null;
		var label = active ? String( active.textContent || '' ).trim().toLowerCase() : '';
		if ( label === 'widescreen' ) return 'wide';
		if ( label === 'desktop' ) return 'desktop';
		if ( label === 'laptop' ) return 'laptop';
		if ( label === 'tablet' ) return 'tablet';
		if ( label === 'mobile' ) return 'mobile';
		return 'wide';
	}

	function updateViewport() {
		var stage = document.querySelector( '.cc-standalone-stage' );
		var frame = document.querySelector( '.cc-standalone-frame' );
		var canvas = document.querySelector( '.cc-session-canvas' );
		var toolbar = document.querySelector( '.cc-standalone-viewport-toolbar' );
		if ( ! stage || ! frame || ! canvas || ! toolbar ) return;

		var device = selectedDevice( toolbar );
		var select = toolbar.querySelector( 'select' );
		var zoomValue = select ? String( select.value || 'fit' ) : 'fit';
		var frameWidth = parseFloat( frame.style.width ) || frame.getBoundingClientRect().width || stage.clientWidth;
		var scale = 1;

		if ( zoomValue === 'fit' ) {
			scale = Math.min( 1, Math.max( 0.2, stage.clientWidth / Math.max( 1, frameWidth ) ) );
		} else {
			scale = Math.max( 0.25, Math.min( 1.5, Number( zoomValue ) / 100 || 1 ) );
		}

		stage.setAttribute( 'data-cresco-device', device );
		frame.style.transform = 'none';
		frame.style.zoom = String( scale );

		var logicalHeight = Math.max( 1, stage.clientHeight / scale );
		frame.style.minHeight = logicalHeight + 'px';
		canvas.style.minHeight = logicalHeight + 'px';
	}

	function scheduleUpdate() {
		window.requestAnimationFrame( updateViewport );
	}

	function boot() {
		var stage = document.querySelector( '.cc-standalone-stage' );
		if ( ! stage ) {
			window.setTimeout( boot, 80 );
			return;
		}

		updateViewport();
		if ( window.ResizeObserver ) {
			new window.ResizeObserver( scheduleUpdate ).observe( stage );
		}
		document.addEventListener( 'click', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-standalone-devices' ) ) scheduleUpdate();
		} );
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-standalone-viewport-toolbar' ) ) scheduleUpdate();
		} );
	}

	boot();
} )( window, document );
