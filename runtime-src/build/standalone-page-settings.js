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
	var dialog = null;
	var content = null;
	var footer = null;
	var currentView = 'root';
	var pageSettings = null;
	var globalSettings = null;
	var siteIdentity = null;
	var loading = false;
	var saving = false;
	var lastFocus = null;
	var globalGuideEnabled = false;
	var dirty = { page: false, global: false, identity: false };
	var statusMessage = '';
	var statusIsError = false;

	var viewMeta = {
		root: { title: __( 'Site Settings', 'cresco-canvas' ), scope: '' },
		colors: { title: __( 'Global Colors', 'cresco-canvas' ), scope: 'global' },
		fonts: { title: __( 'Global Fonts', 'cresco-canvas' ), scope: 'global' },
		typography: { title: __( 'Typography', 'cresco-canvas' ), scope: 'global' },
		buttons: { title: __( 'Buttons', 'cresco-canvas' ), scope: 'global' },
		images: { title: __( 'Images', 'cresco-canvas' ), scope: 'global' },
		forms: { title: __( 'Form Fields', 'cresco-canvas' ), scope: 'global' },
		header: { title: __( 'Header', 'cresco-canvas' ), scope: 'page' },
		footer: { title: __( 'Footer', 'cresco-canvas' ), scope: 'page' },
		identity: { title: __( 'Site Identity', 'cresco-canvas' ), scope: 'identity' },
		background: { title: __( 'Background', 'cresco-canvas' ), scope: 'global' },
		layout: { title: __( 'Layout Settings', 'cresco-canvas' ), scope: 'mixed' },
		lightbox: { title: __( 'Lightbox', 'cresco-canvas' ), scope: '' },
		transitions: { title: __( 'Page Transitions', 'cresco-canvas' ), scope: '' },
		customCss: { title: __( 'Custom CSS', 'cresco-canvas' ), scope: 'global' },
		additional: { title: __( 'Additional Settings', 'cresco-canvas' ), scope: 'global' }
	};

	function element( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( text !== undefined ) node.textContent = text;
		return node;
	}

	function icon( name ) {
		var node = element( 'span', 'dashicons dashicons-' + name + ' cc-site-settings-icon' );
		node.setAttribute( 'aria-hidden', 'true' );
		return node;
	}

	function makeButton( label, className ) {
		var button = element( 'button', className || '', label );
		button.type = 'button';
		return button;
	}

	function clone( value ) {
		return JSON.parse( JSON.stringify( value || {} ) );
	}

	function canManageGlobal() {
		return !! settings.canManageGlobal && !! settings.settingsPath;
	}

	function defaultPageSettings() {
		return {
			layout: 'full-width',
			pageTitle: 'hide',
			header: 'inherit',
			footer: 'inherit',
			contentRoot: 'viewport'
		};
	}

	function defaultGlobalSettings() {
		return {
			primary: '#635bff',
			text: '#111827',
			muted: '#6b7280',
			background: '#ffffff',
			containerMax: 1440,
			contentMax: 1200,
			radius: 12,
			fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			fluidTokens: {
				fontBase: 'clamp(1rem, 0.95rem + 0.2vw, 1.125rem)',
				h1: 'clamp(2.25rem, 1.45rem + 3.1vw, 4.75rem)',
				h2: 'clamp(1.875rem, 1.35rem + 2vw, 3.375rem)',
				h3: 'clamp(1.5rem, 1.15rem + 1.35vw, 2.5rem)',
				h4: 'clamp(1.25rem, 1.05rem + 0.85vw, 1.875rem)',
				h5: 'clamp(1.125rem, 1rem + 0.5vw, 1.5rem)',
				h6: 'clamp(1rem, 0.94rem + 0.25vw, 1.1875rem)',
				containerGutter: 'clamp(1rem, 0.5rem + 2vw, 2.5rem)',
				gridGap: 'clamp(1rem, 0.7rem + 1vw, 2rem)',
				radiusSm: 'clamp(0.375rem, 0.32rem + 0.15vw, 0.5rem)',
				radiusMd: 'clamp(0.5rem, 0.4rem + 0.25vw, 0.75rem)',
				controlHeight: 'clamp(2.75rem, 2.55rem + 0.5vw, 3.125rem)',
				buttonPadding: 'clamp(1rem, 0.8rem + 0.65vw, 1.5rem)'
			},
			breakpoints: { mobile: 0, tablet: 768, laptop: 1025, desktop: 1440, wide: 1920 },
			customColors: {},
			aliases: {},
			customCss: '',
			removeDataOnUninstall: false
		};
	}

	function mergeGlobalDefaults( input ) {
		var base = defaultGlobalSettings();
		var result = Object.assign( {}, base, input || {} );
		result.fluidTokens = Object.assign( {}, base.fluidTokens, ( input && input.fluidTokens ) || {} );
		result.breakpoints = Object.assign( {}, base.breakpoints, ( input && input.breakpoints ) || {} );
		result.customColors = Object.assign( {}, ( input && input.customColors ) || {} );
		result.aliases = Object.assign( {}, ( input && input.aliases ) || {} );
		result.customCss = typeof result.customCss === 'string' ? result.customCss : '';
		return result;
	}

	function setStatus( text, error ) {
		statusMessage = text || '';
		statusIsError = !! error;
		var node = footer ? footer.querySelector( '.cc-page-settings-status' ) : null;
		if ( node ) {
			node.textContent = statusMessage;
			node.classList.toggle( 'is-error', statusIsError );
		}
	}

	function markDirty( scope ) {
		if ( dirty[ scope ] !== undefined ) dirty[ scope ] = true;
		setStatus( __( 'Unsaved changes', 'cresco-canvas' ), false );
	}

	function sectionTitle( text ) {
		return element( 'div', 'cc-site-settings-section-label', text );
	}

	function panelSection( title ) {
		var section = element( 'section', 'cc-site-settings-panel-section' );
		if ( title ) section.appendChild( element( 'h3', 'cc-site-settings-panel-title', title ) );
		return section;
	}

	function navRow( label, iconName, view, disabled ) {
		var row = makeButton( '', 'cc-site-settings-nav-row' + ( disabled ? ' is-disabled' : '' ) );
		row.disabled = !! disabled;
		row.appendChild( icon( iconName ) );
		row.appendChild( element( 'span', 'cc-site-settings-nav-label', label ) );
		row.appendChild( icon( 'arrow-right-alt2' ) );
		if ( ! disabled ) row.addEventListener( 'click', function () { showView( view ); } );
		return row;
	}

	function helpText( text ) {
		return element( 'p', 'cc-site-settings-help', text );
	}

	function notice( text, tone ) {
		return element( 'div', 'cc-site-settings-notice' + ( tone ? ' is-' + tone : '' ), text );
	}

	function field( label, control, help ) {
		var wrapper = element( 'label', 'cc-site-settings-field' );
		wrapper.appendChild( element( 'span', 'cc-site-settings-field-label', label ) );
		wrapper.appendChild( control );
		if ( help ) wrapper.appendChild( helpText( help ) );
		return wrapper;
	}

	function textInput( value, onChange, options ) {
		options = options || {};
		var input = element( options.multiline ? 'textarea' : 'input', 'cc-site-settings-input' + ( options.code ? ' is-code' : '' ) );
		if ( ! options.multiline ) input.type = options.type || 'text';
		if ( options.multiline ) input.rows = options.rows || 8;
		input.value = value === undefined || value === null ? '' : String( value );
		if ( options.placeholder ) input.placeholder = options.placeholder;
		if ( options.min !== undefined ) input.min = String( options.min );
		if ( options.max !== undefined ) input.max = String( options.max );
		if ( options.step !== undefined ) input.step = String( options.step );
		input.addEventListener( 'input', function () { onChange( input.value ); } );
		return input;
	}

	function selectInput( value, options, onChange, name ) {
		var select = element( 'select', 'cc-site-settings-input cc-site-settings-select' );
		if ( name ) select.name = name;
		options.forEach( function ( option ) {
			var item = element( 'option', '', option.label );
			item.value = option.value;
			select.appendChild( item );
		} );
		select.value = value;
		select.addEventListener( 'change', function () { onChange( select.value ); } );
		return select;
	}

	function toggleInput( checked, onChange ) {
		var wrapper = element( 'span', 'cc-site-settings-toggle' );
		var input = element( 'input', '' );
		input.type = 'checkbox';
		input.checked = !! checked;
		input.addEventListener( 'change', function () { onChange( input.checked ); } );
		var track = element( 'span', 'cc-site-settings-toggle-track' );
		track.setAttribute( 'aria-hidden', 'true' );
		wrapper.appendChild( input );
		wrapper.appendChild( track );
		return wrapper;
	}

	function toggleRow( label, checked, onChange ) {
		var row = element( 'label', 'cc-site-settings-inline-row' );
		row.appendChild( element( 'span', '', label ) );
		row.appendChild( toggleInput( checked, onChange ) );
		return row;
	}

	function normalizeHex( value ) {
		var match = String( value || '' ).trim().match( /^#([0-9a-f]{6})$/i );
		return match ? '#' + match[ 1 ].toLowerCase() : '#667085';
	}

	function colorControl( label, value, onChange ) {
		var row = element( 'div', 'cc-site-settings-color-row' );
		row.appendChild( element( 'span', 'cc-site-settings-color-label', label ) );
		var controls = element( 'div', 'cc-site-settings-color-controls' );
		var text = textInput( value, function ( next ) { onChange( next ); } );
		text.classList.add( 'cc-site-settings-color-text' );
		var picker = element( 'input', 'cc-site-settings-color-picker' );
		picker.type = 'color';
		picker.value = normalizeHex( value );
		picker.setAttribute( 'aria-label', label + ' color picker' );
		picker.addEventListener( 'input', function () {
			text.value = picker.value;
			onChange( picker.value );
		} );
		controls.appendChild( text );
		controls.appendChild( picker );
		row.appendChild( controls );
		return row;
	}

	function numberControl( label, value, min, max, onChange, suffix ) {
		var row = element( 'div', 'cc-site-settings-control-row' );
		row.appendChild( element( 'span', 'cc-site-settings-color-label', label ) );
		var wrap = element( 'div', 'cc-site-settings-number-wrap' );
		var input = textInput( value, function ( next ) {
			var number = Number( next );
			if ( Number.isFinite( number ) ) onChange( number );
		}, { type: 'number', min: min, max: max } );
		input.classList.add( 'cc-site-settings-number' );
		wrap.appendChild( input );
		if ( suffix ) wrap.appendChild( element( 'span', 'cc-site-settings-suffix', suffix ) );
		row.appendChild( wrap );
		return row;
	}

	function ensureGlobalPermission( target ) {
		if ( canManageGlobal() ) return true;
		target.appendChild( notice( __( 'Your account can edit this Page, but cannot change site-wide design settings.', 'cresco-canvas' ), 'warning' ) );
		return false;
	}

	function renderGlobalGuide( target ) {
		target.appendChild( toggleRow( __( 'Show global settings guide', 'cresco-canvas' ), globalGuideEnabled, function ( enabled ) {
			globalGuideEnabled = enabled;
			if ( app ) app.classList.toggle( 'cc-site-settings-guide-enabled', enabled );
		} ) );
		target.appendChild( helpText( __( 'Temporarily highlights the editor while you review global colors and typography.', 'cresco-canvas' ) ) );
	}

	function renderRoot() {
		var fragment = document.createDocumentFragment();
		fragment.appendChild( sectionTitle( __( 'DESIGN SYSTEM', 'cresco-canvas' ) ) );
		var design = element( 'div', 'cc-site-settings-nav-group' );
		design.appendChild( navRow( __( 'Global Colors', 'cresco-canvas' ), 'art', 'colors', ! canManageGlobal() ) );
		design.appendChild( navRow( __( 'Global Fonts', 'cresco-canvas' ), 'editor-textcolor', 'fonts', ! canManageGlobal() ) );
		fragment.appendChild( design );

		fragment.appendChild( sectionTitle( __( 'THEME STYLE', 'cresco-canvas' ) ) );
		var style = element( 'div', 'cc-site-settings-nav-group' );
		style.appendChild( navRow( __( 'Typography', 'cresco-canvas' ), 'editor-textcolor', 'typography', ! canManageGlobal() ) );
		style.appendChild( navRow( __( 'Buttons', 'cresco-canvas' ), 'button', 'buttons', ! canManageGlobal() ) );
		style.appendChild( navRow( __( 'Images', 'cresco-canvas' ), 'format-image', 'images', ! canManageGlobal() ) );
		style.appendChild( navRow( __( 'Form Fields', 'cresco-canvas' ), 'feedback', 'forms', ! canManageGlobal() ) );
		style.appendChild( navRow( __( 'Page Header', 'cresco-canvas' ), 'align-wide', 'header', false ) );
		style.appendChild( navRow( __( 'Page Footer', 'cresco-canvas' ), 'align-wide', 'footer', false ) );
		fragment.appendChild( style );

		fragment.appendChild( sectionTitle( __( 'SETTINGS', 'cresco-canvas' ) ) );
		var settingsGroup = element( 'div', 'cc-site-settings-nav-group' );
		settingsGroup.appendChild( navRow( __( 'Site Identity', 'cresco-canvas' ), 'id', 'identity', ! canManageGlobal() ) );
		settingsGroup.appendChild( navRow( __( 'Background', 'cresco-canvas' ), 'admin-appearance', 'background', ! canManageGlobal() ) );
		settingsGroup.appendChild( navRow( __( 'Layout', 'cresco-canvas' ), 'layout', 'layout', false ) );
		settingsGroup.appendChild( navRow( __( 'Lightbox', 'cresco-canvas' ), 'editor-expand', 'lightbox', false ) );
		settingsGroup.appendChild( navRow( __( 'Page Transitions', 'cresco-canvas' ), 'image-rotate', 'transitions', false ) );
		settingsGroup.appendChild( navRow( __( 'Custom CSS', 'cresco-canvas' ), 'editor-code', 'customCss', ! canManageGlobal() ) );
		settingsGroup.appendChild( navRow( __( 'Additional Settings', 'cresco-canvas' ), 'admin-tools', 'additional', ! canManageGlobal() ) );
		fragment.appendChild( settingsGroup );
		return fragment;
	}

	function renderColors() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		renderGlobalGuide( wrap );
		var system = panelSection( __( 'System Colors', 'cresco-canvas' ) );
		[ [ 'Primary', 'primary' ], [ 'Text', 'text' ], [ 'Muted', 'muted' ], [ 'Background', 'background' ] ].forEach( function ( item ) {
			system.appendChild( colorControl( __( item[ 0 ], 'cresco-canvas' ), globalSettings[ item[ 1 ] ], function ( value ) {
				globalSettings[ item[ 1 ] ] = value;
				markDirty( 'global' );
			} ) );
		} );
		wrap.appendChild( system );

		var custom = panelSection( __( 'Custom Colors', 'cresco-canvas' ) );
		var customKeys = Object.keys( globalSettings.customColors || {} );
		if ( ! customKeys.length ) custom.appendChild( helpText( __( 'No custom color tokens yet.', 'cresco-canvas' ) ) );
		customKeys.forEach( function ( slug ) {
			var row = element( 'div', 'cc-site-settings-removable-row' );
			row.appendChild( colorControl( slug, globalSettings.customColors[ slug ], function ( value ) {
				globalSettings.customColors[ slug ] = value;
				markDirty( 'global' );
			} ) );
			var remove = makeButton( '×', 'cc-site-settings-icon-button' );
			remove.setAttribute( 'aria-label', __( 'Remove color', 'cresco-canvas' ) + ' ' + slug );
			remove.addEventListener( 'click', function () {
				delete globalSettings.customColors[ slug ];
				markDirty( 'global' );
				renderCurrentView();
			} );
			row.appendChild( remove );
			custom.appendChild( row );
		} );
		var add = makeButton( '+ ' + __( 'Add Color', 'cresco-canvas' ), 'cc-site-settings-secondary-button' );
		add.addEventListener( 'click', function () {
			var index = 1;
			var slug = 'custom-' + index;
			while ( globalSettings.customColors[ slug ] ) { index += 1; slug = 'custom-' + index; }
			globalSettings.customColors[ slug ] = '#667085';
			markDirty( 'global' );
			renderCurrentView();
		} );
		custom.appendChild( add );
		wrap.appendChild( custom );
		return wrap;
	}

	function renderFonts() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		renderGlobalGuide( wrap );
		var system = panelSection( __( 'System Fonts', 'cresco-canvas' ) );
		system.appendChild( field( __( 'Primary', 'cresco-canvas' ), textInput( globalSettings.fontFamily, function ( value ) {
			globalSettings.fontFamily = value;
			markDirty( 'global' );
		} ), __( 'Cresco currently uses one global font stack. Secondary, Text and Accent inherit this stack.', 'cresco-canvas' ) ) );
		[ 'Secondary', 'Text', 'Accent' ].forEach( function ( label ) {
			var row = element( 'div', 'cc-site-settings-inherit-row' );
			row.appendChild( element( 'span', '', __( label, 'cresco-canvas' ) ) );
			row.appendChild( element( 'span', 'cc-site-settings-badge', __( 'Inherits Primary', 'cresco-canvas' ) ) );
			system.appendChild( row );
		} );
		wrap.appendChild( system );
		var custom = panelSection( __( 'Custom Fonts', 'cresco-canvas' ) );
		custom.appendChild( notice( __( 'Custom font file loading is not part of the current Cresco runtime. You can enter any font stack already available on the site.', 'cresco-canvas' ), 'info' ) );
		wrap.appendChild( custom );
		return wrap;
	}

	function renderTypography() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		var body = panelSection( __( 'Body', 'cresco-canvas' ) );
		body.appendChild( colorControl( __( 'Text Color', 'cresco-canvas' ), globalSettings.text, function ( value ) { globalSettings.text = value; markDirty( 'global' ); } ) );
		body.appendChild( field( __( 'Typography', 'cresco-canvas' ), textInput( globalSettings.fontFamily, function ( value ) { globalSettings.fontFamily = value; markDirty( 'global' ); } ) ) );
		body.appendChild( field( __( 'Base Size', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.fontBase, function ( value ) { globalSettings.fluidTokens.fontBase = value; markDirty( 'global' ); } ), __( 'Accepts a safe CSS size such as 16px, 1rem or clamp(...).', 'cresco-canvas' ) ) );
		wrap.appendChild( body );

		var link = panelSection( __( 'Link', 'cresco-canvas' ) );
		link.appendChild( colorControl( __( 'Color', 'cresco-canvas' ), globalSettings.primary, function ( value ) { globalSettings.primary = value; markDirty( 'global' ); } ) );
		wrap.appendChild( link );

		[ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].forEach( function ( key ) {
			var heading = panelSection( key.toUpperCase() );
			heading.appendChild( field( __( 'Typography Size', 'cresco-canvas' ), textInput( globalSettings.fluidTokens[ key ], function ( value ) { globalSettings.fluidTokens[ key ] = value; markDirty( 'global' ); } ) ) );
			wrap.appendChild( heading );
		} );
		return wrap;
	}

	function renderButtons() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		var style = panelSection( __( 'Global Button Style', 'cresco-canvas' ) );
		style.appendChild( colorControl( __( 'Background Color', 'cresco-canvas' ), globalSettings.primary, function ( value ) { globalSettings.primary = value; markDirty( 'global' ); } ) );
		style.appendChild( field( __( 'Border Radius', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.radiusMd, function ( value ) { globalSettings.fluidTokens.radiusMd = value; markDirty( 'global' ); } ) ) );
		style.appendChild( field( __( 'Control Height', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.controlHeight, function ( value ) { globalSettings.fluidTokens.controlHeight = value; markDirty( 'global' ); } ) ) );
		style.appendChild( field( __( 'Horizontal Padding', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.buttonPadding, function ( value ) { globalSettings.fluidTokens.buttonPadding = value; markDirty( 'global' ); } ) ) );
		style.appendChild( helpText( __( 'These values are the same global tokens used by Cresco Button widgets, so existing pages update consistently after saving.', 'cresco-canvas' ) ) );
		wrap.appendChild( style );
		return wrap;
	}

	function renderImages() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		var section = panelSection( __( 'Images', 'cresco-canvas' ) );
		section.appendChild( field( __( 'Border Radius', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.radiusMd, function ( value ) { globalSettings.fluidTokens.radiusMd = value; markDirty( 'global' ); } ) ) );
		section.appendChild( notice( __( 'The global radius applies to Cresco image media. Opacity, shadow and CSS filters remain widget-level so individual image treatments stay portable with the Session.', 'cresco-canvas' ), 'info' ) );
		wrap.appendChild( section );
		return wrap;
	}

	function renderForms() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		var section = panelSection( __( 'Form Fields', 'cresco-canvas' ) );
		section.appendChild( colorControl( __( 'Text Color', 'cresco-canvas' ), globalSettings.text, function ( value ) { globalSettings.text = value; markDirty( 'global' ); } ) );
		section.appendChild( colorControl( __( 'Accent Color', 'cresco-canvas' ), globalSettings.primary, function ( value ) { globalSettings.primary = value; markDirty( 'global' ); } ) );
		section.appendChild( field( __( 'Typography', 'cresco-canvas' ), textInput( globalSettings.fontFamily, function ( value ) { globalSettings.fontFamily = value; markDirty( 'global' ); } ) ) );
		section.appendChild( field( __( 'Control Height', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.controlHeight, function ( value ) { globalSettings.fluidTokens.controlHeight = value; markDirty( 'global' ); } ) ) );
		section.appendChild( helpText( __( 'Cresco form widgets use the global typography and accent tokens. Per-field borders and shadows stay in the widget Inspector.', 'cresco-canvas' ) ) );
		wrap.appendChild( section );
		return wrap;
	}

	function pageChoice( key, label, options, help ) {
		return field( label, selectInput( pageSettings[ key ], options, function ( value ) {
			pageSettings[ key ] = value;
			if ( key === 'layout' ) enforcePageLayout();
			markDirty( 'page' );
			if ( key === 'layout' ) renderCurrentView();
		}, key ), help );
	}

	function enforcePageLayout() {
		if ( pageSettings.layout === 'full-width' || pageSettings.layout === 'canvas' ) pageSettings.contentRoot = 'viewport';
		if ( pageSettings.layout === 'canvas' ) {
			pageSettings.pageTitle = 'hide';
			pageSettings.header = 'hide';
			pageSettings.footer = 'hide';
		}
	}

	function renderHeaderFooter( which ) {
		var wrap = element( 'div', 'cc-site-settings-view' );
		var section = panelSection( which === 'header' ? __( 'Page Header', 'cresco-canvas' ) : __( 'Page Footer', 'cresco-canvas' ) );
		section.appendChild( notice( __( 'The active WordPress theme still owns the header/footer design. Cresco controls whether the theme shell is inherited, shown or hidden for this Page.', 'cresco-canvas' ), 'info' ) );
		section.appendChild( pageChoice( which, which === 'header' ? __( 'Header', 'cresco-canvas' ) : __( 'Footer', 'cresco-canvas' ), [
			{ label: __( 'Inherit theme', 'cresco-canvas' ), value: 'inherit' },
			{ label: __( 'Show', 'cresco-canvas' ), value: 'show' },
			{ label: __( 'Hide', 'cresco-canvas' ), value: 'hide' }
		] ) );
		wrap.appendChild( section );
		return wrap;
	}

	function mediaCard( label, media, kind ) {
		var section = element( 'div', 'cc-site-settings-media' );
		section.appendChild( element( 'div', 'cc-site-settings-field-label', label ) );
		var preview = element( 'div', 'cc-site-settings-media-preview' );
		if ( media && media.url ) {
			var image = element( 'img', '' );
			image.src = media.url;
			image.alt = '';
			preview.appendChild( image );
		} else {
			preview.appendChild( icon( 'format-image' ) );
		}
		section.appendChild( preview );
		var actions = element( 'div', 'cc-site-settings-media-actions' );
		var choose = makeButton( media && media.id ? __( 'Replace', 'cresco-canvas' ) : __( 'Choose Image', 'cresco-canvas' ), 'cc-site-settings-secondary-button' );
		choose.addEventListener( 'click', function () { chooseMedia( kind ); } );
		actions.appendChild( choose );
		if ( media && media.id ) {
			var remove = makeButton( __( 'Remove', 'cresco-canvas' ), 'cc-site-settings-text-button' );
			remove.addEventListener( 'click', function () {
				siteIdentity[ kind ] = { id: 0, url: '' };
				markDirty( 'identity' );
				renderCurrentView();
			} );
			actions.appendChild( remove );
		}
		section.appendChild( actions );
		return section;
	}

	function chooseMedia( kind ) {
		if ( ! wp.media ) {
			setStatus( __( 'The WordPress media picker is not available.', 'cresco-canvas' ), true );
			return;
		}
		var frame = wp.media( {
			title: kind === 'logo' ? __( 'Choose Site Logo', 'cresco-canvas' ) : __( 'Choose Site Favicon', 'cresco-canvas' ),
			button: { text: __( 'Use this image', 'cresco-canvas' ) },
			library: { type: 'image' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var selected = frame.state().get( 'selection' ).first();
			var data = selected ? selected.toJSON() : null;
			if ( ! data ) return;
			siteIdentity[ kind ] = { id: Number( data.id ) || 0, url: data.url || '' };
			markDirty( 'identity' );
			renderCurrentView();
		} );
		frame.open();
	}

	function renderIdentity() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		if ( ! siteIdentity ) {
			wrap.appendChild( notice( __( 'Site identity could not be loaded.', 'cresco-canvas' ), 'warning' ) );
			return wrap;
		}
		wrap.appendChild( notice( __( 'Site identity changes affect WordPress after saving. Reload Preview to see theme-owned output update.', 'cresco-canvas' ), 'info' ) );
		var section = panelSection();
		section.appendChild( field( __( 'Site Name', 'cresco-canvas' ), textInput( siteIdentity.name || '', function ( value ) { siteIdentity.name = value; markDirty( 'identity' ); } ) ) );
		section.appendChild( field( __( 'Site Description', 'cresco-canvas' ), textInput( siteIdentity.description || '', function ( value ) { siteIdentity.description = value; markDirty( 'identity' ); } ) ) );
		section.appendChild( mediaCard( __( 'Site Logo', 'cresco-canvas' ), siteIdentity.logo || {}, 'logo' ) );
		section.appendChild( mediaCard( __( 'Site Favicon', 'cresco-canvas' ), siteIdentity.favicon || {}, 'favicon' ) );
		wrap.appendChild( section );
		return wrap;
	}

	function renderBackground() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		var section = panelSection( __( 'Background', 'cresco-canvas' ) );
		section.appendChild( colorControl( __( 'Page Background', 'cresco-canvas' ), globalSettings.background, function ( value ) { globalSettings.background = value; markDirty( 'global' ); } ) );
		section.appendChild( helpText( __( 'This is the global Cresco canvas background token and updates the editor and frontend Cresco scope.', 'cresco-canvas' ) ) );
		wrap.appendChild( section );
		return wrap;
	}

	function renderLayout() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		var page = panelSection( __( 'Current Page', 'cresco-canvas' ) );
		page.appendChild( pageChoice( 'layout', __( 'Page Layout', 'cresco-canvas' ), [
			{ label: __( 'Theme Default', 'cresco-canvas' ), value: 'theme-default' },
			{ label: __( 'Full Width', 'cresco-canvas' ), value: 'full-width' },
			{ label: __( 'Canvas', 'cresco-canvas' ), value: 'canvas' }
		], __( 'Canvas removes the theme title, header and footer. Full Width keeps the theme shell and expands the Cresco root to the viewport.', 'cresco-canvas' ) ) );
		var titleField = pageChoice( 'pageTitle', __( 'Page Title', 'cresco-canvas' ), [ { label: __( 'Show', 'cresco-canvas' ), value: 'show' }, { label: __( 'Hide', 'cresco-canvas' ), value: 'hide' } ] );
		var rootField = pageChoice( 'contentRoot', __( 'Cresco Root', 'cresco-canvas' ), [ { label: __( 'Theme Content Width', 'cresco-canvas' ), value: 'theme' }, { label: __( 'Full Viewport', 'cresco-canvas' ), value: 'viewport' } ] );
		var titleSelect = titleField.querySelector( 'select' );
		var rootSelect = rootField.querySelector( 'select' );
		if ( titleSelect ) titleSelect.disabled = pageSettings.layout === 'canvas';
		if ( rootSelect ) rootSelect.disabled = pageSettings.layout === 'full-width' || pageSettings.layout === 'canvas';
		page.appendChild( titleField );
		page.appendChild( rootField );
		wrap.appendChild( page );

		if ( canManageGlobal() ) {
			var global = panelSection( __( 'Global Layout', 'cresco-canvas' ) );
			global.appendChild( numberControl( __( 'Container Width', 'cresco-canvas' ), globalSettings.containerMax, 960, 2560, function ( value ) { globalSettings.containerMax = value; markDirty( 'global' ); }, 'px' ) );
			global.appendChild( numberControl( __( 'Content Width', 'cresco-canvas' ), globalSettings.contentMax, 640, 2560, function ( value ) { globalSettings.contentMax = value; markDirty( 'global' ); }, 'px' ) );
			global.appendChild( field( __( 'Container Gutter', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.containerGutter, function ( value ) { globalSettings.fluidTokens.containerGutter = value; markDirty( 'global' ); } ) ) );
			global.appendChild( field( __( 'Grid Gap', 'cresco-canvas' ), textInput( globalSettings.fluidTokens.gridGap, function ( value ) { globalSettings.fluidTokens.gridGap = value; markDirty( 'global' ); } ) ) );
			wrap.appendChild( global );

			var breakpoints = panelSection( __( 'Breakpoints', 'cresco-canvas' ) );
			[ [ 'Mobile', 'mobile', 0, 767 ], [ 'Tablet', 'tablet', 1, 1024 ], [ 'Laptop', 'laptop', 2, 1439 ], [ 'Desktop', 'desktop', 3, 1919 ], [ 'Wide', 'wide', 4, 3840 ] ].forEach( function ( item ) {
				breakpoints.appendChild( numberControl( __( item[ 0 ], 'cresco-canvas' ), globalSettings.breakpoints[ item[ 1 ] ], 0, item[ 3 ], function ( value ) { globalSettings.breakpoints[ item[ 1 ] ] = value; markDirty( 'global' ); }, 'px' ) );
			} );
			wrap.appendChild( breakpoints );
		}
		return wrap;
	}

	function renderUnavailable( kind ) {
		var wrap = element( 'div', 'cc-site-settings-view' );
		var section = panelSection();
		var message = kind === 'lightbox'
			? __( 'A site-wide lightbox runtime is not part of Cresco Session v1 yet. Image widgets can still use links, while visual image controls remain scoped to each widget.', 'cresco-canvas' )
			: __( 'Site-wide preloader and page-transition runtime is not part of the current release candidate. No placeholder setting is saved until the frontend runtime exists.', 'cresco-canvas' );
		section.appendChild( notice( message, 'info' ) );
		section.appendChild( helpText( __( 'The panel is included in the new Site Settings information architecture so this capability can be added without redesigning navigation later.', 'cresco-canvas' ) ) );
		wrap.appendChild( section );
		return wrap;
	}

	function renderCustomCss() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		var section = panelSection( __( 'Custom CSS', 'cresco-canvas' ) );
		section.appendChild( notice( __( 'Global Custom CSS is automatically scoped to the Cresco page root. @import, @media, external url(), script-like expressions and selectors that escape the page root are rejected by the server.', 'cresco-canvas' ), 'info' ) );
		section.appendChild( field( __( 'CSS', 'cresco-canvas' ), textInput( globalSettings.customCss || '', function ( value ) { globalSettings.customCss = value; markDirty( 'global' ); }, { multiline: true, rows: 14, code: true, placeholder: 'p { margin: 0; }\n.hero { border-radius: 24px; }' } ), __( 'Use ordinary selectors; Cresco prefixes them with the current page scope when rendering.', 'cresco-canvas' ) ) );
		wrap.appendChild( section );
		return wrap;
	}

	function renderAdditional() {
		var wrap = element( 'div', 'cc-site-settings-view' );
		if ( ! ensureGlobalPermission( wrap ) ) return wrap;
		var section = panelSection( __( 'Data & Maintenance', 'cresco-canvas' ) );
		section.appendChild( toggleRow( __( 'Remove plugin data on uninstall', 'cresco-canvas' ), !! globalSettings.removeDataOnUninstall, function ( enabled ) { globalSettings.removeDataOnUninstall = enabled; markDirty( 'global' ); } ) );
		section.appendChild( helpText( __( 'User-authored WordPress page content is never deleted. This controls Cresco-owned settings and metadata only.', 'cresco-canvas' ) ) );
		wrap.appendChild( section );
		return wrap;
	}

	function renderViewBody() {
		switch ( currentView ) {
			case 'colors': return renderColors();
			case 'fonts': return renderFonts();
			case 'typography': return renderTypography();
			case 'buttons': return renderButtons();
			case 'images': return renderImages();
			case 'forms': return renderForms();
			case 'header': return renderHeaderFooter( 'header' );
			case 'footer': return renderHeaderFooter( 'footer' );
			case 'identity': return renderIdentity();
			case 'background': return renderBackground();
			case 'layout': return renderLayout();
			case 'lightbox': return renderUnavailable( 'lightbox' );
			case 'transitions': return renderUnavailable( 'transitions' );
			case 'customCss': return renderCustomCss();
			case 'additional': return renderAdditional();
			default: return renderRoot();
		}
	}

	function renderHeader() {
		var header = element( 'header', 'cc-page-settings-dialog__header' );
		var left = element( 'div', 'cc-site-settings-header-slot' );
		if ( currentView !== 'root' ) {
			var back = makeButton( '', 'cc-site-settings-header-button' );
			back.setAttribute( 'aria-label', __( 'Back to Site Settings', 'cresco-canvas' ) );
			back.appendChild( icon( 'arrow-left-alt2' ) );
			back.addEventListener( 'click', function () { showView( 'root' ); } );
			left.appendChild( back );
		}
		header.appendChild( left );
		var title = element( 'h2', 'cc-site-settings-header-title', viewMeta[ currentView ].title );
		title.id = 'cc-page-settings-title';
		header.appendChild( title );
		var right = element( 'div', 'cc-site-settings-header-slot is-right' );
		var close = makeButton( '', 'cc-site-settings-header-button cc-page-settings-close' );
		close.setAttribute( 'aria-label', __( 'Close Page Settings', 'cresco-canvas' ) );
		close.title = __( 'Close Page Settings', 'cresco-canvas' );
		close.appendChild( icon( 'no-alt' ) );
		close.addEventListener( 'click', closeDialog );
		right.appendChild( close );
		header.appendChild( right );
		return header;
	}

	function currentScope() {
		return viewMeta[ currentView ] ? viewMeta[ currentView ].scope : '';
	}

	function renderFooter() {
		var node = element( 'footer', 'cc-page-settings-dialog__footer' );
		var status = element( 'div', 'cc-page-settings-status', statusMessage );
		status.setAttribute( 'aria-live', 'polite' );
		status.classList.toggle( 'is-error', statusIsError );
		node.appendChild( status );
		var actions = element( 'div', 'cc-page-settings-actions' );
		var scope = currentScope();
		if ( currentView === 'root' ) {
			var help = element( 'span', 'cc-site-settings-need-help', __( 'Need Help?', 'cresco-canvas' ) );
			help.appendChild( icon( 'editor-help' ) );
			actions.appendChild( help );
		} else if ( scope === 'page' || scope === 'mixed' ) {
			var savePage = makeButton( __( 'Save Page Settings', 'cresco-canvas' ), 'cc-site-settings-primary-button cc-page-settings-save' );
			savePage.disabled = saving || ! dirty.page;
			savePage.addEventListener( 'click', savePageSettings );
			actions.appendChild( savePage );
			if ( scope === 'mixed' && canManageGlobal() ) {
				var saveGlobalFromLayout = makeButton( __( 'Save Global', 'cresco-canvas' ), 'cc-site-settings-secondary-button' );
				saveGlobalFromLayout.disabled = saving || ! dirty.global;
				saveGlobalFromLayout.addEventListener( 'click', saveGlobalSettings );
				actions.appendChild( saveGlobalFromLayout );
			}
		} else if ( scope === 'global' ) {
			var saveGlobal = makeButton( __( 'Save Global Settings', 'cresco-canvas' ), 'cc-site-settings-primary-button' );
			saveGlobal.disabled = saving || ! dirty.global;
			saveGlobal.addEventListener( 'click', saveGlobalSettings );
			actions.appendChild( saveGlobal );
		} else if ( scope === 'identity' ) {
			var saveIdentityButton = makeButton( __( 'Save Site Identity', 'cresco-canvas' ), 'cc-site-settings-primary-button' );
			saveIdentityButton.disabled = saving || ! dirty.identity;
			saveIdentityButton.addEventListener( 'click', saveIdentity );
			actions.appendChild( saveIdentityButton );
		}
		node.appendChild( actions );
		return node;
	}

	function renderCurrentView() {
		if ( ! dialog ) return;
		dialog.innerHTML = '';
		dialog.appendChild( renderHeader() );
		content = element( 'div', 'cc-page-settings-form' );
		content.appendChild( renderViewBody() );
		dialog.appendChild( content );
		footer = renderFooter();
		dialog.appendChild( footer );
	}

	function showView( view ) {
		currentView = viewMeta[ view ] ? view : 'root';
		setStatus( '', false );
		renderCurrentView();
		if ( content ) content.scrollTop = 0;
	}

	function savePageSettings() {
		if ( saving || ! pageSettings ) return;
		enforcePageLayout();
		saving = true;
		setStatus( __( 'Saving…', 'cresco-canvas' ), false );
		renderCurrentView();
		apiFetch( { path: settings.pageSettingsPath, method: 'POST', data: { settings: pageSettings } } ).then( function ( result ) {
			pageSettings = Object.assign( defaultPageSettings(), clone( result.settings || result ) );
			dirty.page = false;
			setStatus( __( 'Page Settings saved. Preview will use the new page shell.', 'cresco-canvas' ), false );
			window.dispatchEvent( new CustomEvent( 'cresco:page-settings-saved', { detail: result } ) );
		} ).catch( function ( error ) {
			setStatus( error && error.message ? error.message : __( 'Page Settings could not be saved.', 'cresco-canvas' ), true );
		} ).finally( function () {
			saving = false;
			renderCurrentView();
		} );
	}

	function saveGlobalSettings() {
		if ( saving || ! globalSettings || ! canManageGlobal() ) return;
		saving = true;
		setStatus( __( 'Saving global settings…', 'cresco-canvas' ), false );
		renderCurrentView();
		apiFetch( { path: settings.settingsPath, method: 'POST', data: globalSettings } ).then( function ( result ) {
			globalSettings = mergeGlobalDefaults( result );
			dirty.global = false;
			setStatus( __( 'Global settings saved.', 'cresco-canvas' ), false );
			window.dispatchEvent( new CustomEvent( 'cresco:global-settings-saved', { detail: result } ) );
		} ).catch( function ( error ) {
			setStatus( error && error.message ? error.message : __( 'Global settings could not be saved.', 'cresco-canvas' ), true );
		} ).finally( function () {
			saving = false;
			renderCurrentView();
		} );
	}

	function saveIdentity() {
		if ( saving || ! siteIdentity || ! canManageGlobal() ) return;
		saving = true;
		setStatus( __( 'Saving site identity…', 'cresco-canvas' ), false );
		renderCurrentView();
		apiFetch( {
			path: '/cresco-canvas/v1/site-identity',
			method: 'POST',
			data: {
				name: siteIdentity.name || '',
				description: siteIdentity.description || '',
				logoId: siteIdentity.logo ? Number( siteIdentity.logo.id ) || 0 : 0,
				faviconId: siteIdentity.favicon ? Number( siteIdentity.favicon.id ) || 0 : 0
			}
		} ).then( function ( result ) {
			siteIdentity = clone( result );
			dirty.identity = false;
			setStatus( __( 'Site identity saved.', 'cresco-canvas' ), false );
		} ).catch( function ( error ) {
			setStatus( error && error.message ? error.message : __( 'Site identity could not be saved.', 'cresco-canvas' ), true );
		} ).finally( function () {
			saving = false;
			renderCurrentView();
		} );
	}

	function loadSettings() {
		if ( loading ) return Promise.resolve();
		loading = true;
		setStatus( __( 'Loading Site Settings…', 'cresco-canvas' ), false );
		var requests = [
			apiFetch( { path: settings.pageSettingsPath } ).then( function ( result ) {
				pageSettings = Object.assign( defaultPageSettings(), clone( result.settings || result ) );
			} )
		];
		if ( canManageGlobal() ) {
			requests.push( apiFetch( { path: settings.settingsPath } ).then( function ( result ) {
				globalSettings = mergeGlobalDefaults( result );
			} ) );
			requests.push( apiFetch( { path: '/cresco-canvas/v1/site-identity' } ).then( function ( result ) {
				siteIdentity = clone( result );
			} ).catch( function () { siteIdentity = null; } ) );
		} else {
			globalSettings = mergeGlobalDefaults( {} );
		}
		return Promise.all( requests ).then( function () {
			dirty = { page: false, global: false, identity: false };
			setStatus( '', false );
		} ).catch( function ( error ) {
			setStatus( error && error.message ? error.message : __( 'Site Settings could not be loaded.', 'cresco-canvas' ), true );
		} ).finally( function () {
			loading = false;
			renderCurrentView();
		} );
	}

	function closeDialog() {
		if ( ! overlay || overlay.hidden ) return;
		overlay.hidden = true;
		overlay.classList.remove( 'is-open' );
		document.body.classList.remove( 'cc-page-settings-open' );
		if ( app ) app.classList.remove( 'cc-site-settings-guide-enabled' );
		globalGuideEnabled = false;
		currentView = 'root';
		if ( lastFocus && document.body.contains( lastFocus ) ) lastFocus.focus();
	}

	function buildDialog() {
		if ( overlay && document.body.contains( overlay ) ) return;
		overlay = element( 'div', 'cc-page-settings-overlay' );
		overlay.hidden = true;
		overlay.addEventListener( 'mousedown', function ( event ) { if ( event.target === overlay ) closeDialog(); } );
		dialog = element( 'section', 'cc-page-settings-dialog' );
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-label', __( 'Page Settings', 'cresco-canvas' ) );
		overlay.appendChild( dialog );
		app.appendChild( overlay );
		renderCurrentView();
	}

	function openDialog() {
		buildDialog();
		lastFocus = document.activeElement;
		overlay.hidden = false;
		overlay.classList.add( 'is-open' );
		document.body.classList.add( 'cc-page-settings-open' );
		currentView = 'root';
		renderCurrentView();
		loadSettings();
	}

	function ensureButton() {
		if ( ! app ) return;
		var actions = app.querySelector( '.cc-standalone-header-actions' );
		if ( ! actions ) return;
		var existing = actions.querySelector( '.cc-page-settings-trigger' );
		if ( existing ) { trigger = existing; return; }
		trigger = makeButton( '', 'components-button is-secondary cc-page-settings-trigger' );
		trigger.setAttribute( 'aria-label', __( 'Page Settings', 'cresco-canvas' ) );
		trigger.title = __( 'Site Settings', 'cresco-canvas' );
		trigger.appendChild( icon( 'admin-settings' ) );
		trigger.appendChild( element( 'span', 'cc-page-settings-trigger__label', __( 'Site', 'cresco-canvas' ) ) );
		trigger.addEventListener( 'click', openDialog );
		var preview = Array.prototype.find.call( actions.querySelectorAll( 'a,button' ), function ( node ) { return String( node.textContent || '' ).trim() === __( 'Preview', 'cresco-canvas' ); } );
		actions.insertBefore( trigger, preview || null );
	}

	function handleKeydown( event ) {
		if ( event.key === 'Escape' && overlay && ! overlay.hidden ) {
			event.preventDefault();
			if ( currentView !== 'root' ) showView( 'root' );
			else closeDialog();
		}
	}

	function boot() {
		app = document.querySelector( '.cc-standalone-app' );
		if ( ! app ) { window.setTimeout( boot, 80 ); return; }
		ensureButton();
		document.addEventListener( 'keydown', handleKeydown );
		if ( window.MutationObserver ) new window.MutationObserver( ensureButton ).observe( app, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )( window.wp, window, document );