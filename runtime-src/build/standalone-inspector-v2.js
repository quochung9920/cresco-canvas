( function ( window, document ) {
	'use strict';

	var STORAGE_PREFIX = 'cresco-inspector-v2:';
	var DEVICE_PARENT = { Desktop: 'Widescreen', Laptop: 'Desktop', Tablet: 'Laptop', Mobile: 'Tablet' };
	var STYLE_LABEL_TO_KEY = {
		'width': 'width',
		'maximum width': 'maxWidth',
		'minimum height': 'minHeight',
		'gap': 'gap',
		'padding top': 'paddingTop',
		'padding right': 'paddingRight',
		'padding bottom': 'paddingBottom',
		'padding left': 'paddingLeft',
		'margin top': 'marginTop',
		'margin right': 'marginRight',
		'margin bottom': 'marginBottom',
		'margin left': 'marginLeft',
		'text color': 'color',
		'background': 'background',
		'font size': 'fontSize',
		'font weight': 'fontWeight',
		'line height': 'lineHeight',
		'letter spacing': 'letterSpacing',
		'text align': 'textAlign',
		'border radius': 'borderRadius',
		'box shadow': 'boxShadow',
		'opacity': 'opacity',
		'position': 'position',
		'top': 'top',
		'right': 'right',
		'bottom': 'bottom',
		'left': 'left',
		'z-index': 'zIndex',
		'overflow': 'overflow'
	};
	var activeTab = 'primary';
	var lastWidgetId = '';
	var scheduled = false;
	var destroyed = false;
	var mutationObserver = null;
	var observerRoot = null;
	var bootTimer = null;

	function text( node ) {
		return node ? String( node.textContent || '' ).replace( /\s+/g, ' ' ).trim() : '';
	}

	function normalize( value ) {
		return String( value || '' ).toLowerCase().replace( /\s+/g, ' ' ).trim();
	}

	function textWithout( node, excludedClass ) {
		if ( ! node ) return '';
		var value = '';
		Array.prototype.forEach.call( node.childNodes || [], function ( child ) {
			if ( child.nodeType === 3 ) value += child.nodeValue || '';
			else if ( child.nodeType === 1 && ! child.classList.contains( excludedClass ) ) value += child.textContent || '';
		} );
		return String( value ).replace( /\s+/g, ' ' ).trim();
	}

	function selectedWidgetLabel( inspector ) {
		var strong = inspector.querySelector( '.cc-inspector-header strong' );
		var value = text( strong ).replace( /^Edit\s+/i, '' );
		return value || 'Widget';
	}

	function selectedWidgetId( inspector ) {
		var code = inspector.querySelector( '.cc-inspector-header code' );
		return text( code ) || 'widget';
	}

	function selectedWidgetType( inspector ) {
		var label = normalize( selectedWidgetLabel( inspector ) );
		var known = [ 'container', 'columns', 'heading', 'text', 'button', 'image', 'list', 'divider', 'spacer' ];
		if ( known.indexOf( label ) !== -1 ) return label;
		return label.replace( /[^a-z0-9_-]+/g, '-' ) || 'widget';
	}

	function widgetDefinition( type ) {
		var settings = window.crescoCanvasStandaloneSettings || {};
		var catalog = settings.widgetCatalog && typeof settings.widgetCatalog === 'object' ? settings.widgetCatalog : {};
		return catalog[ type ] && typeof catalog[ type ] === 'object' ? catalog[ type ] : null;
	}

	function supportsStyle( type, key ) {
		var definition = widgetDefinition( type );
		if ( ! definition || ! Array.isArray( definition.style ) ) return true;
		return definition.style.indexOf( key ) !== -1;
	}

	function primaryTabLabel( type ) {
		return type === 'container' || type === 'columns' ? 'Layout' : 'Content';
	}

	function primaryTabIcon( type ) {
		return type === 'container' || type === 'columns' ? 'layout' : 'edit';
	}

	function storageKey( inspector, sectionKeyValue ) {
		var settings = window.crescoCanvasStandaloneSettings || {};
		return STORAGE_PREFIX + String( settings.postId || 'page' ) + ':' + selectedWidgetId( inspector ) + ':' + sectionKeyValue;
	}

	function getStoredOpen( inspector, sectionKeyValue, fallback ) {
		try {
			var value = window.sessionStorage.getItem( storageKey( inspector, sectionKeyValue ) );
			if ( value === '1' ) return true;
			if ( value === '0' ) return false;
		} catch ( error ) {}
		return fallback;
	}

	function setStoredOpen( inspector, sectionKeyValue, value ) {
		try { window.sessionStorage.setItem( storageKey( inspector, sectionKeyValue ), value ? '1' : '0' ); } catch ( error ) {}
	}

	function sectionHeading( section ) {
		return section.querySelector( ':scope > h3, :scope > .cc-inspector-section-heading h3' );
	}

	function headingText( heading ) {
		return textWithout( heading, 'cc-inspector-v2-section-toggle' );
	}

	function sectionKey( section ) {
		if ( section.dataset.ccInspectorSectionKey ) return section.dataset.ccInspectorSectionKey;
		var key = normalize( headingText( sectionHeading( section ) ) ).replace( /[^a-z0-9]+/g, '-' ).replace( /^-|-$/g, '' ) || 'section';
		section.dataset.ccInspectorSectionKey = key;
		return key;
	}

	function categoryForSection( section ) {
		var heading = normalize( headingText( sectionHeading( section ) ) );
		if ( heading === 'appearance' || heading.indexOf( 'style' ) !== -1 || heading.indexOf( 'typography' ) !== -1 ) return 'style';
		if ( heading === 'spacing' || heading === 'advanced' || heading.indexOf( 'custom css' ) !== -1 ) return 'advanced';
		return 'primary';
	}

	function currentDevice( inspector ) {
		var active = inspector.querySelector( '.cc-inspector-device-switcher button.is-active' );
		return text( active ) || 'Widescreen';
	}

	function tabMarkup( button, label, icon ) {
		var iconNode = button.querySelector( '.dashicons' );
		var labelNode = button.querySelector( '.cc-inspector-v2-tab-label' );
		var iconClass = 'dashicons dashicons-' + icon;
		if ( ! iconNode || ! labelNode ) {
			button.textContent = '';
			iconNode = document.createElement( 'span' );
			iconNode.setAttribute( 'aria-hidden', 'true' );
			labelNode = document.createElement( 'span' );
			labelNode.className = 'cc-inspector-v2-tab-label';
			button.appendChild( iconNode );
			button.appendChild( labelNode );
		}
		if ( iconNode.className !== iconClass ) iconNode.className = iconClass;
		if ( labelNode.textContent !== label ) labelNode.textContent = label;
	}

	function createTab( name ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cc-inspector-v2-tab';
		button.dataset.tab = name;
		button.setAttribute( 'role', 'tab' );
		button.addEventListener( 'click', function () {
			activeTab = name;
			applyTabVisibility( button.closest( '.cc-inspector' ) );
		} );
		return button;
	}

	function ensureTabs( inspector, type ) {
		var tabs = inspector.querySelector( ':scope > .cc-inspector-v2-tabs' );
		if ( ! tabs ) {
			tabs = document.createElement( 'div' );
			tabs.className = 'cc-inspector-v2-tabs';
			tabs.setAttribute( 'role', 'tablist' );
			tabs.setAttribute( 'aria-label', 'Widget settings' );
			tabs.appendChild( createTab( 'primary' ) );
			tabs.appendChild( createTab( 'style' ) );
			tabs.appendChild( createTab( 'advanced' ) );
			var header = inspector.querySelector( ':scope > .cc-inspector-header' );
			if ( header && header.nextSibling ) inspector.insertBefore( tabs, header.nextSibling );
			else inspector.appendChild( tabs );
		}
		var primary = tabs.querySelector( '[data-tab="primary"]' );
		var style = tabs.querySelector( '[data-tab="style"]' );
		var advanced = tabs.querySelector( '[data-tab="advanced"]' );
		tabMarkup( primary, primaryTabLabel( type ), primaryTabIcon( type ) );
		tabMarkup( style, 'Style', 'art' );
		tabMarkup( advanced, 'Advanced', 'admin-generic' );
	}

	function renameHeader( inspector ) {
		var strong = inspector.querySelector( '.cc-inspector-header strong' );
		if ( ! strong ) return;
		var current = text( strong ).replace( /^Edit\s+/i, '' );
		var desired = current ? 'Edit ' + current : '';
		if ( desired && strong.textContent !== desired ) strong.textContent = desired;
	}

	function ensureSectionToggle( inspector, section, defaultOpen ) {
		var heading = sectionHeading( section );
		if ( ! heading ) return;
		var key = sectionKey( section );
		var headingLabel = headingText( heading );
		var open = getStoredOpen( inspector, key, defaultOpen );
		if ( section.classList.contains( 'is-collapsed' ) === open ) section.classList.toggle( 'is-collapsed', ! open );
		var openText = open ? 'true' : 'false';
		if ( section.dataset.sectionOpen !== openText ) section.dataset.sectionOpen = openText;
		var headingWrap = heading.parentElement && heading.parentElement.classList.contains( 'cc-inspector-section-heading' ) ? heading.parentElement : heading;
		var toggle = headingWrap.querySelector( ':scope > .cc-inspector-v2-section-toggle' );
		if ( ! toggle ) {
			toggle = document.createElement( 'button' );
			toggle.type = 'button';
			toggle.className = 'cc-inspector-v2-section-toggle';
			toggle.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				var nextOpen = section.classList.contains( 'is-collapsed' );
				section.classList.toggle( 'is-collapsed', ! nextOpen );
				section.dataset.sectionOpen = nextOpen ? 'true' : 'false';
				toggle.textContent = nextOpen ? '-' : '+';
				toggle.setAttribute( 'aria-expanded', nextOpen ? 'true' : 'false' );
				toggle.setAttribute( 'aria-label', ( nextOpen ? 'Collapse ' : 'Expand ' ) + headingLabel );
				setStoredOpen( inspector, key, nextOpen );
			} );
			headingWrap.appendChild( toggle );
		}
		var toggleText = open ? '-' : '+';
		if ( toggle.textContent !== toggleText ) toggle.textContent = toggleText;
		var ariaLabel = ( open ? 'Collapse ' : 'Expand ' ) + headingLabel;
		if ( toggle.getAttribute( 'aria-label' ) !== ariaLabel ) toggle.setAttribute( 'aria-label', ariaLabel );
		if ( toggle.getAttribute( 'aria-expanded' ) !== openText ) toggle.setAttribute( 'aria-expanded', openText );
	}

	function labelText( label ) {
		return textWithout( label, 'cc-inspector-v2-responsive-badge' );
	}

	function baseControlByLabel( section, labelTextValue ) {
		var labels = section.querySelectorAll( 'label, .components-base-control__label' );
		for ( var index = 0; index < labels.length; index += 1 ) {
			if ( normalize( labelText( labels[ index ] ) ) === normalize( labelTextValue ) ) return labels[ index ].closest( '.components-base-control' ) || labels[ index ].parentElement;
		}
		return null;
	}

	function addSubTitleBefore( control, title ) {
		if ( ! control || ! control.parentElement ) return;
		var parent = control.parentElement;
		var existing = Array.prototype.find.call( parent.querySelectorAll( ':scope > .cc-inspector-v2-subtitle' ), function ( item ) { return item.dataset.title === title; } );
		if ( existing ) {
			if ( existing.nextElementSibling !== control ) parent.insertBefore( existing, control );
			return;
		}
		var node = document.createElement( 'div' );
		node.className = 'cc-inspector-v2-subtitle';
		node.dataset.title = title;
		node.textContent = title;
		parent.insertBefore( node, control );
	}

	function enhanceAppearanceSection( section ) {
		addSubTitleBefore( baseControlByLabel( section, 'Text color' ), 'Colors & Background' );
		addSubTitleBefore( baseControlByLabel( section, 'Font size' ), 'Typography' );
		addSubTitleBefore( baseControlByLabel( section, 'Border radius' ), 'Border & Shadow' );
	}

	function enhanceSpacingSection( section ) {
		addSubTitleBefore( baseControlByLabel( section, 'Padding top' ), 'Padding' );
		addSubTitleBefore( baseControlByLabel( section, 'Margin top' ), 'Margin' );
	}

	function enhanceAdvancedSection( section ) {
		addSubTitleBefore( baseControlByLabel( section, 'Opacity' ), 'Visibility & Position' );
	}

	function controlInput( control ) {
		return control ? control.querySelector( 'input, textarea, select' ) : null;
	}

	function controlHasValue( control ) {
		var input = controlInput( control );
		if ( ! input ) return false;
		if ( input.type === 'checkbox' || input.type === 'radio' ) return input.checked;
		return String( input.value || '' ).trim() !== '';
	}

	function setNativeValue( input, value ) {
		if ( ! input ) return;
		var prototype = input instanceof window.HTMLTextAreaElement ? window.HTMLTextAreaElement.prototype : input instanceof window.HTMLSelectElement ? window.HTMLSelectElement.prototype : window.HTMLInputElement.prototype;
		var descriptor = Object.getOwnPropertyDescriptor( prototype, 'value' );
		if ( descriptor && descriptor.set ) descriptor.set.call( input, value );
		else input.value = value;
		input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
	}

	function ensureResetButton( control, label, device, inherited ) {
		if ( ! control ) return;
		var existing = control.querySelector( ':scope > .cc-inspector-v2-reset-override' );
		if ( device === 'Widescreen' || inherited ) {
			if ( existing ) existing.remove();
			return;
		}
		if ( existing ) return;
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cc-inspector-v2-reset-override';
		button.textContent = 'Reset';
		button.setAttribute( 'aria-label', 'Reset ' + label + ' to inherited ' + ( DEVICE_PARENT[ device ] || 'base' ) + ' value' );
		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			setNativeValue( controlInput( control ), '' );
			scheduleEnhance();
		} );
		control.appendChild( button );
	}

	function addResponsiveBadges( inspector ) {
		var device = currentDevice( inspector );
		inspector.querySelectorAll( ':scope > .cc-inspector-section' ).forEach( function ( section ) {
			var key = sectionKey( section );
			if ( key === 'content' || key === 'container' ) return;
			section.querySelectorAll( '.components-base-control__label' ).forEach( function ( label ) {
				var control = label.closest( '.components-base-control' ) || label.parentElement;
				var inherited = device !== 'Widescreen' && ! controlHasValue( control );
				var badge = label.querySelector( ':scope > .cc-inspector-v2-responsive-badge' );
				if ( ! badge ) {
					badge = document.createElement( 'span' );
					badge.className = 'cc-inspector-v2-responsive-badge';
					label.appendChild( badge );
				}
				badge.classList.toggle( 'is-inherited', inherited );
				badge.classList.toggle( 'is-override', device !== 'Widescreen' && ! inherited );
				var badgeText = device === 'Widescreen' ? 'Base' : inherited ? 'Inherited · ' + ( DEVICE_PARENT[ device ] || 'Base' ) : 'Override · ' + device;
				if ( badge.textContent !== badgeText ) badge.textContent = badgeText;
				var title = device === 'Widescreen' ? 'Base value' : inherited ? 'Inherited from ' + ( DEVICE_PARENT[ device ] || 'Base' ) : 'Overrides ' + ( DEVICE_PARENT[ device ] || 'Base' );
				if ( badge.title !== title ) badge.title = title;
				ensureResetButton( control, labelText( label ), device, inherited );
			} );
		} );
	}

	function applyCapabilities( inspector, type ) {
		inspector.querySelectorAll( ':scope > .cc-inspector-section' ).forEach( function ( section ) {
			var category = categoryForSection( section );
			if ( category !== 'style' && category !== 'advanced' ) return;
			section.querySelectorAll( '.components-base-control' ).forEach( function ( control ) {
				var label = control.querySelector( '.components-base-control__label, label' );
				var styleKey = label ? STYLE_LABEL_TO_KEY[ normalize( labelText( label ) ) ] : '';
				if ( ! styleKey ) return;
				var supported = supportsStyle( type, styleKey );
				if ( control.hidden === supported ) control.hidden = ! supported;
				control.dataset.crescoCapability = styleKey;
				control.dataset.crescoCapabilitySupported = supported ? 'true' : 'false';
			} );
		} );
	}

	function setHeadingLabel( heading, value ) {
		if ( ! heading ) return;
		var current = headingText( heading );
		if ( current === value ) return;
		var textNode = Array.prototype.find.call( heading.childNodes || [], function ( child ) { return child.nodeType === 3; } );
		if ( textNode ) textNode.nodeValue = value;
		else heading.insertBefore( document.createTextNode( value ), heading.firstChild || null );
	}

	function enhanceContainerRules( inspector, type ) {
		if ( type !== 'container' ) return;
		var sections = inspector.querySelectorAll( ':scope > .cc-inspector-section' );
		var contentSection = null;
		var dimensionsSection = null;
		sections.forEach( function ( section ) {
			var key = sectionKey( section );
			if ( key === 'content' || key === 'container' ) contentSection = section;
			if ( key === 'size-layout' || key === 'dimensions' ) dimensionsSection = section;
		} );
		if ( contentSection ) setHeadingLabel( sectionHeading( contentSection ), 'Container' );
		if ( dimensionsSection ) setHeadingLabel( sectionHeading( dimensionsSection ), 'Dimensions' );
		var widthControl = contentSection && baseControlByLabel( contentSection, 'Content width' );
		var select = widthControl && widthControl.querySelector( 'select' );
		var maximum = dimensionsSection && baseControlByLabel( dimensionsSection, 'Maximum width' );
		var isFull = ! select || select.value === 'full';
		if ( maximum && maximum.hidden !== isFull ) maximum.hidden = isFull;
		if ( widthControl ) {
			var help = widthControl.querySelector( ':scope > .cc-inspector-v2-help' );
			if ( ! help ) {
				help = document.createElement( 'p' );
				help.className = 'cc-inspector-v2-help';
				widthControl.appendChild( help );
			}
			var helpText = isFull ? 'Full Width uses 100% of the parent container.' : 'Boxed is constrained by the Global container maximum width.';
			if ( help.textContent !== helpText ) help.textContent = helpText;
		}
		if ( select && ! select.dataset.ccInspectorV2Bound ) {
			select.dataset.ccInspectorV2Bound = '1';
			select.addEventListener( 'change', scheduleEnhance );
		}
	}

	function classifySections( inspector ) {
		var seen = { primary: false, style: false, advanced: false };
		var sections = inspector.querySelectorAll( ':scope > .cc-inspector-section' );
		sections.forEach( function ( section ) {
			var category = categoryForSection( section );
			if ( section.dataset.inspectorTab !== category ) section.dataset.inspectorTab = category;
			var heading = normalize( headingText( sectionHeading( section ) ) );
			if ( heading === 'appearance' ) enhanceAppearanceSection( section );
			if ( heading === 'spacing' ) enhanceSpacingSection( section );
			if ( heading === 'advanced' ) enhanceAdvancedSection( section );
			ensureSectionToggle( inspector, section, ! seen[ category ] );
			seen[ category ] = true;
		} );
	}

	function applyTabVisibility( inspector ) {
		if ( ! inspector ) return;
		if ( inspector.dataset.activeInspectorTab !== activeTab ) inspector.dataset.activeInspectorTab = activeTab;
		inspector.querySelectorAll( ':scope > .cc-inspector-v2-tabs .cc-inspector-v2-tab' ).forEach( function ( button ) {
			var selected = button.dataset.tab === activeTab;
			if ( button.classList.contains( 'is-active' ) !== selected ) button.classList.toggle( 'is-active', selected );
			var selectedText = selected ? 'true' : 'false';
			if ( button.getAttribute( 'aria-selected' ) !== selectedText ) button.setAttribute( 'aria-selected', selectedText );
			button.tabIndex = selected ? 0 : -1;
		} );
		inspector.querySelectorAll( ':scope > .cc-inspector-section' ).forEach( function ( section ) {
			var hide = section.dataset.inspectorTab !== activeTab;
			if ( section.hidden !== hide ) section.hidden = hide;
		} );
		var switcher = inspector.querySelector( ':scope > .cc-inspector-device-switcher' );
		if ( switcher && switcher.hidden ) switcher.hidden = false;
	}

	function enhanceInspector( inspector ) {
		if ( ! inspector ) return;
		var widgetId = selectedWidgetId( inspector );
		if ( lastWidgetId !== widgetId ) {
			lastWidgetId = widgetId;
			activeTab = 'primary';
		}
		var type = selectedWidgetType( inspector );
		if ( ! inspector.classList.contains( 'cc-inspector-v2' ) ) inspector.classList.add( 'cc-inspector-v2' );
		if ( inspector.dataset.widgetType !== type ) inspector.dataset.widgetType = type;
		renameHeader( inspector );
		ensureTabs( inspector, type );
		classifySections( inspector );
		applyCapabilities( inspector, type );
		enhanceContainerRules( inspector, type );
		addResponsiveBadges( inspector );
		applyTabVisibility( inspector );
	}

	function scheduleEnhance() {
		if ( scheduled || destroyed ) return;
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			if ( destroyed ) return;
			var inspector = document.querySelector( '.cc-inspector' );
			if ( inspector ) enhanceInspector( inspector );
		} );
	}

	function mutationNeedsEnhance( records ) {
		return Array.prototype.some.call( records || [], function ( record ) {
			var target = record && record.target ? record.target : null;
			if ( ! target || ! target.closest ) return true;
			return !! target.closest( '.cc-inspector, .cc-standalone-left-content' );
		} );
	}

	function handleMutation( records ) {
		if ( mutationNeedsEnhance( records ) ) scheduleEnhance();
	}

	function observeInspectorHost() {
		if ( destroyed ) return false;
		var nextRoot = document.querySelector( '.cc-standalone-left-content' );
		if ( ! nextRoot ) return false;
		if ( observerRoot === nextRoot && mutationObserver ) return true;
		if ( mutationObserver ) mutationObserver.disconnect();
		observerRoot = nextRoot;
		if ( window.MutationObserver ) {
			mutationObserver = new window.MutationObserver( handleMutation );
			mutationObserver.observe( observerRoot, { childList: true, subtree: true } );
		}
		return true;
	}

	function handleChange( event ) {
		if ( event.target && event.target.closest && event.target.closest( '.cc-inspector' ) ) scheduleEnhance();
	}

	function handleClick( event ) {
		if ( event.target && event.target.closest && event.target.closest( '.cc-inspector-device-switcher' ) ) scheduleEnhance();
	}

	function destroy() {
		if ( destroyed ) return;
		destroyed = true;
		window.clearTimeout( bootTimer );
		if ( mutationObserver ) mutationObserver.disconnect();
		document.removeEventListener( 'change', handleChange );
		document.removeEventListener( 'click', handleClick );
		window.removeEventListener( 'pagehide', destroy );
	}

	function boot() {
		if ( destroyed ) return;
		if ( ! observeInspectorHost() ) {
			bootTimer = window.setTimeout( boot, 80 );
			return;
		}
		scheduleEnhance();
		document.addEventListener( 'change', handleChange );
		document.addEventListener( 'click', handleClick );
		window.addEventListener( 'pagehide', destroy );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot, { once: true } );
	else boot();
} )( window, document );
