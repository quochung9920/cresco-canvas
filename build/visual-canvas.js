( function ( wp, window, document ) {
	'use strict';

	var Cresco = window.CrescoCanvas;
	if ( ! wp || ! wp.data || ! wp.i18n || ! Cresco || ! Cresco.adapter || ! Cresco.dragDrop || ! Cresco.ui ) return;

	var __ = wp.i18n.__;
	var ROOT_CLASS = 'cresco-visual-canvas-active';
	var FRAME_CLASS = 'cc-visual-canvas-frame';
	var OVERLAY_ID = 'cc-visual-canvas-overlay';
	var FRAME_STYLE_ID = 'cc-visual-canvas-style';
	var CONTROL_ID = 'cc-visual-mode-control';
	var FAILED_CLASS = 'cresco-app-shell-failed';
	var documents = new Map();
	var currentState = Cresco.ui.getState();
	var activeDrag = null;
	var temporaryNative = false;
	var renderQueued = false;
	var storeUnsubscribe = null;
	var editorReady = false;
	var scanQueued = false;

	function currentScriptUrl() {
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

	function appShellFailed() {
		return Boolean( document.body && document.body.classList.contains( FAILED_CLASS ) );
	}

	function visualEnabled() {
		return editorReady && ! appShellFailed() && currentState.visualMode !== false && ! temporaryNative;
	}

	function canvasNode( targetDocument ) {
		return targetDocument.querySelector( '.block-editor-block-list__layout, .editor-styles-wrapper, .edit-post-visual-editor, .editor-visual-editor' );
	}

	function hasEditorCanvas( targetDocument ) {
		if ( ! targetDocument || ! targetDocument.body ) return false;
		var node = canvasNode( targetDocument );
		return Boolean( node && node.isConnected );
	}

	function findBlockNode( targetDocument, clientId ) {
		var state = documents.get( targetDocument );
		if ( ! state || ! clientId ) return null;
		var cached = state.nodes.get( clientId );
		if ( cached && cached.isConnected ) return cached;
		var nodes = targetDocument.querySelectorAll( '[data-block]' );
		for ( var index = 0; index < nodes.length; index += 1 ) {
			var id = nodes[ index ].getAttribute( 'data-block' );
			if ( id ) state.nodes.set( id, nodes[ index ] );
			if ( id === clientId ) cached = nodes[ index ];
		}
		return cached || null;
	}

	function closestBlockNode( node ) {
		return node && typeof node.closest === 'function' ? node.closest( '[data-block]' ) : null;
	}

	function factoryNames( elementId ) {
		var factory = Cresco.dragDrop.factories.get( elementId );
		return factory && Array.isArray( factory.names ) ? factory.names : [];
	}

	function descriptorFromPoint( targetDocument, clientX, clientY, names, movingClientId ) {
		var target = targetDocument.elementFromPoint( clientX, clientY );
		var blockNode = closestBlockNode( target );
		if ( blockNode ) {
			var targetClientId = blockNode.getAttribute( 'data-block' );
			if ( ! targetClientId || targetClientId === movingClientId || Cresco.adapter.isDescendant( targetClientId, movingClientId ) ) return null;
			var rect = blockNode.getBoundingClientRect();
			var ratio = rect.height > 0 ? ( clientY - rect.top ) / rect.height : 0.5;
			var inside = { targetClientId: targetClientId, zone: 'inside', rect: rect };
			if ( ratio >= 0.25 && ratio <= 0.75 && Cresco.adapter.pointForDescriptor( inside, names, movingClientId ) ) return inside;
			var side = { targetClientId: targetClientId, zone: ratio < 0.5 ? 'before' : 'after', rect: rect };
			return Cresco.adapter.pointForDescriptor( side, names, movingClientId ) ? side : null;
		}

		var canvas = canvasNode( targetDocument );
		if ( ! canvas ) return null;
		var canvasRect = canvas.getBoundingClientRect();
		if ( clientX < canvasRect.left || clientX > canvasRect.right || clientY < canvasRect.top || clientY > canvasRect.bottom ) return null;
		var root = { targetClientId: '', zone: 'root-end', rect: canvasRect };
		return Cresco.adapter.pointForDescriptor( root, names, movingClientId ) ? root : null;
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
		var dragButton = makeButton( targetDocument, 'cc-visual-selection__drag', __( 'Drag widget to move it', 'cresco-canvas' ), '⠿' );
		var label = targetDocument.createElement( 'span' );
		label.className = 'cc-visual-selection__label';
		var upButton = makeButton( targetDocument, 'cc-visual-selection__action', __( 'Move widget up', 'cresco-canvas' ), '↑' );
		var downButton = makeButton( targetDocument, 'cc-visual-selection__action', __( 'Move widget down', 'cresco-canvas' ), '↓' );
		var duplicateButton = makeButton( targetDocument, 'cc-visual-selection__action', __( 'Duplicate widget', 'cresco-canvas' ), '⧉' );
		var deleteButton = makeButton( targetDocument, 'cc-visual-selection__action is-destructive', __( 'Delete widget', 'cresco-canvas' ), '×' );
		bar.appendChild( dragButton );
		bar.appendChild( label );
		bar.appendChild( upButton );
		bar.appendChild( downButton );
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
		root._cc = {
			deleteButton: deleteButton,
			downButton: downButton,
			dragButton: dragButton,
			drop: drop,
			dropLabel: dropLabel,
			duplicateButton: duplicateButton,
			label: label,
			selection: selection,
			upButton: upButton
		};
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

	function labelForBlock( clientId ) {
		var block = Cresco.adapter.getBlock( clientId );
		if ( ! block ) return __( 'Widget', 'cresco-canvas' );
		var metadata = block.attributes && block.attributes.metadata;
		if ( metadata && metadata.name ) return String( metadata.name );
		var type = wp.blocks.getBlockType ? wp.blocks.getBlockType( block.name ) : null;
		return type && type.title ? String( type.title ) : String( block.name || __( 'Widget', 'cresco-canvas' ) ).replace( /^[^/]+\//, '' );
	}

	function observeSelectedNode( targetDocument, node ) {
		var state = documents.get( targetDocument );
		if ( ! state ) return;
		if ( state.selectedNode === node ) return;
		if ( state.resizeObserver ) state.resizeObserver.disconnect();
		state.selectedNode = node || null;
		if ( node && typeof ResizeObserver === 'function' ) {
			state.resizeObserver = new ResizeObserver( queueRender );
			state.resizeObserver.observe( node );
		}
	}

	function updateSelection( targetDocument ) {
		var state = documents.get( targetDocument );
		if ( ! state || ! state.overlay || ! state.overlay._cc ) return;
		var ui = state.overlay._cc;
		if ( ! visualEnabled() ) {
			ui.selection.hidden = true;
			ui.drop.hidden = true;
			observeSelectedNode( targetDocument, null );
			return;
		}
		var clientId = Cresco.adapter.selectedClientId();
		var node = clientId ? findBlockNode( targetDocument, clientId ) : null;
		observeSelectedNode( targetDocument, node );
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
		ui.selection.classList.toggle( 'is-flipped', rect.top < 54 );
		ui.label.textContent = labelForBlock( clientId );
	}

	function renderDrop( targetDocument, descriptor ) {
		var state = documents.get( targetDocument );
		if ( ! state || ! state.overlay || ! state.overlay._cc ) return;
		var ui = state.overlay._cc;
		if ( ! descriptor || ! descriptor.rect || ! visualEnabled() ) {
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
			ui.dropLabel.textContent = __( 'Drop inside', 'cresco-canvas' );
		} else {
			var y = descriptor.zone === 'before' ? rect.top : rect.bottom;
			if ( descriptor.zone === 'root-end' ) y = rect.bottom - 8;
			ui.drop.style.transform = 'translate3d(' + Math.round( rect.left ) + 'px,' + Math.round( y ) + 'px,0)';
			ui.drop.style.width = Math.round( rect.width ) + 'px';
			ui.drop.style.height = '0px';
			ui.dropLabel.textContent = descriptor.zone === 'before' ? __( 'Insert above', 'cresco-canvas' ) : __( 'Insert below', 'cresco-canvas' );
		}
	}

	function hideDrops() {
		documents.forEach( function ( state ) {
			if ( state.overlay && state.overlay._cc ) state.overlay._cc.drop.hidden = true;
		} );
	}

	function queueRender() {
		if ( renderQueued ) return;
		renderQueued = true;
		window.requestAnimationFrame( function () {
			renderQueued = false;
			documents.forEach( function ( state, targetDocument ) { updateSelection( targetDocument ); } );
		} );
	}

	function moveRelative( direction ) {
		var clientId = Cresco.adapter.selectedClientId();
		if ( ! clientId || ! Cresco.adapter.canMove( clientId ) ) return;
		var root = Cresco.adapter.getBlockRoot( clientId );
		var order = Cresco.adapter.getBlockOrder( root );
		var index = order.indexOf( clientId );
		var targetIndex = direction < 0 ? index - 1 : index + 1;
		if ( targetIndex < 0 || targetIndex >= order.length ) return;
		var targetClientId = order[ targetIndex ];
		Cresco.dragDrop.moveBlock( clientId, { targetClientId: targetClientId, zone: direction < 0 ? 'before' : 'after' } );
		queueRender();
	}

	function beginMove( event, targetDocument ) {
		if ( event.button !== 0 || ! visualEnabled() ) return;
		var clientId = Cresco.adapter.selectedClientId();
		if ( ! clientId || ! Cresco.adapter.canMove( clientId ) ) return;
		event.preventDefault();
		event.stopPropagation();
		var state = documents.get( targetDocument );
		var handle = event.currentTarget;
		activeDrag = { clientId: clientId, descriptor: null, document: targetDocument };
		state.overlay.classList.add( 'is-dragging' );
		document.body.classList.add( 'cresco-visual-canvas-dragging' );
		if ( handle.setPointerCapture ) {
			try { handle.setPointerCapture( event.pointerId ); } catch ( error ) {}
		}

		function move( moveEvent ) {
			if ( ! activeDrag ) return;
			var name = Cresco.adapter.getBlockName( clientId );
			activeDrag.descriptor = descriptorFromPoint( targetDocument, moveEvent.clientX, moveEvent.clientY, [ name ], clientId );
			renderDrop( targetDocument, activeDrag.descriptor );
		}

		function finish( shouldCommit ) {
			handle.removeEventListener( 'pointermove', move );
			handle.removeEventListener( 'pointerup', end );
			handle.removeEventListener( 'pointercancel', cancel );
			if ( shouldCommit && activeDrag && activeDrag.descriptor ) Cresco.dragDrop.moveBlock( clientId, activeDrag.descriptor );
			activeDrag = null;
			state.overlay.classList.remove( 'is-dragging' );
			document.body.classList.remove( 'cresco-visual-canvas-dragging' );
			hideDrops();
			queueRender();
		}
		function end() { finish( true ); }
		function cancel() { finish( false ); }
		handle.addEventListener( 'pointermove', move );
		handle.addEventListener( 'pointerup', end );
		handle.addEventListener( 'pointercancel', cancel );
	}

	function attachDocument( targetDocument ) {
		if ( ! targetDocument || ! targetDocument.body || documents.has( targetDocument ) || ! hasEditorCanvas( targetDocument ) ) return;
		injectStyles( targetDocument );
		var overlay = createOverlay( targetDocument );
		var state = { nodes: new Map(), observer: null, overlay: overlay, resizeObserver: null, selectedNode: null };
		documents.set( targetDocument, state );

		var ui = overlay._cc;
		ui.dragButton.addEventListener( 'pointerdown', function ( event ) { beginMove( event, targetDocument ); } );
		ui.upButton.addEventListener( 'click', function () { moveRelative( -1 ); } );
		ui.downButton.addEventListener( 'click', function () { moveRelative( 1 ); } );
		ui.duplicateButton.addEventListener( 'click', function () {
			var id = Cresco.adapter.selectedClientId();
			if ( id ) Cresco.adapter.duplicateBlocks( [ id ] );
		} );
		ui.deleteButton.addEventListener( 'click', function () {
			var id = Cresco.adapter.selectedClientId();
			if ( id ) Cresco.adapter.removeBlocks( [ id ] );
		} );

		function clickCapture( event ) {
			if ( ! visualEnabled() || event.target.closest( '.cc-visual-overlay-root' ) ) return;
			var node = closestBlockNode( event.target );
			if ( node ) Cresco.adapter.selectBlock( node.getAttribute( 'data-block' ) );
		}
		function dragOverCapture( event ) {
			if ( ! visualEnabled() || ! event.dataTransfer || Array.prototype.indexOf.call( event.dataTransfer.types || [], Cresco.dragDrop.MIME ) === -1 ) return;
			var id = event.dataTransfer.getData( Cresco.dragDrop.MIME );
			if ( ! id ) return;
			event.preventDefault();
			event.stopPropagation();
			event.dataTransfer.dropEffect = 'copy';
			renderDrop( targetDocument, descriptorFromPoint( targetDocument, event.clientX, event.clientY, factoryNames( id ), null ) );
		}
		function dropCapture( event ) {
			if ( ! visualEnabled() || ! event.dataTransfer ) return;
			var id = event.dataTransfer.getData( Cresco.dragDrop.MIME );
			if ( ! id ) return;
			var descriptor = descriptorFromPoint( targetDocument, event.clientX, event.clientY, factoryNames( id ), null );
			if ( ! descriptor ) return;
			event.preventDefault();
			event.stopPropagation();
			if ( typeof event.stopImmediatePropagation === 'function' ) event.stopImmediatePropagation();
			Cresco.dragDrop.insertElement( id, descriptor );
			hideDrops();
			Cresco.ui.open( 'edit' );
		}
		function scrollCapture() { queueRender(); }
		targetDocument.addEventListener( 'click', clickCapture, true );
		targetDocument.addEventListener( 'dragover', dragOverCapture, true );
		targetDocument.addEventListener( 'drop', dropCapture, true );
		targetDocument.addEventListener( 'scroll', scrollCapture, true );

		state.observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.prototype.forEach.call( mutation.addedNodes || [], function ( node ) {
					if ( ! node || node.nodeType !== 1 ) return;
					if ( node.matches && node.matches( '[data-block]' ) ) state.nodes.set( node.getAttribute( 'data-block' ), node );
					if ( node.querySelectorAll ) node.querySelectorAll( '[data-block]' ).forEach( function ( child ) { state.nodes.set( child.getAttribute( 'data-block' ), child ); } );
				} );
			} );
			queueRender();
		} );
		state.observer.observe( targetDocument.body, { childList: true, subtree: true } );
		updateSelection( targetDocument );
	}

	function ensureControl() {
		if ( ! editorReady || appShellFailed() || document.getElementById( CONTROL_ID ) || ! document.body ) return;
		var control = document.createElement( 'div' );
		control.id = CONTROL_ID;
		control.className = 'cc-visual-mode-control';
		var button = makeButton( document, 'cc-visual-mode-control__button', __( 'Switch between Cresco canvas and native Gutenberg controls', 'cresco-canvas' ), '' );
		var label = document.createElement( 'span' );
		label.className = 'cc-visual-mode-control__label';
		button.appendChild( label );
		var hint = document.createElement( 'span' );
		hint.className = 'cc-visual-mode-control__hint';
		hint.textContent = __( 'Hold Alt for native controls', 'cresco-canvas' );
		control.appendChild( button );
		control.appendChild( hint );
		document.body.appendChild( control );
		button.addEventListener( 'click', function () { Cresco.ui.setState( { visualMode: ! currentState.visualMode } ); } );
	}

	function syncClasses() {
		var enabled = visualEnabled();
		if ( ! document.body ) return;
		document.body.classList.toggle( ROOT_CLASS, enabled );
		document.body.classList.toggle( 'cresco-visual-canvas-native', editorReady && ! enabled );
		documents.forEach( function ( state, targetDocument ) {
			targetDocument.documentElement.classList.toggle( FRAME_CLASS, enabled );
		} );
		var control = document.getElementById( CONTROL_ID );
		if ( control ) {
			control.hidden = ! editorReady || appShellFailed();
			control.classList.toggle( 'is-native', ! enabled );
			var label = control.querySelector( '.cc-visual-mode-control__label' );
			if ( label ) label.textContent = enabled ? __( 'Cresco canvas', 'cresco-canvas' ) : __( 'Native controls', 'cresco-canvas' );
		}
		queueRender();
	}

	function scanDocuments() {
		var ready = false;
		if ( hasEditorCanvas( document ) ) {
			ready = true;
			attachDocument( document );
		}
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) {
			try {
				if ( iframe.contentDocument && hasEditorCanvas( iframe.contentDocument ) ) {
					ready = true;
					attachDocument( iframe.contentDocument );
				}
				if ( ! iframe._ccVisualCanvasLoad ) {
					iframe._ccVisualCanvasLoad = function () { scheduleScan(); };
					iframe.addEventListener( 'load', iframe._ccVisualCanvasLoad );
				}
			} catch ( error ) {}
		} );
		if ( editorReady !== ready ) editorReady = ready;
		if ( editorReady ) ensureControl();
		syncClasses();
	}

	function scheduleScan() {
		if ( scanQueued ) return;
		scanQueued = true;
		window.requestAnimationFrame( function () {
			scanQueued = false;
			scanDocuments();
		} );
	}

	function start() {
		if ( ! document.body ) return;
		scanDocuments();
		var scanObserver = new MutationObserver( scheduleScan );
		scanObserver.observe( document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
		Cresco.ui.subscribe( function ( next ) { currentState = next; syncClasses(); } );
		window.addEventListener( 'resize', queueRender, { passive: true } );
		window.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Alt' && ! temporaryNative ) { temporaryNative = true; syncClasses(); }
		} );
		window.addEventListener( 'keyup', function ( event ) {
			if ( event.key === 'Alt' && temporaryNative ) { temporaryNative = false; syncClasses(); }
		} );
		window.addEventListener( 'blur', function () { if ( temporaryNative ) { temporaryNative = false; syncClasses(); } } );
		if ( wp.data.subscribe ) storeUnsubscribe = wp.data.subscribe( queueRender );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )( window.wp, window, document );
