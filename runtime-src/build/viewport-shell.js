( function ( window, document ) {
	'use strict';

	var settings = window.crescoCanvasStandaloneSettings || {};
	var structureStorageKey = 'cresco-structure-collapsed:' + String( settings.postId || 'page' );
	var collapsedStructureIds = new Set();
	var structureApplying = false;

	try {
		var savedStructureIds = JSON.parse( window.sessionStorage.getItem( structureStorageKey ) || '[]' );
		if ( Array.isArray( savedStructureIds ) ) savedStructureIds.forEach( function ( id ) { collapsedStructureIds.add( String( id ) ); } );
	} catch ( error ) {}

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

	function structureDepth( item ) {
		var value = parseFloat( item.style.paddingInlineStart || item.style.paddingLeft || '12' );
		if ( ! Number.isFinite( value ) ) value = 12;
		return Math.max( 0, Math.round( ( value - 12 ) / 14 ) );
	}

	function structureNodeId( item ) {
		var small = item.querySelector( 'small' );
		return small ? String( small.textContent || '' ).trim() : '';
	}

	function saveStructureState() {
		try { window.sessionStorage.setItem( structureStorageKey, JSON.stringify( Array.from( collapsedStructureIds ) ) ); } catch ( error ) {}
	}

	function updateStructureTree() {
		if ( structureApplying ) return;
		var root = document.querySelector( '.cc-standalone-structure' );
		if ( ! root ) return;
		structureApplying = true;

		var items = Array.prototype.slice.call( root.querySelectorAll( '.cc-standalone-structure-item' ) );
		var collapsedDepths = [];

		items.forEach( function ( item, index ) {
			var depth = structureDepth( item );
			var id = structureNodeId( item );
			var next = items[ index + 1 ];
			var hasChildren = !! next && structureDepth( next ) > depth;

			while ( collapsedDepths.length && collapsedDepths[ collapsedDepths.length - 1 ] >= depth ) collapsedDepths.pop();

			item.hidden = collapsedDepths.length > 0;
			item.dataset.crescoStructureDepth = String( depth );
			item.style.setProperty( '--cc-structure-depth', String( depth ) );
			item.classList.toggle( 'has-children', hasChildren );
			item.classList.toggle( 'is-collapsed', hasChildren && collapsedStructureIds.has( id ) );
			if ( hasChildren ) item.setAttribute( 'aria-expanded', String( ! collapsedStructureIds.has( id ) ) );
			else item.removeAttribute( 'aria-expanded' );

			if ( hasChildren && collapsedStructureIds.has( id ) ) collapsedDepths.push( depth );
		} );

		structureApplying = false;
	}

	function scheduleStructureUpdate() {
		window.requestAnimationFrame( updateStructureTree );
	}

	function isStructureToggleClick( event, item ) {
		if ( ! item.classList.contains( 'has-children' ) ) return false;
		var rect = item.getBoundingClientRect();
		var depth = Number( item.dataset.crescoStructureDepth || 0 );
		var toggleLeft = 7 + depth * 14;
		var localX = event.clientX - rect.left;
		return localX >= toggleLeft - 4 && localX <= toggleLeft + 18;
	}

	function handleStructureClick( event ) {
		var item = event.target && event.target.closest ? event.target.closest( '.cc-standalone-structure-item' ) : null;
		if ( ! item || ! isStructureToggleClick( event, item ) ) return;
		var id = structureNodeId( item );
		if ( ! id ) return;
		event.preventDefault();
		event.stopPropagation();
		if ( collapsedStructureIds.has( id ) ) collapsedStructureIds.delete( id );
		else collapsedStructureIds.add( id );
		saveStructureState();
		updateStructureTree();
	}

	function boot() {
		var stage = document.querySelector( '.cc-standalone-stage' );
		var structure = document.querySelector( '.cc-standalone-structure' );
		if ( ! stage || ! structure ) {
			window.setTimeout( boot, 80 );
			return;
		}

		updateViewport();
		updateStructureTree();
		if ( window.ResizeObserver ) new window.ResizeObserver( scheduleUpdate ).observe( stage );
		if ( window.MutationObserver ) {
			new window.MutationObserver( scheduleStructureUpdate ).observe( structure, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'style' ] } );
		}
		structure.addEventListener( 'click', handleStructureClick, true );
		document.addEventListener( 'click', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-standalone-devices' ) ) scheduleUpdate();
		} );
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-standalone-viewport-toolbar' ) ) scheduleUpdate();
		} );
	}

	boot();
} )( window, document );
