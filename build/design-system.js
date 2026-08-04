( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editor || ! wp.element || ! wp.components || ! wp.apiFetch ) {
		return;
	}

	var el = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var Spinner = wp.components.Spinner;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var bootstrap = window.crescoCanvasEditorSettings || { canManageSettings: false, restPath: '/cresco-canvas/v1/' };

	function slugify( value ) {
		return String( value || '' )
			.toLowerCase()
			.trim()
			.replace( /[^a-z0-9_-]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	function cloneRecord( record ) {
		return Object.assign( {}, record || {} );
	}

	function ColorInput( props ) {
		return el( 'label', { className: 'cc-ds-color-field' },
			el( 'span', null, props.label ),
			el( 'input', {
				type: 'color',
				value: props.value,
				onChange: function ( event ) { props.onChange( event.target.value ); }
			} )
		);
	}

	function DesignSystemSidebar() {
		var settingsState = useState( null );
		var settings = settingsState[ 0 ];
		var setSettings = settingsState[ 1 ];
		var loadingState = useState( bootstrap.canManageSettings );
		var loading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];
		var savingState = useState( false );
		var saving = savingState[ 0 ];
		var setSaving = savingState[ 1 ];
		var noticeState = useState( null );
		var notice = noticeState[ 0 ];
		var setNotice = noticeState[ 1 ];
		var colorSlugState = useState( '' );
		var colorSlug = colorSlugState[ 0 ];
		var setColorSlug = colorSlugState[ 1 ];
		var colorValueState = useState( '#635bff' );
		var colorValue = colorValueState[ 0 ];
		var setColorValue = colorValueState[ 1 ];
		var aliasSlugState = useState( '' );
		var aliasSlug = aliasSlugState[ 0 ];
		var setAliasSlug = aliasSlugState[ 1 ];
		var aliasTargetState = useState( 'primary' );
		var aliasTarget = aliasTargetState[ 0 ];
		var setAliasTarget = aliasTargetState[ 1 ];
		var importState = useState( '' );
		var importValue = importState[ 0 ];
		var setImportValue = importState[ 1 ];

		useEffect( function () {
			if ( ! bootstrap.canManageSettings ) {
				setLoading( false );
				return;
			}
			apiFetch( { path: bootstrap.restPath + 'settings' } )
				.then( setSettings )
				.catch( function ( error ) {
					setNotice( { status: 'error', message: error && error.message ? error.message : __( 'Global Design could not be loaded.', 'cresco-canvas' ) } );
				} )
				.finally( function () { setLoading( false ); } );
		}, [] );

		var aliasOptions = useMemo( function () {
			var options = [
				{ label: __( 'Primary', 'cresco-canvas' ), value: 'primary' },
				{ label: __( 'Text', 'cresco-canvas' ), value: 'text' },
				{ label: __( 'Muted', 'cresco-canvas' ), value: 'muted' },
				{ label: __( 'Background', 'cresco-canvas' ), value: 'background' }
			];
			Object.keys( settings && settings.customColors ? settings.customColors : {} ).forEach( function ( key ) {
				options.push( { label: __( 'Custom:', 'cresco-canvas' ) + ' ' + key, value: 'custom-' + key } );
			} );
			return options;
		}, [ settings ] );

		function patch( changes ) {
			setSettings( Object.assign( {}, settings, changes ) );
		}

		function save() {
			if ( ! settings || saving ) return;
			setSaving( true );
			setNotice( null );
			apiFetch( { path: bootstrap.restPath + 'settings', method: 'POST', data: settings } )
				.then( function ( result ) {
					setSettings( result );
					setNotice( { status: 'success', message: __( 'Global Design saved.', 'cresco-canvas' ) } );
				} )
				.catch( function ( error ) {
					setNotice( { status: 'error', message: error && error.message ? error.message : __( 'Global Design could not be saved.', 'cresco-canvas' ) } );
				} )
				.finally( function () { setSaving( false ); } );
		}

		function reset() {
			if ( saving ) return;
			setSaving( true );
			apiFetch( { path: bootstrap.restPath + 'settings/reset', method: 'POST' } )
				.then( function ( result ) {
					setSettings( result );
					setNotice( { status: 'success', message: __( 'Global Design reset to defaults.', 'cresco-canvas' ) } );
				} )
				.catch( function ( error ) {
					setNotice( { status: 'error', message: error && error.message ? error.message : __( 'Global Design could not be reset.', 'cresco-canvas' ) } );
				} )
				.finally( function () { setSaving( false ); } );
		}

		function addColor() {
			var slug = slugify( colorSlug );
			if ( ! slug || ! settings || Object.keys( settings.customColors || {} ).length >= 24 ) return;
			var colors = cloneRecord( settings.customColors );
			colors[ slug ] = colorValue;
			patch( { customColors: colors } );
			setColorSlug( '' );
		}

		function deleteColor( slug ) {
			var used = Object.keys( settings.aliases || {} ).some( function ( alias ) {
				return settings.aliases[ alias ] === 'custom-' + slug;
			} );
			if ( used ) {
				setNotice( { status: 'warning', message: __( 'Remove aliases that use this color before deleting it.', 'cresco-canvas' ) } );
				return;
			}
			var colors = cloneRecord( settings.customColors );
			delete colors[ slug ];
			patch( { customColors: colors } );
		}

		function addAlias() {
			var slug = slugify( aliasSlug );
			if ( ! slug || ! settings || Object.keys( settings.aliases || {} ).length >= 24 ) return;
			var aliases = cloneRecord( settings.aliases );
			aliases[ slug ] = aliasTarget;
			patch( { aliases: aliases } );
			setAliasSlug( '' );
		}

		function deleteAlias( slug ) {
			var aliases = cloneRecord( settings.aliases );
			delete aliases[ slug ];
			patch( { aliases: aliases } );
		}

		function applyImport() {
			try {
				var parsed = JSON.parse( importValue );
				if ( ! parsed || typeof parsed !== 'object' || typeof parsed.customColors !== 'object' || typeof parsed.aliases !== 'object' ) {
					throw new Error( __( 'The JSON is not a complete Cresco Design System export.', 'cresco-canvas' ) );
				}
				setSettings( parsed );
				setImportValue( '' );
				setNotice( { status: 'success', message: __( 'Imported values are ready. Save to persist them.', 'cresco-canvas' ) } );
			} catch ( error ) {
				setNotice( { status: 'error', message: error && error.message ? error.message : __( 'Invalid JSON.', 'cresco-canvas' ) } );
			}
		}

		var content;
		if ( ! bootstrap.canManageSettings ) {
			content = el( Notice, { status: 'warning', isDismissible: false }, __( 'You do not have permission to manage site-wide design settings.', 'cresco-canvas' ) );
		} else if ( loading ) {
			content = el( 'div', { className: 'cc-ds-loading' }, el( Spinner ) );
		} else if ( ! settings ) {
			content = el( Notice, { status: 'error', isDismissible: false }, __( 'Global Design settings are unavailable.', 'cresco-canvas' ) );
		} else {
			content = el( wp.element.Fragment, null,
				notice && el( Notice, { status: notice.status, isDismissible: true, onRemove: function () { setNotice( null ); } }, notice.message ),
				el( PanelBody, { title: __( 'Foundation', 'cresco-canvas' ), initialOpen: true },
					el( ColorInput, { label: __( 'Primary', 'cresco-canvas' ), value: settings.primary, onChange: function ( value ) { patch( { primary: value } ); } } ),
					el( ColorInput, { label: __( 'Text', 'cresco-canvas' ), value: settings.text, onChange: function ( value ) { patch( { text: value } ); } } ),
					el( ColorInput, { label: __( 'Muted', 'cresco-canvas' ), value: settings.muted, onChange: function ( value ) { patch( { muted: value } ); } } ),
					el( ColorInput, { label: __( 'Background', 'cresco-canvas' ), value: settings.background, onChange: function ( value ) { patch( { background: value } ); } } ),
					el( TextControl, { label: __( 'Font family stack', 'cresco-canvas' ), value: settings.fontFamily, onChange: function ( value ) { patch( { fontFamily: value } ); } } ),
					el( TextControl, { label: __( 'Container maximum width', 'cresco-canvas' ), type: 'number', min: 960, max: 2560, value: settings.containerMax, onChange: function ( value ) { patch( { containerMax: Number( value ) } ); } } ),
					el( TextControl, { label: __( 'Content maximum width', 'cresco-canvas' ), type: 'number', min: 640, max: settings.containerMax, value: settings.contentMax, onChange: function ( value ) { patch( { contentMax: Number( value ) } ); } } ),
					el( TextControl, { label: __( 'Global radius', 'cresco-canvas' ), type: 'number', min: 0, max: 80, value: settings.radius, onChange: function ( value ) { patch( { radius: Number( value ) } ); } } )
				),
				el( PanelBody, { title: __( 'Custom colors', 'cresco-canvas' ), initialOpen: false },
					Object.keys( settings.customColors || {} ).map( function ( slug ) {
						return el( 'div', { className: 'cc-ds-token-row', key: slug },
							el( 'code', null, '--cc-color-' + slug ),
							el( 'input', { type: 'color', value: settings.customColors[ slug ], onChange: function ( event ) { var colors = cloneRecord( settings.customColors ); colors[ slug ] = event.target.value; patch( { customColors: colors } ); } } ),
							el( Button, { isDestructive: true, variant: 'tertiary', onClick: function () { deleteColor( slug ); } }, __( 'Delete', 'cresco-canvas' ) )
						);
					} ),
					el( TextControl, { label: __( 'New color slug', 'cresco-canvas' ), value: colorSlug, onChange: setColorSlug } ),
					el( ColorInput, { label: __( 'New color value', 'cresco-canvas' ), value: colorValue, onChange: setColorValue } ),
					el( Button, { variant: 'secondary', disabled: ! slugify( colorSlug ), onClick: addColor }, __( 'Add custom color', 'cresco-canvas' ) )
				),
				el( PanelBody, { title: __( 'Aliases', 'cresco-canvas' ), initialOpen: false },
					Object.keys( settings.aliases || {} ).map( function ( slug ) {
						return el( 'div', { className: 'cc-ds-token-row', key: slug },
							el( 'code', null, '--cc-alias-' + slug ),
							el( 'span', null, settings.aliases[ slug ] ),
							el( Button, { isDestructive: true, variant: 'tertiary', onClick: function () { deleteAlias( slug ); } }, __( 'Delete', 'cresco-canvas' ) )
						);
					} ),
					el( TextControl, { label: __( 'New alias slug', 'cresco-canvas' ), value: aliasSlug, onChange: setAliasSlug } ),
					el( SelectControl, { label: __( 'Alias target', 'cresco-canvas' ), value: aliasTarget, options: aliasOptions, onChange: setAliasTarget } ),
					el( Button, { variant: 'secondary', disabled: ! slugify( aliasSlug ), onClick: addAlias }, __( 'Add alias', 'cresco-canvas' ) )
				),
				el( PanelBody, { title: __( 'Import, export, and reset', 'cresco-canvas' ), initialOpen: false },
					el( TextareaControl, { label: __( 'Export JSON', 'cresco-canvas' ), readOnly: true, rows: 8, value: JSON.stringify( settings, null, 2 ) } ),
					el( TextareaControl, { label: __( 'Import JSON', 'cresco-canvas' ), rows: 8, value: importValue, onChange: setImportValue } ),
					el( Button, { variant: 'secondary', disabled: ! importValue.trim(), onClick: applyImport }, __( 'Apply import', 'cresco-canvas' ) ),
					el( Button, { variant: 'tertiary', disabled: saving, onClick: reset }, __( 'Reset to defaults', 'cresco-canvas' ) )
				),
				el( PanelBody, { title: __( 'Data', 'cresco-canvas' ), initialOpen: false },
					el( ToggleControl, { label: __( 'Remove plugin data on uninstall', 'cresco-canvas' ), checked: !! settings.removeDataOnUninstall, onChange: function ( value ) { patch( { removeDataOnUninstall: value } ); } } )
				),
				el( 'div', { className: 'cc-ds-actions' }, el( Button, { variant: 'primary', isBusy: saving, disabled: saving, onClick: save }, saving ? __( 'Saving…', 'cresco-canvas' ) : __( 'Save Design System', 'cresco-canvas' ) ) )
			);
		}

		return el( wp.element.Fragment, null,
			el( PluginSidebarMoreMenuItem, { target: 'cresco-canvas-design-system' }, __( 'Cresco Design System', 'cresco-canvas' ) ),
			el( PluginSidebar, { name: 'cresco-canvas-design-system', title: __( 'Cresco Design System', 'cresco-canvas' ), icon: 'admin-customizer', className: 'cresco-canvas-design-system' }, content )
		);
	}

	registerPlugin( 'cresco-canvas-design-system', { icon: 'admin-customizer', render: DesignSystemSidebar } );
} )( window.wp );
