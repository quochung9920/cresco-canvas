( function () {
	'use strict';
	function filters( root ) {
		var data = { search: '', tax: {} };
		var search = root.querySelector( 'input[type="search"]' );
		if ( search ) data.search = search.value || '';
		root.querySelectorAll( 'select[data-taxonomy]' ).forEach( function ( select ) {
			var value = select.value || '';
			if ( value ) data.tax[ select.getAttribute( 'data-taxonomy' ) ] = [ value ];
		} );
		return data;
	}
	function chips( root ) {
		var host = root.querySelector( '.cresco-filterable-loop__active-filters' );
		if ( ! host ) {
			host = document.createElement( 'div' );
			host.className = 'cresco-filterable-loop__active-filters';
			var controls = root.querySelector( '.cresco-filterable-loop__controls' );
			if ( controls ) controls.after( host );
		}
		host.innerHTML = '';
		var active = [];
		var search = root.querySelector( 'input[type="search"]' );
		if ( search && search.value ) active.push( { label: search.value, target: search } );
		root.querySelectorAll( 'select[data-taxonomy]' ).forEach( function ( select ) {
			if ( select.value ) active.push( { label: select.options[ select.selectedIndex ].text.replace( /\s+\(\d+\)$/, '' ), target: select } );
		} );
		active.forEach( function ( item ) {
			var button = document.createElement( 'button' );
			button.type = 'button'; button.className = 'cresco-filterable-loop__chip'; button.textContent = item.label + ' ×';
			button.addEventListener( 'click', function () { item.target.value = ''; item.target.dispatchEvent( new Event( 'change', { bubbles: true } ) ); } );
			host.appendChild( button );
		} );
		if ( active.length ) {
			var clear = document.createElement( 'button' );
			clear.type = 'button'; clear.className = 'cresco-filterable-loop__clear'; clear.textContent = 'Clear all';
			clear.addEventListener( 'click', function () {
				if ( search ) search.value = '';
				root.querySelectorAll( 'select[data-taxonomy]' ).forEach( function ( select ) { select.value = ''; } );
				( search || root.querySelector( 'select[data-taxonomy]' ) ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			host.appendChild( clear );
		}
	}
	function counts( root ) {
		var payload = root.getAttribute( 'data-payload' );
		var signature = root.getAttribute( 'data-signature' );
		var endpoint = root.getAttribute( 'data-endpoint' );
		if ( ! payload || ! signature || ! endpoint ) return;
		endpoint = endpoint.replace( /interactive-query\/?$/, 'facet-counts' );
		fetch( endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( { payload: payload, signature: signature, filters: filters( root ) } ) } )
			.then( function ( response ) { return response.ok ? response.json() : null; } )
			.then( function ( data ) {
				if ( ! data || ! data.counts ) return;
				root.querySelectorAll( 'select[data-taxonomy]' ).forEach( function ( select ) {
					var map = data.counts[ select.getAttribute( 'data-taxonomy' ) ] || {};
					Array.prototype.forEach.call( select.options, function ( option ) {
						if ( ! option.value ) return;
						var base = option.text.replace( /\s+\(\d+\)$/, '' );
						option.text = base + ' (' + ( map[ option.value ] || 0 ) + ')';
					} );
				} );
			} ).catch( function () {} );
	}
	function enhance( root ) {
		chips( root ); counts( root );
		root.addEventListener( 'change', function () { window.setTimeout( function () { chips( root ); counts( root ); }, 0 ); } );
		root.addEventListener( 'submit', function () { window.setTimeout( function () { chips( root ); counts( root ); }, 0 ); } );
	}
	function boot() { document.querySelectorAll( '[data-cresco-query="1"]' ).forEach( enhance ); }
	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot ); else boot();
} )();
