( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch || ! wp.i18n ) return;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var settings = window.crescoCanvasStandaloneSettings || {};
	if ( ! settings.settingsPath || ! settings.settingsImportPreviewPath ) return;

	function element( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( text !== undefined ) node.textContent = text;
		return node;
	}

	function button( label, variant ) {
		var node = element( 'button', 'components-button is-' + ( variant || 'secondary' ), label );
		node.type = 'button';
		return node;
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value || {} ) );
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

	function setStatus( node, text, error ) {
		node.textContent = text || '';
		node.classList.toggle( 'is-error', !! error );
	}

	function textInput( value, className ) {
		var input = element( 'input', className || 'cc-global-field__input' );
		input.type = 'text';
		input.value = value === undefined || value === null ? '' : String( value );
		return input;
	}

	function numberInput( value ) {
		var input = textInput( value, 'cc-global-field__input cc-global-field__input--number' );
		input.inputMode = 'numeric';
		return input;
	}

	function simpleField( label, input, suffix ) {
		var row = element( 'label', 'cc-global-field' );
		row.appendChild( element( 'span', 'cc-global-field__label', label ) );
		var control = element( 'span', 'cc-global-field__control' );
		control.appendChild( input );
		if ( suffix ) control.appendChild( element( 'small', '', suffix ) );
		row.appendChild( control );
		return row;
	}

	function colorRow( slug, value, custom ) {
		var row = element( 'div', 'cc-global-color-row' + ( custom ? ' cc-global-custom-row' : '' ) );
		var swatch = element( 'span', 'cc-global-color-swatch' );
		swatch.style.background = value || 'transparent';
		row.appendChild( swatch );

		var name = textInput( slug, 'cc-global-color-name' );
		name.disabled = ! custom;
		name.setAttribute( 'aria-label', custom ? __( 'Custom color token name', 'cresco-canvas' ) : slug );
		row.appendChild( name );

		var color = textInput( value, 'cc-global-color-value' );
		color.setAttribute( 'aria-label', slug + ' ' + __( 'color value', 'cresco-canvas' ) );
		color.addEventListener( 'input', function () { swatch.style.background = color.value || 'transparent'; } );
		row.appendChild( color );

		if ( custom ) {
			var remove = button( '×', 'tertiary' );
			remove.classList.add( 'cc-global-color-remove' );
			remove.setAttribute( 'aria-label', __( 'Remove custom color', 'cresco-canvas' ) );
			remove.addEventListener( 'click', function () { row.remove(); } );
			row.appendChild( remove );
		}
		return row;
	}

	function serialize( root, base ) {
		var next = clone( base );
		[ 'primary', 'text', 'muted', 'background' ].forEach( function ( key ) {
			var row = root.querySelector( '.cc-global-color-row[data-key="' + key + '"]' );
			if ( row ) next[ key ] = row.querySelector( '.cc-global-color-value' ).value.trim();
		} );
		next.customColors = {};
		Array.prototype.forEach.call( root.querySelectorAll( '.cc-global-custom-row' ), function ( row ) {
			var slug = row.querySelector( '.cc-global-color-name' ).value.trim().replace( /^--/, '' );
			var value = row.querySelector( '.cc-global-color-value' ).value.trim();
			if ( slug && value ) next.customColors[ slug ] = value;
		} );
		next.fontFamily = root.querySelector( '[data-global-field="fontFamily"]' ).value.trim();
		next.containerMax = Number( root.querySelector( '[data-global-field="containerMax"]' ).value ) || 1440;
		next.contentMax = Number( root.querySelector( '[data-global-field="contentMax"]' ).value ) || 1200;
		next.radius = Number( root.querySelector( '[data-global-field="radius"]' ).value ) || 0;
		next.breakpoints = next.breakpoints || {};
		[ 'mobile', 'tablet', 'laptop', 'desktop', 'wide' ].forEach( function ( key ) {
			next.breakpoints[ key ] = Number( root.querySelector( '[data-breakpoint="' + key + '"]' ).value ) || 0;
		} );
		return next;
	}

	function renderEditor( panel, data ) {
		if ( ! document.body.contains( panel ) ) return;
		panel.classList.add( 'cc-global-panel--simple' );
		var old = panel.querySelector( '.cc-global-simple-editor' );
		if ( old ) old.remove();

		var root = element( 'div', 'cc-global-simple-editor' );
		var current = clone( data );
		var status = element( 'div', 'cc-global-simple-status' );

		var header = element( 'div', 'cc-global-simple-header' );
		header.appendChild( element( 'strong', '', __( 'Global Design', 'cresco-canvas' ) ) );
		var headerActions = element( 'div', 'cc-global-simple-header__actions' );
		var importButton = button( __( 'Import', 'cresco-canvas' ), 'secondary' );
		var exportButton = button( __( 'Export', 'cresco-canvas' ), 'secondary' );
		headerActions.appendChild( importButton );
		headerActions.appendChild( exportButton );
		header.appendChild( headerActions );
		root.appendChild( header );

		if ( settings.canManageGlobal === false ) {
			root.appendChild( element( 'p', 'cc-global-simple-status is-error', __( 'Your account cannot change site-wide Global Design settings.', 'cresco-canvas' ) ) );
			panel.appendChild( root );
			return;
		}

		var colorsCard = element( 'section', 'cc-global-simple-card' );
		colorsCard.appendChild( element( 'h3', '', __( 'Colors', 'cresco-canvas' ) ) );
		var colorList = element( 'div', 'cc-global-color-list' );
		[ 'primary', 'text', 'muted', 'background' ].forEach( function ( key ) {
			var row = colorRow( key, current[ key ] || '', false );
			row.dataset.key = key;
			colorList.appendChild( row );
		} );
		Object.keys( current.customColors || {} ).forEach( function ( key ) { colorList.appendChild( colorRow( key, current.customColors[ key ], true ) ); } );
		colorsCard.appendChild( colorList );
		var addColor = button( '+ ' + __( 'Color', 'cresco-canvas' ), 'tertiary' );
		addColor.addEventListener( 'click', function () { colorList.appendChild( colorRow( 'new-color', '#ffffff', true ) ); } );
		colorsCard.appendChild( addColor );
		root.appendChild( colorsCard );

		var typographyCard = element( 'section', 'cc-global-simple-card' );
		typographyCard.appendChild( element( 'h3', '', __( 'Typography', 'cresco-canvas' ) ) );
		var fontInput = textInput( current.fontFamily || '' );
		fontInput.dataset.globalField = 'fontFamily';
		typographyCard.appendChild( simpleField( __( 'Font family', 'cresco-canvas' ), fontInput ) );
		root.appendChild( typographyCard );

		var layoutCard = element( 'section', 'cc-global-simple-card' );
		layoutCard.appendChild( element( 'h3', '', __( 'Layout', 'cresco-canvas' ) ) );
		[ [ 'containerMax', __( 'Container max', 'cresco-canvas' ) ], [ 'contentMax', __( 'Content max', 'cresco-canvas' ) ], [ 'radius', __( 'Radius', 'cresco-canvas' ) ] ].forEach( function ( item ) {
			var input = numberInput( current[ item[ 0 ] ] );
			input.dataset.globalField = item[ 0 ];
			layoutCard.appendChild( simpleField( item[ 1 ], input, 'px' ) );
		} );
		root.appendChild( layoutCard );

		var breakpointsCard = element( 'section', 'cc-global-simple-card' );
		breakpointsCard.appendChild( element( 'h3', '', __( 'Breakpoints', 'cresco-canvas' ) ) );
		[ 'mobile', 'tablet', 'laptop', 'desktop', 'wide' ].forEach( function ( key ) {
			var input = numberInput( current.breakpoints && current.breakpoints[ key ] !== undefined ? current.breakpoints[ key ] : 0 );
			input.dataset.breakpoint = key;
			breakpointsCard.appendChild( simpleField( key, input, 'px' ) );
		} );
		root.appendChild( breakpointsCard );

		var importBox = element( 'section', 'cc-global-simple-import' );
		importBox.hidden = true;
		importBox.appendChild( element( 'h3', '', __( 'Import Global Config', 'cresco-canvas' ) ) );
		var importInput = element( 'textarea', 'cc-global-simple-import__input' );
		importInput.rows = 11;
		importInput.placeholder = '--bg: oklch(98% 0.005 250);\n--ink: oklch(22% 0.02 250);\n--blue: oklch(55% 0.15 235);\nfont-family: Poppins, sans-serif;';
		importBox.appendChild( importInput );
		var importActions = element( 'div', 'cc-global-simple-actions' );
		var cancelImport = button( __( 'Cancel', 'cresco-canvas' ), 'tertiary' );
		var applyImport = button( __( 'Import Config', 'cresco-canvas' ), 'primary' );
		importActions.appendChild( cancelImport );
		importActions.appendChild( applyImport );
		importBox.appendChild( importActions );
		root.appendChild( importBox );

		var footer = element( 'div', 'cc-global-simple-footer' );
		var save = button( __( 'Save Global', 'cresco-canvas' ), 'primary' );
		var reload = button( __( 'Reload editor', 'cresco-canvas' ), 'secondary' );
		reload.hidden = true;
		footer.appendChild( save );
		footer.appendChild( reload );
		footer.appendChild( status );
		root.appendChild( footer );
		panel.appendChild( root );

		function saveSettings( payload, successText ) {
			save.disabled = true;
			setStatus( status, __( 'Saving…', 'cresco-canvas' ), false );
			return apiFetch( { path: settings.settingsPath, method: 'POST', data: payload } ).then( function ( result ) {
				current = clone( result );
				setStatus( status, successText || __( 'Global Design saved.', 'cresco-canvas' ), false );
				reload.hidden = false;
				return result;
			} ).catch( function ( error ) {
				setStatus( status, error && error.message ? error.message : __( 'Global Design could not be saved.', 'cresco-canvas' ), true );
				throw error;
			} ).finally( function () { save.disabled = false; } );
		}

		save.addEventListener( 'click', function () { saveSettings( serialize( root, current ) ); } );
		reload.addEventListener( 'click', function () { window.location.reload(); } );

		importButton.addEventListener( 'click', function () {
			importBox.hidden = ! importBox.hidden;
			if ( ! importBox.hidden ) importInput.focus();
		} );
		cancelImport.addEventListener( 'click', function () { importBox.hidden = true; importInput.value = ''; } );

		applyImport.addEventListener( 'click', function () {
			if ( ! importInput.value.trim() ) return;
			applyImport.disabled = true;
			setStatus( status, __( 'Checking import…', 'cresco-canvas' ), false );
			apiFetch( { path: settings.settingsImportPreviewPath, method: 'POST', data: { input: importInput.value } } ).then( function ( preview ) {
				var count = Array.isArray( preview.mapping ) ? preview.mapping.length : 0;
				var ignored = Array.isArray( preview.ignored ) ? preview.ignored : [];
				var message = __( 'Import', 'cresco-canvas' ) + ' ' + count + ' ' + __( 'mapped values?', 'cresco-canvas' );
				if ( ignored.length ) message += '\n' + ignored.length + ' ' + __( 'unsupported values will be ignored.', 'cresco-canvas' );
				if ( ! window.confirm( message ) ) return null;
				return saveSettings( preview.settings, __( 'Global Config imported.', 'cresco-canvas' ) ).then( function ( saved ) {
					importBox.hidden = true;
					importInput.value = '';
					renderEditor( panel, saved );
					return saved;
				} );
			} ).catch( function ( error ) {
				setStatus( status, error && error.message ? error.message : __( 'Global Config could not be imported.', 'cresco-canvas' ), true );
			} ).finally( function () { applyImport.disabled = false; } );
		} );

		exportButton.addEventListener( 'click', function () {
			copyText( serialize( root, current ) ).then( function () { setStatus( status, __( 'Global Config copied.', 'cresco-canvas' ), false ); } ).catch( function () { setStatus( status, __( 'Clipboard access failed.', 'cresco-canvas' ), true ); } );
		} );
	}

	function mount( panel ) {
		if ( panel.querySelector( '.cc-global-simple-editor' ) ) return;
		panel.classList.add( 'cc-global-panel--simple' );
		if ( settings.canManageGlobal === false ) {
			renderEditor( panel, {} );
			return;
		}
		apiFetch( { path: settings.settingsPath } ).then( function ( result ) { renderEditor( panel, result ); } ).catch( function () {
			panel.classList.remove( 'cc-global-panel--simple' );
		} );
	}

	function scan() {
		var panel = document.querySelector( '.cc-global-panel' );
		if ( panel ) mount( panel );
	}

	scan();
	var observer = new MutationObserver( scan );
	observer.observe( document.body, { childList: true, subtree: true } );
} )( window.wp, window, document );
