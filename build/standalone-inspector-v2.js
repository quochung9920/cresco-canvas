( function ( window, document ) {
	'use strict';

	var STORAGE_PREFIX = 'cresco-inspector-v2:';
	var activeTab = 'primary';
	var lastWidgetId = '';
	var scheduled = false;

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

	function addResponsiveBadges( inspector ) {
		var device = currentDevice( inspector );
		inspector.querySelectorAll( ':scope > .cc-inspector-section' ).forEach( function ( section ) {
			var key = sectionKey( section );
			if ( key === 'content' || key === 'container' ) return;
			section.querySelectorAll( '.components-base-control__label' ).forEach( function ( label ) {
				var badge = label.querySelector( ':scope > .cc-inspector-v2-responsive-badge' );
				if ( ! badge ) {
					badge = document.createElement( 'span' );
					badge.className = 'cc-inspector-v2-responsive-badge';
					badge.setAttribute( 'aria-hidden', 'true' );
					label.appendChild( badge );
				}
				if ( badge.textContent !== device ) badge.textContent = device;
				var title = 'Editing ' + device + ' value';
				if ( badge.title !== title ) badge.title = title;
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
		if ( window.MutationObserver ) {
			new window.MutationObserver( scheduleEnhance ).observe( document.body, { childList: true, subtree: true } );
		}
		document.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-inspector' ) ) scheduleEnhance();
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-inspector-device-switcher' ) ) scheduleEnhance();
		} );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )( window, document );
