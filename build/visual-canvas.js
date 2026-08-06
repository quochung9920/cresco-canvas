( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.blocks || ! wp.i18n ) return;

	var DRAG_MIME = 'application/x-cresco-canvas-element';
	var ROOT_CLASS = 'cresco-visual-canvas-active';
	var FRAME_CLASS = 'cc-visual-canvas-frame';
	var OVERLAY_ID = 'cc-visual-canvas-overlay';
	var FRAME_STYLE_ID = 'cc-visual-canvas-style';
	var CONTAINER_BLOCKS = [
		'cresco/container',
		'core/group',
		'core/cover',
		'core/column',
		'core/columns',
		'core/buttons',
		'core/navigation'
	];
	var attachedDocuments = new Map();
	var pendingNewDrop = null;
	var activeExistingDrag = null;
	var unsubscribeStore = null;
	var scheduledRender = false;

	function currentScriptUrl() {
		var script = document.currentScript;
		if ( script && script.src ) return script.src;
		var scripts = Array.prototype.slice.call( document.scripts || [] );
		for ( var index = scripts.length - 1; index >= 0; index -= 1 ) {
			if ( /\/visual-canvas\.js(?:\?|$)/.test( scripts[ index ].src || '' ) ) return scripts[ index ].src;
		}
		return '';
	}

	function visualCssUrl() {
		var source = currentScriptUrl();
		if ( ! source ) return '';
		try {
			var url = new URL( source, window.location.href );
			url.pathname = url.pathname.replace( /\/build\/visual-canvas\.js$/, '/assets/css/visual-canvas.css' );
			return url.toString();
		} catch ( error ) {
			return source.replace( /\/build\/visual-canvas\.js(?:\?.*)?$/, '/assets/css/visual-canvas.css' );
		}
	}

	var CSS_URL = visualCssUrl();

	function blockSelect() {
		try {
			return wp.data.select( 'core/block-editor' );
		} catch ( error ) {
			return null;
		}
	}

	function blockDispatch() {
		try {
			return wp.data.dispatch( 'core/block-editor' );
		} catch ( error ) {
			return null;
		}
	}

	function selectedClientId() {
		var select = blockSelect();
		return select && select.getSelectedBlockClientId ? select.getSelectedBlockClientId() : null;
	}

	function blockCount( blocks ) {
		return ( blocks || [] ).reduce( function ( total, block ) {
			return total + 1 + blockCount( block.innerBlocks || [] );
		}, 0 );
	}

	function totalBlockCount() {
		var select = blockSelect();
		return select && select.getBlocks ? blockCount( select.getBlocks() ) : 0;
	}

	function blockLabel( clientId ) {
		var select = blockSelect();
		if ( ! select || ! clientId ) return 'Widget';
		var block = select.getBlock ? select.getBlock( clientId ) : null;
		if ( ! block ) return 'Widget';
		var metadata = block.attributes && block.attributes.metadata;
		if ( metadata && metadata.name ) return String( metadata.name );
		var type = wp.blocks.getBlockType ? wp.blocks.getBlockType( block.name ) : null;
		return type && type.title ? String( type.title ) : String( block.name || 'Widget' ).replace( /^[^/]+\//, '' );
	}

	function isContainer( clientId ) {
		var select = blockSelect();
		var name = select && select.getBlockName ? select.getBlockName( clientId ) : '';
		return CONTAINER_BLOCKS.indexOf( name || '' ) !== -1;
	}

	function rootId( clientId ) {
		var select = blockSelect();
		if ( ! select || ! clientId || ! select.getBlockRootClientId ) return undefined;
		return select.getBlockRootClientId( clientId ) || undefined;
	}

	function sameRoot( left, right ) {
		return ( left || undefined ) === ( right || undefined );
	}

	function isDescendantOf( candidateId, ancestorId ) {
		if ( ! candidateId || ! ancestorId ) return false;
		var select = blockSelect();
		if ( ! select || ! select.getBlockParents ) return false;
		return select.getBlockParents( candidateId ).indexOf( ancestorId ) !== -1;
	}

	function findBlockNode( targetDocument, clientId ) {
		if ( ! targetDocument || ! clientId ) return null;
		var nodes = targetDocument.querySelectorAll( '[data-block]' );
		for ( var index = 0; index < nodes.length; index += 1 ) {
			if ( nodes[ index ].getAttribute( 'data-block' ) === clientId ) return nodes[ index ];
		}
		return null;
	}

	function closestBlockNode( node ) {
		return node && typeof node.closest === 'function' ? node.closest( '[data-block]' ) : null;
	}

	function canvasNode( targetDocument ) {
		return targetDocument.querySelector( '.block-editor-block-list__layout, .editor-styles-wrapper, .edit-post-visual-editor, .editor-visual-editor' );
	}

	function pointInsideRect( x, y, rect ) {
		return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
	}

	function describeDropAtPoint( targetDocument, clientX, clientY, movingClientId ) {
		if ( ! targetDocument ) return null;
		var target = targetDocument.elementFromPoint( clientX, clientY );
		var blockNode = closestBlockNode( target );
		if ( blockNode ) {
			var targetClientId = blockNode.getAttribute( 'data-block' );
			if ( ! targetClientId ) return null;
			if ( movingClientId && ( targetClientId === movingClientId || isDescendantOf( targetClientId, movingClientId ) ) ) return null;
			var rect = blockNode.getBoundingClientRect();
			var ratio = rect.height > 0 ? ( clientY - rect.top ) / rect.height : 0.5;
			var zone;
			if ( isContainer( targetClientId ) && ratio >= 0.27 && ratio <= 0.73 ) zone = 'inside';
			else zone = ratio < 0.5 ? 'before' : 'after';
			return { targetClientId: targetClientId, zone: zone, rect: rect };
		}

		var canvas = canvasNode( targetDocument );
		if ( canvas ) {
			var canvasRect = canvas.getBoundingClientRect();
			if ( pointInsideRect( clientX, clientY, canvasRect ) ) {
				return { targetClientId: null, zone: 'root-end', rect: canvasRect };
			}
		}
		return null;
	}

	function insertionPoint( descriptor, movingClientId ) {
		var select = blockSelect();
		if ( ! select || ! descriptor ) return null;
		var destinationRoot;
		var destinationIndex;
		if ( ! descriptor.targetClientId || descriptor.zone === 'root-end' ) {
			destinationRoot = undefined;
			destinationIndex = select.getBlockOrder ? select.getBlockOrder().length : 0;
		} else if ( descriptor.zone === 'inside' ) {
			destinationRoot = descriptor.targetClientId;
			destinationIndex = select.getBlockOrder ? select.getBlockOrder( destinationRoot ).length : 0;
		} else {
			destinationRoot = rootId( descriptor.targetClientId );
			destinationIndex = Math.max( 0, ( select.getBlockIndex ? select.getBlockIndex( descriptor.targetClientId ) : 0 ) + ( descriptor.zone === 'after' ? 1 : 0 ) );
		}

		if ( movingClientId ) {
			var sourceRoot = rootId( movingClientId );
			var sourceIndex = select.getBlockIndex ? select.getBlockIndex( movingClientId ) : -1;
			if ( sameRoot( sourceRoot, destinationRoot ) && sourceIndex >= 0 && sourceIndex < destinationIndex ) destinationIndex -= 1;
			if ( destinationRoot === movingClientId || ( destinationRoot && isDescendantOf( destinationRoot, movingClientId ) ) ) return null;
		}
		return { rootClientId: destinationRoot, index: Math.max( 0, destinationIndex ) };
	}

	function canMoveTo( clientId, point ) {
		if ( ! clientId || ! point ) return false;
		var select = blockSelect();
		if ( ! select ) return false;
		var name = select.getBlockName ? select.getBlockName( clientId ) : '';
		if ( select.canInsertBlockType && name && ! select.canInsertBlockType( name, point.rootClientId ) ) return false;
		return true;
	}

	function moveBlockToDescriptor( clientId, descriptor ) {
		var select = blockSelect();
		var dispatch = blockDispatch();
		if ( ! select || ! dispatch || ! clientId || ! descriptor ) return false;
		var point = insertionPoint( descriptor, clientId );
		if ( ! point || ! canMoveTo( clientId, point ) ) return false;
		var sourceRoot = rootId( clientId );
		try {
			if ( typeof dispatch.moveBlockToPosition === 'function' ) {
				dispatch.moveBlockToPosition( clientId, sourceRoot, point.rootClientId, point.index );
			} else if ( typeof dispatch.moveBlocksToPosition === 'function' ) {
				dispatch.moveBlocksToPosition( [ clientId ], sourceRoot, point.rootClientId, point.index );
			} else {
				return false;
			}
			if ( dispatch.selectBlock ) dispatch.selectBlock( clientId );
			return true;
		} catch ( error ) {
			return false;
		}
	}

	function makeButton( targetDocument, className, label, text ) {
		var button = targetDocument.createElement( 'button' );
		button.type = 'button';
		button.className = className;
		button.setAttribute( 'aria-label', label );
		button.title = label;
		button.textContent = text;
		return button;
	}

	function createOverlay( targetDocument ) {
		var existing = targetDocument.getElementById( OVERLAY_ID );
		if ( existing ) return existing;
		var root = targetDocument.createElement( 'div' );
		root.id = OVERLAY_ID;
		root.className = 'cc-visual-overlay-root';

		var selection = targetDocument.createElement( 'div' );
		selection.className = 'cc-visual-selection';
		selection.hidden = true;
		var bar = targetDocument.createElement( 'div' );
		bar.className = 'cc-visual-selection__bar';
		var dragButton = makeButton( targetDocument, 'cc-visual-selection__drag', 'Kéo widget để di chuyển', '⠿' );
		var label = targetDocument.createElement( 'span' );
		label.className = 'cc-visual-selection__label';
		var duplicateButton = makeButton( targetDocument, 'cc-visual-selection__action', 'Nhân bản widget', '⧉' );
		var deleteButton = makeButton( targetDocument, 'cc-visual-selection__action is-destructive', 'Xóa widget', '×' );
		bar.appendChild( dragButton );
		bar.appendChild( label );
		bar.appendChild( duplicateButton );
		bar.appendChild( deleteButton );
		selection.appendChild( bar );

		var drop = targetDocument.createElement( 'div' );
		drop.className = 'cc-visual-drop-indicator';
		drop.hidden = true;
		var dropLabel = targetDocument.createElement( 'span' );
		dropLabel.className = 'cc-visual-drop-indicator__label';
		drop.appendChild( dropLabel );

		root.appendChild( selection );
		root.appendChild( drop );
		targetDocument.body.appendChild( root );
		root._cc = { selection: selection, bar: bar, dragButton: dragButton, label: label, duplicateButton: duplicateButton, deleteButton: deleteButton, drop: drop, dropLabel: dropLabel };
		return root;
	}

	function injectStyles( targetDocument ) {
		if ( ! targetDocument || targetDocument.getElementById( FRAME_STYLE_ID ) || ! CSS_URL ) return;
		var link = targetDocument.createElement( 'link' );
		link.id = FRAME_STYLE_ID;
		link.rel = 'stylesheet';
		link.href = CSS_URL;
		( targetDocument.head || targetDocument.documentElement ).appendChild( link );
	}

	function updateSelection( targetDocument ) {
		var state = attachedDocuments.get( targetDocument );
		if ( ! state || ! state.overlay || ! state.overlay._cc ) return;
		var clientId = selectedClientId();
		var node = clientId ? findBlockNode( targetDocument, clientId ) : null;
		var ui = state.overlay._cc;
		if ( ! node || ! node.getClientRects().length ) {
			ui.selection.hidden = true;
			return;
		}
		var rect = node.getBoundingClientRect();
		if ( rect.width <= 0 || rect.height <= 0 ) {
			ui.selection.hidden = true;
			return;
		}
		ui.selection.hidden = false;
		ui.selection.style.transform = 'translate3d(' + Math.round( rect.left ) + 'px,' + Math.round( rect.top ) + 'px,0)';
		ui.selection.style.width = Math.round( rect.width ) + 'px';
		ui.selection.style.height = Math.round( rect.height ) + 'px';
		ui.selection.classList.toggle( 'is-flipped', rect.top < 52 );
		ui.label.textContent = blockLabel( clientId );
	}

	function renderDropIndicator( targetDocument, descriptor ) {
		var state = attachedDocuments.get( targetDocument );
		if ( ! state || ! state.overlay || ! state.overlay._cc ) return;
		var ui = state.overlay._cc;
		if ( ! descriptor || ! descriptor.rect ) {
			ui.drop.hidden = true;
			return;
		}
		var rect = descriptor.rect;
		ui.drop.hidden = false;
		ui.drop.className = 'cc-visual-drop-indicator is-' + descriptor.zone;
		if ( descriptor.zone === 'inside' ) {
			ui.drop.style.transform = 'translate3d(' + Math.round( rect.left ) + 'px,' + Math.round( rect.top ) + 'px,0)';
			ui.drop.style.width = Math.round( rect.width ) + 'px';
			ui.drop.style.height = Math.round( rect.height ) + 'px';
			ui.dropLabel.textContent = 'Thả vào bên trong';
		} else {
			var y = descriptor.zone === 'before' ? rect.top : rect.bottom;
			if ( descriptor.zone === 'root-end' ) y = rect.bottom - 8;
			ui.drop.style.transform = 'translate3d(' + Math.round( rect.left ) + 'px,' + Math.round( y ) + 'px,0)';
			ui.drop.style.width = Math.round( rect.width ) + 'px';
			ui.drop.style.height = '0px';
			ui.dropLabel.textContent = descriptor.zone === 'before' ? 'Chèn phía trên' : 'Chèn phía dưới';
		}
	}

	function hideAllDropIndicators() {
		attachedDocuments.forEach( function ( state ) {
			if ( state.overlay && state.overlay._cc ) state.overlay._cc.drop.hidden = true;
		} );
	}

	function scheduleRender() {
		if ( scheduledRender ) return;
		scheduledRender = true;
		window.requestAnimationFrame( function () {
			scheduledRender = false;
			attachedDocuments.forEach( function ( state, targetDocument ) { updateSelection( targetDocument ); } );
		} );
	}

	function beginExistingDrag( event, targetDocument ) {
		if ( event.button !== 0 ) return;
		var clientId = selectedClientId();
		if ( ! clientId ) return;
		var state = attachedDocuments.get( targetDocument );
		if ( ! state || ! state.overlay || ! state.overlay._cc ) return;
		event.preventDefault();
		event.stopPropagation();
		var handle = event.currentTarget;
		if ( handle.setPointerCapture ) {
			try { handle.setPointerCapture( event.pointerId ); } catch ( error ) {}
		}
		activeExistingDrag = { clientId: clientId, document: targetDocument, descriptor: null, handle: handle, pointerId: event.pointerId };
		state.overlay.classList.add( 'is-dragging' );
		document.body.classList.add( 'cresco-visual-canvas-dragging' );

		function move( moveEvent ) {
			if ( ! activeExistingDrag ) return;
			var descriptor = describeDropAtPoint( targetDocument, moveEvent.clientX, moveEvent.clientY, clientId );
			activeExistingDrag.descriptor = descriptor;
			renderDropIndicator( targetDocument, descriptor );
		}

		function end() {
			handle.removeEventListener( 'pointermove', move );
			handle.removeEventListener( 'pointerup', end );
			handle.removeEventListener( 'pointercancel', cancel );
			if ( activeExistingDrag && activeExistingDrag.descriptor ) moveBlockToDescriptor( clientId, activeExistingDrag.descriptor );
			activeExistingDrag = null;
			state.overlay.classList.remove( 'is-dragging' );
			document.body.classList.remove( 'cresco-visual-canvas-dragging' );
			hideAllDropIndicators();
			scheduleRender();
		}

		function cancel() {
			activeExistingDrag = null;
			state.overlay.classList.remove( 'is-dragging' );
			document.body.classList.remove( 'cresco-visual-canvas-dragging' );
			hideAllDropIndicators();
			handle.removeEventListener( 'pointermove', move );
			handle.removeEventListener( 'pointerup', end );
			handle.removeEventListener( 'pointercancel', cancel );
		}

		handle.addEventListener( 'pointermove', move );
		handle.addEventListener( 'pointerup', end );
		handle.addEventListener( 'pointercancel', cancel );
	}

	function handleNewDragOver( event, targetDocument ) {
		if ( ! event.dataTransfer || Array.prototype.indexOf.call( event.dataTransfer.types || [], DRAG_MIME ) === -1 ) return;
		event.preventDefault();
		event.dataTransfer.dropEffect = 'copy';
		var descriptor = describeDropAtPoint( targetDocument, event.clientX, event.clientY, null );
		if ( descriptor ) renderDropIndicator( targetDocument, descriptor );
	}

	function handleNewDropCapture( event, targetDocument ) {
		if ( ! event.dataTransfer ) return;
		var id = event.dataTransfer.getData( DRAG_MIME );
		if ( ! id ) return;
		var descriptor = describeDropAtPoint( targetDocument, event.clientX, event.clientY, null );
		if ( ! descriptor ) return;
		pendingNewDrop = {
			descriptor: { targetClientId: descriptor.targetClientId, zone: descriptor.zone },
			beforeCount: totalBlockCount(),
			beforeSelected: selectedClientId(),
			expiresAt: Date.now() + 3000
		};
		window.setTimeout( hideAllDropIndicators, 60 );
	}

	function handleStoreChange() {
		if ( pendingNewDrop ) {
			if ( Date.now() > pendingNewDrop.expiresAt ) {
				pendingNewDrop = null;
			} else {
				var count = totalBlockCount();
				var selected = selectedClientId();
				if ( count > pendingNewDrop.beforeCount && selected && selected !== pendingNewDrop.beforeSelected ) {
					moveBlockToDescriptor( selected, pendingNewDrop.descriptor );
					pendingNewDrop = null;
				}
			}
		}
		scheduleRender();
	}

	function attachDocument( targetDocument ) {
		if ( ! targetDocument || attachedDocuments.has( targetDocument ) || ! targetDocument.body ) return;
		injectStyles( targetDocument );
		targetDocument.documentElement.classList.add( FRAME_CLASS );
		var overlay = createOverlay( targetDocument );
		var state = { overlay: overlay, observer: null, listeners: [] };
		attachedDocuments.set( targetDocument, state );

		var ui = overlay._cc;
		ui.dragButton.addEventListener( 'pointerdown', function ( event ) { beginExistingDrag( event, targetDocument ); } );
		ui.duplicateButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			var dispatch = blockDispatch();
			var clientId = selectedClientId();
			if ( dispatch && clientId && dispatch.duplicateBlocks ) dispatch.duplicateBlocks( [ clientId ] );
		} );
		ui.deleteButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			var dispatch = blockDispatch();
			var clientId = selectedClientId();
			if ( dispatch && clientId && dispatch.removeBlock ) dispatch.removeBlock( clientId );
		} );

		function clickCapture( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-visual-overlay-root' ) ) return;
			var blockNode = closestBlockNode( event.target );
			if ( ! blockNode ) return;
			var clientId = blockNode.getAttribute( 'data-block' );
			var dispatch = blockDispatch();
			if ( clientId && dispatch && dispatch.selectBlock ) dispatch.selectBlock( clientId );
		}
		function dragOver( event ) { handleNewDragOver( event, targetDocument ); }
		function dropCapture( event ) { handleNewDropCapture( event, targetDocument ); }
		function scroll() { scheduleRender(); }
		targetDocument.addEventListener( 'click', clickCapture, true );
		targetDocument.addEventListener( 'dragover', dragOver, true );
		targetDocument.addEventListener( 'drop', dropCapture, true );
		targetDocument.addEventListener( 'scroll', scroll, true );
		state.listeners.push( [ 'click', clickCapture, true ], [ 'dragover', dragOver, true ], [ 'drop', dropCapture, true ], [ 'scroll', scroll, true ] );
		state.observer = new MutationObserver( scheduleRender );
		state.observer.observe( targetDocument.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'style', 'data-block' ] } );
		updateSelection( targetDocument );
	}

	function scanDocuments() {
		attachDocument( document );
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) {
			try {
				if ( iframe.contentDocument && iframe.contentDocument.body ) attachDocument( iframe.contentDocument );
				if ( ! iframe._ccVisualCanvasLoad ) {
					iframe._ccVisualCanvasLoad = function () {
						try { attachDocument( iframe.contentDocument ); } catch ( error ) {}
					};
					iframe.addEventListener( 'load', iframe._ccVisualCanvasLoad );
				}
			} catch ( error ) {}
		} );
	}

	function start() {
		if ( ! document.body ) return;
		document.body.classList.add( ROOT_CLASS );
		scanDocuments();
		var observer = new MutationObserver( scanDocuments );
		observer.observe( document.documentElement, { childList: true, subtree: true } );
		window.addEventListener( 'resize', scheduleRender, { passive: true } );
		if ( wp.data.subscribe ) unsubscribeStore = wp.data.subscribe( handleStoreChange );
		handleStoreChange();
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )( window.wp );
