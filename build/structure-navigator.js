( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.data || ! wp.blocks || ! wp.i18n ) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var getBlockType = wp.blocks.getBlockType;
	var __ = wp.i18n.__;
	var HOST_ID = 'cresco-canvas-structure-navigator-host';
	var ROOT_ID = 'cresco-canvas-structure-navigator-root';
	var STORAGE_KEY = 'crescoCanvas.structureNavigator.v1';
	var mountedRoot = null;

	function clamp( value, minimum, maximum ) {
		return Math.min( maximum, Math.max( minimum, value ) );
	}

	function readSettings() {
		var fallback = {
			visible: true,
			collapsed: false,
			docked: true,
			position: { x: Math.max( 16, window.innerWidth - 320 ), y: 96 },
			size: { width: 288, height: 560 }
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
		} catch ( error ) {
			return fallback;
		}
	}

	function writeSettings( settings ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( settings ) );
		} catch ( error ) {}
	}

	function textPreview( value ) {
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

		var previewKeys = [ 'content', 'text', 'value', 'label' ];
		for ( var index = 0; index < previewKeys.length; index += 1 ) {
			var preview = textPreview( attributes[ previewKeys[ index ] ] );
			if ( preview ) return preview.length > 34 ? preview.slice( 0, 33 ) + '…' : preview;
		}

		var type = getBlockType( block.name );
		return type && type.title ? String( type.title ) : String( block.name || __( 'Widget', 'cresco-canvas' ) );
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
		return ( blocks || [] ).reduce( function ( total, block ) {
			return total + 1 + countBlocks( block.innerBlocks || [] );
		}, 0 );
	}

	function findBlockElement( clientId ) {
		var documents = [ document ];
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) {
			try {
				if ( iframe.contentDocument ) documents.push( iframe.contentDocument );
			} catch ( error ) {}
		} );

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
			if ( node && typeof node.scrollIntoView === 'function' ) {
				node.scrollIntoView( { behavior: 'smooth', block: 'center', inline: 'nearest' } );
			}
		} );
	}

	function StructureNode( props ) {
		var block = props.block;
		var depth = props.depth;
		var selectedClientId = props.selectedClientId;
		var expanded = props.expanded;
		var toggleExpanded = props.toggleExpanded;
		var selectBlock = props.selectBlock;
		var children = block.innerBlocks || [];
		var hasChildren = children.length > 0;
		var isExpanded = expanded[ block.clientId ] !== false;
		var isSelected = selectedClientId === block.clientId;

		return el( 'li', { className: 'cc-structure-node', role: 'treeitem', 'aria-expanded': hasChildren ? isExpanded : undefined, 'aria-selected': isSelected },
			el( 'div', { className: 'cc-structure-node__row' + ( isSelected ? ' is-selected' : '' ), style: { paddingLeft: ( 8 + depth * 14 ) + 'px' } },
				hasChildren ? el( 'button', {
					type: 'button',
					className: 'cc-structure-node__toggle',
					'aria-label': isExpanded ? __( 'Collapse item', 'cresco-canvas' ) : __( 'Expand item', 'cresco-canvas' ),
					onClick: function ( event ) { event.stopPropagation(); toggleExpanded( block.clientId ); }
				}, el( 'span', { className: 'dashicons ' + ( isExpanded ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-right-alt2' ), 'aria-hidden': 'true' } ) ) : el( 'span', { className: 'cc-structure-node__toggle-spacer', 'aria-hidden': 'true' } ),
				el( 'button', {
					type: 'button',
					className: 'cc-structure-node__select',
					title: blockTypeLabel( block ),
					onClick: function () { selectBlock( block.clientId ); }
				},
					el( 'span', { className: 'cc-structure-node__icon dashicons ' + blockIconClass( block.name ), 'aria-hidden': 'true' } ),
					el( 'span', { className: 'cc-structure-node__label' }, blockLabel( block ) )
				)
			),
			hasChildren && isExpanded ? el( 'ul', { className: 'cc-structure-tree__group', role: 'group' }, children.map( function ( child ) {
				return el( StructureNode, {
					key: child.clientId,
					block: child,
					depth: depth + 1,
					selectedClientId: selectedClientId,
					expanded: expanded,
					toggleExpanded: toggleExpanded,
					selectBlock: selectBlock
				} );
			} ) ) : null
		);
	}

	function StructureNavigator() {
		var initial = useMemo( readSettings, [] );
		var visibleState = useState( initial.visible );
		var visible = visibleState[ 0 ];
		var setVisible = visibleState[ 1 ];
		var collapsedState = useState( initial.collapsed );
		var collapsed = collapsedState[ 0 ];
		var setCollapsed = collapsedState[ 1 ];
		var dockedState = useState( initial.docked );
		var docked = dockedState[ 0 ];
		var setDocked = dockedState[ 1 ];
		var positionState = useState( initial.position );
		var position = positionState[ 0 ];
		var setPosition = positionState[ 1 ];
		var sizeState = useState( initial.size );
		var size = sizeState[ 0 ];
		var setSize = sizeState[ 1 ];
		var expandedState = useState( {} );
		var expanded = expandedState[ 0 ];
		var setExpanded = expandedState[ 1 ];
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

		useEffect( function () {
			writeSettings( {
				visible: visible,
				collapsed: collapsed,
				docked: docked,
				position: position,
				size: size
			} );
		}, [ visible, collapsed, docked, position.x, position.y, size.width, size.height ] );

		useEffect( function () {
			if ( ! editorState.selectedClientId ) return;
			setExpanded( function ( current ) {
				var next = Object.assign( {}, current );
				( editorState.parents || [] ).forEach( function ( clientId ) { next[ clientId ] = true; } );
				return next;
			} );
			var selectedRow = panelRef.current && panelRef.current.querySelector( '[aria-selected="true"]' );
			if ( selectedRow && typeof selectedRow.scrollIntoView === 'function' ) {
				window.setTimeout( function () { selectedRow.scrollIntoView( { block: 'nearest' } ); }, 0 );
			}
		}, [ editorState.selectedClientId ] );

		useEffect( function () {
			function keepOnScreen() {
				if ( docked ) return;
				setPosition( function ( current ) {
					return {
						x: clamp( current.x, 8, Math.max( 8, window.innerWidth - size.width - 8 ) ),
						y: clamp( current.y, 48, Math.max( 48, window.innerHeight - 80 ) )
					};
				} );
			}
			window.addEventListener( 'resize', keepOnScreen, { passive: true } );
			return function () { window.removeEventListener( 'resize', keepOnScreen ); };
		}, [ docked, size.width ] );

		function toggleExpanded( clientId ) {
			setExpanded( function ( current ) {
				var next = Object.assign( {}, current );
				next[ clientId ] = current[ clientId ] === false;
				return next;
			} );
		}

		function selectBlock( clientId ) {
			if ( blockDispatch && blockDispatch.selectBlock ) blockDispatch.selectBlock( clientId );
			scrollToBlock( clientId );
		}

		function beginDrag( event ) {
			if ( event.button !== 0 || event.target.closest( 'button' ) || ! panelRef.current ) return;
			var rect = panelRef.current.getBoundingClientRect();
			var startX = event.clientX;
			var startY = event.clientY;
			var startLeft = rect.left;
			var startTop = rect.top;
			setDocked( false );
			event.preventDefault();

			function move( moveEvent ) {
				setPosition( {
					x: clamp( startLeft + moveEvent.clientX - startX, 8, Math.max( 8, window.innerWidth - rect.width - 8 ) ),
					y: clamp( startTop + moveEvent.clientY - startY, 48, Math.max( 48, window.innerHeight - 56 ) )
				} );
			}

			function end() {
				window.removeEventListener( 'pointermove', move );
				window.removeEventListener( 'pointerup', end );
			}

			window.addEventListener( 'pointermove', move );
			window.addEventListener( 'pointerup', end, { once: true } );
		}

		function beginResize( event ) {
			if ( event.button !== 0 || ! panelRef.current ) return;
			var rect = panelRef.current.getBoundingClientRect();
			var startX = event.clientX;
			var startY = event.clientY;
			event.preventDefault();
			event.stopPropagation();

			function move( moveEvent ) {
				setSize( {
					width: clamp( rect.width + moveEvent.clientX - startX, 240, Math.min( 480, window.innerWidth - 16 ) ),
					height: clamp( rect.height + moveEvent.clientY - startY, 240, Math.max( 280, window.innerHeight - 72 ) )
				} );
			}

			function end() {
				window.removeEventListener( 'pointermove', move );
				window.removeEventListener( 'pointerup', end );
			}

			window.addEventListener( 'pointermove', move );
			window.addEventListener( 'pointerup', end, { once: true } );
		}

		if ( ! visible ) {
			return el( 'button', {
				type: 'button',
				className: 'cc-structure-launcher',
				onClick: function () { setVisible( true ); setDocked( true ); },
				'aria-label': __( 'Open Structure navigator', 'cresco-canvas' )
			}, el( 'span', { className: 'dashicons dashicons-list-view', 'aria-hidden': 'true' } ), el( 'span', null, __( 'Structure', 'cresco-canvas' ) ) );
		}

		var panelStyle = {
			width: size.width + 'px',
			height: collapsed ? 'auto' : size.height + 'px'
		};
		if ( ! docked ) {
			panelStyle.left = position.x + 'px';
			panelStyle.top = position.y + 'px';
		}

		return el( 'aside', {
			ref: panelRef,
			className: 'cc-structure-navigator' + ( docked ? ' is-docked' : ' is-floating' ) + ( collapsed ? ' is-collapsed' : '' ),
			style: panelStyle,
			'aria-label': __( 'Page structure', 'cresco-canvas' )
		},
			el( 'header', { className: 'cc-structure-navigator__header', onPointerDown: beginDrag },
				el( 'span', { className: 'cc-structure-navigator__brand' },
					el( 'span', { className: 'dashicons dashicons-list-view', 'aria-hidden': 'true' } ),
					el( 'strong', null, __( 'Structure', 'cresco-canvas' ) )
				),
				el( 'span', { className: 'cc-structure-navigator__actions' },
					el( 'button', { type: 'button', onClick: function () { setDocked( true ); }, title: __( 'Dock right', 'cresco-canvas' ), 'aria-label': __( 'Dock Structure to the right', 'cresco-canvas' ) }, el( 'span', { className: 'dashicons dashicons-align-right', 'aria-hidden': 'true' } ) ),
					el( 'button', { type: 'button', onClick: function () { setCollapsed( ! collapsed ); }, title: collapsed ? __( 'Expand', 'cresco-canvas' ) : __( 'Collapse', 'cresco-canvas' ), 'aria-label': collapsed ? __( 'Expand Structure', 'cresco-canvas' ) : __( 'Collapse Structure', 'cresco-canvas' ) }, el( 'span', { className: 'dashicons ' + ( collapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-minus' ), 'aria-hidden': 'true' } ) ),
					el( 'button', { type: 'button', onClick: function () { setVisible( false ); }, title: __( 'Close', 'cresco-canvas' ), 'aria-label': __( 'Close Structure', 'cresco-canvas' ) }, el( 'span', { className: 'dashicons dashicons-no-alt', 'aria-hidden': 'true' } ) )
				)
			),
			! collapsed ? el( Fragment, null,
				el( 'div', { className: 'cc-structure-navigator__tree' },
					editorState.blocks.length ? el( 'ul', { className: 'cc-structure-tree', role: 'tree', 'aria-label': __( 'Block hierarchy', 'cresco-canvas' ) }, editorState.blocks.map( function ( block ) {
						return el( StructureNode, {
							key: block.clientId,
							block: block,
							depth: 0,
							selectedClientId: editorState.selectedClientId,
							expanded: expanded,
							toggleExpanded: toggleExpanded,
							selectBlock: selectBlock
						} );
					} ) ) : el( 'div', { className: 'cc-structure-navigator__empty' }, __( 'This page has no widgets yet.', 'cresco-canvas' ) )
				),
				el( 'footer', { className: 'cc-structure-navigator__footer' },
					el( 'span', null, totalBlocks + ' ' + ( totalBlocks === 1 ? __( 'widget', 'cresco-canvas' ) : __( 'widgets', 'cresco-canvas' ) ) ),
					el( 'span', null, docked ? __( 'Docked right', 'cresco-canvas' ) : __( 'Floating', 'cresco-canvas' ) )
				),
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
		if ( typeof wp.element.createRoot === 'function' ) {
			mountedRoot = wp.element.createRoot( rootNode );
			mountedRoot.render( el( StructureNavigator ) );
		} else if ( typeof wp.element.render === 'function' ) {
			wp.element.render( el( StructureNavigator ), rootNode );
			mountedRoot = true;
		} else {
			return false;
		}
		return true;
	}

	function start() {
		if ( mount() ) return;
		var observer = new MutationObserver( function () {
			if ( mount() ) observer.disconnect();
		} );
		observer.observe( document.documentElement, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )( window.wp );
