( function ( window, document ) {
	'use strict';

	var STORAGE_PREFIX = 'cresco-inspector-v2:';
	var activeTab = 'primary';
	var lastSignature = '';
	var observer = null;
	var scheduled = false;

	function text( node ) {
		return node ? String( node.textContent || '' ).replace( /\s+/g, ' ' ).trim() : '';
	}

	function normalize( value ) {
		return String( value || '' ).toLowerCase().replace( /\s+/g, ' ' ).trim();
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
		if ( label === 'container' ) return 'container';
		if ( label === 'columns' ) return 'columns';
		if ( label === 'heading' ) return 'heading';
		if ( label === 'text' ) return 'text';
		if ( label === 'button' ) return 'button';
		if ( label === 'image' ) return 'image';
		if ( label === 'list' ) return 'list';
		if ( label === 'divider' ) return 'divider';
		if ( label === 'spacer' ) return 'spacer';
		return label.replace( /[^a-z0-9_-]+/g, '-' ) || 'widget';
	}

	function primaryTabLabel( type ) {
		return type === 'container' || type === 'columns' ? 'Layout' : 'Content';
	}

	function primaryTabIcon( type ) {
		return type === 'container' || type === 'columns' ? 'layout' : 'edit';
	}

	function storageKey( inspector, sectionKey ) {
		var settings = window.crescoCanvasStandaloneSettings || {};
		return STORAGE_PREFIX + String( settings.postId || 'page' ) + ':' + selectedWidgetId( inspector ) + ':' + sectionKey;
	}

	function getStoredOpen( inspector, sectionKey, fallback ) {
		try {
			var value = window.sessionStorage.getItem( storageKey( inspector, sectionKey ) );
			if ( value === '1' ) return true;
			if ( value === '0' ) return false;
		} catch ( error ) {}
		return fallback;
	}

	function setStoredOpen( inspector, sectionKey, value ) {
		try { window.sessionStorage.setItem( storageKey( inspector, sectionKey ), value ? '1' : '0' ); } catch ( error ) {}
	}

	function sectionHeading( section ) {
		return section.querySelector( ':scope > h3, :scope > .cc-inspector-section-heading h3' );
	}

	function sectionKey( section ) {
		return normalize( text( sectionHeading( section ) ) ).replace( /[^a-z0-9]+/g, '-' ) || 'section';
	}

	function categoryForSection( section ) {
		var heading = normalize( text( sectionHeading( section ) ) );
		if ( heading === 'appearance' || heading.indexOf( 'style' ) !== -1 || heading.indexOf( 'typography' ) !== -1 ) return 'style';
		if ( heading === 'spacing' || heading === 'advanced' || heading.indexOf( 'custom css' ) !== -1 ) return 'advanced';
		return 'primary';
	}

	function currentDevice( inspector ) {
		var active = inspector.querySelector( '.cc-inspector-device-switcher button.is-active' );
		return text( active ) || 'Widescreen';
	}

	function createTab( name, label, icon ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cc-inspector-v2-tab';
		button.dataset.tab = name;
		button.setAttribute( 'aria-selected', activeTab === name ? 'true' : 'false' );
		button.innerHTML = '<span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span><span>' + label + '</span>';
		button.addEventListener( 'click', function () {
			activeTab = name;
			applyTabVisibility( button.closest( '.cc-inspector' ) );
		} );
		return button;
	}

	function ensureTabs( inspector, type ) {
		var tabs = inspector.querySelector( ':scope > .cc-inspector-v2-tabs' );
		if ( tabs ) tabs.remove();
		tabs = document.createElement( 'div' );
		tabs.className = 'cc-inspector-v2-tabs';
		tabs.setAttribute( 'role', 'tablist' );
		tabs.setAttribute( 'aria-label', 'Widget settings' );
		tabs.appendChild( createTab( 'primary', primaryTabLabel( type ), primaryTabIcon( type ) ) );
		tabs.appendChild( createTab( 'style', 'Style', 'art' ) );
		tabs.appendChild( createTab( 'advanced', 'Advanced', 'admin-generic' ) );
		var header = inspector.querySelector( ':scope > .cc-inspector-header' );
		if ( header && header.nextSibling ) inspector.insertBefore( tabs, header.nextSibling );
		else inspector.appendChild( tabs );
	}

	function renameHeader( inspector ) {
		var strong = inspector.querySelector( '.cc-inspector-header strong' );
		if ( ! strong ) return;
		var current = text( strong ).replace( /^Edit\s+/i, '' );
		if ( current ) strong.textContent = 'Edit ' + current;
	}

	function ensureSectionToggle( inspector, section, defaultOpen ) {
		var heading = sectionHeading( section );
		if ( ! heading ) return;
		var key = sectionKey( section );
		var open = getStoredOpen( inspector, key, defaultOpen );
		section.classList.toggle( 'is-collapsed', ! open );
		section.dataset.sectionOpen = open ? 'true' : 'false';
		var headingWrap = heading.parentElement && heading.parentElement.classList.contains( 'cc-inspector-section-heading' ) ? heading.parentElement : heading;
		var toggle = headingWrap.querySelector( '.cc-inspector-v2-section-toggle' );
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
				toggle.textContent = nextOpen ? '−' : '+';
				toggle.setAttribute( 'aria-expanded', nextOpen ? 'true' : 'false' );
				setStoredOpen( inspector, key, nextOpen );
			} );
			headingWrap.appendChild( toggle );
		}
		toggle.textContent = open ? '−' : '+';
		toggle.setAttribute( 'aria-label', ( open ? 'Collapse ' : 'Expand ' ) + text( heading ) );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}

	function baseControlByLabel( section, labelText ) {
		var labels = section.querySelectorAll( 'label, .components-base-control__label' );
		for ( var index = 0; index < labels.length; index += 1 ) {
			if ( normalize( text( labels[ index ] ) ) === normalize( labelText ) ) return labels[ index ].closest( '.components-base-control' ) || labels[ index ].parentElement;
		}
		return null;
	}

	function addSubTitleBefore( control, title ) {
		if ( ! control || ! control.parentElement ) return;
		var previous = control.previousElementSibling;
		if ( previous && previous.classList.contains( 'cc-inspector-v2-subtitle' ) && previous.dataset.title === title ) return;
		var node = document.createElement( 'div' );
		node.className = 'cc-inspector-v2-subtitle';
		node.dataset.title = title;
		node.textContent = title;
		control.parentElement.insertBefore( node, control );
	}

	function enhanceAppearanceSection( section ) {
		var textColor = baseControlByLabel( section, 'Text color' );
		var fontSize = baseControlByLabel( section, 'Font size' );
		var radius = baseControlByLabel( section, 'Border radius' );
		addSubTitleBefore( textColor, 'Colors & Background' );
		addSubTitleBefore( fontSize, 'Typography' );
		addSubTitleBefore( radius, 'Border & Shadow' );
	}

	function enhanceSpacingSection( section ) {
		addSubTitleBefore( baseControlByLabel( section, 'Padding top' ), 'Padding' );
		addSubTitleBefore( baseControlByLabel( section, 'Margin top' ), 'Margin' );
	}

	function enhanceAdvancedSection( section ) {
		addSubTitleBefore( baseControlByLabel( section, 'Opacity' ), 'Visibility & Position' );
	}

	function addResponsiveBadges( inspector ) {
		var device = currentDevice( inspector );
		var labels = inspector.querySelectorAll( '.cc-inspector-section .components-base-control__label' );
		labels.forEach( function ( label ) {
			var old = label.querySelector( '.cc-inspector-v2-responsive-badge' );
			if ( old ) old.remove();
			var badge = document.createElement( 'span' );
			badge.className = 'cc-inspector-v2-responsive-badge';
			badge.textContent = device;
			badge.title = 'Editing ' + device + ' value';
			label.appendChild( badge );
		} );
	}

	function enhanceContainerRules( inspector, type ) {
		if ( type !== 'container' ) return;
		var sections = inspector.querySelectorAll( ':scope > .cc-inspector-section' );
		var contentSection = null;
		var dimensionsSection = null;
		sections.forEach( function ( section ) {
			var heading = normalize( text( sectionHeading( section ) ) );
			if ( heading === 'content' || heading === 'container' ) contentSection = section;
			if ( heading === 'size & layout' || heading === 'dimensions' ) dimensionsSection = section;
		} );
		if ( contentSection ) {
			var heading = sectionHeading( contentSection );
			if ( heading && heading.firstChild && heading.firstChild.nodeType === 3 ) heading.firstChild.nodeValue = 'Container';
		}
		if ( dimensionsSection ) {
			var dimensionsHeading = sectionHeading( dimensionsSection );
			if ( dimensionsHeading && dimensionsHeading.firstChild && dimensionsHeading.firstChild.nodeType === 3 ) dimensionsHeading.firstChild.nodeValue = 'Dimensions';
		}
		var widthControl = contentSection && baseControlByLabel( contentSection, 'Content width' );
		var select = widthControl && widthControl.querySelector( 'select' );
		var maximum = dimensionsSection && baseControlByLabel( dimensionsSection, 'Maximum width' );
		var isFull = ! select || select.value === 'full';
		if ( maximum ) {
			maximum.classList.toggle( 'cc-inspector-v2-boxed-only', true );
			maximum.hidden = isFull;
		}
		if ( widthControl ) {
			var oldHelp = widthControl.querySelector( '.cc-inspector-v2-help' );
			if ( oldHelp ) oldHelp.remove();
			var help = document.createElement( 'p' );
			help.className = 'cc-inspector-v2-help';
			help.textContent = isFull ? 'Full Width uses 100% of the parent container.' : 'Boxed is constrained by the Global container maximum width.';
			widthControl.appendChild( help );
		}
		if ( select && ! select.dataset.ccInspectorV2Bound ) {
			select.dataset.ccInspectorV2Bound = '1';
			select.addEventListener( 'change', scheduleEnhance );
		}
	}

	function classifySections( inspector ) {
		var sections = inspector.querySelectorAll( ':scope > .cc-inspector-section' );
		sections.forEach( function ( section, index ) {
			var category = categoryForSection( section );
			section.dataset.inspectorTab = category;
			var heading = normalize( text( sectionHeading( section ) ) );
			if ( heading === 'appearance' ) enhanceAppearanceSection( section );
			if ( heading === 'spacing' ) enhanceSpacingSection( section );
			if ( heading === 'advanced' ) enhanceAdvancedSection( section );
			ensureSectionToggle( inspector, section, index < 2 || category === activeTab );
		} );
	}

	function applyTabVisibility( inspector ) {
		if ( ! inspector ) return;
		inspector.dataset.activeInspectorTab = activeTab;
		inspector.querySelectorAll( ':scope > .cc-inspector-v2-tabs .cc-inspector-v2-tab' ).forEach( function ( button ) {
			var selected = button.dataset.tab === activeTab;
			button.classList.toggle( 'is-active', selected );
			button.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
		} );
		inspector.querySelectorAll( ':scope > .cc-inspector-section' ).forEach( function ( section ) {
			section.hidden = section.dataset.inspectorTab !== activeTab;
		} );
		var switcher = inspector.querySelector( ':scope > .cc-inspector-device-switcher' );
		if ( switcher ) switcher.hidden = false;
	}

	function enhanceInspector( inspector ) {
		if ( ! inspector ) return;
		var type = selectedWidgetType( inspector );
		inspector.classList.add( 'cc-inspector-v2' );
		inspector.dataset.widgetType = type;
		renameHeader( inspector );
		ensureTabs( inspector, type );
		classifySections( inspector );
		enhanceContainerRules( inspector, type );
		addResponsiveBadges( inspector );
		applyTabVisibility( inspector );
	}

	function scheduleEnhance() {
		if ( scheduled ) return;
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			var inspector = document.querySelector( '.cc-inspector' );
			if ( inspector ) enhanceInspector( inspector );
		} );
	}

	function boot() {
		scheduleEnhance();
		observer = new window.MutationObserver( scheduleEnhance );
		observer.observe( document.body, { childList: true, subtree: true } );
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-inspector' ) ) scheduleEnhance();
		} );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )( window, document );
