( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.blocks || ! wp.i18n ) return;

	var __ = wp.i18n.__;
	var namespace = window.CrescoCanvas = window.CrescoCanvas || {};
	var STORAGE_KEY = 'crescoCanvas.editorFoundation.v1';
	var DRAG_MIME = 'application/x-cresco-canvas-element';
	var DEVICE_ORDER = [ 'wide', 'desktop', 'laptop', 'tablet', 'mobile' ];
	var DEFAULT_STATE = {
		activeView: 'widgets',
		device: 'wide',
		open: true,
		visualMode: true,
		width: 320
	};
	var views = new Map();
	var viewListeners = new Set();
	var diagnostics = [];
	var factories = new Map();
	var state = Object.assign( {}, DEFAULT_STATE, readState() );

	function readState() {
		try {
			var stored = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) || 'null' );
			if ( ! stored || typeof stored !== 'object' ) return {};
			return {
				activeView: [ 'widgets', 'edit', 'global' ].indexOf( stored.activeView ) !== -1 ? stored.activeView : DEFAULT_STATE.activeView,
				device: DEVICE_ORDER.indexOf( stored.device ) !== -1 ? stored.device : DEFAULT_STATE.device,
				open: stored.open !== false,
				visualMode: stored.visualMode !== false,
				width: Number.isFinite( stored.width ) ? Math.max( 300, Math.min( 420, stored.width ) ) : DEFAULT_STATE.width
			};
		} catch ( error ) {
			return {};
		}
	}

	function writeState() {
		try { window.localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) ); } catch ( error ) {}
	}

	function emitState() {
		writeState();
		viewListeners.forEach( function ( listener ) {
			try { listener( Object.assign( {}, state ) ); } catch ( error ) {}
		} );
		try { window.dispatchEvent( new CustomEvent( 'cresco-canvas:state', { detail: Object.assign( {}, state ) } ) ); } catch ( error ) {}
	}

	function updateState( patch ) {
		state = Object.assign( {}, state, patch || {} );
		state.width = Math.max( 300, Math.min( 420, Number( state.width ) || DEFAULT_STATE.width ) );
		if ( DEVICE_ORDER.indexOf( state.device ) === -1 ) state.device = DEFAULT_STATE.device;
		if ( [ 'widgets', 'edit', 'global' ].indexOf( state.activeView ) === -1 ) state.activeView = DEFAULT_STATE.activeView;
		emitState();
		return Object.assign( {}, state );
	}

	function report( level, code, message, context ) {
		var entry = {
			code: String( code || 'unknown' ),
			context: context || {},
			level: level || 'error',
			message: String( message || '' ),
			time: new Date().toISOString()
		};
		diagnostics.push( entry );
		if ( diagnostics.length > 100 ) diagnostics.shift();
		if ( window.console && console[ level ] ) console[ level ]( '[Cresco Canvas][' + entry.code + '] ' + entry.message, entry.context );
		try { window.dispatchEvent( new CustomEvent( 'cresco-canvas:diagnostic', { detail: entry } ) ); } catch ( error ) {}
		return entry;
	}

	function safe( code, callback, fallback ) {
		try { return callback(); } catch ( error ) {
			report( 'error', code, error && error.message ? error.message : String( error ), { stack: error && error.stack ? error.stack : '' } );
			return fallback;
		}
	}

	function selectEditor() {
		return safe( 'adapter-select', function () { return wp.data.select( 'core/block-editor' ); }, null );
	}

	function dispatchEditor() {
		return safe( 'adapter-dispatch', function () { return wp.data.dispatch( 'core/block-editor' ); }, null );
	}

	function selectorRoot( rootClientId ) {
		return rootClientId || undefined;
	}

	function actionRoot( rootClientId ) {
		return rootClientId || '';
	}

	function getBlock( clientId ) {
		var select = selectEditor();
		return select && clientId && select.getBlock ? select.getBlock( clientId ) : null;
	}

	function getBlockName( clientId ) {
		var select = selectEditor();
		return select && clientId && select.getBlockName ? select.getBlockName( clientId ) : '';
	}

	function getBlockRoot( clientId ) {
		var select = selectEditor();
		return select && clientId && select.getBlockRootClientId ? select.getBlockRootClientId( clientId ) || '' : '';
	}

	function getBlockOrder( rootClientId ) {
		var select = selectEditor();
		if ( ! select || ! select.getBlockOrder ) return [];
		return rootClientId ? select.getBlockOrder( rootClientId ) || [] : select.getBlockOrder() || [];
	}

	function getBlockIndex( clientId, rootClientId ) {
		var select = selectEditor();
		if ( ! select || ! select.getBlockIndex || ! clientId ) return -1;
		return select.getBlockIndex( clientId, selectorRoot( rootClientId ) );
	}

	function getParents( clientId ) {
		var select = selectEditor();
		return select && clientId && select.getBlockParents ? select.getBlockParents( clientId ) || [] : [];
	}

	function isDescendant( candidateId, ancestorId ) {
		return Boolean( candidateId && ancestorId && getParents( candidateId ).indexOf( ancestorId ) !== -1 );
	}

	function templateLock( rootClientId ) {
		var select = selectEditor();
		return select && select.getTemplateLock ? select.getTemplateLock( selectorRoot( rootClientId ) ) : false;
	}

	function canInsertNames( names, rootClientId ) {
		var select = selectEditor();
		if ( ! select ) return false;
		var lock = templateLock( rootClientId );
		if ( lock === 'all' || lock === 'insert' ) return false;
		if ( typeof select.canInsertBlockType !== 'function' ) return true;
		return ( names || [] ).every( function ( name ) {
			return Boolean( name && select.canInsertBlockType( name, selectorRoot( rootClientId ) ) );
		} );
	}

	function canMove( clientId ) {
		var select = selectEditor();
		if ( ! select || ! clientId ) return false;
		if ( typeof select.canMoveBlock === 'function' && ! select.canMoveBlock( clientId ) ) return false;
		return templateLock( getBlockRoot( clientId ) ) !== 'all';
	}

	function blockNames( blocks ) {
		return ( blocks || [] ).map( function ( block ) { return block && block.name; } ).filter( Boolean );
	}

	function pointForDescriptor( descriptor, names, movingClientId ) {
		var select = selectEditor();
		if ( ! select || ! descriptor ) return null;
		var targetClientId = descriptor.targetClientId || '';
		var zone = descriptor.zone || 'root-end';
		var destinationRoot = '';
		var destinationIndex = 0;

		if ( ! targetClientId || zone === 'root-end' ) {
			destinationRoot = '';
			destinationIndex = getBlockOrder( '' ).length;
		} else if ( zone === 'inside' ) {
			if ( ! canInsertNames( names, targetClientId ) ) return null;
			destinationRoot = targetClientId;
			destinationIndex = getBlockOrder( targetClientId ).length;
		} else {
			destinationRoot = getBlockRoot( targetClientId );
			destinationIndex = Math.max( 0, getBlockIndex( targetClientId, destinationRoot ) + ( zone === 'after' ? 1 : 0 ) );
			if ( ! canInsertNames( names, destinationRoot ) ) return null;
		}

		if ( movingClientId ) {
			if ( destinationRoot === movingClientId || ( destinationRoot && isDescendant( destinationRoot, movingClientId ) ) ) return null;
			var sourceRoot = getBlockRoot( movingClientId );
			var sourceIndex = getBlockIndex( movingClientId, sourceRoot );
			if ( sourceRoot === destinationRoot && sourceIndex >= 0 && sourceIndex < destinationIndex ) destinationIndex -= 1;
		}

		return { index: Math.max( 0, destinationIndex ), rootClientId: destinationRoot };
	}

	function defaultPoint( blocks, selectedClientId ) {
		var names = blockNames( blocks );
		if ( selectedClientId && canInsertNames( names, selectedClientId ) ) {
			return { index: getBlockOrder( selectedClientId ).length, rootClientId: selectedClientId };
		}
		if ( selectedClientId ) {
			var root = getBlockRoot( selectedClientId );
			if ( canInsertNames( names, root ) ) return { index: Math.max( 0, getBlockIndex( selectedClientId, root ) + 1 ), rootClientId: root };
		}
		return canInsertNames( names, '' ) ? { index: getBlockOrder( '' ).length, rootClientId: '' } : null;
	}

	function insertBlocksExact( blocks, point ) {
		var dispatch = dispatchEditor();
		if ( ! dispatch || ! dispatch.insertBlocks || ! blocks || ! blocks.length || ! point ) return false;
		if ( ! canInsertNames( blockNames( blocks ), point.rootClientId ) ) return false;
		dispatch.insertBlocks( blocks, point.index, selectorRoot( point.rootClientId ) );
		if ( dispatch.selectBlock && blocks[ 0 ] && blocks[ 0 ].clientId ) dispatch.selectBlock( blocks[ 0 ].clientId );
		return true;
	}

	function moveBlockExact( clientId, point ) {
		var dispatch = dispatchEditor();
		if ( ! dispatch || ! clientId || ! point || ! canMove( clientId ) ) return false;
		var name = getBlockName( clientId );
		if ( ! canInsertNames( [ name ], point.rootClientId ) ) return false;
		var sourceRoot = getBlockRoot( clientId );
		if ( typeof dispatch.moveBlockToPosition === 'function' ) dispatch.moveBlockToPosition( clientId, actionRoot( sourceRoot ), actionRoot( point.rootClientId ), point.index );
		else if ( typeof dispatch.moveBlocksToPosition === 'function' ) dispatch.moveBlocksToPosition( [ clientId ], actionRoot( sourceRoot ), actionRoot( point.rootClientId ), point.index );
		else return false;
		if ( dispatch.selectBlock ) dispatch.selectBlock( clientId );
		return true;
	}

	function selectedClientId() {
		var select = selectEditor();
		return select && select.getSelectedBlockClientId ? select.getSelectedBlockClientId() : null;
	}

	function selectBlock( clientId ) {
		var dispatch = dispatchEditor();
		if ( dispatch && dispatch.selectBlock && clientId ) dispatch.selectBlock( clientId );
	}

	function duplicateBlocks( clientIds ) {
		var dispatch = dispatchEditor();
		if ( dispatch && dispatch.duplicateBlocks && clientIds && clientIds.length ) dispatch.duplicateBlocks( clientIds );
	}

	function removeBlocks( clientIds ) {
		var dispatch = dispatchEditor();
		if ( ! dispatch || ! clientIds || ! clientIds.length ) return;
		if ( dispatch.removeBlocks ) dispatch.removeBlocks( clientIds );
		else if ( dispatch.removeBlock ) clientIds.forEach( function ( clientId ) { dispatch.removeBlock( clientId ); } );
	}

	function registerFactory( definition ) {
		if ( ! definition || ! definition.id || typeof definition.create !== 'function' ) return false;
		var cachedNames = [];
		safe( 'factory-inspect-' + definition.id, function () { cachedNames = blockNames( definition.create() ); } );
		factories.set( definition.id, {
			create: definition.create,
			id: definition.id,
			label: definition.label || definition.id,
			names: cachedNames
		} );
		return true;
	}

	function createElementBlocks( id ) {
		var factory = factories.get( id );
		if ( ! factory ) return [];
		var blocks = safe( 'factory-create-' + id, function () { return factory.create(); }, [] );
		return Array.isArray( blocks ) ? blocks : [];
	}

	function insertElement( id, descriptor ) {
		var blocks = createElementBlocks( id );
		if ( ! blocks.length ) return { error: 'empty', ok: false };
		var point = descriptor ? pointForDescriptor( descriptor, blockNames( blocks ) ) : defaultPoint( blocks, selectedClientId() );
		if ( ! point ) return { error: 'restricted', ok: false };
		var ok = safe( 'element-insert-' + id, function () { return insertBlocksExact( blocks, point ); }, false );
		if ( ok ) announce( __( 'Widget added.', 'cresco-canvas' ) );
		return { blocks: blocks, ok: ok, point: point };
	}

	function moveBlock( clientId, descriptor ) {
		var name = getBlockName( clientId );
		var point = pointForDescriptor( descriptor, [ name ], clientId );
		if ( ! point ) return false;
		var ok = safe( 'block-move', function () { return moveBlockExact( clientId, point ); }, false );
		if ( ok ) announce( __( 'Widget moved.', 'cresco-canvas' ) );
		return ok;
	}

	function announce( message ) {
		var live = document.getElementById( 'cresco-canvas-live-region' );
		if ( ! live && document.body ) {
			live = document.createElement( 'div' );
			live.id = 'cresco-canvas-live-region';
			live.className = 'screen-reader-text';
			live.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( live );
		}
		if ( live ) {
			live.textContent = '';
			window.setTimeout( function () { live.textContent = String( message || '' ); }, 20 );
		}
	}

	function getManagedStyle( attributes ) {
		var metadata = attributes && attributes.metadata && typeof attributes.metadata === 'object' ? attributes.metadata : {};
		return JSON.parse( JSON.stringify( metadata.crescoStyle || attributes && attributes.crescoStyle || {} ) );
	}

	function setNested( object, path, value ) {
		var next = object && typeof object === 'object' ? JSON.parse( JSON.stringify( object ) ) : {};
		var cursor = next;
		for ( var index = 0; index < path.length - 1; index += 1 ) {
			if ( ! cursor[ path[ index ] ] || typeof cursor[ path[ index ] ] !== 'object' ) cursor[ path[ index ] ] = {};
			cursor = cursor[ path[ index ] ];
		}
		if ( value === undefined || value === '' ) delete cursor[ path[ path.length - 1 ] ];
		else cursor[ path[ path.length - 1 ] ] = value;
		return next;
	}

	function responsiveValue( style, device, path, fallback ) {
		var responsive = style && style.responsive && style.responsive[ device ];
		var cursor = responsive;
		for ( var index = 0; cursor && index < path.length; index += 1 ) cursor = cursor[ path[ index ] ];
		if ( cursor !== undefined ) return cursor;
		cursor = style;
		for ( var baseIndex = 0; cursor && baseIndex < path.length; baseIndex += 1 ) cursor = cursor[ path[ baseIndex ] ];
		return cursor !== undefined ? cursor : fallback;
	}

	function setResponsiveValue( style, device, path, value ) {
		if ( ! device || device === 'wide' ) return setNested( style, path, value );
		return setNested( style, [ 'responsive', device ].concat( path ), value );
	}

	function registerView( id, component, options ) {
		if ( ! id || typeof component !== 'function' ) return function () {};
		views.set( id, { component: component, options: options || {} } );
		try { window.dispatchEvent( new CustomEvent( 'cresco-canvas:views' ) ); } catch ( error ) {}
		return function () { views.delete( id ); };
	}

	function subscribe( listener ) {
		if ( typeof listener !== 'function' ) return function () {};
		viewListeners.add( listener );
		return function () { viewListeners.delete( listener ); };
	}

	namespace.version = '1.0.0-rc.1-foundation.1';
	namespace.ui = {
		getState: function () { return Object.assign( {}, state ); },
		getView: function ( id ) { return views.get( id ) || null; },
		getViews: function () { return new Map( views ); },
		open: function ( view ) { return updateState( { activeView: view || state.activeView, open: true } ); },
		registerView: registerView,
		setState: updateState,
		subscribe: subscribe
	};
	namespace.adapter = {
		blockNames: blockNames,
		canInsertNames: canInsertNames,
		canMove: canMove,
		defaultPoint: defaultPoint,
		duplicateBlocks: duplicateBlocks,
		getBlock: getBlock,
		getBlockIndex: getBlockIndex,
		getBlockName: getBlockName,
		getBlockOrder: getBlockOrder,
		getBlockRoot: getBlockRoot,
		getParents: getParents,
		insertBlocksExact: insertBlocksExact,
		isDescendant: isDescendant,
		moveBlockExact: moveBlockExact,
		pointForDescriptor: pointForDescriptor,
		removeBlocks: removeBlocks,
		selectBlock: selectBlock,
		selectedClientId: selectedClientId
	};
	namespace.dragDrop = {
		MIME: DRAG_MIME,
		factories: factories,
		insertElement: insertElement,
		moveBlock: moveBlock,
		registerFactory: registerFactory
	};
	namespace.responsive = {
		devices: DEVICE_ORDER.slice(),
		getManagedStyle: getManagedStyle,
		getValue: responsiveValue,
		setValue: setResponsiveValue
	};
	namespace.diagnostics = {
		getAll: function () { return diagnostics.slice(); },
		report: report,
		safe: safe
	};
	namespace.announce = announce;

	window.addEventListener( 'error', function ( event ) {
		var source = String( event && event.filename || '' );
		var stack = String( event && event.error && event.error.stack || '' );
		if ( source.indexOf( 'cresco-canvas' ) === -1 && stack.indexOf( 'cresco-canvas' ) === -1 ) return;
		report( 'error', 'runtime-error', event.message || __( 'Cresco Canvas encountered an editor error.', 'cresco-canvas' ), { filename: source, line: event.lineno || 0 } );
		updateState( { visualMode: false } );
	} );

	emitState();
} )( window.wp, window, document );
