( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch || ! wp.i18n ) return;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var settings = window.crescoCanvasStandaloneSettings || {};
	if ( ! settings.pageSettingsPath ) return;

	var app = null;
	var trigger = null;
	var overlay = null;
	var form = null;
	var current = null;
	var loading = false;
	var lastFocus = null;
	var activeTab = 'settings';
	var styleDevice = 'desktop';
	var previewStyle = null;
	var mediaFrame = null;

	function element( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( text !== undefined ) node.textContent = text;
		return node;
	}

	function safeObject( value ) {
		return value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	function makeButton( label, variant ) {
		var node = element( 'button', 'components-button is-' + ( variant || 'secondary' ), label );
		node.type = 'button';
		return node;
	}

	function pageDefaults() {
		var sides = { top: '', right: '', bottom: '', left: '' };
		function spacing() {
			return { unit: 'px', linked: true, desktop: clone( sides ), tablet: clone( sides ), mobile: clone( sides ) };
		}
		return {
			version: 2,
			layout: 'full-width',
			pageTitle: 'hide',
			header: 'inherit',
			footer: 'inherit',
			contentRoot: 'viewport',
			bodyStyle: {
				margin: spacing(),
				padding: spacing(),
				background: {
					type: 'classic',
					color: '',
					image: { id: 0, url: '', position: 'center-center', repeat: 'no-repeat', size: 'cover', attachment: 'scroll' },
					gradient: { color1: '', color2: '', angle: 180 }
				}
			},
			customCSS: '',
			scrollSnap: { enabled: false, axis: 'y', strictness: 'proximity', align: 'start', stop: 'normal', offset: 0 }
		};
	}

	function normalize( value ) {
		var defaults = pageDefaults();
		var input = safeObject( value );
		var body = safeObject( input.bodyStyle );
		var background = safeObject( body.background );
		var image = safeObject( background.image );
		var gradient = safeObject( background.gradient );
		var snap = safeObject( input.scrollSnap );
		function spacing( key ) {
			var base = defaults.bodyStyle[ key ];
			var source = safeObject( body[ key ] );
			return {
				unit: source.unit || base.unit,
				linked: source.linked === undefined ? base.linked : !! source.linked,
				desktop: Object.assign( {}, base.desktop, safeObject( source.desktop ) ),
				tablet: Object.assign( {}, base.tablet, safeObject( source.tablet ) ),
				mobile: Object.assign( {}, base.mobile, safeObject( source.mobile ) )
			};
		}
		return {
			version: Number( input.version || defaults.version ),
			layout: input.layout || defaults.layout,
			pageTitle: input.pageTitle || defaults.pageTitle,
			header: input.header || defaults.header,
			footer: input.footer || defaults.footer,
			contentRoot: input.contentRoot || defaults.contentRoot,
			bodyStyle: {
				margin: spacing( 'margin' ),
				padding: spacing( 'padding' ),
				background: {
					type: background.type || defaults.bodyStyle.background.type,
					color: background.color || '',
					image: Object.assign( {}, defaults.bodyStyle.background.image, image ),
					gradient: Object.assign( {}, defaults.bodyStyle.background.gradient, gradient )
				}
			},
			customCSS: typeof input.customCSS === 'string' ? input.customCSS : '',
			scrollSnap: Object.assign( {}, defaults.scrollSnap, snap )
		};
	}

	function selectField( label, key, options, help ) {
		var wrapper = element( 'label', 'cc-page-settings-field' );
		wrapper.dataset.pageField = key;
		wrapper.appendChild( element( 'span', 'cc-page-settings-field__label', label ) );
		var select = element( 'select', 'cc-page-settings-field__control' );
		select.name = key;
		options.forEach( function ( option ) {
			var node = element( 'option', '', option.label );
			node.value = option.value;
			select.appendChild( node );
		} );
		wrapper.appendChild( select );
		if ( help ) wrapper.appendChild( element( 'small', 'cc-page-settings-field__help', help ) );
		return wrapper;
	}

	function inputField( label, name, type, help ) {
		var wrapper = element( 'label', 'cc-page-settings-field' );
		wrapper.appendChild( element( 'span', 'cc-page-settings-field__label', label ) );
		var input = element( 'input', 'cc-page-settings-field__control' );
		input.name = name;
		input.type = type || 'text';
		wrapper.appendChild( input );
		if ( help ) wrapper.appendChild( element( 'small', 'cc-page-settings-field__help', help ) );
		return wrapper;
	}

	function colorField( label, name ) {
		var wrapper = element( 'div', 'cc-page-settings-field' );
		wrapper.appendChild( element( 'span', 'cc-page-settings-field__label', label ) );
		var controls = element( 'div', 'cc-page-settings-color-control' );
		var swatch = element( 'input', 'cc-page-settings-color-swatch' );
		swatch.type = 'color';
		swatch.value = '#ffffff';
		swatch.dataset.colorTarget = name;
		var text = element( 'input', 'cc-page-settings-field__control' );
		text.name = name;
		text.type = 'text';
		text.placeholder = '#ffffff';
		text.autocomplete = 'off';
		controls.appendChild( swatch );
		controls.appendChild( text );
		wrapper.appendChild( controls );
		return wrapper;
	}

	function status( text, error ) {
		var node = overlay ? overlay.querySelector( '.cc-page-settings-status' ) : null;
		if ( ! node ) return;
		node.textContent = text || '';
		node.classList.toggle( 'is-error', !! error );
	}

	function valueOf( name, fallback ) {
		var input = form ? form.querySelector( '[name="' + name + '"]' ) : null;
		return input ? input.value : fallback;
	}

	function boolOf( name ) {
		var input = form ? form.querySelector( '[name="' + name + '"]' ) : null;
		return !! ( input && input.checked );
	}

	function spacingValues( key ) {
		var linkedButton = form.querySelector( '[data-spacing-link="' + key + '"]' );
		var result = {
			unit: valueOf( key + 'Unit', 'px' ),
			linked: linkedButton ? linkedButton.getAttribute( 'aria-pressed' ) === 'true' : true,
			desktop: {}, tablet: {}, mobile: {}
		};
		[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
			[ 'top', 'right', 'bottom', 'left' ].forEach( function ( side ) {
				result[ device ][ side ] = valueOf( key + '-' + device + '-' + side, '' );
			} );
		} );
		return result;
	}

	function values() {
		if ( ! form ) return pageDefaults();
		return {
			version: 2,
			layout: valueOf( 'layout', 'full-width' ),
			pageTitle: valueOf( 'pageTitle', 'hide' ),
			header: valueOf( 'header', 'inherit' ),
			footer: valueOf( 'footer', 'inherit' ),
			contentRoot: valueOf( 'contentRoot', 'viewport' ),
			bodyStyle: {
				margin: spacingValues( 'margin' ),
				padding: spacingValues( 'padding' ),
				background: {
					type: valueOf( 'backgroundType', 'classic' ),
					color: valueOf( 'backgroundColor', '' ),
					image: {
						id: Number( valueOf( 'backgroundImageId', '0' ) ) || 0,
						url: valueOf( 'backgroundImageUrl', '' ),
						position: valueOf( 'backgroundImagePosition', 'center-center' ),
						repeat: valueOf( 'backgroundImageRepeat', 'no-repeat' ),
						size: valueOf( 'backgroundImageSize', 'cover' ),
						attachment: valueOf( 'backgroundImageAttachment', 'scroll' )
					},
					gradient: {
						color1: valueOf( 'gradientColor1', '' ),
						color2: valueOf( 'gradientColor2', '' ),
						angle: Number( valueOf( 'gradientAngle', '180' ) ) || 0
					}
				}
			},
			customCSS: valueOf( 'customCSS', '' ),
			scrollSnap: {
				enabled: boolOf( 'scrollSnapEnabled' ),
				axis: valueOf( 'scrollSnapAxis', 'y' ),
				strictness: valueOf( 'scrollSnapStrictness', 'proximity' ),
				align: valueOf( 'scrollSnapAlign', 'start' ),
				stop: valueOf( 'scrollSnapStop', 'normal' ),
				offset: Number( valueOf( 'scrollSnapOffset', '0' ) ) || 0
			}
		};
	}

	function setValue( name, value ) {
		var input = form ? form.querySelector( '[name="' + name + '"]' ) : null;
		if ( input ) input.value = value === undefined || value === null ? '' : String( value );
	}

	function setChecked( name, checked ) {
		var input = form ? form.querySelector( '[name="' + name + '"]' ) : null;
		if ( input ) input.checked = !! checked;
	}

	function setSpacing( key, control ) {
		setValue( key + 'Unit', control.unit || 'px' );
		var link = form.querySelector( '[data-spacing-link="' + key + '"]' );
		if ( link ) {
			link.setAttribute( 'aria-pressed', control.linked ? 'true' : 'false' );
			link.classList.toggle( 'is-linked', !! control.linked );
		}
		[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
			[ 'top', 'right', 'bottom', 'left' ].forEach( function ( side ) {
				setValue( key + '-' + device + '-' + side, safeObject( control[ device ] )[ side ] || '' );
			} );
		} );
	}

	function populate( data, remember ) {
		if ( ! form ) return;
		var next = normalize( data && data.settings ? data.settings : data || {} );
		if ( remember !== false ) current = clone( next );
		setValue( 'layout', next.layout );
		setValue( 'pageTitle', next.pageTitle );
		setValue( 'header', next.header );
		setValue( 'footer', next.footer );
		setValue( 'contentRoot', next.contentRoot );
		setSpacing( 'margin', next.bodyStyle.margin );
		setSpacing( 'padding', next.bodyStyle.padding );
		setValue( 'backgroundType', next.bodyStyle.background.type );
		setValue( 'backgroundColor', next.bodyStyle.background.color );
		setValue( 'backgroundImageId', next.bodyStyle.background.image.id );
		setValue( 'backgroundImageUrl', next.bodyStyle.background.image.url );
		setValue( 'backgroundImagePosition', next.bodyStyle.background.image.position );
		setValue( 'backgroundImageRepeat', next.bodyStyle.background.image.repeat );
		setValue( 'backgroundImageSize', next.bodyStyle.background.image.size );
		setValue( 'backgroundImageAttachment', next.bodyStyle.background.image.attachment );
		setValue( 'gradientColor1', next.bodyStyle.background.gradient.color1 );
		setValue( 'gradientColor2', next.bodyStyle.background.gradient.color2 );
		setValue( 'gradientAngle', next.bodyStyle.background.gradient.angle );
		setValue( 'customCSS', next.customCSS );
		syncCodeGutter();
		setChecked( 'scrollSnapEnabled', next.scrollSnap.enabled );
		setValue( 'scrollSnapAxis', next.scrollSnap.axis );
		setValue( 'scrollSnapStrictness', next.scrollSnap.strictness );
		setValue( 'scrollSnapAlign', next.scrollSnap.align );
		setValue( 'scrollSnapStop', next.scrollSnap.stop );
		setValue( 'scrollSnapOffset', next.scrollSnap.offset );
		syncLayoutControls();
		syncStyleDevice();
		syncBackgroundControls();
		syncScrollControls();
		syncColorSwatches();
		applyPreview( next );
	}

	function syncLayoutControls() {
		if ( ! form ) return;
		var layout = form.querySelector( '[name="layout"]' );
		var title = form.querySelector( '[name="pageTitle"]' );
		var header = form.querySelector( '[name="header"]' );
		var footer = form.querySelector( '[name="footer"]' );
		var root = form.querySelector( '[name="contentRoot"]' );
		if ( ! layout || ! title || ! header || ! footer || ! root ) return;
		var isCanvas = layout.value === 'canvas';
		var isFullWidth = layout.value === 'full-width';
		if ( isFullWidth || isCanvas ) root.value = 'viewport';
		if ( isCanvas ) {
			title.value = 'hide';
			header.value = 'hide';
			footer.value = 'hide';
		}
		title.disabled = isCanvas;
		header.disabled = isCanvas;
		footer.disabled = isCanvas;
		root.disabled = isFullWidth || isCanvas;
		var note = form.querySelector( '.cc-page-settings-layout-note' );
		if ( note ) {
			note.textContent = isCanvas
				? __( 'Canvas renders only the Cresco document. Theme title, header, and footer are removed.', 'cresco-canvas' )
				: isFullWidth
					? __( 'Full Width keeps the selected theme shell while the Cresco root spans the browser viewport.', 'cresco-canvas' )
					: __( 'Theme Default keeps the active theme content flow and lets Cresco inherit the theme content width.', 'cresco-canvas' );
		}
	}

	function syncStyleDevice() {
		if ( ! form ) return;
		form.querySelectorAll( '[data-style-device]' ).forEach( function ( button ) {
			var active = button.dataset.styleDevice === styleDevice;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
		form.querySelectorAll( '[data-spacing-device]' ).forEach( function ( group ) {
			group.hidden = group.dataset.spacingDevice !== styleDevice;
		} );
	}

	function syncBackgroundControls() {
		if ( ! form ) return;
		var type = valueOf( 'backgroundType', 'classic' );
		form.querySelectorAll( '[data-background-type]' ).forEach( function ( button ) {
			var active = button.dataset.backgroundType === type;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
		var classic = form.querySelector( '[data-background-panel="classic"]' );
		var gradient = form.querySelector( '[data-background-panel="gradient"]' );
		if ( classic ) classic.hidden = type !== 'classic';
		if ( gradient ) gradient.hidden = type !== 'gradient';
		var imageUrl = valueOf( 'backgroundImageUrl', '' );
		var preview = form.querySelector( '.cc-page-settings-image-preview' );
		if ( preview ) {
			preview.hidden = ! imageUrl;
			preview.style.backgroundImage = imageUrl ? 'url("' + imageUrl.replace( /["\\]/g, '\\$&' ) + '")' : '';
		}
	}

	function syncScrollControls() {
		if ( ! form ) return;
		var enabled = boolOf( 'scrollSnapEnabled' );
		var panel = form.querySelector( '.cc-page-settings-scroll-fields' );
		if ( panel ) {
			panel.classList.toggle( 'is-disabled', ! enabled );
			panel.querySelectorAll( 'input,select' ).forEach( function ( input ) { input.disabled = ! enabled; } );
		}
	}

	function syncCodeGutter() {
		if ( ! form ) return;
		var textarea = form.querySelector( '[name="customCSS"]' );
		var gutter = form.querySelector( '.cc-page-settings-code-gutter' );
		if ( ! textarea || ! gutter ) return;
		var lines = Math.max( 1, textarea.value.split( /\r?\n/ ).length );
		gutter.textContent = Array.from( { length: lines }, function ( _, index ) { return index + 1; } ).join( '\n' );
	}

	function syncColorSwatches() {
		if ( ! form ) return;
		form.querySelectorAll( '[data-color-target]' ).forEach( function ( swatch ) {
			var value = valueOf( swatch.dataset.colorTarget, '' );
			if ( /^#[0-9a-f]{6}$/i.test( value ) ) swatch.value = value;
		} );
	}

	function resolvedSpacing( control, device ) {
		var result = { top: '', right: '', bottom: '', left: '' };
		var order = [ 'desktop' ];
		if ( device === 'tablet' || device === 'mobile' ) order.push( 'tablet' );
		if ( device === 'mobile' ) order.push( 'mobile' );
		order.forEach( function ( bucket ) {
			var valuesForDevice = safeObject( control[ bucket ] );
			Object.keys( result ).forEach( function ( side ) {
				if ( valuesForDevice[ side ] !== undefined && String( valuesForDevice[ side ] ) !== '' ) result[ side ] = String( valuesForDevice[ side ] );
			} );
		} );
		return result;
	}

	function activePreviewDevice() {
		var button = app ? app.querySelector( '.cc-standalone-devices button.is-active' ) : null;
		var label = button ? String( button.textContent || '' ).toLowerCase() : '';
		if ( label.indexOf( 'mobile' ) !== -1 ) return 'mobile';
		if ( label.indexOf( 'tablet' ) !== -1 ) return 'tablet';
		return 'desktop';
	}

	function cssDeclarationsForSpacing( data, device ) {
		var declarations = '';
		[ 'margin', 'padding' ].forEach( function ( key ) {
			var control = safeObject( data.bodyStyle[ key ] );
			var unit = control.unit || 'px';
			var resolved = resolvedSpacing( control, device );
			[ 'top', 'right', 'bottom', 'left' ].forEach( function ( side ) {
				if ( resolved[ side ] !== '' ) declarations += key + '-' + side + ':' + resolved[ side ] + unit + '!important;';
			} );
		} );
		return declarations;
	}

	function backgroundDeclarations( background ) {
		background = safeObject( background );
		if ( background.type === 'none' ) return '';
		var css = '';
		if ( background.type === 'classic' ) {
			if ( background.color ) css += 'background-color:' + background.color + ';';
			var image = safeObject( background.image );
			if ( image.url ) {
				css += 'background-image:url("' + String( image.url ).replace( /["\\]/g, '\\$&' ) + '");';
				css += 'background-position:' + String( image.position || 'center-center' ).replace( '-', ' ' ) + ';';
				css += 'background-repeat:' + ( image.repeat || 'no-repeat' ) + ';';
				css += 'background-size:' + ( image.size || 'cover' ) + ';';
				css += 'background-attachment:' + ( image.attachment || 'scroll' ) + ';';
			}
		} else if ( background.type === 'gradient' ) {
			var gradient = safeObject( background.gradient );
			if ( gradient.color1 && gradient.color2 ) css += 'background-image:linear-gradient(' + Number( gradient.angle || 0 ) + 'deg,' + gradient.color1 + ',' + gradient.color2 + ');';
			else if ( gradient.color1 || gradient.color2 ) css += 'background-color:' + ( gradient.color1 || gradient.color2 ) + ';';
		}
		return css;
	}

	function safePreviewCustomCss( css ) {
		css = String( css || '' ).trim();
		if ( ! css ) return '';
		if ( /(?:@import|@charset|@namespace|@media|@supports|url\s*\(|expression\s*\(|javascript:|behavior\s*:|-moz-binding|<\/?style|<!--|-->)/i.test( css ) ) return '';
		if ( ( css.match( /\{/g ) || [] ).length !== ( css.match( /\}/g ) || [] ).length ) return '';
		var cursor = 0;
		while ( true ) {
			var open = css.indexOf( '{', cursor );
			if ( open === -1 ) break;
			var close = css.indexOf( '}', open + 1 );
			var selector = css.slice( cursor, open ).trim();
			if ( close === -1 || ! selector || ( selector.indexOf( '&' ) === -1 && ! /\bselector\b/i.test( selector ) ) ) return '';
			cursor = close + 1;
		}
		return css.replace( /\bselector\b/gi, '.cc-session-canvas' ).split( '&' ).join( '.cc-session-canvas' );
	}

	function applyPreview( raw ) {
		if ( ! app ) return;
		var data = normalize( raw || current || pageDefaults() );
		if ( ! previewStyle ) {
			previewStyle = document.getElementById( 'cc-page-settings-live-preview' );
			if ( ! previewStyle ) {
				previewStyle = document.createElement( 'style' );
				previewStyle.id = 'cc-page-settings-live-preview';
				document.head.appendChild( previewStyle );
			}
		}
		var device = activePreviewDevice();
		var css = '.cc-session-canvas{' + cssDeclarationsForSpacing( data, device ) + backgroundDeclarations( data.bodyStyle.background ) + '}';
		css += safePreviewCustomCss( data.customCSS );
		if ( data.scrollSnap.enabled ) {
			css += '.cc-standalone-stage{scroll-snap-type:' + data.scrollSnap.axis + ' ' + data.scrollSnap.strictness + ';scroll-padding-top:' + Number( data.scrollSnap.offset || 0 ) + 'px;}.cc-session-canvas>.cc-canvas-node{scroll-snap-align:' + data.scrollSnap.align + ';scroll-snap-stop:' + data.scrollSnap.stop + ';}';
		}
		previewStyle.textContent = css;
	}

	function closeDialog( revert ) {
		if ( ! overlay || overlay.hidden ) return;
		if ( revert !== false && current ) populate( current, false );
		overlay.hidden = true;
		overlay.classList.remove( 'is-open' );
		document.body.classList.remove( 'cc-page-settings-open' );
		if ( lastFocus && document.body.contains( lastFocus ) ) lastFocus.focus();
	}

	function loadSettings() {
		if ( loading ) return Promise.resolve( null );
		loading = true;
		status( __( 'Loading Page Settings…', 'cresco-canvas' ), false );
		return apiFetch( { path: settings.pageSettingsPath } ).then( function ( result ) {
			if ( form ) populate( result, true );
			else { current = normalize( result && result.settings ? result.settings : result || {} ); applyPreview( current ); }
			status( '', false );
			return result;
		} ).catch( function ( error ) {
			status( error && error.message ? error.message : __( 'Page Settings could not be loaded.', 'cresco-canvas' ), true );
			return null;
		} ).finally( function () { loading = false; } );
	}

	function saveSettings() {
		if ( ! form || loading ) return;
		syncLayoutControls();
		loading = true;
		var save = overlay ? overlay.querySelector( '.cc-page-settings-save' ) : null;
		if ( save ) save.disabled = true;
		status( __( 'Saving…', 'cresco-canvas' ), false );
		apiFetch( { path: settings.pageSettingsPath, method: 'POST', data: { settings: values() } } ).then( function ( result ) {
			populate( result, true );
			status( __( 'Page Settings saved. Editor and Preview now use the new settings.', 'cresco-canvas' ), false );
			window.dispatchEvent( new CustomEvent( 'cresco:page-settings-saved', { detail: result } ) );
		} ).catch( function ( error ) {
			status( error && error.message ? error.message : __( 'Page Settings could not be saved.', 'cresco-canvas' ), true );
		} ).finally( function () {
			loading = false;
			if ( save ) save.disabled = false;
		} );
	}

	function accordion( title, id, expanded, content ) {
		var section = element( 'section', 'cc-page-settings-accordion' );
		var button = element( 'button', 'cc-page-settings-accordion__toggle' );
		button.type = 'button';
		button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		button.setAttribute( 'aria-controls', id );
		var arrow = element( 'span', 'dashicons dashicons-arrow-down-alt2' );
		arrow.setAttribute( 'aria-hidden', 'true' );
		button.appendChild( arrow );
		button.appendChild( element( 'span', '', title ) );
		var panel = element( 'div', 'cc-page-settings-accordion__content' );
		panel.id = id;
		panel.hidden = ! expanded;
		panel.appendChild( content );
		button.addEventListener( 'click', function () {
			var open = button.getAttribute( 'aria-expanded' ) !== 'true';
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			panel.hidden = ! open;
		} );
		section.appendChild( button );
		section.appendChild( panel );
		return section;
	}

	function buildSettingsPanel() {
		var panel = element( 'div', 'cc-page-settings-tab-panel' );
		panel.dataset.pageTabPanel = 'settings';
		panel.setAttribute( 'role', 'tabpanel' );

		var layoutSection = element( 'section', 'cc-page-settings-section' );
		layoutSection.appendChild( element( 'h3', '', __( 'Page Layout', 'cresco-canvas' ) ) );
		layoutSection.appendChild( selectField( __( 'Layout', 'cresco-canvas' ), 'layout', [
			{ label: __( 'Theme Default', 'cresco-canvas' ), value: 'theme-default' },
			{ label: __( 'Full Width', 'cresco-canvas' ), value: 'full-width' },
			{ label: __( 'Canvas', 'cresco-canvas' ), value: 'canvas' }
		] ) );
		layoutSection.appendChild( element( 'p', 'cc-page-settings-layout-note' ) );
		panel.appendChild( layoutSection );

		var elementsSection = element( 'section', 'cc-page-settings-section' );
		elementsSection.appendChild( element( 'h3', '', __( 'Page Elements', 'cresco-canvas' ) ) );
		elementsSection.appendChild( selectField( __( 'Page Title', 'cresco-canvas' ), 'pageTitle', [ { label: __( 'Show', 'cresco-canvas' ), value: 'show' }, { label: __( 'Hide', 'cresco-canvas' ), value: 'hide' } ] ) );
		elementsSection.appendChild( selectField( __( 'Site Header', 'cresco-canvas' ), 'header', [ { label: __( 'Inherit theme', 'cresco-canvas' ), value: 'inherit' }, { label: __( 'Show', 'cresco-canvas' ), value: 'show' }, { label: __( 'Hide', 'cresco-canvas' ), value: 'hide' } ] ) );
		elementsSection.appendChild( selectField( __( 'Site Footer', 'cresco-canvas' ), 'footer', [ { label: __( 'Inherit theme', 'cresco-canvas' ), value: 'inherit' }, { label: __( 'Show', 'cresco-canvas' ), value: 'show' }, { label: __( 'Hide', 'cresco-canvas' ), value: 'hide' } ] ) );
		panel.appendChild( elementsSection );

		var rootSection = element( 'section', 'cc-page-settings-section' );
		rootSection.appendChild( element( 'h3', '', __( 'Cresco Content', 'cresco-canvas' ) ) );
		rootSection.appendChild( selectField( __( 'Cresco Root', 'cresco-canvas' ), 'contentRoot', [ { label: __( 'Theme Content Width', 'cresco-canvas' ), value: 'theme' }, { label: __( 'Full Viewport', 'cresco-canvas' ), value: 'viewport' } ], __( 'Full Width and Canvas always use Full Viewport. Container Full Width still means 100% of its parent.', 'cresco-canvas' ) ) );
		panel.appendChild( rootSection );
		return panel;
	}

	function buildDeviceSwitcher() {
		var switcher = element( 'div', 'cc-page-settings-device-switcher' );
		[ [ 'desktop', 'desktop', __( 'Desktop', 'cresco-canvas' ) ], [ 'tablet', 'tablet', __( 'Tablet', 'cresco-canvas' ) ], [ 'mobile', 'smartphone', __( 'Mobile', 'cresco-canvas' ) ] ].forEach( function ( item ) {
			var button = element( 'button', 'cc-page-settings-device-button' );
			button.type = 'button';
			button.dataset.styleDevice = item[ 0 ];
			button.setAttribute( 'aria-label', item[ 2 ] );
			button.setAttribute( 'aria-pressed', 'false' );
			var icon = element( 'span', 'dashicons dashicons-' + item[ 1 ] );
			icon.setAttribute( 'aria-hidden', 'true' );
			button.appendChild( icon );
			button.appendChild( element( 'span', '', item[ 2 ] ) );
			button.addEventListener( 'click', function () {
				styleDevice = item[ 0 ];
				syncStyleDevice();
				var editorLabels = { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' };
				var editorButton = Array.prototype.find.call( app.querySelectorAll( '.cc-standalone-devices button' ), function ( candidate ) { return String( candidate.textContent || '' ).trim() === editorLabels[ styleDevice ]; } );
				if ( editorButton ) editorButton.click();
				window.setTimeout( function () { applyPreview( values() ); }, 0 );
			} );
			switcher.appendChild( button );
		} );
		return switcher;
	}

	function buildSpacingControl( label, key, allowNegative ) {
		var block = element( 'div', 'cc-page-settings-spacing' );
		var header = element( 'div', 'cc-page-settings-spacing__header' );
		header.appendChild( element( 'strong', '', label ) );
		var actions = element( 'div', 'cc-page-settings-spacing__actions' );
		var unit = element( 'select', 'cc-page-settings-unit' );
		unit.name = key + 'Unit';
		[ 'px', '%', 'em', 'rem', 'vh', 'vw' ].forEach( function ( value ) { var option = element( 'option', '', value ); option.value = value; unit.appendChild( option ); } );
		var link = element( 'button', 'cc-page-settings-link' );
		link.type = 'button';
		link.dataset.spacingLink = key;
		link.setAttribute( 'aria-label', __( 'Link values', 'cresco-canvas' ) );
		link.setAttribute( 'aria-pressed', 'true' );
		var linkIcon = element( 'span', 'dashicons dashicons-admin-links' );
		linkIcon.setAttribute( 'aria-hidden', 'true' );
		link.appendChild( linkIcon );
		link.addEventListener( 'click', function () {
			var linked = link.getAttribute( 'aria-pressed' ) !== 'true';
			link.setAttribute( 'aria-pressed', linked ? 'true' : 'false' );
			link.classList.toggle( 'is-linked', linked );
		} );
		actions.appendChild( unit );
		actions.appendChild( link );
		header.appendChild( actions );
		block.appendChild( header );

		[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
			var group = element( 'div', 'cc-page-settings-spacing__grid' );
			group.dataset.spacingDevice = device;
			[ 'top', 'right', 'bottom', 'left' ].forEach( function ( side ) {
				var field = element( 'label', 'cc-page-settings-side' );
				var input = element( 'input', 'cc-page-settings-side__input' );
				input.type = 'number';
				input.step = '0.1';
				if ( ! allowNegative ) input.min = '0';
				input.name = key + '-' + device + '-' + side;
				input.dataset.spacingKey = key;
				input.dataset.spacingDeviceInput = device;
				input.addEventListener( 'input', function () {
					if ( link.getAttribute( 'aria-pressed' ) === 'true' ) {
						group.querySelectorAll( '.cc-page-settings-side__input' ).forEach( function ( sibling ) { if ( sibling !== input ) sibling.value = input.value; } );
					}
				} );
				field.appendChild( input );
				field.appendChild( element( 'span', '', side.charAt( 0 ).toUpperCase() + side.slice( 1 ) ) );
				group.appendChild( field );
			} );
			block.appendChild( group );
		} );
		return block;
	}

	function buildBackgroundControl() {
		var block = element( 'div', 'cc-page-settings-background' );
		var row = element( 'div', 'cc-page-settings-background__header' );
		row.appendChild( element( 'strong', '', __( 'Background Type', 'cresco-canvas' ) ) );
		var typeValue = element( 'input' );
		typeValue.type = 'hidden';
		typeValue.name = 'backgroundType';
		row.appendChild( typeValue );
		var types = element( 'div', 'cc-page-settings-segmented' );
		[ [ 'none', 'minus', __( 'None', 'cresco-canvas' ) ], [ 'classic', 'art', __( 'Classic', 'cresco-canvas' ) ], [ 'gradient', 'image-filter', __( 'Gradient', 'cresco-canvas' ) ] ].forEach( function ( item ) {
			var button = element( 'button', 'cc-page-settings-segmented__button' );
			button.type = 'button';
			button.dataset.backgroundType = item[ 0 ];
			button.title = item[ 2 ];
			button.setAttribute( 'aria-label', item[ 2 ] );
			button.setAttribute( 'aria-pressed', 'false' );
			var icon = element( 'span', 'dashicons dashicons-' + item[ 1 ] ); icon.setAttribute( 'aria-hidden', 'true' ); button.appendChild( icon );
			button.addEventListener( 'click', function () { typeValue.value = item[ 0 ]; syncBackgroundControls(); applyPreview( values() ); } );
			types.appendChild( button );
		} );
		row.appendChild( types );
		block.appendChild( row );

		var classic = element( 'div', 'cc-page-settings-background-panel' );
		classic.dataset.backgroundPanel = 'classic';
		classic.appendChild( colorField( __( 'Color', 'cresco-canvas' ), 'backgroundColor' ) );
		var image = element( 'div', 'cc-page-settings-image-field' );
		var imageLabel = element( 'div', 'cc-page-settings-image-field__label', __( 'Image', 'cresco-canvas' ) );
		image.appendChild( imageLabel );
		var imageControls = element( 'div', 'cc-page-settings-image-field__controls' );
		var imagePreview = element( 'div', 'cc-page-settings-image-preview' ); imagePreview.hidden = true;
		var imageId = element( 'input' ); imageId.type = 'hidden'; imageId.name = 'backgroundImageId';
		var imageUrl = element( 'input' ); imageUrl.type = 'hidden'; imageUrl.name = 'backgroundImageUrl';
		var choose = makeButton( __( 'Choose Image', 'cresco-canvas' ), 'secondary' ); choose.classList.add( 'cc-page-settings-image-choose' );
		var clear = makeButton( __( 'Clear', 'cresco-canvas' ), 'tertiary' ); clear.classList.add( 'cc-page-settings-image-clear' );
		choose.addEventListener( 'click', chooseBackgroundImage );
		clear.addEventListener( 'click', function () { imageId.value = '0'; imageUrl.value = ''; syncBackgroundControls(); applyPreview( values() ); } );
		imageControls.appendChild( imagePreview ); imageControls.appendChild( imageId ); imageControls.appendChild( imageUrl ); imageControls.appendChild( choose ); imageControls.appendChild( clear );
		image.appendChild( imageControls );
		classic.appendChild( image );
		classic.appendChild( selectField( __( 'Position', 'cresco-canvas' ), 'backgroundImagePosition', [
			{ label: __( 'Center Center', 'cresco-canvas' ), value: 'center-center' }, { label: __( 'Center Top', 'cresco-canvas' ), value: 'center-top' }, { label: __( 'Center Bottom', 'cresco-canvas' ), value: 'center-bottom' },
			{ label: __( 'Left Top', 'cresco-canvas' ), value: 'left-top' }, { label: __( 'Left Center', 'cresco-canvas' ), value: 'left-center' }, { label: __( 'Left Bottom', 'cresco-canvas' ), value: 'left-bottom' },
			{ label: __( 'Right Top', 'cresco-canvas' ), value: 'right-top' }, { label: __( 'Right Center', 'cresco-canvas' ), value: 'right-center' }, { label: __( 'Right Bottom', 'cresco-canvas' ), value: 'right-bottom' }
		] ) );
		classic.appendChild( selectField( __( 'Repeat', 'cresco-canvas' ), 'backgroundImageRepeat', [ { label: __( 'No Repeat', 'cresco-canvas' ), value: 'no-repeat' }, { label: __( 'Repeat', 'cresco-canvas' ), value: 'repeat' }, { label: __( 'Repeat X', 'cresco-canvas' ), value: 'repeat-x' }, { label: __( 'Repeat Y', 'cresco-canvas' ), value: 'repeat-y' } ] ) );
		classic.appendChild( selectField( __( 'Size', 'cresco-canvas' ), 'backgroundImageSize', [ { label: __( 'Cover', 'cresco-canvas' ), value: 'cover' }, { label: __( 'Contain', 'cresco-canvas' ), value: 'contain' }, { label: __( 'Auto', 'cresco-canvas' ), value: 'auto' } ] ) );
		classic.appendChild( selectField( __( 'Attachment', 'cresco-canvas' ), 'backgroundImageAttachment', [ { label: __( 'Scroll', 'cresco-canvas' ), value: 'scroll' }, { label: __( 'Fixed', 'cresco-canvas' ), value: 'fixed' } ] ) );
		block.appendChild( classic );

		var gradient = element( 'div', 'cc-page-settings-background-panel' );
		gradient.dataset.backgroundPanel = 'gradient';
		gradient.appendChild( colorField( __( 'Color 1', 'cresco-canvas' ), 'gradientColor1' ) );
		gradient.appendChild( colorField( __( 'Color 2', 'cresco-canvas' ), 'gradientColor2' ) );
		var angle = inputField( __( 'Angle', 'cresco-canvas' ), 'gradientAngle', 'number' );
		var angleInput = angle.querySelector( 'input' ); angleInput.min = '0'; angleInput.max = '360'; angleInput.step = '1';
		gradient.appendChild( angle );
		block.appendChild( gradient );
		return block;
	}

	function buildStylePanel() {
		var panel = element( 'div', 'cc-page-settings-tab-panel' );
		panel.dataset.pageTabPanel = 'style';
		panel.setAttribute( 'role', 'tabpanel' );
		var body = element( 'div', 'cc-page-settings-body-style' );
		body.appendChild( buildDeviceSwitcher() );
		body.appendChild( buildSpacingControl( __( 'Margin', 'cresco-canvas' ), 'margin', true ) );
		body.appendChild( buildSpacingControl( __( 'Padding', 'cresco-canvas' ), 'padding', false ) );
		body.appendChild( buildBackgroundControl() );
		panel.appendChild( accordion( __( 'Body Style', 'cresco-canvas' ), 'cc-page-body-style', true, body ) );
		return panel;
	}

	function buildCustomCss() {
		var wrap = element( 'div', 'cc-page-settings-custom-css' );
		var header = element( 'div', 'cc-page-settings-custom-css__header' );
		header.appendChild( element( 'span', '', __( 'Add your own custom CSS', 'cresco-canvas' ) ) );
		var ai = element( 'button', 'cc-page-settings-ai-css' );
		ai.type = 'button';
		ai.innerHTML = '<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>' + __( 'Edit with AI', 'cresco-canvas' );
		ai.addEventListener( 'click', editCssWithAi );
		header.appendChild( ai );
		wrap.appendChild( header );
		var editor = element( 'div', 'cc-page-settings-code-editor' );
		var gutter = element( 'div', 'cc-page-settings-code-gutter', '1' );
		var textarea = element( 'textarea', 'cc-page-settings-code-textarea' );
		textarea.name = 'customCSS';
		textarea.rows = 10;
		textarea.spellcheck = false;
		textarea.placeholder = 'selector {\n  /* Page-scoped styles */\n}';
		textarea.addEventListener( 'input', function () {
			var lines = Math.max( 1, textarea.value.split( /\r?\n/ ).length );
			gutter.textContent = Array.from( { length: lines }, function ( _, index ) { return index + 1; } ).join( '\n' );
		} );
		editor.appendChild( gutter ); editor.appendChild( textarea ); wrap.appendChild( editor );
		var help = element( 'p', 'cc-page-settings-code-help' );
		help.appendChild( document.createTextNode( __( 'Use ', 'cresco-canvas' ) ) );
		help.appendChild( element( 'code', '', 'selector' ) );
		help.appendChild( document.createTextNode( __( ' or & to target this Cresco page root. Global selectors, @media, @import, url(), and executable CSS are blocked.', 'cresco-canvas' ) ) );
		wrap.appendChild( help );
		return wrap;
	}

	function buildScrollSnap() {
		var wrap = element( 'div', 'cc-page-settings-scroll-snap' );
		var enabled = element( 'label', 'cc-page-settings-toggle' );
		var checkbox = element( 'input' ); checkbox.type = 'checkbox'; checkbox.name = 'scrollSnapEnabled';
		enabled.appendChild( checkbox ); enabled.appendChild( element( 'span', 'cc-page-settings-toggle__track' ) ); enabled.appendChild( element( 'span', 'cc-page-settings-toggle__label', __( 'Enable Scroll Snap', 'cresco-canvas' ) ) );
		wrap.appendChild( enabled );
		var fields = element( 'div', 'cc-page-settings-scroll-fields' );
		fields.appendChild( selectField( __( 'Axis', 'cresco-canvas' ), 'scrollSnapAxis', [ { label: __( 'Vertical', 'cresco-canvas' ), value: 'y' }, { label: __( 'Horizontal', 'cresco-canvas' ), value: 'x' }, { label: __( 'Both', 'cresco-canvas' ), value: 'both' } ] ) );
		fields.appendChild( selectField( __( 'Behavior', 'cresco-canvas' ), 'scrollSnapStrictness', [ { label: __( 'Proximity', 'cresco-canvas' ), value: 'proximity' }, { label: __( 'Mandatory', 'cresco-canvas' ), value: 'mandatory' } ] ) );
		fields.appendChild( selectField( __( 'Section Align', 'cresco-canvas' ), 'scrollSnapAlign', [ { label: __( 'Start', 'cresco-canvas' ), value: 'start' }, { label: __( 'Center', 'cresco-canvas' ), value: 'center' }, { label: __( 'End', 'cresco-canvas' ), value: 'end' } ] ) );
		fields.appendChild( selectField( __( 'Stop', 'cresco-canvas' ), 'scrollSnapStop', [ { label: __( 'Normal', 'cresco-canvas' ), value: 'normal' }, { label: __( 'Always', 'cresco-canvas' ), value: 'always' } ] ) );
		var offset = inputField( __( 'Top Offset', 'cresco-canvas' ), 'scrollSnapOffset', 'number', __( 'Useful when the site uses a sticky header.', 'cresco-canvas' ) );
		var offsetInput = offset.querySelector( 'input' ); offsetInput.min = '0'; offsetInput.max = '500'; offsetInput.step = '1';
		fields.appendChild( offset );
		wrap.appendChild( fields );
		return wrap;
	}

	function buildAdvancedPanel() {
		var panel = element( 'div', 'cc-page-settings-tab-panel' );
		panel.dataset.pageTabPanel = 'advanced';
		panel.setAttribute( 'role', 'tabpanel' );
		panel.appendChild( accordion( __( 'Custom CSS', 'cresco-canvas' ), 'cc-page-custom-css', true, buildCustomCss() ) );
		panel.appendChild( accordion( __( 'Scroll Snap', 'cresco-canvas' ), 'cc-page-scroll-snap', false, buildScrollSnap() ) );
		return panel;
	}

	function activateTab( id, focus ) {
		activeTab = id;
		if ( ! form ) return;
		form.querySelectorAll( '[data-page-tab]' ).forEach( function ( button ) {
			var active = button.dataset.pageTab === id;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			button.tabIndex = active ? 0 : -1;
			if ( active && focus ) button.focus();
		} );
		form.querySelectorAll( '[data-page-tab-panel]' ).forEach( function ( panel ) { panel.hidden = panel.dataset.pageTabPanel !== id; } );
	}

	function buildTabs() {
		var tabs = element( 'div', 'cc-page-settings-tabs' );
		tabs.setAttribute( 'role', 'tablist' );
		[ [ 'settings', 'admin-tools', __( 'Settings', 'cresco-canvas' ) ], [ 'style', 'art', __( 'Style', 'cresco-canvas' ) ], [ 'advanced', 'admin-generic', __( 'Advanced', 'cresco-canvas' ) ] ].forEach( function ( item ) {
			var button = element( 'button', 'cc-page-settings-tab' );
			button.type = 'button';
			button.dataset.pageTab = item[ 0 ];
			button.setAttribute( 'role', 'tab' );
			var icon = element( 'span', 'dashicons dashicons-' + item[ 1 ] ); icon.setAttribute( 'aria-hidden', 'true' );
			button.appendChild( icon ); button.appendChild( element( 'span', '', item[ 2 ] ) );
			button.addEventListener( 'click', function () { activateTab( item[ 0 ], false ); } );
			button.addEventListener( 'keydown', function ( event ) {
				if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) return;
				event.preventDefault();
				var order = [ 'settings', 'style', 'advanced' ];
				var index = order.indexOf( item[ 0 ] );
				index = event.key === 'ArrowRight' ? ( index + 1 ) % order.length : ( index + order.length - 1 ) % order.length;
				activateTab( order[ index ], true );
			} );
			tabs.appendChild( button );
		} );
		return tabs;
	}

	function chooseBackgroundImage() {
		if ( ! window.wp || ! window.wp.media || ! form ) return;
		mediaFrame = window.wp.media( { title: __( 'Choose Page Background', 'cresco-canvas' ), button: { text: __( 'Use Background', 'cresco-canvas' ) }, library: { type: 'image' }, multiple: false } );
		mediaFrame.on( 'select', function () {
			var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
			setValue( 'backgroundImageId', attachment.id || 0 );
			setValue( 'backgroundImageUrl', attachment.url || '' );
			syncBackgroundControls();
			applyPreview( values() );
		} );
		mediaFrame.open();
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) return navigator.clipboard.writeText( text );
		return new Promise( function ( resolve, reject ) {
			try {
				var field = document.createElement( 'textarea' ); field.value = text; field.style.position = 'fixed'; field.style.opacity = '0'; document.body.appendChild( field ); field.select(); document.execCommand( 'copy' ); field.remove(); resolve();
			} catch ( error ) { reject( error ); }
		} );
	}

	function editCssWithAi() {
		var css = valueOf( 'customCSS', '' );
		var prompt = [
			'Edit the following Cresco Page Custom CSS.',
			'Rules: use `selector` to target the Cresco page root; do not use @media, @import, url(), JavaScript, or global html/body/:root selectors; return only scoped CSS.',
			'',
			css || 'selector {\n\n}'
		].join( '\n' );
		copyText( prompt ).then( function () {
			status( __( 'AI CSS prompt copied. Paste the returned scoped CSS back into this editor.', 'cresco-canvas' ), false );
			window.dispatchEvent( new CustomEvent( 'cresco:page-css-ai-request', { detail: { prompt: prompt, css: css } } ) );
		} ).catch( function () { status( __( 'Clipboard access failed.', 'cresco-canvas' ), true ); } );
	}

	function buildDialog() {
		if ( overlay && document.body.contains( overlay ) ) return;
		overlay = element( 'div', 'cc-page-settings-overlay' );
		overlay.hidden = true;
		overlay.addEventListener( 'mousedown', function ( event ) { if ( event.target === overlay ) closeDialog( true ); } );

		var dialog = element( 'section', 'cc-page-settings-dialog' );
		dialog.setAttribute( 'role', 'dialog' ); dialog.setAttribute( 'aria-modal', 'true' ); dialog.setAttribute( 'aria-labelledby', 'cc-page-settings-title' );
		overlay.appendChild( dialog );

		var header = element( 'header', 'cc-page-settings-dialog__header' );
		var title = element( 'h2', '', __( 'Page Settings', 'cresco-canvas' ) ); title.id = 'cc-page-settings-title';
		header.appendChild( title );
		var close = makeButton( '×', 'tertiary' ); close.classList.add( 'cc-page-settings-close' ); close.setAttribute( 'aria-label', __( 'Close Page Settings', 'cresco-canvas' ) ); close.addEventListener( 'click', function () { closeDialog( true ); } ); header.appendChild( close );
		dialog.appendChild( header );

		form = element( 'div', 'cc-page-settings-form' );
		form.appendChild( buildTabs() );
		var content = element( 'div', 'cc-page-settings-content' );
		content.appendChild( buildSettingsPanel() ); content.appendChild( buildStylePanel() ); content.appendChild( buildAdvancedPanel() );
		form.appendChild( content );
		form.addEventListener( 'change', handleFormChange );
		form.addEventListener( 'input', handleFormInput );
		dialog.appendChild( form );

		var footer = element( 'footer', 'cc-page-settings-dialog__footer' );
		var statusNode = element( 'div', 'cc-page-settings-status' ); statusNode.setAttribute( 'aria-live', 'polite' ); footer.appendChild( statusNode );
		var actions = element( 'div', 'cc-page-settings-actions' );
		var cancel = makeButton( __( 'Cancel', 'cresco-canvas' ), 'secondary' ); cancel.addEventListener( 'click', function () { closeDialog( true ); } );
		var save = makeButton( __( 'Save Page Settings', 'cresco-canvas' ), 'primary' ); save.classList.add( 'cc-page-settings-save' ); save.addEventListener( 'click', saveSettings );
		actions.appendChild( cancel ); actions.appendChild( save ); footer.appendChild( actions ); dialog.appendChild( footer );
		app.appendChild( overlay );
		activateTab( activeTab, false );
	}

	function handleFormInput( event ) {
		if ( ! event.target ) return;
		if ( event.target.name === 'customCSS' ) syncCodeGutter();
		if ( event.target.matches( '[data-color-target]' ) ) {
			setValue( event.target.dataset.colorTarget, event.target.value );
		}
		if ( event.target.name && /^backgroundColor$|^gradientColor[12]$/.test( event.target.name ) && /^#[0-9a-f]{6}$/i.test( event.target.value ) ) syncColorSwatches();
		applyPreview( values() );
	}

	function handleFormChange( event ) {
		if ( ! event.target ) return;
		if ( event.target.name === 'layout' ) {
			if ( event.target.value === 'theme-default' ) setValue( 'contentRoot', 'theme' );
			syncLayoutControls();
		}
		if ( event.target.name === 'scrollSnapEnabled' ) syncScrollControls();
		if ( event.target.name && event.target.name.indexOf( 'background' ) === 0 ) syncBackgroundControls();
		applyPreview( values() );
	}

	function openDialog() {
		buildDialog();
		lastFocus = document.activeElement;
		overlay.hidden = false;
		overlay.classList.add( 'is-open' );
		document.body.classList.add( 'cc-page-settings-open' );
		activateTab( activeTab, false );
		if ( current ) {
			populate( current, false );
			var active = form.querySelector( '[data-page-tab].is-active' ); if ( active ) active.focus();
			return;
		}
		loadSettings().then( function () { var active = form && form.querySelector( '[data-page-tab].is-active' ); if ( active ) active.focus(); } );
	}

	function ensureButton() {
		if ( ! app ) return;
		var actions = app.querySelector( '.cc-standalone-header-actions' );
		if ( ! actions ) return;
		var existing = actions.querySelector( '.cc-page-settings-trigger' );
		if ( existing ) { trigger = existing; return; }
		trigger = makeButton( '', 'secondary' ); trigger.classList.add( 'cc-page-settings-trigger' ); trigger.setAttribute( 'aria-label', __( 'Page Settings', 'cresco-canvas' ) ); trigger.title = __( 'Page Settings', 'cresco-canvas' );
		var icon = element( 'span', 'dashicons dashicons-admin-settings' ); icon.setAttribute( 'aria-hidden', 'true' );
		trigger.appendChild( icon ); trigger.appendChild( element( 'span', 'cc-page-settings-trigger__label', __( 'Page', 'cresco-canvas' ) ) ); trigger.addEventListener( 'click', openDialog );
		var preview = Array.prototype.find.call( actions.querySelectorAll( 'a,button' ), function ( node ) { return String( node.textContent || '' ).trim() === __( 'Preview', 'cresco-canvas' ); } );
		actions.insertBefore( trigger, preview || null );
	}

	function handleKeydown( event ) {
		if ( ! overlay || overlay.hidden ) return;
		if ( event.key === 'Escape' ) { event.preventDefault(); closeDialog( true ); return; }
		if ( event.key !== 'Tab' ) return;
		var focusable = Array.prototype.filter.call( overlay.querySelectorAll( 'button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href]' ), function ( node ) { return ! node.hidden && node.offsetParent !== null; } );
		if ( ! focusable.length ) return;
		var first = focusable[ 0 ]; var last = focusable[ focusable.length - 1 ];
		if ( event.shiftKey && document.activeElement === first ) { event.preventDefault(); last.focus(); }
		else if ( ! event.shiftKey && document.activeElement === last ) { event.preventDefault(); first.focus(); }
	}

	function boot() {
		app = document.querySelector( '.cc-standalone-app' );
		if ( ! app ) { window.setTimeout( boot, 80 ); return; }
		ensureButton();
		document.addEventListener( 'keydown', handleKeydown );
		app.addEventListener( 'click', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-standalone-devices button' ) ) window.setTimeout( function () { applyPreview( form ? values() : current ); }, 0 );
		} );
		if ( window.MutationObserver ) new window.MutationObserver( ensureButton ).observe( app, { childList: true, subtree: true } );
		loadSettings();
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )( window.wp, window, document );
