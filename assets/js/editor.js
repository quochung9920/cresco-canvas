( function ( wp, settings ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;
	var apiFetch = wp.apiFetch;
	var parse = wp.blocks.parse;
	var serialize = wp.blocks.serialize;
	var createBlock = wp.blocks.createBlock;
	var BlockEditorProvider = wp.blockEditor.BlockEditorProvider;
	var BlockCanvas = wp.blockEditor.BlockCanvas;
	var BlockInspector = wp.blockEditor.BlockInspector;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var TextControl = wp.components.TextControl;

	apiFetch.use( apiFetch.createNonceMiddleware( settings.nonce ) );

	var elements = [
		[ 'cresco/container', 'Container' ],
		[ 'core/heading', 'Heading' ],
		[ 'core/paragraph', 'Text' ],
		[ 'core/buttons', 'Buttons' ],
		[ 'core/image', 'Image' ],
		[ 'core/video', 'Video' ],
		[ 'core/list', 'List' ],
		[ 'core/spacer', 'Spacer' ],
		[ 'core/separator', 'Divider' ],
		[ 'core/columns', 'Columns' ]
	];

	function request( path, options ) {
		return apiFetch( Object.assign( { path: '/cresco-canvas/v1/' + path }, options || {} ) );
	}

	function App() {
		var _page = useState( null ), page = _page[ 0 ], setPage = _page[ 1 ];
		var _blocks = useState( [] ), blocks = _blocks[ 0 ], setBlocks = _blocks[ 1 ];
		var _device = useState( 'desktop' ), device = _device[ 0 ], setDevice = _device[ 1 ];
		var _loading = useState( true ), loading = _loading[ 0 ], setLoading = _loading[ 1 ];
		var _saving = useState( false ), saving = _saving[ 0 ], setSaving = _saving[ 1 ];
		var _notice = useState( '' ), notice = _notice[ 0 ], setNotice = _notice[ 1 ];
		var _settingsOpen = useState( false ), settingsOpen = _settingsOpen[ 0 ], setSettingsOpen = _settingsOpen[ 1 ];
		var _global = useState( null ), global = _global[ 0 ], setGlobal = _global[ 1 ];

		useEffect( function () {
			var globalRequest = settings.canManageSettings ? request( 'settings' ).catch( function () { return null; } ) : Promise.resolve( null );

			globalRequest.then( function ( globalSettings ) {
				setGlobal( globalSettings );

				if ( settings.postId ) {
					loadPage( settings.postId );
				} else {
					setLoading( false );
				}
			} ).catch( showError );
		}, [] );

		function showError( error ) {
			setNotice( error && error.message ? error.message : 'Something went wrong.' );
			setLoading( false );
			setSaving( false );
		}

		function loadPage( id ) {
			setLoading( true );
			request( 'pages/' + id ).then( function ( data ) {
				setPage( data );
				setBlocks( parse( data.content || '' ) );
				setLoading( false );
				setNotice( '' );
			} ).catch( showError );
		}

		function savePage() {
			if ( ! page ) {
				return;
			}

			setSaving( true );
			request( 'pages/' + page.id, {
				method: 'POST',
				data: {
					title: page.title,
					status: page.status,
					content: serialize( blocks )
				}
			} ).then( function ( data ) {
				setPage( Object.assign( {}, page, { preview: data.preview } ) );
				setNotice( 'Page saved successfully.' );
				setSaving( false );
			} ).catch( showError );
		}

		function addElement( name ) {
			var block;

			if ( name === 'core/buttons' ) {
				block = createBlock( 'core/buttons', {}, [ createBlock( 'core/button', { text: 'Button' } ) ] );
			} else if ( name === 'cresco/container' ) {
				block = createBlock( name, {}, [ createBlock( 'core/paragraph', { content: 'Start building here…' } ) ] );
			} else {
				block = createBlock( name );
			}

			setBlocks( blocks.concat( [ block ] ) );
		}

		function saveGlobal() {
			if ( ! global ) {
				return;
			}

			request( 'settings', { method: 'POST', data: global } ).then( function ( data ) {
				setGlobal( data );
				setNotice( 'Global settings saved.' );
			} ).catch( showError );
		}

		var editorSettings = useMemo( function () {
			return {
				canLockBlocks: true,
				hasFixedToolbar: false,
				focusMode: false,
				templateLock: false
			};
		}, [] );

		var elementPanel = el( 'aside', { className: 'cc-panel' },
			el( 'div', { className: 'cc-panel-header' }, 'Elements' ),
			el( 'div', { className: 'cc-panel-body' }, elements.map( function ( item ) {
				return el( 'button', {
					key: item[ 0 ],
					className: 'cc-element',
					disabled: ! page,
					onClick: function () { addElement( item[ 0 ] ); }
				}, item[ 1 ] );
			} ) )
		);

		var emptyCanvas = el( 'section', { className: 'cc-canvas-wrap' },
			el( 'div', { className: 'cc-canvas', 'data-device': device },
				loading ? el( 'div', { className: 'cc-empty' }, el( Spinner ) ) : el( 'div', { className: 'cc-empty' },
					el( 'strong', null, 'No Page selected.' ),
					el( 'p', null, 'Open Pages and click the normal Edit link to launch Cresco Canvas for that Page.' ),
					el( Button, { variant: 'primary', href: settings.pagesUrl }, 'Open Pages' )
				)
			)
		);

		var shell;

		if ( page ) {
			shell = el( BlockEditorProvider, {
				value: blocks,
				onInput: setBlocks,
				onChange: setBlocks,
				settings: editorSettings
			},
				el( 'main', { className: 'cc-shell' },
					elementPanel,
					el( 'section', { className: 'cc-canvas-wrap' },
						el( 'div', { className: 'cc-canvas', 'data-device': device },
							loading ? el( 'div', { className: 'cc-empty' }, el( Spinner ) ) : el( 'div', { className: 'cc-editor editor-styles-wrapper' }, el( BlockCanvas, { height: '100%' } ) )
						)
					),
					el( 'aside', { className: 'cc-panel cc-panel-right' },
						settingsOpen && global ? el( Fragment, null,
							el( 'div', { className: 'cc-panel-header' }, 'Global Design System' ),
							el( 'div', { className: 'cc-panel-body cc-settings-grid' },
								colorField( 'Primary', 'primary', global, setGlobal ),
								colorField( 'Text', 'text', global, setGlobal ),
								colorField( 'Background', 'background', global, setGlobal ),
								el( TextControl, { label: 'Boxed max-width', type: 'number', value: global.containerMax, onChange: function ( value ) { setGlobal( Object.assign( {}, global, { containerMax: Number( value ) } ) ); } } ),
								el( TextControl, { label: 'Content max-width', type: 'number', value: global.contentMax, onChange: function ( value ) { setGlobal( Object.assign( {}, global, { contentMax: Number( value ) } ) ); } } ),
								el( TextControl, { label: 'Global radius', type: 'number', value: global.radius, onChange: function ( value ) { setGlobal( Object.assign( {}, global, { radius: Number( value ) } ) ); } } ),
								el( Button, { variant: 'primary', onClick: saveGlobal }, 'Save Global Settings' )
							)
						) : el( Fragment, null,
							el( 'div', { className: 'cc-panel-header' }, 'Element Settings' ),
							el( 'div', { className: 'cc-panel-body' }, el( BlockInspector ) )
						)
					)
				)
			);
		} else {
			shell = el( 'main', { className: 'cc-shell' },
				elementPanel,
				emptyCanvas,
				el( 'aside', { className: 'cc-panel cc-panel-right' },
					el( 'div', { className: 'cc-panel-header' }, 'Element Settings' ),
					el( 'div', { className: 'cc-panel-body' }, 'Select a Page to begin editing.' )
				)
			);
		}

		return el( 'div', { className: 'cc-app' },
			el( 'header', { className: 'cc-topbar' },
				el( Button, { variant: 'tertiary', href: settings.pagesUrl }, '← Pages' ),
				el( 'div', { className: 'cc-brand' }, settings.brand || 'Cresco Canvas' ),
				page ? el( TextControl, {
					className: 'cc-page-title',
					value: page.title || '',
					onChange: function ( value ) { setPage( Object.assign( {}, page, { title: value } ) ); }
				} ) : null,
				el( 'div', { className: 'cc-device-switcher' }, [ '4k', 'desktop', 'laptop', 'tablet', 'mobile' ].map( function ( item ) {
					return el( Button, {
						key: item,
						variant: device === item ? 'primary' : 'secondary',
						onClick: function () { setDevice( item ); }
					}, item.toUpperCase() );
				} ) ),
				el( 'div', { className: 'cc-spacer' } ),
				el( 'span', { className: 'cc-status' }, saving ? 'Saving…' : notice ),
				settings.canManageSettings ? el( Button, { variant: 'secondary', onClick: function () { setSettingsOpen( ! settingsOpen ); } }, 'Global Settings' ) : null,
				page && settings.nativeEditUrl ? el( Button, { variant: 'secondary', href: settings.nativeEditUrl }, 'WordPress Editor' ) : null,
				page ? el( Button, { variant: 'secondary', href: page.preview, target: '_blank' }, 'Preview' ) : null,
				page ? el( Button, { variant: 'primary', isBusy: saving, onClick: savePage }, saving ? 'Saving' : 'Save' ) : null
			),
			shell
		);
	}

	function colorField( label, key, state, setState ) {
		return el( 'label', { key: key },
			label,
			el( 'input', {
				type: 'color',
				value: state[ key ],
				onChange: function ( event ) {
					var update = {};
					update[ key ] = event.target.value;
					setState( Object.assign( {}, state, update ) );
				}
			} )
		);
	}

	wp.element.render( el( App ), document.getElementById( 'cresco-canvas-app' ) );
} )( window.wp, window.crescoCanvasSettings );
