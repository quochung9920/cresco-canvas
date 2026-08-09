( function ( window, document ) {
	'use strict';

	var Cresco = window.CrescoCanvas;
	if ( ! Cresco || ! Cresco.ui ) return;

	var EVENT_NAME = 'cresco-canvas-preview-device-change';
	var syncingFromPreview = false;
	var lastPreviewDevice = '';
	var buttonIndex = { wide: 0, desktop: 1, laptop: 2, tablet: 3, mobile: 4 };

	function deviceForWidth( width ) {
		width = Number( width ) || 1920;
		if ( width < 768 ) return 'mobile';
		if ( width < 1025 ) return 'tablet';
		if ( width < 1440 ) return 'laptop';
		if ( width < 1920 ) return 'desktop';
		return 'wide';
	}

	function foundationDevice( detail ) {
		if ( detail && detail.device === 'custom' ) return deviceForWidth( detail.width );
		if ( detail && detail.device === 'widescreen' ) return 'wide';
		return detail && buttonIndex[ detail.device ] !== undefined ? detail.device : 'wide';
	}

	function previewId( device ) {
		return device === 'wide' ? 'widescreen' : device;
	}

	function selectViewportButton( device ) {
		var toolbar = document.querySelector( '.cc-canvas-stage-toolbar__devices' );
		if ( ! toolbar || buttonIndex[ device ] === undefined ) return false;
		var buttons = toolbar.querySelectorAll( 'button' );
		var button = buttons[ buttonIndex[ device ] ];
		if ( ! button || button.getAttribute( 'aria-pressed' ) === 'true' ) return Boolean( button );
		button.click();
		return true;
	}

	window.addEventListener( EVENT_NAME, function ( event ) {
		var device = foundationDevice( event.detail || {} );
		lastPreviewDevice = device;
		syncingFromPreview = true;
		Cresco.ui.setState( { device: device } );
		syncingFromPreview = false;
	} );

	Cresco.ui.subscribe( function ( state ) {
		if ( syncingFromPreview || ! state || ! state.device || state.device === lastPreviewDevice ) return;
		var desired = previewId( state.device );
		if ( desired && selectViewportButton( state.device ) ) lastPreviewDevice = state.device;
	} );

	var observer = new MutationObserver( function () {
		var state = Cresco.ui.getState();
		if ( state && state.device && state.device !== lastPreviewDevice ) selectViewportButton( state.device );
	} );
	observer.observe( document.documentElement, { childList: true, subtree: true } );
} )( window, document );
