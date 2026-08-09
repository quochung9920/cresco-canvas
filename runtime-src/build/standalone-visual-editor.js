( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.components || ! wp.apiFetch || ! wp.i18n ) return;

	var h = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var SearchControl = wp.components.SearchControl;
	var SelectControl = wp.components.SelectControl;
	var Spinner = wp.components.Spinner;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var settings = window.crescoCanvasStandaloneSettings || {};
	var rootNode = document.getElementById( 'cresco-canvas-standalone-editor' );
	if ( ! rootNode || ! settings.postId || ! settings.aiContextPath || ! settings.sessionPath ) return;

	var DEVICE_ORDER = [ 'wide', 'desktop', 'laptop', 'tablet', 'mobile' ];
	var DEVICE_LABELS = { wide: 'Widescreen', desktop: 'Desktop', laptop: 'Laptop', tablet: 'Tablet', mobile: 'Mobile' };
	var DEFAULT_WIDTHS = Object.assign( { wide: 1920, desktop: 1440, laptop: 1366, tablet: 768, mobile: 390 }, settings.previewWidths || {} );
	var DRAG_MIME = 'application/x-cresco-session-widget';
	var WIDGET_ICONS = { container: 'layout', heading: 'heading', text: 'editor-paragraph', button: 'button', image: 'format-image', list: 'editor-ul', divider: 'minus', spacer: 'image-flip-vertical', columns: 'columns' };
	var STYLE_GROUPS = [
		{ title: 'Size & layout', fields: [ [ 'width', 'Width', '100%' ], [ 'maxWidth', 'Maximum width', '{layout.contentMax}' ], [ 'minHeight', 'Minimum height', '320px' ], [ 'gap', 'Gap', '{spacing.md}' ] ] },
		{ title: 'Spacing', fields: [ [ 'paddingTop', 'Padding top', '{spacing.lg}' ], [ 'paddingRight', 'Padding right', '{spacing.md}' ], [ 'paddingBottom', 'Padding bottom', '{spacing.lg}' ], [ 'paddingLeft', 'Padding left', '{spacing.md}' ], [ 'marginTop', 'Margin top', '0' ], [ 'marginRight', 'Margin right', '0' ], [ 'marginBottom', 'Margin bottom', '{spacing.md}' ], [ 'marginLeft', 'Margin left', '0' ] ] },
		{ title: 'Appearance', fields: [ [ 'color', 'Text color', '{colors.text}' ], [ 'background', 'Background', '{colors.background}' ], [ 'fontSize', 'Font size', '{typography.sizes.base}' ], [ 'fontWeight', 'Font weight', '600' ], [ 'lineHeight', 'Line height', '1.5' ], [ 'letterSpacing', 'Letter spacing', '-0.02em' ], [ 'textAlign', 'Text align', 'center' ], [ 'borderRadius', 'Border radius', '{radius.md}' ], [ 'boxShadow', 'Box shadow', '{shadows.md}' ] ] },
		{ title: 'Advanced', fields: [ [ 'opacity', 'Opacity', '1' ], [ 'position', 'Position', 'relative' ], [ 'top', 'Top', '0' ], [ 'right', 'Right', '0' ], [ 'bottom', 'Bottom', '0' ], [ 'left', 'Left', '0' ], [ 'zIndex', 'Z-index', '1' ], [ 'overflow', 'Overflow', 'hidden' ] ] }
	];

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	function safeObject( value ) {
		return value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	}

	function countNodes( nodes ) {
		return ( nodes || [] ).reduce( function ( count, node ) { return count + 1 + countNodes( node.children || [] ); }, 0 );
	}

	function flattenNodes( nodes, depth, output ) {
		output = output || [];
		( nodes || [] ).forEach( function ( node ) {
			output.push( { node: node, depth: depth || 0 } );
			flattenNodes( node.children || [], ( depth || 0 ) + 1, output );
		} );
		return output;
	}

	function findNode( nodes, id ) {
		for ( var index = 0; index < ( nodes || [] ).length; index += 1 ) {
			var node = nodes[ index ];
			if ( node.id === id ) return node;
			var child = findNode( node.children || [], id );
			if ( child ) return child;
		}
		return null;
	}

	function mapNodes( nodes, id, mapper ) {
		return ( nodes || [] ).map( function ( node ) {
			if ( node.id === id ) return mapper( clone( node ) );
			var next = clone( node );
			next.children = mapNodes( node.children || [], id, mapper );
			return next;
		} );
	}

	function removeNode( nodes, id ) {
		return ( nodes || [] ).filter( function ( node ) { return node.id !== id; } ).map( function ( node ) {
			var next = clone( node );
			next.children = removeNode( node.children || [], id );
			return next;
		} );
	}

	function insertInside( nodes, parentId, child ) {
		return mapNodes( nodes, parentId, function ( parent ) {
			parent.children = ( parent.children || [] ).concat( [ child ] );
			return parent;
		} );
	}

	function makeId( type ) {
		return String( type || 'widget' ).replace( /[^a-z0-9_-]/gi, '-' ).toLowerCase() + '-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 7 );
	}

	function remapIds( node ) {
		var next = clone( node );
		next.id = makeId( next.type );
		next.children = ( next.children || [] ).map( remapIds );
		return next;
	}

	function readPath( object, path ) {
		return String( path || '' ).split( '.' ).reduce( function ( current, part ) {
			return current && Object.prototype.hasOwnProperty.call( current, part ) ? current[ part ] : undefined;
		}, object );
	}

	function resolveToken( value, globalConfig ) {
		if ( typeof value !== 'string' ) return value;
		var match = value.match( /^\{([a-zA-Z0-9._-]+)\}$/ );
		if ( ! match ) return value;
		var resolved = readPath( globalConfig, match[ 1 ] );
		return resolved === undefined || resolved === null ? value : resolved;
	}

	function propsStyle( node ) {
		var props = safeObject( node.props );
		if ( node.type === 'container' ) {
			var layout = props.layout || 'block';
			var contentWidth = props.contentWidth || 'full';
			var style = {
				display: layout,
				width: '100%',
				maxWidth: contentWidth === 'boxed' ? 'var(--cc-container-max)' : 'none',
				marginLeft: contentWidth === 'boxed' ? 'auto' : '0',
				marginRight: contentWidth === 'boxed' ? 'auto' : '0'
			};
			if ( layout === 'flex' ) {
				style.flexDirection = props.direction || 'column';
				style.alignItems = props.align || 'stretch';
				style.justifyContent = props.justify || 'flex-start';
			}
			if ( layout === 'grid' ) style.gridTemplateColumns = 'repeat(' + Number( props.columns || 2 ) + ', minmax(0, 1fr))';
			return style;
		}
		if ( node.type === 'columns' ) return { display: 'grid', gridTemplateColumns: 'repeat(' + Number( props.columns || 2 ) + ', minmax(0, 1fr))', gap: 'var(--cc-grid-gap)' };
		if ( node.type === 'spacer' ) return { minHeight: props.height || '48px' };
		return {};
	}

	function resolvedStyle( node, device, globalConfig ) {
		var result = Object.assign( {}, propsStyle( node ), safeObject( node.style ) );
		if ( device !== 'wide' ) result = Object.assign( result, safeObject( safeObject( node.responsive )[ device ] ) );
		Object.keys( result ).forEach( function ( key ) { result[ key ] = resolveToken( result[ key ], globalConfig ); } );
		return result;
	}

	function scopedCss( nodes, device ) {
		var css = '';
		( nodes || [] ).forEach( function ( node ) {
			var selector = '[data-cresco-id="' + String( node.id ).replace( /[^a-zA-Z0-9_-]/g, '-' ) + '"]';
			var custom = safeObject( node.customCSS );
			if ( custom.base ) css += String( custom.base ).split( '&' ).join( selector );
			if ( device !== 'wide' && custom[ device ] ) css += String( custom[ device ] ).split( '&' ).join( selector );
			css += scopedCss( node.children || [], device );
		} );
		return css;
	}

	function defaultProps( definition ) {
		var output = {};
		Object.keys( safeObject( definition.props ) ).forEach( function ( key ) {
			var schema = definition.props[ key ];
			output[ key ] = clone( schema.default === undefined ? '' : schema.default );
		} );
		return output;
	}

	function createWidgetNode( type, catalog ) {
		var definition = catalog[ type ];
		if ( ! definition ) return null;
		return { id: makeId( type ), type: type, props: defaultProps( definition ), style: {}, responsive: {}, customCSS: {}, children: [] };
	}

	function copyText( value ) {
		var text = typeof value === 'string' ? value : JSON.stringify( value, null, 2 );
		if ( navigator.clipboard && navigator.clipboard.writeText ) return navigator.clipboard.writeText( text );
		return new Promise( function ( resolve, reject ) {
			try {
				var field = document.createElement( 'textarea' );
				field.value = text;
				field.setAttribute( 'readonly', 'readonly' );
				field.style.position = 'fixed';
				field.style.opacity = '0';
				document.body.appendChild( field );
				field.select();
				document.execCommand( 'copy' );
				field.remove();
				resolve();
			} catch ( error ) { reject( error ); }
		} );
	}

	function VisualEditorApp() {
		var loadingPair = useState( true ), loading = loadingPair[ 0 ], setLoading = loadingPair[ 1 ];
		var savingPair = useState( false ), saving = savingPair[ 0 ], setSaving = savingPair[ 1 ];
		var dirtyPair = useState( false ), dirty = dirtyPair[ 0 ], setDirty = dirtyPair[ 1 ];
		var noticePair = useState( null ), notice = noticePair[ 0 ], setNotice = noticePair[ 1 ];
		var contextPair = useState( null ), aiContext = contextPair[ 0 ], setAiContext = contextPair[ 1 ];
		var sessionPair = useState( null ), session = sessionPair[ 0 ], setSession = sessionPair[ 1 ];
		var titlePair = useState( settings.initialTitle || '' ), title = titlePair[ 0 ], setTitle = titlePair[ 1 ];
		var modePair = useState( 'widgets' ), mode = modePair[ 0 ], setMode = modePair[ 1 ];
		var searchPair = useState( '' ), search = searchPair[ 0 ], setSearch = searchPair[ 1 ];
		var selectedPair = useState( null ), selectedId = selectedPair[ 0 ], setSelectedId = selectedPair[ 1 ];
		var devicePair = useState( 'wide' ), device = devicePair[ 0 ], setDevice = devicePair[ 1 ];
		var zoomPair = useState( 'fit' ), zoom = zoomPair[ 0 ], setZoom = zoomPair[ 1 ];
		var importPair = useState( '' ), importText = importPair[ 0 ], setImportText = importPair[ 1 ];
		var importPreviewPair = useState( null ), importPreview = importPreviewPair[ 0 ], setImportPreview = importPreviewPair[ 1 ];
		var stageSizePair = useState( 0 ), stageSize = stageSizePair[ 0 ], setStageSize = stageSizePair[ 1 ];
		var stageRef = useRef( null );
		var historyRef = useRef( [] );
		var historyIndexRef = useRef( -1 );
		var snapshotTimerRef = useRef( null );

		var globalConfig = aiContext ? safeObject( aiContext.global ) : {};
		var catalog = aiContext ? safeObject( aiContext.widgets ) : {};
		var nodes = session ? session.nodes || [] : [];
		var selected = selectedId ? findNode( nodes, selectedId ) : null;

		function storeSnapshot( nextSession ) {
			var json = JSON.stringify( nextSession );
			if ( historyRef.current[ historyIndexRef.current ] === json ) return;
			historyRef.current = historyRef.current.slice( 0, historyIndexRef.current + 1 );
			historyRef.current.push( json );
			if ( historyRef.current.length > 80 ) historyRef.current.shift();
			historyIndexRef.current = historyRef.current.length - 1;
		}

		function commitSession( nextSession, immediate ) {
			setSession( nextSession );
			setDirty( true );
			window.clearTimeout( snapshotTimerRef.current );
			if ( immediate ) storeSnapshot( nextSession );
			else snapshotTimerRef.current = window.setTimeout( function () { storeSnapshot( nextSession ); }, 350 );
		}

		useEffect( function () {
			apiFetch( { path: settings.aiContextPath } ).then( function ( value ) {
				setAiContext( value );
				setSession( value.session );
				setTitle( value.postTitle || settings.initialTitle || '' );
				historyRef.current = [ JSON.stringify( value.session ) ];
				historyIndexRef.current = 0;
			} ).catch( function ( error ) {
				setNotice( { status: 'error', text: error && error.message ? error.message : __( 'Cresco Session could not be loaded.', 'cresco-canvas' ) } );
			} ).finally( function () { setLoading( false ); } );
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
			if ( ! stageRef.current ) return;
			function measure() { if ( stageRef.current ) setStageSize( stageRef.current.clientWidth ); }
			measure();
			if ( typeof ResizeObserver !== 'undefined' ) {
				var observer = new ResizeObserver( measure );
				observer.observe( stageRef.current );
				return function () { observer.disconnect(); };
			}
			window.addEventListener( 'resize', measure );
			return function () { window.removeEventListener( 'resize', measure ); };
		}, [ loading ] );

		function changeNodes( nextNodes, immediate ) {
			var next = Object.assign( {}, session, { nodes: nextNodes } );
			commitSession( next, immediate );
		}

		function updateSelected( updater, immediate ) {
			if ( ! selectedId ) return;
			changeNodes( mapNodes( nodes, selectedId, updater ), immediate );
		}

		function insertType( type, parentId ) {
			var node = createWidgetNode( type, catalog );
			if ( ! node ) return;
			var parent = parentId ? findNode( nodes, parentId ) : selected;
			var canContain = parent && catalog[ parent.type ] && catalog[ parent.type ].allowsChildren;
			var nextNodes = canContain ? insertInside( nodes, parent.id, node ) : nodes.concat( [ node ] );
			changeNodes( nextNodes, true );
			setSelectedId( node.id );
			setMode( 'edit' );
		}

		function deleteSelected() {
			if ( ! selectedId ) return;
			changeNodes( removeNode( nodes, selectedId ), true );
			setSelectedId( null );
			setMode( 'widgets' );
		}

		function duplicateSelected() {
			if ( ! selected ) return;
			var copy = remapIds( selected );
			changeNodes( nodes.concat( [ copy ] ), true );
			setSelectedId( copy.id );
		}

		function updateProp( key, value ) {
			updateSelected( function ( node ) { node.props = Object.assign( {}, node.props, ( function () { var patch = {}; patch[ key ] = value; return patch; } )() ); return node; } );
		}

		function styleBucket( node ) {
			if ( device === 'wide' ) return safeObject( node.style );
			return safeObject( safeObject( node.responsive )[ device ] );
		}

		function updateStyle( key, value ) {
			updateSelected( function ( node ) {
				if ( device === 'wide' ) {
					var base = Object.assign( {}, node.style );
					if ( value === '' ) delete base[ key ]; else base[ key ] = value;
					node.style = base;
				} else {
					var responsive = Object.assign( {}, node.responsive );
					var bucket = Object.assign( {}, responsive[ device ] );
					if ( value === '' ) delete bucket[ key ]; else bucket[ key ] = value;
					if ( Object.keys( bucket ).length ) responsive[ device ] = bucket; else delete responsive[ device ];
					node.responsive = responsive;
				}
				return node;
			} );
		}

		function updateCustomCss( value ) {
			updateSelected( function ( node ) {
				var custom = Object.assign( {}, node.customCSS );
				var key = device === 'wide' ? 'base' : device;
				if ( value === '' ) delete custom[ key ]; else custom[ key ] = value;
				node.customCSS = custom;
				return node;
			} );
		}

		function resetDeviceOverrides() {
			if ( ! selected || device === 'wide' ) return;
			updateSelected( function ( node ) {
				var responsive = Object.assign( {}, node.responsive );
				var custom = Object.assign( {}, node.customCSS );
				delete responsive[ device ];
				delete custom[ device ];
				node.responsive = responsive;
				node.customCSS = custom;
				return node;
			}, true );
		}

		function savePage() {
			if ( saving || ! session ) return;
			window.clearTimeout( snapshotTimerRef.current );
			storeSnapshot( session );
			setSaving( true );
			setNotice( null );
			apiFetch( { path: settings.sessionPath, method: 'POST', data: { session: session, postTitle: title } } ).then( function ( result ) {
				setSession( result.session );
				setDirty( false );
				setNotice( { status: 'success', text: __( 'Cresco Session saved.', 'cresco-canvas' ) } );
			} ).catch( function ( error ) {
				setNotice( { status: 'error', text: error && error.message ? error.message : __( 'Cresco Session could not be saved.', 'cresco-canvas' ) } );
			} ).finally( function () { setSaving( false ); } );
		}

		function undo() {
			window.clearTimeout( snapshotTimerRef.current );
			storeSnapshot( session );
			if ( historyIndexRef.current <= 0 ) return;
			historyIndexRef.current -= 1;
			setSession( JSON.parse( historyRef.current[ historyIndexRef.current ] ) );
			setDirty( true );
		}

		function redo() {
			window.clearTimeout( snapshotTimerRef.current );
			if ( historyIndexRef.current >= historyRef.current.length - 1 ) return;
			historyIndexRef.current += 1;
			setSession( JSON.parse( historyRef.current[ historyIndexRef.current ] ) );
			setDirty( true );
		}

		function copyPayload( payload, label ) {
			copyText( payload ).then( function () { setNotice( { status: 'success', text: label } ); } ).catch( function () { setNotice( { status: 'error', text: __( 'Clipboard access failed.', 'cresco-canvas' ) } ); } );
		}

		function currentAiContext() {
			return Object.assign( {}, aiContext, { session: session, postTitle: title } );
		}

		function validateImport() {
			setImportPreview( null );
			var parsed;
			try { parsed = JSON.parse( importText ); } catch ( error ) { setNotice( { status: 'error', text: __( 'Import JSON is invalid.', 'cresco-canvas' ) } ); return; }
			var candidate = parsed && parsed.session && parsed.session.schema ? parsed.session : parsed;
			apiFetch( { path: settings.validatePath, method: 'POST', data: candidate } ).then( function ( result ) {
				setImportPreview( { session: result.session, nodeCount: result.nodeCount, oldCount: countNodes( nodes ) } );
				setNotice( { status: 'success', text: __( 'Session is valid. Review the summary before applying.', 'cresco-canvas' ) } );
			} ).catch( function ( error ) { setNotice( { status: 'error', text: error && error.message ? error.message : __( 'Session validation failed.', 'cresco-canvas' ) } ); } );
		}

		function applyImport() {
			if ( ! importPreview ) return;
			commitSession( importPreview.session, true );
			setSelectedId( null );
			setImportPreview( null );
			setMode( 'widgets' );
			setNotice( { status: 'success', text: __( 'Imported into the current Cresco Editor session. Save when ready.', 'cresco-canvas' ) } );
		}

		function chooseImage() {
			if ( ! window.wp || ! window.wp.media || ! selected || selected.type !== 'image' ) return;
			var frame = window.wp.media( { title: __( 'Choose image', 'cresco-canvas' ), button: { text: __( 'Use image', 'cresco-canvas' ) }, multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				updateSelected( function ( node ) { node.props = Object.assign( {}, node.props, { url: attachment.url || '', alt: attachment.alt || node.props.alt || '' } ); return node; }, true );
			} );
			frame.open();
		}

		function renderWidget( node ) {
			var isSelected = selectedId === node.id;
			var definition = catalog[ node.type ] || {};
			var common = {
				key: node.id,
				className: 'cc-canvas-node cc-canvas-widget-' + node.type + ( isSelected ? ' is-selected' : '' ),
				'data-cresco-id': node.id,
				'data-cresco-widget': node.type,
				'data-cresco-content-width': node.type === 'container' ? ( safeObject( node.props ).contentWidth || 'full' ) : undefined,
				style: resolvedStyle( node, device, globalConfig ),
				onClick: function ( event ) { event.preventDefault(); event.stopPropagation(); setSelectedId( node.id ); setMode( 'edit' ); },
				onDragOver: definition.allowsChildren ? function ( event ) { if ( event.dataTransfer && Array.prototype.indexOf.call( event.dataTransfer.types || [], DRAG_MIME ) !== -1 ) { event.preventDefault(); event.stopPropagation(); } } : undefined,
				onDrop: definition.allowsChildren ? function ( event ) { var type = event.dataTransfer && event.dataTransfer.getData( DRAG_MIME ); if ( type ) { event.preventDefault(); event.stopPropagation(); insertType( type, node.id ); } } : undefined
			};
			var props = safeObject( node.props );
			if ( node.type === 'heading' ) return h( 'h' + Math.max( 1, Math.min( 6, Number( props.level || 2 ) ) ), common, props.text || __( 'Heading', 'cresco-canvas' ) );
			if ( node.type === 'text' ) return h( 'p', common, props.text || __( 'Add your text.', 'cresco-canvas' ) );
			if ( node.type === 'button' ) return h( 'a', Object.assign( {}, common, { href: props.url || '#', onClick: function ( event ) { event.preventDefault(); event.stopPropagation(); setSelectedId( node.id ); setMode( 'edit' ); } } ), h( 'span', { 'data-cresco-part': 'text' }, props.text || __( 'Button', 'cresco-canvas' ) ) );
			if ( node.type === 'image' ) return h( 'figure', common, props.url ? h( 'img', { src: props.url, alt: props.alt || '', 'data-cresco-part': 'media' } ) : h( 'div', { className: 'cc-canvas-image-placeholder', 'data-cresco-part': 'media' }, __( 'Choose an image', 'cresco-canvas' ) ), props.caption ? h( 'figcaption', { 'data-cresco-part': 'caption' }, props.caption ) : null );
			if ( node.type === 'list' ) return h( 'ul', common, ( props.items || [] ).map( function ( item, index ) { return h( 'li', { key: index, 'data-cresco-part': 'item' }, item ); } ) );
			if ( node.type === 'divider' ) return h( 'hr', common );
			if ( node.type === 'spacer' ) return h( 'div', Object.assign( {}, common, { 'aria-hidden': true } ) );
			var children = ( node.children || [] ).map( renderWidget );
			if ( ! children.length ) children = [ h( 'div', { key: 'empty', className: 'cc-canvas-container-empty' }, __( 'Drop widgets here', 'cresco-canvas' ) ) ];
			return h( 'div', common, children );
		}

		function renderWidgetsPanel() {
			var term = String( search || '' ).trim().toLowerCase();
			var keys = Object.keys( catalog ).filter( function ( key ) { return ! term || key.indexOf( term ) !== -1 || String( catalog[ key ].label || '' ).toLowerCase().indexOf( term ) !== -1; } );
			return h( Fragment, null,
				h( SearchControl, { value: search, onChange: setSearch, placeholder: __( 'Search widgets', 'cresco-canvas' ) } ),
				h( 'div', { className: 'cc-standalone-widget-grid' }, keys.map( function ( type ) {
					return h( 'button', { key: type, type: 'button', className: 'cc-standalone-widget', draggable: true, onClick: function () { insertType( type ); }, onDragStart: function ( event ) { event.dataTransfer.setData( DRAG_MIME, type ); event.dataTransfer.effectAllowed = 'copy'; } }, h( 'span', { className: 'dashicons dashicons-' + ( WIDGET_ICONS[ type ] || 'block-default' ), 'aria-hidden': true } ), h( 'span', null, catalog[ type ].label || type ) );
				} ) )
			);
		}

		function renderDeviceSwitcher() {
			return h( 'div', { className: 'cc-inspector-device-switcher' }, DEVICE_ORDER.map( function ( id ) { return h( 'button', { key: id, type: 'button', className: device === id ? 'is-active' : '', onClick: function () { setDevice( id ); }, 'aria-pressed': device === id }, DEVICE_LABELS[ id ] ); } ) );
		}

		function contentFields() {
			if ( ! selected ) return null;
			var props = safeObject( selected.props );
			if ( selected.type === 'heading' ) return h( Fragment, null, h( TextareaControl, { label: __( 'Text', 'cresco-canvas' ), value: props.text || '', onChange: function ( value ) { updateProp( 'text', value ); } } ), h( SelectControl, { label: __( 'HTML heading', 'cresco-canvas' ), value: String( props.level || 2 ), options: [ 1, 2, 3, 4, 5, 6 ].map( function ( level ) { return { label: 'H' + level, value: String( level ) }; } ), onChange: function ( value ) { updateProp( 'level', Number( value ) ); } } ) );
			if ( selected.type === 'text' ) return h( TextareaControl, { label: __( 'Text', 'cresco-canvas' ), rows: 6, value: props.text || '', onChange: function ( value ) { updateProp( 'text', value ); } } );
			if ( selected.type === 'button' ) return h( Fragment, null, h( TextControl, { label: __( 'Label', 'cresco-canvas' ), value: props.text || '', onChange: function ( value ) { updateProp( 'text', value ); } } ), h( TextControl, { label: __( 'URL', 'cresco-canvas' ), value: props.url || '', onChange: function ( value ) { updateProp( 'url', value ); } } ), h( SelectControl, { label: __( 'Target', 'cresco-canvas' ), value: props.target || '_self', options: [ { label: __( 'Same tab', 'cresco-canvas' ), value: '_self' }, { label: __( 'New tab', 'cresco-canvas' ), value: '_blank' } ], onChange: function ( value ) { updateProp( 'target', value ); } } ) );
			if ( selected.type === 'image' ) return h( Fragment, null, h( TextControl, { label: __( 'Image URL', 'cresco-canvas' ), value: props.url || '', onChange: function ( value ) { updateProp( 'url', value ); } } ), h( Button, { variant: 'secondary', onClick: chooseImage }, __( 'Choose from Media Library', 'cresco-canvas' ) ), h( TextControl, { label: __( 'Alternative text', 'cresco-canvas' ), value: props.alt || '', onChange: function ( value ) { updateProp( 'alt', value ); } } ), h( TextareaControl, { label: __( 'Caption', 'cresco-canvas' ), value: props.caption || '', onChange: function ( value ) { updateProp( 'caption', value ); } } ) );
			if ( selected.type === 'list' ) return h( TextareaControl, { label: __( 'Items, one per line', 'cresco-canvas' ), rows: 7, value: ( props.items || [] ).join( '\n' ), onChange: function ( value ) { updateProp( 'items', value.split( /\r?\n/ ).filter( Boolean ) ); } } );
			if ( selected.type === 'spacer' ) return h( TextControl, { label: __( 'Height', 'cresco-canvas' ), value: props.height || '48px', onChange: function ( value ) { updateProp( 'height', value ); } } );
			if ( selected.type === 'columns' ) return h( TextControl, { label: __( 'Columns', 'cresco-canvas' ), type: 'number', value: String( props.columns || 2 ), onChange: function ( value ) { updateProp( 'columns', Math.max( 1, Math.min( 12, Number( value ) || 1 ) ) ); } } );
			if ( selected.type === 'container' ) return h( Fragment, null,
				h( SelectControl, { label: __( 'Content width', 'cresco-canvas' ), value: props.contentWidth || 'full', options: [ { label: __( 'Full Width', 'cresco-canvas' ), value: 'full' }, { label: __( 'Boxed', 'cresco-canvas' ), value: 'boxed' } ], help: props.contentWidth === 'boxed' ? __( 'Uses the Global container width and centers the container.', 'cresco-canvas' ) : __( 'Stretches the container to the full available width.', 'cresco-canvas' ), onChange: function ( value ) { updateProp( 'contentWidth', value ); } } ),
				h( SelectControl, { label: __( 'Layout', 'cresco-canvas' ), value: props.layout || 'block', options: [ 'block', 'flex', 'grid' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateProp( 'layout', value ); } } ),
				props.layout === 'flex' ? h( Fragment, null, h( SelectControl, { label: __( 'Direction', 'cresco-canvas' ), value: props.direction || 'column', options: [ 'column', 'row' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { updateProp( 'direction', value ); } } ), h( SelectControl, { label: __( 'Align items', 'cresco-canvas' ), value: props.align || 'stretch', options: [ 'stretch', 'flex-start', 'center', 'flex-end', 'baseline' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { updateProp( 'align', value ); } } ), h( SelectControl, { label: __( 'Justify', 'cresco-canvas' ), value: props.justify || 'flex-start', options: [ 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { updateProp( 'justify', value ); } } ) ) : null,
				props.layout === 'grid' ? h( TextControl, { label: __( 'Columns', 'cresco-canvas' ), type: 'number', value: String( props.columns || 2 ), onChange: function ( value ) { updateProp( 'columns', Math.max( 1, Math.min( 12, Number( value ) || 1 ) ) ); } } ) : null
			);
			return h( 'p', { className: 'cc-standalone-help' }, __( 'This widget has no content fields.', 'cresco-canvas' ) );
		}

		function renderEditPanel() {
			if ( ! selected ) return h( 'div', { className: 'cc-standalone-empty' }, __( 'Select a widget on the canvas or in Structure.', 'cresco-canvas' ) );
			var bucket = styleBucket( selected );
			var customKey = device === 'wide' ? 'base' : device;
			return h( 'div', { className: 'cc-inspector' },
				h( 'div', { className: 'cc-inspector-header' }, h( 'div', null, h( 'strong', null, catalog[ selected.type ] ? catalog[ selected.type ].label : selected.type ), h( 'code', null, selected.id ) ), h( 'div', { className: 'cc-inspector-header-actions' }, h( Button, { size: 'small', icon: 'admin-page', label: __( 'Duplicate', 'cresco-canvas' ), onClick: duplicateSelected } ), h( Button, { size: 'small', icon: 'trash', label: __( 'Delete', 'cresco-canvas' ), isDestructive: true, onClick: deleteSelected } ) ) ),
				renderDeviceSwitcher(),
				h( 'section', { className: 'cc-inspector-section' }, h( 'h3', null, __( 'Content', 'cresco-canvas' ) ), contentFields() ),
				STYLE_GROUPS.map( function ( group ) { return h( 'section', { className: 'cc-inspector-section', key: group.title }, h( 'h3', null, group.title ), h( 'div', { className: 'cc-inspector-grid' }, group.fields.map( function ( field ) { return h( TextControl, { key: field[ 0 ], label: field[ 1 ], placeholder: field[ 2 ], value: bucket[ field[ 0 ] ] || '', onChange: function ( value ) { updateStyle( field[ 0 ], value ); } } ); } ) ) ); } ),
				h( 'section', { className: 'cc-inspector-section' }, h( 'div', { className: 'cc-inspector-section-heading' }, h( 'h3', null, __( 'Custom CSS', 'cresco-canvas' ) ), h( 'span', null, device === 'wide' ? __( 'Base', 'cresco-canvas' ) : DEVICE_LABELS[ device ] ) ), h( 'p', { className: 'cc-standalone-help' }, __( 'Use & for this widget. Use data-cresco-part selectors for stable inner parts. @media and url() are intentionally blocked.', 'cresco-canvas' ) ), h( TextareaControl, { rows: 9, value: safeObject( selected.customCSS )[ customKey ] || '', placeholder: '&:hover { transform: translateY(-3px); }', onChange: updateCustomCss } ) ),
				device !== 'wide' ? h( Button, { variant: 'secondary', onClick: resetDeviceOverrides }, __( 'Reset device overrides', 'cresco-canvas' ) ) : null
			);
		}

		function renderGlobalPanel() {
			if ( ! aiContext ) return null;
			var colors = safeObject( globalConfig.colors );
			var layout = safeObject( globalConfig.layout );
			var breakpoints = safeObject( globalConfig.breakpoints );
			return h( 'div', { className: 'cc-global-panel' },
				h( 'div', { className: 'cc-global-panel__header' }, h( 'strong', null, __( 'Global Design', 'cresco-canvas' ) ), h( Button, { variant: 'secondary', onClick: function () { copyPayload( globalConfig, __( 'Global Config copied.', 'cresco-canvas' ) ); } }, __( 'Copy Global Config', 'cresco-canvas' ) ) ),
				h( 'section', { className: 'cc-global-card' }, h( 'h3', null, __( 'Colors', 'cresco-canvas' ) ), Object.keys( colors ).slice( 0, 12 ).map( function ( key ) { return h( 'div', { className: 'cc-global-token', key: key }, h( 'span', { className: 'cc-global-swatch', style: { background: colors[ key ] } } ), h( 'code', null, key ), h( 'span', null, colors[ key ] ) ); } ) ),
				h( 'section', { className: 'cc-global-card' }, h( 'h3', null, __( 'Layout', 'cresco-canvas' ) ), Object.keys( layout ).map( function ( key ) { return h( 'div', { className: 'cc-global-kv', key: key }, h( 'code', null, key ), h( 'span', null, layout[ key ] ) ); } ) ),
				h( 'section', { className: 'cc-global-card' }, h( 'h3', null, __( 'Breakpoints', 'cresco-canvas' ) ), Object.keys( breakpoints ).map( function ( key ) { return h( 'div', { className: 'cc-global-kv', key: key }, h( 'code', null, key ), h( 'span', null, breakpoints[ key ] + 'px' ) ); } ) )
			);
		}

		function renderAiPanel() {
			if ( ! aiContext ) return null;
			return h( 'div', { className: 'cc-ai-panel' },
				h( 'section', { className: 'cc-ai-card' }, h( 'h3', null, __( 'Copy for ChatGPT', 'cresco-canvas' ) ), h( 'p', null, __( 'The AI context contains Global Design, the allowed widget contract, CSS rules, and the current Cresco Session.', 'cresco-canvas' ) ), h( 'div', { className: 'cc-ai-actions' }, h( Button, { variant: 'primary', onClick: function () { copyPayload( currentAiContext(), __( 'AI Context copied.', 'cresco-canvas' ) ); } }, __( 'Copy AI Context', 'cresco-canvas' ) ), h( Button, { variant: 'secondary', onClick: function () { copyPayload( session, __( 'Current Session copied.', 'cresco-canvas' ) ); } }, __( 'Copy Session', 'cresco-canvas' ) ), h( Button, { variant: 'secondary', onClick: function () { copyPayload( catalog, __( 'Widget Catalog copied.', 'cresco-canvas' ) ); } }, __( 'Copy Widgets', 'cresco-canvas' ) ) ) ),
				h( 'section', { className: 'cc-ai-card' }, h( 'h3', null, __( 'Import AI Session', 'cresco-canvas' ) ), h( 'p', null, __( 'Paste a cresco-session/v1 object. Cresco validates widget types, stable IDs, style values, responsive values, and scoped Custom CSS before it can be applied.', 'cresco-canvas' ) ), h( TextareaControl, { rows: 15, value: importText, onChange: function ( value ) { setImportText( value ); setImportPreview( null ); }, placeholder: '{\n  "schema": "cresco-session/v1",\n  "version": 1,\n  "documentId": "home",\n  "nodes": []\n}' } ), h( 'div', { className: 'cc-ai-actions' }, h( Button, { variant: 'secondary', disabled: ! importText.trim(), onClick: validateImport }, __( 'Validate import', 'cresco-canvas' ) ), importPreview ? h( Button, { variant: 'primary', onClick: applyImport }, __( 'Apply to Cresco Editor', 'cresco-canvas' ) ) : null ), importPreview ? h( 'div', { className: 'cc-ai-import-summary' }, h( 'strong', null, __( 'Ready to apply', 'cresco-canvas' ) ), h( 'span', null, importPreview.oldCount + ' → ' + importPreview.nodeCount + ' ' + __( 'widgets', 'cresco-canvas' ) ), h( 'span', null, __( 'Nothing is saved until you click Update.', 'cresco-canvas' ) ) ) : null )
			);
		}

		function renderStructure() {
			var flat = flattenNodes( nodes );
			if ( ! flat.length ) return h( 'div', { className: 'cc-standalone-structure-empty' }, __( 'No widgets yet.', 'cresco-canvas' ) );
			return flat.map( function ( item ) { return h( 'button', { key: item.node.id, type: 'button', className: 'cc-standalone-structure-item' + ( selectedId === item.node.id ? ' is-selected' : '' ), style: { paddingInlineStart: ( 12 + item.depth * 14 ) + 'px' }, onClick: function () { setSelectedId( item.node.id ); setMode( 'edit' ); } }, h( 'span', null, catalog[ item.node.type ] ? catalog[ item.node.type ].label : item.node.type ), h( 'small', null, item.node.id ) ); } );
		}

		if ( loading ) return h( 'div', { className: 'cc-standalone-loading' }, h( Spinner ), h( 'span', null, __( 'Loading Cresco Session…', 'cresco-canvas' ) ) );
		if ( ! session || ! aiContext ) return h( 'div', { className: 'cc-standalone-loading' }, notice ? notice.text : __( 'Cresco Canvas could not start.', 'cresco-canvas' ) );

		var stageWidth = DEFAULT_WIDTHS[ device ] || 1440;
		var scale = zoom === 'fit' ? Math.min( 1, Math.max( 0.2, ( Math.max( 320, stageSize ) - 72 ) / stageWidth ) ) : Math.max( 0.25, Math.min( 1.5, Number( zoom ) / 100 ) );
		var previewCss = scopedCss( nodes, device );

		return h( 'div', { className: 'cc-standalone-app' },
			h( 'style', { dangerouslySetInnerHTML: { __html: previewCss } } ),
			h( 'header', { className: 'cc-standalone-header' },
				h( 'div', { className: 'cc-standalone-brand' }, settings.adminPagesUrl ? h( Button, { icon: 'arrow-left-alt2', label: __( 'Back to Pages', 'cresco-canvas' ), href: settings.adminPagesUrl } ) : null, h( 'span', { className: 'dashicons dashicons-layout', 'aria-hidden': true } ), h( 'strong', null, 'Cresco Canvas' ) ),
				h( 'input', { className: 'cc-standalone-title', value: title, onChange: function ( event ) { setTitle( event.target.value ); setDirty( true ); }, 'aria-label': __( 'Page title', 'cresco-canvas' ) } ),
				h( 'div', { className: 'cc-standalone-header-actions' }, h( Button, { variant: 'tertiary', onClick: undo, disabled: historyIndexRef.current <= 0 }, __( 'Undo', 'cresco-canvas' ) ), h( Button, { variant: 'tertiary', onClick: redo, disabled: historyIndexRef.current >= historyRef.current.length - 1 }, __( 'Redo', 'cresco-canvas' ) ), h( Button, { variant: 'secondary', onClick: function () { setMode( 'ai' ); } }, __( 'AI', 'cresco-canvas' ) ), settings.previewUrl ? h( Button, { variant: 'secondary', href: settings.previewUrl, target: '_blank' }, __( 'Preview', 'cresco-canvas' ) ) : null, h( Button, { variant: 'primary', isBusy: saving, disabled: saving || ! dirty, onClick: savePage }, saving ? __( 'Saving…', 'cresco-canvas' ) : dirty ? __( 'Update', 'cresco-canvas' ) : __( 'Saved', 'cresco-canvas' ) ) )
			),
			notice ? h( Notice, { status: notice.status || 'info', isDismissible: true, onRemove: function () { setNotice( null ); } }, notice.text ) : null,
			h( 'div', { className: 'cc-standalone-workspace' },
				h( 'aside', { className: 'cc-standalone-left' },
					h( 'nav', { className: 'cc-standalone-tabs', 'aria-label': __( 'Cresco editor panels', 'cresco-canvas' ) }, [ { id: 'widgets', label: __( 'Widgets', 'cresco-canvas' ), icon: 'screenoptions' }, { id: 'edit', label: __( 'Edit', 'cresco-canvas' ), icon: 'edit' }, { id: 'global', label: __( 'Global', 'cresco-canvas' ), icon: 'admin-appearance' }, { id: 'ai', label: __( 'AI', 'cresco-canvas' ), icon: 'editor-code' } ].map( function ( tab ) { return h( 'button', { key: tab.id, type: 'button', className: mode === tab.id ? 'is-active' : '', onClick: function () { setMode( tab.id ); } }, h( 'span', { className: 'dashicons dashicons-' + tab.icon, 'aria-hidden': true } ), h( 'span', null, tab.label ) ); } ) ),
					h( 'div', { className: 'cc-standalone-left-content' }, mode === 'widgets' ? renderWidgetsPanel() : mode === 'edit' ? renderEditPanel() : mode === 'global' ? renderGlobalPanel() : renderAiPanel() )
				),
				h( 'main', { className: 'cc-standalone-center' },
					h( 'div', { className: 'cc-standalone-viewport-toolbar' }, h( 'div', { className: 'cc-standalone-devices' }, DEVICE_ORDER.map( function ( id ) { return h( 'button', { key: id, type: 'button', className: device === id ? 'is-active' : '', onClick: function () { setDevice( id ); } }, DEVICE_LABELS[ id ] ); } ) ), h( 'span', { className: 'cc-standalone-width-label' }, stageWidth + 'px' ), h( 'select', { value: zoom, onChange: function ( event ) { setZoom( event.target.value ); }, 'aria-label': __( 'Zoom', 'cresco-canvas' ) }, [ 'fit', '50', '75', '100', '125' ].map( function ( item ) { return h( 'option', { key: item, value: item }, item === 'fit' ? __( 'Fit', 'cresco-canvas' ) : item + '%' ); } ) ) ),
					h( 'div', { ref: stageRef, className: 'cc-standalone-stage', onClick: function () { setSelectedId( null ); }, onDragOver: function ( event ) { if ( event.dataTransfer && Array.prototype.indexOf.call( event.dataTransfer.types || [], DRAG_MIME ) !== -1 ) event.preventDefault(); }, onDrop: function ( event ) { var type = event.dataTransfer && event.dataTransfer.getData( DRAG_MIME ); if ( type ) { event.preventDefault(); insertType( type, null ); } } },
						h( 'div', { className: 'cc-standalone-frame-wrap' }, h( 'div', { className: 'cc-standalone-frame', style: { width: stageWidth + 'px', transform: 'scale(' + scale + ')' } }, h( 'div', { className: 'cc-session-canvas', onClick: function ( event ) { event.stopPropagation(); if ( event.target === event.currentTarget ) setSelectedId( null ); } }, nodes.length ? nodes.map( renderWidget ) : h( 'div', { className: 'cc-canvas-empty-state' }, h( 'strong', null, __( 'Start with a widget', 'cresco-canvas' ) ), h( 'p', null, __( 'Drag a widget here, click one in the library, or import a Cresco Session from ChatGPT.', 'cresco-canvas' ) ) ) ) ) )
					)
				),
				h( 'aside', { className: 'cc-standalone-right' }, h( 'div', { className: 'cc-standalone-right-header' }, h( 'span', null, __( 'Structure', 'cresco-canvas' ) ), h( 'small', null, countNodes( nodes ) ) ), h( 'div', { className: 'cc-standalone-structure' }, renderStructure() ) )
			)
		);
	}

	if ( typeof wp.element.createRoot === 'function' ) wp.element.createRoot( rootNode ).render( h( VisualEditorApp ) );
	else wp.element.render( h( VisualEditorApp ), rootNode );
} )( window.wp, window, document );
