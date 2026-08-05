( function () {
	'use strict';

	var LIBRARY_SELECTOR = '.cc-elements-library';
	var LIST_SELECTOR = '.cc-elements-grid-list';
	var CARD_SELECTOR = '.cc-element-card';
	var INSERT_SELECTOR = '.cc-element-card__insert';
	var FAVORITE_SELECTOR = '.cc-element-card__favorite';
	var FILTER_SELECTOR = '.cc-elements-filters';
	var USAGE_KEY = 'crescoCanvas.elementUsageCounts';
	var LEGACY_KEYS = [ 'crescoCanvas.elementFavorites', 'crescoCanvas.elementRecent' ];
	var MAX_COUNT = 1000000;
	var usage = readUsage();
	var originalOrder = new Map();
	var scheduled = false;

	function normalizeKey( value ) {
		return String( value || '' )
			.trim()
			.toLocaleLowerCase()
			.replace( /\s+/g, ' ' )
			.slice( 0, 120 );
	}

	function readUsage() {
		try {
			var parsed = JSON.parse( window.localStorage.getItem( USAGE_KEY ) || '{}' );
			if ( ! parsed || typeof parsed !== 'object' || Array.isArray( parsed ) ) return {};
			var clean = {};
			Object.keys( parsed ).slice( 0, 200 ).forEach( function ( key ) {
				var normalized = normalizeKey( key );
				var count = Math.max( 0, Math.min( MAX_COUNT, parseInt( parsed[ key ], 10 ) || 0 ) );
				if ( normalized && count > 0 ) clean[ normalized ] = count;
			} );
			return clean;
		} catch ( error ) {
			return {};
		}
	}

	function writeUsage() {
		try {
			window.localStorage.setItem( USAGE_KEY, JSON.stringify( usage ) );
		} catch ( error ) {}
	}

	function removeLegacyPreferences() {
		try {
			LEGACY_KEYS.forEach( function ( key ) { window.localStorage.removeItem( key ); } );
		} catch ( error ) {}
	}

	function cardKey( card ) {
		var insertButton = card && card.querySelector( INSERT_SELECTOR );
		if ( ! insertButton ) return '';
		var label = insertButton.querySelector( 'span:last-child' );
		return normalizeKey( label ? label.textContent : insertButton.textContent );
	}

	function cardLabel( card ) {
		var insertButton = card && card.querySelector( INSERT_SELECTOR );
		var label = insertButton && insertButton.querySelector( 'span:last-child' );
		return String( label ? label.textContent : 'Widget' ).trim() || 'Widget';
	}

	function removeObsoleteControls( root ) {
		root.querySelectorAll( FILTER_SELECTOR + ', ' + FAVORITE_SELECTOR ).forEach( function ( node ) { node.remove(); } );
	}

	function sortLibrary( library ) {
		removeObsoleteControls( library );
		var list = library.querySelector( LIST_SELECTOR );
		if ( ! list ) return;
		var cards = Array.from( list.querySelectorAll( ':scope > ' + CARD_SELECTOR ) );
		cards.forEach( function ( card, index ) {
			var key = cardKey( card );
			if ( ! key ) return;
			card.dataset.ccUsageKey = key;
			if ( ! originalOrder.has( key ) ) originalOrder.set( key, index );
		} );
		cards.slice().sort( function ( first, second ) {
			var firstKey = first.dataset.ccUsageKey || cardKey( first );
			var secondKey = second.dataset.ccUsageKey || cardKey( second );
			var usageDifference = ( usage[ secondKey ] || 0 ) - ( usage[ firstKey ] || 0 );
			if ( usageDifference !== 0 ) return usageDifference;
			return ( originalOrder.get( firstKey ) || 0 ) - ( originalOrder.get( secondKey ) || 0 );
		} ).forEach( function ( card, rank ) { card.style.order = String( rank ); } );
	}

	function applyAll() {
		document.querySelectorAll( LIBRARY_SELECTOR ).forEach( sortLibrary );
	}

	function schedule() {
		if ( scheduled ) return;
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			applyAll();
		} );
	}

	function recordUsage( card ) {
		var key = cardKey( card );
		if ( ! key ) return;
		usage[ key ] = Math.min( MAX_COUNT, ( usage[ key ] || 0 ) + 1 );
		writeUsage();
		schedule();
	}

	function startDragFeedback( event, card ) {
		document.body.classList.add( 'cc-elementor-dragging' );
		card.classList.add( 'is-dragging' );
		var overlay = document.querySelector( '.cc-elementor-drop-overlay' );
		if ( overlay ) overlay.textContent = 'Drop ' + cardLabel( card ) + ' anywhere on the canvas';
		if ( ! event.dataTransfer ) return;
		var ghost = card.cloneNode( true );
		ghost.className += ' cc-elementor-drag-ghost';
		document.body.appendChild( ghost );
		try { event.dataTransfer.setDragImage( ghost, 42, 42 ); } catch ( error ) {}
		window.setTimeout( function () {
			if ( ghost.parentNode ) ghost.parentNode.removeChild( ghost );
		}, 0 );
	}

	function start() {
		removeLegacyPreferences();
		applyAll();
		document.addEventListener( 'click', function ( event ) {
			var insertButton = event.target && event.target.closest ? event.target.closest( INSERT_SELECTOR ) : null;
			if ( ! insertButton ) return;
			var card = insertButton.closest( CARD_SELECTOR );
			if ( card ) recordUsage( card );
		}, true );
		document.addEventListener( 'dragstart', function ( event ) {
			var card = event.target && event.target.closest ? event.target.closest( CARD_SELECTOR ) : null;
			if ( ! card ) return;
			recordUsage( card );
			startDragFeedback( event, card );
		}, true );
		var observer = new MutationObserver( schedule );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )();
