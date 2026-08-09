( function ( wp, window, document ) {
	'use strict';

	var Cresco = window.CrescoCanvas;
	if ( ! wp || ! wp.element || ! wp.data || ! wp.blocks || ! wp.i18n || ! wp.components || ! Cresco || ! Cresco.adapter || ! Cresco.dragDrop ) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var SearchControl = wp.components.SearchControl;
	var getBlockType = wp.blocks.getBlockType;
	var __ = wp.i18n.__;
	var HOST_ID = 'cresco-canvas-structure-navigator-host';
	var ROOT_ID = 'cresco-canvas-structure-navigator-root';
	var STORAGE_KEY = 'crescoCanvas.structureNavigator.v2';
	var DRAG_TYPE = 'application/x-cresco-structure-client';
	var mountedRoot = null;

	function clamp( value, minimum, maximum ) {
		return Math.min( maximum, Math.max( minimum, value ) );
	}

	function readSettings() {
		var fallback = {
			visible: true,
			collapsed: false,
			docked: true,
			position: { x: Math.max( 16, window.innerWidth - 364 ), y: 96 },
			size: { width: 332, height: 620 }
		};
		try {
			var stored = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) || 'null' );
			if ( ! stored || typeof stored !== 'object' ) return fallback;
			return {
				visible: stored.visible !== false,
				collapsed: Boolean( stored.collapsed ),
				docked: stored.docked !== false,
				position: {
					x: Number.isFinite( stored.position && stored.position.x ) ? stored.position.x : fallback.position.x,
					y: Number.isFinite( stored.position && stored.position.y ) ? stored.position.y : fallback.position.y
				},
				size: {
					width: Number.isFinite( stored.size && stored.size.width ) ? stored.size.width : fallback.size.width,
					height: Number.isFinite( stored.size && stored.size.height ) ? stored.size.height : fallback.size.height
				}
			};
		} catch ( error ) { return fallback; }
	}

	function writeSettings( settings ) {
		try { window.localStorage.setItem( STORAGE_KEY, JSON.stringify( settings ) ); } catch ( error ) {}
	}

	function textPreview( value ) {
		if ( value && typeof value === 'object' && typeof value.originalHTML === 'string' ) value = value.originalHTML;
		return String( value == null ? '' : value )
			.replace( /<[^>]*>/g, ' ' )
			.replace( /&nbsp;/gi, ' ' )
			.replace( /&amp;/gi, '&' )
			.replace( /&lt;/gi, '<' )
			.replace( /&gt;/gi, '>' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function blockLabel( block ) {
		if ( ! block ) return __( 'Widget', 'cresco-canvas' );
		var attributes = block.attributes || {};
		var metadata = attributes.metadata && typeof attributes.metadata === 'object' ? attributes.metadata : {};
		if ( metadata.name ) return String( metadata.name );
		var previewKeys = [ 'content', 'text', 'value', 'label', 'title' ];
		for ( var index = 0; index < previewKeys.length; index += 1 ) {
			var preview = textPreview( attributes[ previewKeys[ index ] ] );
			if ( preview ) return preview.length > 38 ? preview.slice( 0, 37 ) + '…' : preview;
		}
		var type = getBlockType( block.name );
		return type && type.title ? String( type.title ) : String( block.name || __( 'Widget', 'cresco-canvas' ) ).replace( /^[^/]+\//, '' );
	}

	function blockTypeLabel( block ) {
		var type = block && getBlockType( block.name );
		return type && type.title ? String( type.title ) : String( block && block.name || '' ).replace( /^[^/]+\//, '' );
	}

	function blockIconClass( name ) {
		name = String( name || '' );
		if ( /image|gallery|media|video|cover/.test( name ) ) return 'dashicons-format-image';
		if ( /heading|title/.test( name ) ) return 'dashicons-heading';
		if ( /paragraph|text|quote/.test( name ) ) return 'dashicons-editor-paragraph';
		if ( /button|link/.test( name ) ) return 'dashicons-button';
		if ( /columns|column|grid|row|group|container|stack|section/.test( name ) ) return 'dashicons-screenoptions';
		if ( /shortcode|html|code/.test( name ) ) return 'dashicons-editor-code';
		if ( /form|input|field/.test( name ) ) return 'dashicons-feedback';
		return 'dashicons-admin-generic';
	}

	function countBlocks( blocks ) {
		return ( blocks || [] ).reduce( function ( total, block ) { return total + 1 + countBlocks( block.innerBlocks || [] ); }, 0 );
	}

	function collectExpanded( blocks, value, expandedValue ) {
		( blocks || [] ).forEach( function ( block ) {
			if ( block.innerBlocks && block.innerBlocks.length ) {
				value[ block.clientId ] = expandedValue;
				collectExpanded( block.innerBlocks, value, expandedValue );
			}
		} );
		return value;
	}

	function findBlockElement( clientId ) {
		var documents = [ document ];
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) { try { if ( iframe.contentDocument ) documents.push( iframe.contentDocument ); } catch ( error ) {} } );
		for ( var index = 0; index < documents.length; index += 1 ) {
			var matches = documents[ index ].querySelectorAll( '[data-block]' );
			for ( var matchIndex = 0; matchIndex < matches.length; matchIndex += 1 ) {
				if ( matches[ matchIndex ].getAttribute( 'data-block' ) === clientId ) return matches[ matchIndex ];
			}
		}
		return null;
	}

	function scrollToBlock( clientId ) {
		window.requestAnimationFrame( function () {
			var node = findBlockElement( clientId );
			if ( node && typeof node.scrollIntoView === 'function' ) node.scrollIntoView( { behavior: 'smooth', block: 'center', inline: 'nearest' } );
		} );
	}

	function matchesQuery( block, query ) {
		if ( ! query ) return true;
		var needle = query.toLowerCase();
		if ( ( blockLabel( block ) + ' ' + blockTypeLabel( block ) + ' ' + block.name ).toLowerCase().indexOf( needle ) !== -1 ) return true;
		return ( block.innerBlocks || [] ).some( function ( child ) { return matchesQuery( child, query ); } );
	}

	function StructureNode( props ) {
		var block = props.block;
		if ( ! matchesQuery( block, props.query ) ) return null;
		var depth = props.depth;
		var children = block.innerBlocks || [];
		var hasChildren = children.length > 0;
		var isExpanded = props.query ? true : props.expanded[ block.clientId ] !== false;
		var isSelected = props.selectedClientId === block.clientId;
		var isLocked = Boolean( block.attributes && block.attributes.lock && ( block.attributes.lock.move || block.attributes.lock.remove ) );
		var drop = props.drop;
		var rowClass = 'cc-structure-node__row' + ( isSelected ? ' is-selected' : '' ) + ( drop && drop.targetClientId === block.clientId ? ' is-drop-' + drop.zone : '' );

		function keyNavigate( event ) {
			var row = event.currentTarget.closest( '.cc-structure-node__row' );
			var panel = row && row.closest( '.cc-structure-navigator' );
			if ( ! panel ) return;
			var buttons = Array.prototype.slice.call( panel.querySelectorAll( '.cc-structure-node__select' ) );
			var index = buttons.indexOf( event.currentTarget );
			if ( event.key === 'ArrowDown' && buttons[ index + 1 ] ) { event.preventDefault(); buttons[ index + 1 ].focus(); }
			if ( event.key === 'ArrowUp' && buttons[ index - 1 ] ) { event.preventDefault(); buttons[ index - 1 ].focus(); }
			if ( event.key === 'Home' && buttons[ 0 ] ) { event.preventDefault(); buttons[ 0 ].focus(); }
			if ( event.key === 'End' && buttons.length ) { event.preventDefault(); buttons[ buttons.length - 1 ].focus(); }
			if ( event.key === 'ArrowRight' && hasChildren && ! isExpanded ) { event.preventDefault(); props.toggleExpanded( block.clientId ); }
			if ( event.key === 'ArrowLeft' && hasChildren && isExpanded ) { event.preventDefault(); props.toggleExpanded( block.clientId ); }
		}

		return el( 'li', { className: 'cc-structure-node', role: 'treeitem', 'aria-expanded': hasChildren ? isExpanded : undefined, 'aria-selected': isSelected },
			el( 'div', {
				className: rowClass,
				style: { paddingLeft: ( 8 + depth * 14 ) + 'px' },
				draggable: ! isLocked,
				onDragStart: function ( event ) { props.startDrag( event, block ); },
				onDragEnd: props.endDrag,
				onDragOver: function ( event ) { props.dragOver( event, block ); },
				onDrop: function ( event ) { props.dropBlock( event, block ); }
			},
				hasChildren ? el( 'button', { type: 'button', className: 'cc-structure-node__toggle', 'aria-label': isExpanded ? __( 'Collapse item', 'cresco-canvas' ) : __( 'Expand item', 'cresco-canvas' ), onClick: function ( event ) { event.stopPropagation(); props.toggleExpanded( block.clientId ); } }, el( 'span', { className: 'dashicons ' + ( isExpanded ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2' ), 'aria-hidden': 'true' } ) ) : el( 'span', { className: 'cc-structure-node__toggle-spacer', 'aria-hidden': 'true' } ),
				el( 'button', { type: 'button', className: 'cc-structure-node__select', title: blockTypeLabel( block ), onClick: function () { props.selectBlock( block.clientId ); }, onKeyDown: keyNavigate },
					el( 'span', { className: 'cc-structure-node__icon dashicons ' + blockIconClass( block.name ), 'aria-hidden': 'true' } ),
					el( 'span', { className: 'cc-structure-node__label' }, blockLabel( block ) ),
					isLocked ? el( 'span', { className: 'dashicons dashicons-lock', title: __( 'Locked', 'cresco-canvas' ), 'aria-hidden': 'true' } ) : null
				),
				el( 'span', { className: 'cc-structure-node__actions' },
					el( 'button', { type: 'button', title: __( 'Rename', 'cresco-canvas' ), 'aria-label': __( 'Rename widget', 'cresco-canvas' ), onClick: function ( event ) { event.stopPropagation(); props.renameBlock( block ); } }, el( 'span', { className: 'dashicons dashicons-edit', 'aria-hidden': 'true' } ) ),
					el( 'button', { type: 'button', title: isLocked ? __( 'Unlock', 'cresco-canvas' ) : __( 'Lock', 'cresco-canvas' ), 'aria-label': isLocked ? __( 'Unlock widget', 'cresco-canvas' ) : __( 'Lock widget', 'cresco-canvas' ), onClick: function ( event ) { event.stopPropagation(); props.toggleLock( block ); } }, el( 'span', { className: 'dashicons ' + ( isLocked ? 'dashicons-unlock' : 'dashicons-lock' ), 'aria-hidden': 'true' } ) ),
					el( 'button', { type: 'button', title: __( 'Duplicate', 'cresco-canvas' ), 'aria-label': __( 'Duplicate widget', 'cresco-canvas' ), onClick: function ( event ) { event.stopPropagation(); Cresco.adapter.duplicateBlocks( [ block.clientId ] ); } }, el( 'span', { className: 'dashicons dashicons-admin-page', 'aria-hidden': 'true' } ) ),
					el( 'button', { type: 'button', className: 'is-destructive', title: __( 'Delete', 'cresco-canvas' ), 'aria-label': __( 'Delete widget', 'cresco-canvas' ), disabled: isLocked, onClick: function ( event ) { event.stopPropagation(); Cresco.adapter.removeBlocks( [ block.clientId ] ); } }, el( 'span', { className: 'dashicons dashicons-trash', 'aria-hidden': 'true' } ) )
				)
			),
			hasChildren && isExpanded ? el( 'ul', { className: 'cc-structure-tree__group', role: 'group' }, children.map( function ( child ) {
				return el( StructureNode, Object.assign( {}, props, { key: child.clientId, block: child, depth: depth + 1 } ) );
			} ) ) : null
		);
	}

	function StructureNavigator() {
		var initial = useMemo( readSettings, [] );
		var visiblePair = useState( initial.visible );
		var visible = visiblePair[ 0 ];
		var setVisible = visiblePair[ 1 ];
		var collapsedPair = useState( initial.collapsed );
		var collapsed = collapsedPair[ 0 ];
		var setCollapsed = collapsedPair[ 1 ];
		var dockedPair = useState( initial.docked );
		var docked = dockedPair[ 0 ];
		var setDocked = dockedPair[ 1 ];
		var positionPair = useState( initial.position );
		var position = positionPair[ 0 ];
		var setPosition = positionPair[ 1 ];
		var sizePair = useState( initial.size );
		var size = sizePair[ 0 ];
		var setSize = sizePair[ 1 ];
		var expandedPair = useState( {} );
		var expanded = expandedPair[ 0 ];
		var setExpanded = expandedPair[ 1 ];
		var queryPair = useState( '' );
		var query = queryPair[ 0 ];
		var setQuery = queryPair[ 1 ];
		var dragPair = useState( null );
		var dragging = dragPair[ 0 ];
		var setDragging = dragPair[ 1 ];
		var dropPair = useState( null );
		var drop = dropPair[ 0 ];
		var setDrop = dropPair[ 1 ];
		var panelRef = useRef( null );

		var editorState = useSelect( function ( select ) {
			var editor = select( 'core/block-editor' );
			var selectedClientId = editor && editor.getSelectedBlockClientId ? editor.getSelectedBlockClientId() : null;
			return {
				blocks: editor && editor.getBlocks ? editor.getBlocks() : [],
				selectedClientId: selectedClientId,
				parents: selectedClientId && editor && editor.getBlockParents ? editor.getBlockParents( selectedClientId ) : []
			};
		}, [] );
		var blockDispatch = useDispatch( 'core/block-editor' );
		var totalBlocks = useMemo( function () { return countBlocks( editorState.blocks ); }, [ editorState.blocks ] );

		useEffect( function () { writeSettings( { visible: visible, collapsed: collapsed, docked: docked, position: position, size: size } ); }, [ visible, collapsed, docked, position.x, position.y, size.width, size.height ] );
		useEffect( function () {
			if ( ! editorState.selectedClientId ) return;
			setExpanded( function ( current ) {
				var next = Object.assign( {}, current );
				( editorState.parents || [] ).forEach( function ( clientId ) { next[ clientId ] = true; } );
				return next;
			} );
			window.setTimeout( function () {
				var selectedRow = panelRef.current && panelRef.current.querySelector( '[aria-selected="true"]' );
				if ( selectedRow && typeof selectedRow.scrollIntoView === 'function' ) selectedRow.scrollIntoView( { block: 'nearest' } );
			}, 0 );
		}, [ editorState.selectedClientId ] );

		function toggleExpanded( clientId ) {
			setExpanded( function ( current ) { var next = Object.assign( {}, current ); next[ clientId ] = current[ clientId ] === false; return next; } );
		}

		function selectBlock( clientId ) {
			Cresco.adapter.selectBlock( clientId );
			Cresco.ui.open( 'edit' );
			scrollToBlock( clientId );
		}

		function renameBlock( block ) {
			var current = blockLabel( block );
			var nextName = window.prompt( __( 'Widget name', 'cresco-canvas' ), current );
			if ( nextName === null ) return;
			var metadata = Object.assign( {}, block.attributes && block.attributes.metadata || {} );
			if ( String( nextName ).trim() ) metadata.name = String( nextName ).trim();
			else delete metadata.name;
			if ( blockDispatch && blockDispatch.updateBlockAttributes ) blockDispatch.updateBlockAttributes( block.clientId, { metadata: metadata } );
		}

		function toggleLock( block ) {
			var current = block.attributes && block.attributes.lock || {};
			var locked = Boolean( current.move || current.remove );
			if ( blockDispatch && blockDispatch.updateBlockAttributes ) blockDispatch.updateBlockAttributes( block.clientId, { lock: locked ? undefined : { move: true, remove: true } } );
		}

		function startDrag( event, block ) {
			if ( ! Cresco.adapter.canMove( block.clientId ) ) { event.preventDefault(); return; }
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData( DRAG_TYPE, block.clientId );
			setDragging( block.clientId );
		}

		function dragOver( event, targetBlock ) {
			var sourceClientId = event.dataTransfer.getData( DRAG_TYPE ) || dragging;
			if ( ! sourceClientId || sourceClientId === targetBlock.clientId || Cresco.adapter.isDescendant( targetBlock.clientId, sourceClientId ) ) return;
			var row = event.currentTarget;
			var rect = row.getBoundingClientRect();
			var ratio = rect.height ? ( event.clientY - rect.top ) / rect.height : 0.5;
			var name = Cresco.adapter.getBlockName( sourceClientId );
			var descriptor = { targetClientId: targetBlock.clientId, zone: ratio < 0.28 ? 'before' : ratio > 0.72 ? 'after' : 'inside' };
			if ( ! Cresco.adapter.pointForDescriptor( descriptor, [ name ], sourceClientId ) ) descriptor.zone = ratio < 0.5 ? 'before' : 'after';
			if ( ! Cresco.adapter.pointForDescriptor( descriptor, [ name ], sourceClientId ) ) return;
			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			if ( sourceClientId !== dragging ) setDragging( sourceClientId );
			setDrop( descriptor );
		}

		function dropBlock( event, targetBlock ) {
			var clientId = event.dataTransfer.getData( DRAG_TYPE ) || dragging;
			if ( ! clientId ) return;
			var descriptor = drop;
			if ( ! descriptor || descriptor.targetClientId !== targetBlock.clientId ) {
				var row = event.currentTarget;
				var rect = row.getBoundingClientRect();
				var ratio = rect.height ? ( event.clientY - rect.top ) / rect.height : 0.5;
				descriptor = { targetClientId: targetBlock.clientId, zone: ratio < 0.5 ? 'before' : 'after' };
			}
			if ( ! Cresco.adapter.pointForDescriptor( descriptor, [ Cresco.adapter.getBlockName( clientId ) ], clientId ) ) return;
			event.preventDefault();
			event.stopPropagation();
			Cresco.dragDrop.moveBlock( clientId, descriptor );
			setDragging( null );
			setDrop( null );
		}

		function endDrag() { setDragging( null ); setDrop( null ); }

		function beginPanelDrag( event ) {
			if ( event.button !== 0 || event.target.closest( 'button, input' ) || ! panelRef.current ) return;
			var rect = panelRef.current.getBoundingClientRect();
			var startX = event.clientX;
			var startY = event.clientY;
			setDocked( false );
			event.preventDefault();
			function move( moveEvent ) { setPosition( { x: clamp( rect.left + moveEvent.clientX - startX, 8, Math.max( 8, window.innerWidth - rect.width - 8 ) ), y: clamp( rect.top + moveEvent.clientY - startY, 48, Math.max( 48, window.innerHeight - 56 ) ) } ); }
			function end() { window.removeEventListener( 'pointermove', move ); window.removeEventListener( 'pointerup', end ); window.removeEventListener( 'pointercancel', end ); }
			window.addEventListener( 'pointermove', move );
			window.addEventListener( 'pointerup', end );
			window.addEventListener( 'pointercancel', end );
		}

		function beginResize( event ) {
			if ( event.button !== 0 || ! panelRef.current ) return;
			var rect = panelRef.current.getBoundingClientRect();
			var startX = event.clientX;
			var startY = event.clientY;
			event.preventDefault();
			event.stopPropagation();
			function move( moveEvent ) { setSize( { width: clamp( rect.width + moveEvent.clientX - startX, 300, Math.min( 520, window.innerWidth - 16 ) ), height: clamp( rect.height + moveEvent.clientY - startY, 280, Math.max( 320, window.innerHeight - 72 ) ) } ); }
			function end() { window.removeEventListener( 'pointermove', move ); window.removeEventListener( 'pointerup', end ); window.removeEventListener( 'pointercancel', end ); }
			window.addEventListener( 'pointermove', move );
			window.addEventListener( 'pointerup', end );
			window.addEventListener( 'pointercancel', end );
		}

		if ( ! visible ) return el( 'button', { type: 'button', className: 'cc-structure-launcher', onClick: function () { setVisible( true ); setDocked( true ); }, 'aria-label': __( 'Open Structure navigator', 'cresco-canvas' ) }, el( 'span', { className: 'dashicons dashicons-list-view', 'aria-hidden': 'true' } ), el( 'span', null, __( 'Structure', 'cresco-canvas' ) ) );

		var panelStyle = { width: size.width + 'px', height: collapsed ? 'auto' : size.height + 'px' };
		if ( ! docked ) { panelStyle.left = position.x + 'px'; panelStyle.top = position.y + 'px'; }
		var nodeProps = { selectedClientId: editorState.selectedClientId, expanded: expanded, toggleExpanded: toggleExpanded, selectBlock: selectBlock, query: query.trim(), startDrag: startDrag, dragOver: dragOver, dropBlock: dropBlock, endDrag: endDrag, dragging: dragging, drop: drop, renameBlock: renameBlock, toggleLock: toggleLock };

		return el( 'aside', { ref: panelRef, className: 'cc-structure-navigator' + ( docked ? ' is-docked' : ' is-floating' ) + ( collapsed ? ' is-collapsed' : '' ), style: panelStyle, 'aria-label': __( 'Page structure', 'cresco-canvas' ) },
			el( 'header', { className: 'cc-structure-navigator__header', onPointerDown: beginPanelDrag },
				el( 'span', { className: 'cc-structure-navigator__brand' }, el( 'span', { className: 'dashicons dashicons-list-view', 'aria-hidden': 'true' } ), el( 'strong', null, __( 'Structure', 'cresco-canvas' ) ) ),
				el( 'span', { className: 'cc-structure-navigator__actions' },
					el( 'button', { type: 'button', onClick: function () { setDocked( true ); }, title: __( 'Dock right', 'cresco-canvas' ), 'aria-label': __( 'Dock Structure to the right', 'cresco-canvas' ) }, el( 'span', { className: 'dashicons dashicons-align-right', 'aria-hidden': 'true' } ) ),
					el( 'button', { type: 'button', onClick: function () { setCollapsed( ! collapsed ); }, title: collapsed ? __( 'Expand', 'cresco-canvas' ) : __( 'Collapse', 'cresco-canvas' ), 'aria-label': collapsed ? __( 'Expand Structure', 'cresco-canvas' ) : __( 'Collapse Structure', 'cresco-canvas' ) }, el( 'span', { className: 'dashicons ' + ( collapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-minus' ), 'aria-hidden': 'true' } ) ),
					el( 'button', { type: 'button', onClick: function () { setVisible( false ); }, title: __( 'Close', 'cresco-canvas' ), 'aria-label': __( 'Close Structure', 'cresco-canvas' ) }, el( 'span', { className: 'dashicons dashicons-no-alt', 'aria-hidden': 'true' } ) )
				)
			),
			! collapsed ? el( Fragment, null,
				el( 'div', { className: 'cc-structure-navigator__tools' },
					el( SearchControl, { label: __( 'Search widgets', 'cresco-canvas' ), placeholder: __( 'Search structure…', 'cresco-canvas' ), value: query, onChange: setQuery } ),
					el( 'span', { className: 'cc-structure-navigator__tree-actions' },
						el( 'button', { type: 'button', onClick: function () { setExpanded( collectExpanded( editorState.blocks, {}, true ) ); } }, __( 'Expand all', 'cresco-canvas' ) ),
						el( 'button', { type: 'button', onClick: function () { setExpanded( collectExpanded( editorState.blocks, {}, false ) ); } }, __( 'Collapse all', 'cresco-canvas' ) )
					)
				),
				el( 'div', { className: 'cc-structure-navigator__tree' },
					editorState.blocks.length ? el( 'ul', { className: 'cc-structure-tree', role: 'tree', 'aria-label': __( 'Block hierarchy', 'cresco-canvas' ) }, editorState.blocks.map( function ( block ) { return el( StructureNode, Object.assign( { key: block.clientId, block: block, depth: 0 }, nodeProps ) ); } ) ) : el( 'div', { className: 'cc-structure-navigator__empty' }, __( 'This page has no widgets yet.', 'cresco-canvas' ) )
				),
				el( 'footer', { className: 'cc-structure-navigator__footer' }, el( 'span', null, totalBlocks + ' ' + ( totalBlocks === 1 ? __( 'widget', 'cresco-canvas' ) : __( 'widgets', 'cresco-canvas' ) ) ), el( 'span', null, docked ? __( 'Docked right', 'cresco-canvas' ) : __( 'Floating', 'cresco-canvas' ) ) ),
				el( 'button', { type: 'button', className: 'cc-structure-navigator__resize', onPointerDown: beginResize, 'aria-label': __( 'Resize Structure panel', 'cresco-canvas' ) } )
			) : null
		);
	}

	function ensureHost() {
		var existing = document.getElementById( HOST_ID );
		if ( existing ) return existing;
		if ( ! document.body ) return null;
		var host = document.createElement( 'div' );
		host.id = HOST_ID;
		host.className = 'cresco-canvas-structure-navigator-host';
		var root = document.createElement( 'div' );
		root.id = ROOT_ID;
		host.appendChild( root );
		document.body.appendChild( host );
		return host;
	}

	function mount() {
		var host = ensureHost();
		if ( ! host || mountedRoot ) return Boolean( host );
		var rootNode = document.getElementById( ROOT_ID );
		if ( ! rootNode ) return false;
		if ( typeof wp.element.createRoot === 'function' ) { mountedRoot = wp.element.createRoot( rootNode ); mountedRoot.render( el( StructureNavigator ) ); }
		else if ( typeof wp.element.render === 'function' ) { wp.element.render( el( StructureNavigator ), rootNode ); mountedRoot = true; }
		else return false;
		return true;
	}

	function start() {
		if ( mount() ) return;
		var observer = new MutationObserver( function () { if ( mount() ) observer.disconnect(); } );
		observer.observe( document.documentElement, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )( window.wp, window, document );
