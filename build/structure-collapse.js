( function ( window, document ) {
	'use strict';

	var settings = window.crescoCanvasStandaloneSettings || {};
	var storageKey = 'cresco-structure-collapsed:' + String( settings.postId || 'page' );
	var collapsed = new Set();
	var observer = null;
	var applying = false;

	try {
		var saved = JSON.parse( window.sessionStorage.getItem( storageKey ) || '[]' );
		if ( Array.isArray( saved ) ) saved.forEach( function ( id ) { collapsed.add( String( id ) ); } );
	} catch ( error ) {}

	function save() {
		try { window.sessionStorage.setItem( storageKey, JSON.stringify( Array.from( collapsed ) ) ); } catch ( error ) {}
	}

	function depthOf( item ) {
		var value = parseFloat( item.style.paddingInlineStart || item.style.paddingLeft || '12' );
		if ( ! Number.isFinite( value ) ) value = 12;
		return Math.max( 0, Math.round( ( value - 12 ) / 14 ) );
	}

	function nodeId( item ) {
		var id = item.querySelector( 'small' );
		return id ? String( id.textContent || '' ).trim() : '';
	}

	function applyTreeState() {
		if ( applying ) return;
		var root = document.querySelector( '.cc-standalone-structure' );
		if ( ! root ) return;
		applying = true;

		var items = Array.prototype.slice.call( root.querySelectorAll( '.cc-standalone-structure-item' ) );
		var activeCollapsedDepths = [];

		items.forEach( function ( item, index ) {
			var depth = depthOf( item );
			var id = nodeId( item );
			var next = items[ index + 1 ];
			var hasChildren = !! next && depthOf( next ) > depth;

			while ( activeCollapsedDepths.length && activeCollapsedDepths[ activeCollapsedDepths.length - 1 ] >= depth ) {
				activeCollapsedDepths.pop();
			}

			var hiddenByParent = activeCollapsedDepths.length > 0;
			item.hidden = hiddenByParent;
			item.dataset.crescoStructureDepth = String( depth );
			item.style.setProperty( '--cc-structure-depth', String( depth ) );
			item.classList.toggle( 'has-children', hasChildren );
			item.classList.toggle( 'is-collapsed', hasChildren && collapsed.has( id ) );
			item.setAttribute( 'aria-expanded', hasChildren ? String( ! collapsed.has( id ) ) : '');

			if ( hasChildren && collapsed.has( id ) ) activeCollapsedDepths.push( depth );
		} );

		applying = false;
	}

	function isToggleClick( event, item ) {
		if ( ! item.classList.contains( 'has-children' ) ) return false;
		var rect = item.getBoundingClientRect();
		var depth = Number( item.dataset.crescoStructureDepth || 0 );
		var left = 7 + depth * 14;
		var localX = event.clientX - rect.left;
		return localX >= left - 3 && localX <= left + 17;
	}

	function onClick( event ) {
		var item = event.target && event.target.closest ? event.target.closest( '.cc-standalone-structure-item' ) : null;
		if ( ! item || ! isToggleClick( event, item ) ) return;
		var id = nodeId( item );
		if ( ! id ) return;
		event.preventDefault();
		event.stopPropagation();
		if ( collapsed.has( id ) ) collapsed.delete( id );
		else collapsed.add( id );
		save();
		applyTreeState();
	}

	function boot() {
		var root = document.querySelector( '.cc-standalone-structure' );
		if ( ! root ) {
			window.setTimeout( boot, 80 );
			return;
		}
		root.addEventListener( 'click', onClick, true );
		observer = new MutationObserver( function () { window.requestAnimationFrame( applyTreeState ); } );
		observer.observe( root, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'style' ] } );
		applyTreeState();
	}

	boot();
} )( window, document );
