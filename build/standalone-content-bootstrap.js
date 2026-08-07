( function ( wp, window ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch || typeof wp.apiFetch.use !== 'function' ) return;

	var settings = window.crescoCanvasStandaloneSettings || {};
	var apiPath = String( settings.apiPath || '' ).split( '?' )[ 0 ];
	var initialContent = typeof settings.initialContent === 'string' ? settings.initialContent : '';
	var initialTitle = typeof settings.initialTitle === 'string' ? settings.initialTitle : '';
	var initialStatus = typeof settings.initialStatus === 'string' ? settings.initialStatus : '';

	if ( ! apiPath ) return;

	wp.apiFetch.use( function ( options, next ) {
		return next( options ).then( function ( response ) {
			var path = options && options.path ? String( options.path ).split( '?' )[ 0 ] : '';
			var method = String( options && options.method || 'GET' ).toUpperCase();
			if ( path !== apiPath || method !== 'GET' || ! response || typeof response !== 'object' ) return response;

			var content = response.content && typeof response.content === 'object' ? response.content : {};
			if ( typeof content.raw !== 'string' || ( ! content.raw && initialContent ) ) {
				response.content = Object.assign( {}, content, { raw: initialContent } );
			}

			var title = response.title && typeof response.title === 'object' ? response.title : {};
			if ( typeof title.raw !== 'string' || ( ! title.raw && initialTitle ) ) {
				response.title = Object.assign( {}, title, { raw: initialTitle } );
			}

			if ( ! response.status && initialStatus ) response.status = initialStatus;
			return response;
		} );
	} );
} )( window.wp, window );