( function ( wp, bootstrap ) {
	'use strict';

	if ( ! wp || ! bootstrap || ! wp.components || ! wp.data || ! wp.editor || ! wp.element || ! wp.i18n || ! wp.plugins ) {
		return;
	}

	var Button = wp.components.Button;
	var Modal = wp.components.Modal;
	var Notice = wp.components.Notice;
	var PanelBody = wp.components.PanelBody;
	var Fragment = wp.element.Fragment;
	var createElement = wp.element.createElement;
	var createPortal = wp.element.createPortal;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;

	var EVENT_NAME = 'cresco-canvas-preview-device-change';
	var DEVICE_STORAGE = 'crescoCanvas.previewDevice';
	var ZOOM_STORAGE = 'crescoCanvas.previewZoom';
	var CUSTOM_WIDTH_STORAGE = 'crescoCanvas.previewCustomWidth';
	var TOOLBAR_ID = 'cresco-canvas-stage-toolbar-root';
	var devices = [
		{ id: '4k', width: 1920 },
		{ id: 'desktop', width: 1440 },
		{ id: 'laptop', width: 1200 },
		{ id: 'tablet', width: 768 },
		{ id: 'mobile', width: 390 },
	];
	var labels = {
		'4k': __( '4K', 'cresco-canvas' ),
		desktop: __( 'Desktop', 'cresco-canvas' ),
		laptop: __( 'Laptop', 'cresco-canvas' ),
		tablet: __( 'Tablet', 'cresco-canvas' ),
		mobile: __( 'Mobile', 'cresco-canvas' ),
		custom: __( 'Custom', 'cresco-canvas' ),
	};

	function clamp( value, minimum, maximum ) {
		return Math.min( maximum, Math.max( minimum, Number( value ) || minimum ) );
	}

	function isDevice( value ) {
		return devices.some( function ( device ) { return device.id === value; } ) || value === 'custom';
	}

	function readStorage( key, fallback ) {
		try {
			var value = window.localStorage.getItem( key );
			return value === null ? fallback : value;
		} catch ( error ) {
			return fallback;
		}
	}

	function writeStorage( key, value ) {
		try { window.localStorage.setItem( key, String( value ) ); } catch ( error ) {}
	}

	function readDevice() {
		var value = readStorage( DEVICE_STORAGE, 'desktop' );
		return isDevice( value ) ? value : 'desktop';
	}

	function readZoom() {
		var value = readStorage( ZOOM_STORAGE, 'fit' );
		return [ 'fit', '50', '75', '100', '125' ].indexOf( value ) !== -1 ? value : 'fit';
	}

	function readCustomWidth() {
		return clamp( readStorage( CUSTOM_WIDTH_STORAGE, '960' ), 320, 2560 );
	}

	function deviceWidth( device, customWidth ) {
		if ( device === 'custom' ) return clamp( customWidth, 320, 2560 );
		var match = devices.find( function ( candidate ) { return candidate.id === device; } );
		return match ? match.width : 1440;
	}

	function applyDevice( targetDocument, device, width ) {
		if ( ! targetDocument ) return;
		targetDocument.documentElement.dataset.ccPreviewDevice = device;
		targetDocument.documentElement.style.setProperty( '--cc-preview-width', width + 'px' );
	}

	function appendRefreshToken( url, refreshKey ) {
		if ( ! url ) return '';
		try {
			var parsed = new URL( url, window.location.href );
			parsed.searchParams.set( 'cresco_canvas_refresh', String( refreshKey ) );
			return parsed.toString();
		} catch ( error ) {
			return url;
		}
	}

	function locateStage() {
		var visualEditor = document.querySelector( '.edit-post-visual-editor, .editor-visual-editor, .edit-site-visual-editor' );
		var center = document.querySelector( '.cresco-canvas-workspace-center, .interface-interface-skeleton__content' );
		var host = visualEditor && visualEditor.parentElement ? visualEditor.parentElement : center;
		return { center: center, host: host, visualEditor: visualEditor };
	}

	function PreviewSidebar() {
		var deviceState = useState( readDevice );
		var device = deviceState[ 0 ];
		var setDevice = deviceState[ 1 ];
		var zoomState = useState( readZoom );
		var zoom = zoomState[ 0 ];
		var setZoom = zoomState[ 1 ];
		var customWidthState = useState( readCustomWidth );
		var customWidth = customWidthState[ 0 ];
		var setCustomWidth = customWidthState[ 1 ];
		var computedZoomState = useState( 100 );
		var computedZoom = computedZoomState[ 0 ];
		var setComputedZoom = computedZoomState[ 1 ];
		var toolbarState = useState( null );
		var toolbarMount = toolbarState[ 0 ];
		var setToolbarMount = toolbarState[ 1 ];
		var openState = useState( false );
		var previewOpen = openState[ 0 ];
		var setPreviewOpen = openState[ 1 ];
		var refreshState = useState( 0 );
		var refreshKey = refreshState[ 0 ];
		var setRefreshKey = refreshState[ 1 ];
		var wasSaving = useRef( false );
		var width = deviceWidth( device, customWidth );

		var editorState = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				previewUrl: ( typeof editor.getEditedPostPreviewLink === 'function' && editor.getEditedPostPreviewLink() ) || bootstrap.previewUrl,
				saving: Boolean(
					( typeof editor.isSavingPost === 'function' && editor.isSavingPost() ) ||
					( typeof editor.isAutosavingPost === 'function' && editor.isAutosavingPost() )
				),
			};
		}, [ bootstrap.previewUrl ] );
		var previewSrc = useMemo( function () {
			return appendRefreshToken( editorState.previewUrl, refreshKey );
		}, [ editorState.previewUrl, refreshKey ] );

		useEffect( function () {
			var currentHost = null;
			var root = null;

			function attach() {
				var stage = locateStage();
				if ( ! stage.host || ! stage.visualEditor ) return;
				if ( currentHost && currentHost !== stage.host ) currentHost.classList.remove( 'cresco-canvas-stage-host' );
				currentHost = stage.host;
				currentHost.classList.add( 'cresco-canvas-stage-host' );
				root = document.getElementById( TOOLBAR_ID );
				if ( ! root ) {
					root = document.createElement( 'div' );
					root.id = TOOLBAR_ID;
					root.className = 'cc-canvas-stage-toolbar-root';
					stage.host.insertBefore( root, stage.visualEditor );
				}
				if ( toolbarMount !== root ) setToolbarMount( root );
			}

			attach();
			var observer = new MutationObserver( attach );
			observer.observe( document.body, { childList: true, subtree: true } );
			return function () {
				observer.disconnect();
				if ( currentHost ) currentHost.classList.remove( 'cresco-canvas-stage-host' );
				if ( root && root.parentNode ) root.parentNode.removeChild( root );
			};
		}, [] );

		useEffect( function () {
			writeStorage( DEVICE_STORAGE, device );
			writeStorage( ZOOM_STORAGE, zoom );
			writeStorage( CUSTOM_WIDTH_STORAGE, customWidth );

			var iframeListeners = new Map();
			var resizeObserver = null;
			var stage = locateStage();

			function resolveZoom() {
				var value;
				if ( zoom === 'fit' ) {
					var available = stage.visualEditor ? Math.max( 280, stage.visualEditor.clientWidth - 56 ) : width;
					value = Math.min( 1, Math.max( 0.25, available / width ) );
				} else {
					value = clamp( Number( zoom ) / 100, 0.5, 1.25 );
				}
				var percent = Math.round( value * 100 );
				setComputedZoom( percent );
				if ( stage.host ) {
					stage.host.style.setProperty( '--cc-canvas-width', width + 'px' );
					stage.host.style.setProperty( '--cc-canvas-zoom', String( value ) );
					stage.host.dataset.ccCanvasDevice = device;
					stage.host.dataset.ccCanvasZoom = zoom;
				}
				return value;
			}

			function attachIframe( iframe ) {
				if ( iframeListeners.has( iframe ) ) return;
				var onLoad = function () { applyDevice( iframe.contentDocument, device, width ); };
				iframeListeners.set( iframe, onLoad );
				iframe.addEventListener( 'load', onLoad );
				onLoad();
			}

			function scan() {
				stage = locateStage();
				applyDevice( document, device, width );
				resolveZoom();
				document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( attachIframe );
			}

			scan();
			if ( window.ResizeObserver && stage.visualEditor ) {
				resizeObserver = new ResizeObserver( resolveZoom );
				resizeObserver.observe( stage.visualEditor );
			}
			var observer = new MutationObserver( scan );
			observer.observe( document.documentElement, { childList: true, subtree: true } );
			window.dispatchEvent( new CustomEvent( EVENT_NAME, { detail: { device: device, width: width, zoom: computedZoom } } ) );

			return function () {
				observer.disconnect();
				if ( resizeObserver ) resizeObserver.disconnect();
				iframeListeners.forEach( function ( onLoad, iframe ) { iframe.removeEventListener( 'load', onLoad ); } );
			};
		}, [ device, zoom, customWidth ] );

		useEffect( function () {
			if ( wasSaving.current && ! editorState.saving && previewOpen ) {
				setRefreshKey( function ( value ) { return value + 1; } );
			}
			wasSaving.current = editorState.saving;
		}, [ editorState.saving, previewOpen ] );

		function selectDevice( value ) {
			setDevice( value );
		}

		function changeCustomWidth( event ) {
			setCustomWidth( clamp( event.target.value, 320, 2560 ) );
			setDevice( 'custom' );
		}

		var toolbar = toolbarMount && typeof createPortal === 'function' ? createPortal(
			createElement( 'div', { className: 'cc-canvas-stage-toolbar', role: 'toolbar', 'aria-label': __( 'Cresco Canvas viewport', 'cresco-canvas' ) },
				createElement( 'div', { className: 'cc-canvas-stage-toolbar__devices', role: 'group', 'aria-label': __( 'Viewport device', 'cresco-canvas' ) },
					[ 'desktop', 'laptop', 'tablet', 'mobile' ].map( function ( id ) {
						return createElement( Button, {
							key: id,
							'aria-pressed': device === id,
							className: device === id ? 'is-active' : '',
							onClick: function () { selectDevice( id ); },
							variant: 'tertiary',
						}, labels[ id ] );
					} )
				),
				createElement( 'label', { className: 'cc-canvas-stage-toolbar__width' },
					createElement( 'span', null, __( 'Width', 'cresco-canvas' ) ),
					createElement( 'input', { type: 'number', min: 320, max: 2560, step: 1, value: width, onChange: changeCustomWidth, 'aria-label': __( 'Custom viewport width in pixels', 'cresco-canvas' ) } ),
					createElement( 'small', null, 'px' )
				),
				createElement( 'label', { className: 'cc-canvas-stage-toolbar__zoom' },
					createElement( 'span', null, __( 'Zoom', 'cresco-canvas' ) ),
					createElement( 'select', { value: zoom, onChange: function ( event ) { setZoom( event.target.value ); } },
						createElement( 'option', { value: 'fit' }, __( 'Fit', 'cresco-canvas' ) ),
						createElement( 'option', { value: '50' }, '50%' ),
						createElement( 'option', { value: '75' }, '75%' ),
						createElement( 'option', { value: '100' }, '100%' ),
						createElement( 'option', { value: '125' }, '125%' )
					)
				),
				createElement( 'span', { className: 'cc-canvas-stage-toolbar__status', 'aria-live': 'polite' }, width + 'px · ' + computedZoom + '%' )
			),
			toolbarMount
		) : null;

		return createElement( Fragment, null,
			toolbar,
			createElement( PluginSidebarMoreMenuItem, { target: 'cresco-canvas-preview' }, __( 'Cresco Preview', 'cresco-canvas' ) ),
			createElement( PluginSidebar, { className: 'cresco-canvas-preview-sidebar', icon: 'visibility', name: 'cresco-canvas-preview', title: __( 'Cresco Preview', 'cresco-canvas' ) },
				createElement( PanelBody, { initialOpen: true, title: __( 'Responsive viewport', 'cresco-canvas' ) },
					createElement( 'div', { 'aria-label': __( 'Preview device', 'cresco-canvas' ), className: 'cc-preview-device-grid', role: 'group' },
						devices.map( function ( candidate ) {
							return createElement( Button, { 'aria-pressed': device === candidate.id, key: candidate.id, onClick: function () { selectDevice( candidate.id ); }, variant: device === candidate.id ? 'primary' : 'secondary' }, labels[ candidate.id ] );
						} )
					),
					createElement( 'p', { className: 'cc-preview-note' }, sprintf( __( '%1$s uses a %2$dpx logical viewport.', 'cresco-canvas' ), labels[ device ] || labels.custom, width ) )
				),
				createElement( PanelBody, { initialOpen: true, title: __( 'Live frontend preview', 'cresco-canvas' ) },
					editorState.previewUrl ? createElement( Fragment, null,
						createElement( 'p', { className: 'cc-preview-note' }, __( 'The iframe shows WordPress frontend output and refreshes after a save or autosave finishes.', 'cresco-canvas' ) ),
						createElement( 'div', { className: 'cc-preview-actions' },
							createElement( Button, { onClick: function () { setPreviewOpen( true ); }, variant: 'primary' }, __( 'Open frontend preview', 'cresco-canvas' ) ),
							createElement( Button, { href: editorState.previewUrl, target: '_blank', variant: 'secondary' }, __( 'Open in new tab', 'cresco-canvas' ) )
						)
					) : createElement( Notice, { isDismissible: false, status: 'warning' }, __( 'A frontend preview URL is not available for this Page yet.', 'cresco-canvas' ) )
				)
			),
			previewOpen && previewSrc ? createElement( Modal, { className: 'cc-frontend-preview-modal', onRequestClose: function () { setPreviewOpen( false ); }, title: __( 'Live frontend preview', 'cresco-canvas' ) },
				createElement( 'div', { className: 'cc-frontend-preview-toolbar' },
					createElement( 'span', { 'aria-live': 'polite' }, editorState.saving ? __( 'Waiting for WordPress to finish saving…', 'cresco-canvas' ) : __( 'Showing the latest saved preview.', 'cresco-canvas' ) ),
					createElement( Button, { onClick: function () { setRefreshKey( function ( value ) { return value + 1; } ); }, variant: 'secondary' }, __( 'Refresh', 'cresco-canvas' ) )
				),
				createElement( 'div', { className: 'cc-frontend-preview-stage' },
					createElement( 'iframe', { className: 'cc-frontend-preview-frame', key: device + '-' + refreshKey, src: previewSrc, style: { inlineSize: width }, title: __( 'Cresco frontend Page preview', 'cresco-canvas' ) } )
				)
			) : null
		);
	}

	registerPlugin( 'cresco-canvas-preview', { icon: 'visibility', render: PreviewSidebar } );
} )( window.wp, window.crescoCanvasPreviewSettings );
