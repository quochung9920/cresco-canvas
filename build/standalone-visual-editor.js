( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.blocks || ! wp.blockEditor || ! wp.components || ! wp.apiFetch || ! wp.data || ! wp.i18n ) return;

	var h = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var parse = wp.blocks.parse;
	var serialize = wp.blocks.serialize;
	var createBlock = wp.blocks.createBlock;
	var getBlockType = wp.blocks.getBlockType;
	var rawHandler = wp.blocks.rawHandler;
	var BlockEditorProvider = wp.blockEditor.BlockEditorProvider;
	var BlockList = wp.blockEditor.BlockList;
	var BlockTools = wp.blockEditor.BlockTools;
	var WritingFlow = wp.blockEditor.WritingFlow;
	var ObserveTyping = wp.blockEditor.ObserveTyping;
	var BlockInspector = wp.blockEditor.BlockInspector;
	var Button = wp.components.Button;
	var SearchControl = wp.components.SearchControl;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var TextareaControl = wp.components.TextareaControl;
	var settings = window.crescoCanvasStandaloneSettings || {};
	var rootNode = document.getElementById( 'cresco-canvas-standalone-editor' );
	if ( ! rootNode || ! settings.postId ) return;

	var DEVICE_ORDER = [ 'wide', 'desktop', 'laptop', 'tablet', 'mobile' ];
	var DEVICE_LABELS = { wide: 'Widescreen', desktop: 'Desktop', laptop: 'Laptop', tablet: 'Tablet', mobile: 'Mobile' };
	var DEFAULT_WIDTHS = Object.assign( { wide: 1920, desktop: 1440, laptop: 1366, tablet: 768, mobile: 390 }, settings.breakpoints || {} );
	var USAGE_KEY = 'crescoCanvas.standaloneWidgetUsage';
	var DRAG_MIME = 'application/x-cresco-canvas-widget';

	function widget( id, label, icon, create ) {
		return { id: id, label: label, icon: icon, create: create };
	}

	function paragraph( text ) { return createBlock( 'core/paragraph', { content: text || __( 'Add text', 'cresco-canvas' ) } ); }
	function heading( text, level ) { return createBlock( 'core/heading', { content: text || __( 'Add heading', 'cresco-canvas' ), level: level || 2 } ); }
	function group( innerBlocks, attributes ) { return createBlock( 'core/group', attributes || {}, innerBlocks || [] ); }

	var WIDGETS = [
		widget( 'section', __( 'Section', 'cresco-canvas' ), 'align-wide', function () { return group( [], { align: 'full', layout: { type: 'constrained' }, style: { spacing: { padding: { top: '72px', bottom: '72px' } } } } ); } ),
		widget( 'container', __( 'Container', 'cresco-canvas' ), 'layout', function () { return group( [], { layout: { type: 'constrained' } } ); } ),
		widget( 'row', __( 'Row', 'cresco-canvas' ), 'columns', function () { return group( [], { layout: { type: 'flex', flexWrap: 'nowrap' } } ); } ),
		widget( 'columns', __( 'Columns', 'cresco-canvas' ), 'columns', function () { return createBlock( 'core/columns', {}, [ createBlock( 'core/column' ), createBlock( 'core/column' ) ] ); } ),
		widget( 'heading', __( 'Heading', 'cresco-canvas' ), 'heading', function () { return heading(); } ),
		widget( 'text', __( 'Text', 'cresco-canvas' ), 'editor-paragraph', function () { return paragraph(); } ),
		widget( 'button', __( 'Button', 'cresco-canvas' ), 'button', function () { return createBlock( 'core/buttons', {}, [ createBlock( 'core/button', { text: __( 'Button', 'cresco-canvas' ), url: '#' } ) ] ); } ),
		widget( 'image', __( 'Image', 'cresco-canvas' ), 'format-image', function () { return createBlock( 'core/image' ); } ),
		widget( 'gallery', __( 'Gallery', 'cresco-canvas' ), 'format-gallery', function () { return createBlock( 'core/gallery', { columns: 3 } ); } ),
		widget( 'video', __( 'Video', 'cresco-canvas' ), 'format-video', function () { return createBlock( 'core/video' ); } ),
		widget( 'spacer', __( 'Spacer', 'cresco-canvas' ), 'image-flip-vertical', function () { return createBlock( 'core/spacer', { height: '48px' } ); } ),
		widget( 'divider', __( 'Divider', 'cresco-canvas' ), 'minus', function () { return createBlock( 'core/separator' ); } ),
		widget( 'list', __( 'List', 'cresco-canvas' ), 'editor-ul', function () { return createBlock( 'core/list' ); } ),
		widget( 'quote', __( 'Quote', 'cresco-canvas' ), 'format-quote', function () { return createBlock( 'core/quote', {}, [ paragraph( __( 'Add quotation', 'cresco-canvas' ) ) ] ); } ),
		widget( 'table', __( 'Table', 'cresco-canvas' ), 'editor-table', function () { return createBlock( 'core/table' ); } ),
		widget( 'navigation', __( 'Navigation', 'cresco-canvas' ), 'menu', function () { return createBlock( 'core/navigation' ); } ),
		widget( 'search', __( 'Search', 'cresco-canvas' ), 'search', function () { return createBlock( 'core/search', { showLabel: false, buttonUseIcon: true } ); } ),
		widget( 'site-logo', __( 'Site Logo', 'cresco-canvas' ), 'format-image', function () { return createBlock( 'core/site-logo' ); } ),
		widget( 'shortcode', __( 'Shortcode', 'cresco-canvas' ), 'shortcode', function () { return createBlock( 'core/shortcode' ); } )
	];

	function readUsage() {
		try { return JSON.parse( window.localStorage.getItem( USAGE_KEY ) || '{}' ) || {}; } catch ( error ) { return {}; }
	}

	function bumpUsage( id ) {
		var counts = readUsage();
		counts[ id ] = ( Number( counts[ id ] ) || 0 ) + 1;
		try { window.localStorage.setItem( USAGE_KEY, JSON.stringify( counts ) ); } catch ( error ) {}
	}

	function titleText( title ) {
		if ( title && typeof title === 'object' && typeof title.raw === 'string' ) return title.raw;
		if ( title && typeof title === 'object' && typeof title.rendered === 'string' ) return title.rendered.replace( /<[^>]+>/g, '' );
		return String( title || '' );
	}

	function normalizeContent( raw ) {
		raw = typeof raw === 'string' ? raw : '';
		if ( ! raw.trim() ) return [];
		try {
			var parsed = parse( raw );
			if ( parsed && parsed.length ) return parsed;
		} catch ( error ) {}
		if ( typeof rawHandler === 'function' ) {
			try {
				var converted = rawHandler( { HTML: raw } );
				if ( converted && converted.length ) return converted;
			} catch ( error ) {}
		}
		return [ createBlock( 'core/html', { content: raw } ) ];
	}

	function blockLabel( block ) {
		if ( ! block ) return __( 'Widget', 'cresco-canvas' );
		var type = getBlockType( block.name );
		return type && type.title ? String( type.title ) : String( block.name || '' ).replace( /^[^/]+\//, '' );
	}

	function flattenBlocks( blocks, depth, output, parentId ) {
		output = output || [];
		( blocks || [] ).forEach( function ( block, index ) {
			output.push( { block: block, depth: depth || 0, parentId: parentId || '', index: index } );
			flattenBlocks( block.innerBlocks || [], ( depth || 0 ) + 1, output, block.clientId );
		} );
		return output;
	}

	function mapTree( blocks, parentId, callback ) {
		var changed = false;
		var next = ( blocks || [] ).map( function ( block, index ) {
			var result = callback( block, index, parentId || '', blocks );
			if ( result !== block ) changed = true;
			if ( result && result.innerBlocks && result.innerBlocks.length ) {
				var inner = mapTree( result.innerBlocks, result.clientId, callback );
				if ( inner !== result.innerBlocks ) {
					result = Object.assign( {}, result, { innerBlocks: inner } );
					changed = true;
				}
			}
			return result;
		} ).filter( Boolean );
		return changed || next.length !== ( blocks || [] ).length ? next : blocks;
	}

	function findBlock( blocks, clientId ) {
		var found = null;
		flattenBlocks( blocks ).some( function ( item ) {
			if ( item.block.clientId !== clientId ) return false;
			found = item.block;
			return true;
		} );
		return found;
	}

	function removeFromTree( blocks, clientId ) {
		return mapTree( blocks, '', function ( block ) { return block.clientId === clientId ? null : block; } );
	}

	function transformSiblingList( blocks, parentId, transform ) {
		if ( ! parentId ) return transform( blocks || [] );
		return mapTree( blocks, '', function ( block ) {
			if ( block.clientId !== parentId ) return block;
			return Object.assign( {}, block, { innerBlocks: transform( block.innerBlocks || [] ) } );
		} );
	}

	function cloneBlock( block ) {
		if ( ! block ) return null;
		try {
			var cloned = normalizeContent( serialize( [ block ] ) );
			return cloned[ 0 ] || null;
		} catch ( error ) {
			return null;
		}
	}

	function VisualEditorApp() {
		var loadedState = useState( false ), loaded = loadedState[ 0 ], setLoaded = loadedState[ 1 ];
		var loadingState = useState( true ), loading = loadingState[ 0 ], setLoading = loadingState[ 1 ];
		var savingState = useState( false ), saving = savingState[ 0 ], setSaving = savingState[ 1 ];
		var dirtyState = useState( false ), dirty = dirtyState[ 0 ], setDirty = dirtyState[ 1 ];
		var noticeState = useState( null ), notice = noticeState[ 0 ], setNotice = noticeState[ 1 ];
		var blocksState = useState( [] ), blocks = blocksState[ 0 ], setBlocks = blocksState[ 1 ];
		var titleState = useState( settings.initialTitle || '' ), title = titleState[ 0 ], setTitle = titleState[ 1 ];
		var statusState = useState( settings.initialStatus || 'draft' ), status = statusState[ 0 ], setStatus = statusState[ 1 ];
		var modeState = useState( 'widgets' ), mode = modeState[ 0 ], setMode = modeState[ 1 ];
		var searchState = useState( '' ), search = searchState[ 0 ], setSearch = searchState[ 1 ];
		var deviceState = useState( 'wide' ), device = deviceState[ 0 ], setDevice = deviceState[ 1 ];
		var zoomState = useState( 'fit' ), zoom = zoomState[ 0 ], setZoom = zoomState[ 1 ];
		var selectedState = useState( null ), selectedClientId = selectedState[ 0 ], setSelectedClientId = selectedState[ 1 ];
		var globalState = useState( null ), globalSettings = globalState[ 0 ], setGlobalSettings = globalState[ 1 ];
		var globalTextState = useState( '' ), globalText = globalTextState[ 0 ], setGlobalText = globalTextState[ 1 ];
		var historyRef = useRef( [] );
		var historyIndexRef = useRef( -1 );
		var historyTimerRef = useRef( null );
		var stageRef = useRef( null );
		var lastSavedRef = useRef( '' );

		function snapshot( nextBlocks ) {
			var content = serialize( nextBlocks || [] );
			if ( historyRef.current[ historyIndexRef.current ] === content ) return;
			historyRef.current = historyRef.current.slice( 0, historyIndexRef.current + 1 );
			historyRef.current.push( content );
			if ( historyRef.current.length > 60 ) historyRef.current.shift();
			historyIndexRef.current = historyRef.current.length - 1;
		}

		function scheduleSnapshot( nextBlocks ) {
			window.clearTimeout( historyTimerRef.current );
			historyTimerRef.current = window.setTimeout( function () { snapshot( nextBlocks ); }, 350 );
		}

		function applyLoadedContent( raw, nextTitle, nextStatus ) {
			var parsed = normalizeContent( raw );
			setBlocks( parsed );
			if ( typeof nextTitle === 'string' ) setTitle( nextTitle );
			if ( nextStatus ) setStatus( nextStatus );
			historyRef.current = [];
			historyIndexRef.current = -1;
			snapshot( parsed );
			lastSavedRef.current = serialize( parsed );
			setDirty( false );
			setLoaded( true );
			return parsed;
		}

		useEffect( function () {
			var bootstrapRaw = typeof settings.initialContent === 'string' ? settings.initialContent : '';
			applyLoadedContent( bootstrapRaw, settings.initialTitle || '', settings.initialStatus || 'draft' );
			setLoading( false );

			apiFetch( { path: settings.apiPath + '?context=edit&_fields=id,title,content,status,slug,template,meta,modified' } ).then( function ( post ) {
				var restRaw = post && post.content && typeof post.content.raw === 'string' ? post.content.raw : bootstrapRaw;
				var restTitle = titleText( post && post.title );
				var restStatus = post && post.status ? post.status : settings.initialStatus || 'draft';
				if ( restRaw !== bootstrapRaw || restTitle !== ( settings.initialTitle || '' ) || restStatus !== ( settings.initialStatus || 'draft' ) ) {
					applyLoadedContent( restRaw, restTitle, restStatus );
				}
			} ).catch( function ( error ) {
				setNotice( { type: 'warning', text: __( 'Cresco loaded the Page from WordPress directly, but live REST refresh was unavailable.', 'cresco-canvas' ) + ( error && error.message ? ' ' + error.message : '' ) } );
			} );

			apiFetch( { path: '/cresco-canvas/v1/settings' } ).then( function ( value ) {
				setGlobalSettings( value );
				setGlobalText( JSON.stringify( value, null, 2 ) );
			} ).catch( function () {} );
		}, [] );

		useEffect( function () {
			function beforeUnload( event ) {
				if ( ! dirty ) return;
				event.preventDefault();
				event.returnValue = '';
			}
			window.addEventListener( 'beforeunload', beforeUnload );
			return function () { window.removeEventListener( 'beforeunload', beforeUnload ); };
		}, [ dirty ] );

		useEffect( function () {
			if ( ! loaded ) return;
			var unsubscribe = wp.data.subscribe( function () {
				var editor = wp.data.select( 'core/block-editor' );
				var id = editor && editor.getSelectedBlockClientId ? editor.getSelectedBlockClientId() : null;
				setSelectedClientId( function ( current ) {
					if ( current === id ) return current;
					if ( id ) setMode( 'edit' );
					return id;
				} );
			} );
			return unsubscribe;
		}, [ loaded ] );

		function changeBlocks( nextBlocks ) {
			nextBlocks = Array.isArray( nextBlocks ) ? nextBlocks : [];
			setBlocks( nextBlocks );
			setDirty( serialize( nextBlocks ) !== lastSavedRef.current );
			scheduleSnapshot( nextBlocks );
		}

		function insertWidget( definition ) {
			if ( ! definition || typeof definition.create !== 'function' ) return;
			var nextBlock = definition.create();
			if ( ! nextBlock ) return;
			var next = blocks.concat( [ nextBlock ] );
			changeBlocks( next );
			bumpUsage( definition.id );
			window.requestAnimationFrame( function () {
				var dispatch = wp.data.dispatch( 'core/block-editor' );
				if ( dispatch && dispatch.selectBlock ) dispatch.selectBlock( nextBlock.clientId );
			} );
		}

		function savePage() {
			if ( saving ) return;
			var content = serialize( blocks );
			setSaving( true );
			setNotice( null );
			apiFetch( {
				path: settings.apiPath,
				method: 'POST',
				data: { title: title, content: content, status: status }
			} ).then( function ( post ) {
				lastSavedRef.current = content;
				setDirty( false );
				if ( post && post.status ) setStatus( post.status );
				setNotice( { type: 'success', text: __( 'Page saved to WordPress. Gutenberg will read the same content.', 'cresco-canvas' ) } );
			} ).catch( function ( error ) {
				setNotice( { type: 'error', text: error && error.message ? error.message : __( 'Page could not be saved.', 'cresco-canvas' ) } );
			} ).finally( function () { setSaving( false ); } );
		}

		function reloadFromWordPress() {
			if ( dirty && ! window.confirm( __( 'Discard unsaved Cresco changes and reload the latest WordPress content?', 'cresco-canvas' ) ) ) return;
			setLoading( true );
			apiFetch( { path: settings.apiPath + '?context=edit&_fields=id,title,content,status' } ).then( function ( post ) {
				var raw = post && post.content && typeof post.content.raw === 'string' ? post.content.raw : settings.initialContent || '';
				applyLoadedContent( raw, titleText( post.title ), post.status || 'draft' );
				setNotice( { type: 'success', text: __( 'Latest WordPress/Gutenberg content loaded.', 'cresco-canvas' ) } );
			} ).catch( function ( error ) {
				setNotice( { type: 'error', text: error && error.message ? error.message : __( 'Could not reload WordPress content.', 'cresco-canvas' ) } );
			} ).finally( function () { setLoading( false ); } );
		}

		function undo() {
			window.clearTimeout( historyTimerRef.current );
			if ( historyIndexRef.current <= 0 ) return;
			historyIndexRef.current -= 1;
			changeBlocks( normalizeContent( historyRef.current[ historyIndexRef.current ] || '' ) );
		}

		function redo() {
			window.clearTimeout( historyTimerRef.current );
			if ( historyIndexRef.current >= historyRef.current.length - 1 ) return;
			historyIndexRef.current += 1;
			changeBlocks( normalizeContent( historyRef.current[ historyIndexRef.current ] || '' ) );
		}

		function removeBlock( clientId ) {
			if ( ! clientId ) return;
			changeBlocks( removeFromTree( blocks, clientId ) );
			var dispatch = wp.data.dispatch( 'core/block-editor' );
			if ( dispatch && dispatch.clearSelectedBlock ) dispatch.clearSelectedBlock();
		}

		function duplicateBlock( clientId, parentId, index ) {
			var original = findBlock( blocks, clientId );
			var copy = cloneBlock( original );
			if ( ! copy ) return;
			var next = transformSiblingList( blocks, parentId, function ( siblings ) {
				var result = siblings.slice();
				result.splice( index + 1, 0, copy );
				return result;
			} );
			changeBlocks( next );
			window.requestAnimationFrame( function () {
				var dispatch = wp.data.dispatch( 'core/block-editor' );
				if ( dispatch && dispatch.selectBlock ) dispatch.selectBlock( copy.clientId );
			} );
		}

		function moveBlock( clientId, parentId, index, direction ) {
			var next = transformSiblingList( blocks, parentId, function ( siblings ) {
				var target = index + direction;
				if ( target < 0 || target >= siblings.length ) return siblings;
				var result = siblings.slice();
				var item = result.splice( index, 1 )[ 0 ];
				result.splice( target, 0, item );
				return result;
			} );
			if ( next !== blocks ) changeBlocks( next );
		}

		function saveGlobal() {
			try {
				var parsed = JSON.parse( globalText );
				apiFetch( { path: '/cresco-canvas/v1/settings', method: 'POST', data: parsed } ).then( function ( value ) {
					setGlobalSettings( value );
					setGlobalText( JSON.stringify( value, null, 2 ) );
					setNotice( { type: 'success', text: __( 'Global Design saved.', 'cresco-canvas' ) } );
				} ).catch( function ( error ) { setNotice( { type: 'error', text: error.message || __( 'Global Design could not be saved.', 'cresco-canvas' ) } ); } );
			} catch ( error ) {
				setNotice( { type: 'error', text: __( 'Global Design JSON is invalid.', 'cresco-canvas' ) } );
			}
		}

		var usage = readUsage();
		var filteredWidgets = useMemo( function () {
			var term = String( search || '' ).trim().toLowerCase();
			return WIDGETS.filter( function ( item ) { return ! term || item.label.toLowerCase().indexOf( term ) !== -1 || item.id.indexOf( term ) !== -1; } ).sort( function ( a, b ) {
				return ( Number( usage[ b.id ] ) || 0 ) - ( Number( usage[ a.id ] ) || 0 );
			} );
		}, [ search, blocks.length ] );

		var stageWidth = DEFAULT_WIDTHS[ device ] || 1440;
		var scale = 1;
		if ( zoom !== 'fit' ) scale = Math.max( 0.5, Math.min( 1.25, Number( zoom ) / 100 ) );

		function dropOnStage( event ) {
			var id = event.dataTransfer && event.dataTransfer.getData( DRAG_MIME );
			if ( ! id ) return;
			event.preventDefault();
			var found = WIDGETS.find( function ( item ) { return item.id === id; } );
			if ( found ) insertWidget( found );
		}

		function renderWidgets() {
			return h( Fragment, null,
				h( SearchControl, { value: search, onChange: setSearch, placeholder: __( 'Search widgets', 'cresco-canvas' ) } ),
				h( 'div', { className: 'cc-standalone-widget-grid' }, filteredWidgets.map( function ( item ) {
					return h( 'button', {
						key: item.id,
						type: 'button',
						className: 'cc-standalone-widget',
						draggable: true,
						onClick: function () { insertWidget( item ); },
						onDragStart: function ( event ) {
							event.dataTransfer.setData( DRAG_MIME, item.id );
							event.dataTransfer.effectAllowed = 'copy';
						}
					}, h( 'span', { className: 'dashicons dashicons-' + item.icon, 'aria-hidden': 'true' } ), h( 'span', null, item.label ) );
				} ) )
			);
		}

		function renderEdit() {
			if ( ! selectedClientId ) return h( 'div', { className: 'cc-standalone-empty' }, __( 'Select a widget on the canvas to edit it.', 'cresco-canvas' ) );
			return BlockInspector ? h( BlockInspector ) : h( 'div', { className: 'cc-standalone-empty' }, __( 'Inspector is unavailable on this WordPress version.', 'cresco-canvas' ) );
		}

		function renderGlobal() {
			if ( ! globalSettings ) return h( 'div', { className: 'cc-standalone-empty' }, __( 'Global Design is loading or unavailable.', 'cresco-canvas' ) );
			return h( 'div', { className: 'cc-standalone-global' },
				h( TextareaControl, { label: __( 'Global Design JSON', 'cresco-canvas' ), rows: 24, value: globalText, onChange: setGlobalText } ),
				h( Button, { variant: 'primary', onClick: saveGlobal }, __( 'Save Global Design', 'cresco-canvas' ) )
			);
		}

		function renderStructure() {
			var flat = flattenBlocks( blocks );
			if ( ! flat.length ) return h( 'div', { className: 'cc-standalone-structure-empty' }, __( 'No widgets yet.', 'cresco-canvas' ) );
			return flat.map( function ( item ) {
				var siblings = item.parentId ? ( findBlock( blocks, item.parentId ) || {} ).innerBlocks || [] : blocks;
				return h( 'div', { key: item.block.clientId, className: 'cc-standalone-structure-row' + ( selectedClientId === item.block.clientId ? ' is-selected' : '' ), style: { paddingInlineStart: ( 8 + item.depth * 14 ) + 'px' } },
					h( 'button', { type: 'button', className: 'cc-standalone-structure-item', onClick: function () {
						var dispatch = wp.data.dispatch( 'core/block-editor' );
						if ( dispatch && dispatch.selectBlock ) dispatch.selectBlock( item.block.clientId );
					} }, blockLabel( item.block ) ),
					h( 'div', { className: 'cc-standalone-structure-actions' },
						h( 'button', { type: 'button', disabled: item.index <= 0, title: __( 'Move up', 'cresco-canvas' ), onClick: function () { moveBlock( item.block.clientId, item.parentId, item.index, -1 ); } }, '↑' ),
						h( 'button', { type: 'button', disabled: item.index >= siblings.length - 1, title: __( 'Move down', 'cresco-canvas' ), onClick: function () { moveBlock( item.block.clientId, item.parentId, item.index, 1 ); } }, '↓' ),
						h( 'button', { type: 'button', title: __( 'Duplicate', 'cresco-canvas' ), onClick: function () { duplicateBlock( item.block.clientId, item.parentId, item.index ); } }, '⧉' ),
						h( 'button', { type: 'button', className: 'is-destructive', title: __( 'Delete', 'cresco-canvas' ), onClick: function () { removeBlock( item.block.clientId ); } }, '×' )
					)
				);
			} );
		}

		if ( loading ) return h( 'div', { className: 'cc-standalone-loading' }, h( Spinner ), h( 'span', null, __( 'Loading Cresco Canvas…', 'cresco-canvas' ) ) );
		if ( ! loaded ) return h( 'div', { className: 'cc-standalone-loading' }, notice ? notice.text : __( 'Cresco Canvas could not start.', 'cresco-canvas' ) );

		var editorSettings = { hasFixedToolbar: false, focusMode: false, canLockBlocks: true, templateLock: false };
		var canvasContent = h( BlockList );
		if ( ObserveTyping ) canvasContent = h( ObserveTyping, null, canvasContent );
		if ( WritingFlow ) canvasContent = h( WritingFlow, null, canvasContent );
		if ( BlockTools ) canvasContent = h( BlockTools, null, canvasContent );

		return h( BlockEditorProvider, { value: blocks, onInput: changeBlocks, onChange: changeBlocks, settings: editorSettings },
			h( 'div', { className: 'cc-standalone-app' },
				h( 'header', { className: 'cc-standalone-header' },
					h( 'div', { className: 'cc-standalone-brand' }, h( 'span', { className: 'dashicons dashicons-layout' } ), h( 'strong', null, 'Cresco Canvas' ) ),
					h( 'input', { className: 'cc-standalone-title', value: title, onChange: function ( event ) { setTitle( event.target.value ); setDirty( true ); }, 'aria-label': __( 'Page title', 'cresco-canvas' ) } ),
					h( 'div', { className: 'cc-standalone-header-actions' },
						h( Button, { variant: 'tertiary', disabled: historyIndexRef.current <= 0, onClick: undo }, __( 'Undo', 'cresco-canvas' ) ),
						h( Button, { variant: 'tertiary', disabled: historyIndexRef.current >= historyRef.current.length - 1, onClick: redo }, __( 'Redo', 'cresco-canvas' ) ),
						h( Button, { variant: 'tertiary', onClick: reloadFromWordPress }, __( 'Reload', 'cresco-canvas' ) ),
						settings.previewUrl ? h( Button, { variant: 'secondary', href: settings.previewUrl, target: '_blank' }, __( 'Preview', 'cresco-canvas' ) ) : null,
						h( Button, { variant: 'primary', isBusy: saving, disabled: saving || ! dirty, onClick: savePage }, saving ? __( 'Saving…', 'cresco-canvas' ) : dirty ? __( 'Update', 'cresco-canvas' ) : __( 'Saved', 'cresco-canvas' ) )
					)
				),
				notice ? h( Notice, { status: notice.type, isDismissible: true, onRemove: function () { setNotice( null ); } }, notice.text ) : null,
				h( 'div', { className: 'cc-standalone-workspace' },
					h( 'aside', { className: 'cc-standalone-left' },
						h( 'nav', { className: 'cc-standalone-tabs' }, [
							{ id: 'widgets', label: __( 'Widgets', 'cresco-canvas' ), icon: 'screenoptions' },
							{ id: 'edit', label: __( 'Edit', 'cresco-canvas' ), icon: 'edit' },
							{ id: 'global', label: __( 'Global', 'cresco-canvas' ), icon: 'admin-appearance' }
						].map( function ( tab ) { return h( 'button', { key: tab.id, type: 'button', className: mode === tab.id ? 'is-active' : '', onClick: function () { setMode( tab.id ); } }, h( 'span', { className: 'dashicons dashicons-' + tab.icon } ), h( 'span', null, tab.label ) ); } ) ),
						h( 'div', { className: 'cc-standalone-left-content' }, mode === 'widgets' ? renderWidgets() : mode === 'edit' ? renderEdit() : renderGlobal() )
					),
					h( 'main', { className: 'cc-standalone-center' },
						h( 'div', { className: 'cc-standalone-viewport-toolbar' },
							h( 'div', { className: 'cc-standalone-devices' }, DEVICE_ORDER.map( function ( id ) { return h( 'button', { key: id, type: 'button', className: device === id ? 'is-active' : '', onClick: function () { setDevice( id ); } }, DEVICE_LABELS[ id ] ); } ) ),
							h( 'span', { className: 'cc-standalone-width-label' }, stageWidth + 'px' ),
							h( 'select', { value: zoom, onChange: function ( event ) { setZoom( event.target.value ); }, 'aria-label': __( 'Zoom', 'cresco-canvas' ) }, [ 'fit', '50', '75', '100', '125' ].map( function ( item ) { return h( 'option', { key: item, value: item }, item === 'fit' ? __( 'Fit', 'cresco-canvas' ) : item + '%' ); } ) )
						),
						h( 'div', { ref: stageRef, className: 'cc-standalone-stage', onDragOver: function ( event ) { if ( event.dataTransfer && Array.prototype.indexOf.call( event.dataTransfer.types || [], DRAG_MIME ) !== -1 ) event.preventDefault(); }, onDrop: dropOnStage },
							h( 'div', { className: 'cc-standalone-frame-wrap' },
								h( 'div', { className: 'cc-standalone-frame', style: { width: stageWidth + 'px', transform: 'scale(' + scale + ')' } },
									h( 'div', { className: 'editor-styles-wrapper cc-standalone-editor-canvas' }, canvasContent )
								)
							)
						)
					),
					h( 'aside', { className: 'cc-standalone-right' }, h( 'div', { className: 'cc-standalone-right-header' }, __( 'Structure', 'cresco-canvas' ) ), h( 'div', { className: 'cc-standalone-structure' }, renderStructure() ) )
				)
			)
		);
	}

	if ( typeof wp.element.createRoot === 'function' ) {
		wp.element.createRoot( rootNode ).render( h( VisualEditorApp ) );
	} else {
		wp.element.render( h( VisualEditorApp ), rootNode );
	}
} )( window.wp, window, document );