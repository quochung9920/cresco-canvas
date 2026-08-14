( function ( window, document ) {
	'use strict';

	var STORAGE_PREFIX = 'cresco-control-engine:';
	var scheduled = false;
	var destroyed = false;
	var observer = null;
	var observerRoot = null;
	var bootTimer = null;

	var SPACING_PRESETS = function () {
		return [
			[ '2XS', '{spacing.2xs}' ], [ 'XS', '{spacing.xs}' ], [ 'SM', '{spacing.sm}' ],
			[ 'MD', '{spacing.md}' ], [ 'LG', '{spacing.lg}' ], [ 'XL', '{spacing.xl}' ],
			[ '2XL', '{spacing.2xl}' ], [ '3XL', '{spacing.3xl}' ]
		];
	};

	var PRESETS = {
		width: [ [ 'Content', '{layout.contentMax}' ], [ 'Container', '{layout.containerMax}' ], [ 'Full', '100%' ], [ 'Auto', 'auto' ] ],
		maxWidth: [ [ 'Content', '{layout.contentMax}' ], [ 'Container', '{layout.containerMax}' ], [ 'None', 'none' ] ],
		fontSize: [ [ 'Body', '{typography.sizes.base}' ], [ 'H1', '{typography.sizes.h1}' ], [ 'H2', '{typography.sizes.h2}' ], [ 'H3', '{typography.sizes.h3}' ], [ 'H4', '{typography.sizes.h4}' ], [ 'H5', '{typography.sizes.h5}' ], [ 'H6', '{typography.sizes.h6}' ] ],
		fontWeight: [ [ 'Regular', '400' ], [ 'Medium', '500' ], [ 'Semibold', '600' ], [ 'Bold', '700' ], [ 'Black', '900' ] ],
		lineHeight: [ [ 'Tight', '1.15' ], [ 'Snug', '1.3' ], [ 'Normal', '1.5' ], [ 'Relaxed', '1.7' ] ],
		textAlign: [ [ 'Left', 'left' ], [ 'Center', 'center' ], [ 'Right', 'right' ], [ 'Justify', 'justify' ] ],
		color: [ [ 'Text', '{colors.text}' ], [ 'Muted', '{colors.muted}' ], [ 'Primary', '{colors.primary}' ], [ 'Background', '{colors.background}' ] ],
		background: [ [ 'Background', '{colors.background}' ], [ 'Primary', '{colors.primary}' ], [ 'Text', '{colors.text}' ], [ 'Transparent', 'transparent' ] ],
		borderRadius: [ [ 'Small', '{radius.sm}' ], [ 'Medium', '{radius.md}' ], [ 'Large', '{radius.lg}' ], [ 'Pill', '{radius.pill}' ], [ 'None', '0' ] ],
		boxShadow: [ [ 'Small', '{shadows.sm}' ], [ 'Medium', '{shadows.md}' ], [ 'Large', '{shadows.lg}' ], [ 'None', 'none' ] ],
		opacity: [ [ '100%', '1' ], [ '75%', '0.75' ], [ '50%', '0.5' ], [ '25%', '0.25' ], [ 'Hidden', '0' ] ],
		position: [ [ 'Static', 'static' ], [ 'Relative', 'relative' ], [ 'Absolute', 'absolute' ], [ 'Sticky', 'sticky' ], [ 'Fixed', 'fixed' ] ],
		overflow: [ [ 'Visible', 'visible' ], [ 'Hidden', 'hidden' ], [ 'Auto', 'auto' ], [ 'Clip', 'clip' ] ]
	};

	[ 'gap', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft' ].forEach( function ( key ) {
		PRESETS[ key ] = SPACING_PRESETS();
	} );

	var LENGTH_UNITS = [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch' ];
	var UNITS = {
		width: LENGTH_UNITS.concat( [ 'auto' ] ),
		maxWidth: LENGTH_UNITS.concat( [ 'auto', 'none' ] ),
		minHeight: LENGTH_UNITS,
		fontSize: [ 'px', 'rem', 'em', 'vw' ],
		letterSpacing: [ 'px', 'rem', 'em' ],
		borderRadius: [ 'px', '%', 'rem', 'em' ],
		gap: LENGTH_UNITS,
		paddingTop: LENGTH_UNITS,
		paddingRight: LENGTH_UNITS,
		paddingBottom: LENGTH_UNITS,
		paddingLeft: LENGTH_UNITS,
		marginTop: LENGTH_UNITS.concat( [ 'auto' ] ),
		marginRight: LENGTH_UNITS.concat( [ 'auto' ] ),
		marginBottom: LENGTH_UNITS.concat( [ 'auto' ] ),
		marginLeft: LENGTH_UNITS.concat( [ 'auto' ] ),
		top: LENGTH_UNITS,
		right: LENGTH_UNITS,
		bottom: LENGTH_UNITS,
		left: LENGTH_UNITS
	};

	var LABEL_TO_KEY = {
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

	function normalize( value ) {
		return String( value || '' ).replace( /\s+/g, ' ' ).trim().toLowerCase();
	}

	function labelText( label ) {
		if ( ! label ) return '';
		var copy = label.cloneNode( true );
		copy.querySelectorAll( '.cc-inspector-v2-responsive-badge' ).forEach( function ( badge ) { badge.remove(); } );
		return normalize( copy.textContent || '' );
	}

	function currentDevice( inspector ) {
		var active = inspector.querySelector( '.cc-inspector-device-switcher button.is-active' );
		return active ? normalize( active.textContent ) : 'widescreen';
	}

	function selectedWidgetId( inspector ) {
		var code = inspector.querySelector( '.cc-inspector-header code' );
		return code ? normalize( code.textContent ) : 'widget';
	}

	function storageKey( inspector, prefix ) {
		var settings = window.crescoCanvasStandaloneSettings || {};
		return STORAGE_PREFIX + String( settings.postId || 'page' ) + ':' + selectedWidgetId( inspector ) + ':' + currentDevice( inspector ) + ':' + prefix;
	}

	function readLinkedState( inspector, prefix ) {
		try { return window.sessionStorage.getItem( storageKey( inspector, prefix ) ) === '1'; } catch ( error ) { return false; }
	}

	function writeLinkedState( inspector, prefix, value ) {
		try { window.sessionStorage.setItem( storageKey( inspector, prefix ), value ? '1' : '0' ); } catch ( error ) {}
	}

	function inputPrototype( input ) {
		if ( input instanceof window.HTMLTextAreaElement ) return window.HTMLTextAreaElement.prototype;
		if ( input instanceof window.HTMLSelectElement ) return window.HTMLSelectElement.prototype;
		return window.HTMLInputElement.prototype;
	}

	function setNativeValue( input, value ) {
		if ( ! input ) return;
		var descriptor = Object.getOwnPropertyDescriptor( inputPrototype( input ), 'value' );
		if ( descriptor && descriptor.set ) descriptor.set.call( input, value );
		else input.value = value;
		input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
	}

	function parseUnit( value ) {
		var match = String( value || '' ).trim().match( /^(-?\d+(?:\.\d+)?)(px|%|rem|em|vw|vh|ch)$/i );
		return match ? { number: match[ 1 ], unit: match[ 2 ] } : null;
	}

	function addOption( select, label, value ) {
		var option = document.createElement( 'option' );
		option.value = value;
		option.textContent = label;
		select.appendChild( option );
	}

	function renderActions( control, key, input, inspector ) {
		var row = control.querySelector( ':scope > .cc-control-engine-actions' );
		if ( ! row ) {
			row = document.createElement( 'div' );
			row.className = 'cc-control-engine-actions';
			control.appendChild( row );
		}
		row.textContent = '';

		var presets = PRESETS[ key ] || [];
		if ( presets.length ) {
			var preset = document.createElement( 'select' );
			preset.className = 'cc-control-engine-token';
			preset.setAttribute( 'aria-label', 'Choose a structured preset' );
			addOption( preset, 'Preset', '' );
			presets.forEach( function ( item ) { addOption( preset, item[ 0 ], item[ 1 ] ); } );
			preset.addEventListener( 'change', function () {
				if ( preset.value ) setNativeValue( input, preset.value );
				preset.value = '';
			} );
			row.appendChild( preset );
		}

		var units = UNITS[ key ] || [];
		if ( units.length ) {
			var unit = document.createElement( 'select' );
			unit.className = 'cc-control-engine-unit';
			unit.setAttribute( 'aria-label', 'CSS unit' );
			units.forEach( function ( value ) { addOption( unit, value, value ); } );
			var parsed = parseUnit( input.value );
			if ( parsed && units.indexOf( parsed.unit ) !== -1 ) unit.value = parsed.unit;
			unit.addEventListener( 'change', function () {
				var current = parseUnit( input.value );
				if ( unit.value === 'auto' || unit.value === 'none' ) setNativeValue( input, unit.value );
				else if ( current ) setNativeValue( input, current.number + unit.value );
				else if ( /^-?\d+(?:\.\d+)?$/.test( String( input.value ).trim() ) ) setNativeValue( input, String( input.value ).trim() + unit.value );
			} );
			row.appendChild( unit );
		}

		var reset = document.createElement( 'button' );
		reset.type = 'button';
		reset.className = 'cc-control-engine-reset';
		reset.textContent = currentDevice( inspector ) === 'widescreen' ? 'Reset' : 'Inherit';
		reset.title = currentDevice( inspector ) === 'widescreen' ? 'Clear this base value' : 'Remove this device override and inherit from the larger breakpoint';
		reset.addEventListener( 'click', function () { setNativeValue( input, '' ); } );
		row.appendChild( reset );
	}

	function enhanceControl( control, inspector ) {
		var label = control.querySelector( '.components-base-control__label, label' );
		var key = LABEL_TO_KEY[ labelText( label ) ];
		if ( ! key ) return;
		var input = control.querySelector( 'input[type="text"], input[type="number"], textarea' );
		if ( ! input ) return;
		control.dataset.crescoControlKey = key;
		control.classList.add( 'cc-control-engine-field' );
		renderActions( control, key, input, inspector );
	}

	function sideInputs( section, prefix ) {
		return [ 'Top', 'Right', 'Bottom', 'Left' ].map( function ( side ) {
			return section.querySelector( '[data-cresco-control-key="' + prefix + side + '"] input[type="text"], [data-cresco-control-key="' + prefix + side + '"] input[type="number"]' );
		} );
	}

	function syncLinkedValues( inputs, source ) {
		inputs.forEach( function ( input ) {
			if ( input && input !== source && input.value !== source.value ) setNativeValue( input, source.value );
		} );
	}

	function bindLinkedInputs( inspector, section, prefix, inputs ) {
		inputs.forEach( function ( input ) {
			if ( ! input || input.dataset.ccControlLinkedBound === prefix ) return;
			input.dataset.ccControlLinkedBound = prefix;
			input.addEventListener( 'input', function () {
				if ( readLinkedState( inspector, prefix ) ) syncLinkedValues( inputs, input );
			} );
		} );
	}

	function enhanceLinking( inspector, section, prefix ) {
		var inputs = sideInputs( section, prefix );
		if ( inputs.some( function ( input ) { return ! input; } ) ) return;
		bindLinkedInputs( inspector, section, prefix, inputs );

		var button = section.querySelector( '.cc-control-engine-link[data-prefix="' + prefix + '"]' );
		if ( ! button ) {
			button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'cc-control-engine-link';
			button.dataset.prefix = prefix;
			button.addEventListener( 'click', function () {
				var next = ! readLinkedState( inspector, prefix );
				writeLinkedState( inspector, prefix, next );
				if ( next ) {
					var source = inputs.find( function ( input ) { return String( input.value || '' ).trim() !== ''; } ) || inputs[ 0 ];
					syncLinkedValues( inputs, source );
				}
				schedule();
			} );
			var grid = section.querySelector( '.cc-inspector-grid' );
			if ( grid ) grid.insertBefore( button, grid.firstChild );
		}
		var linked = readLinkedState( inspector, prefix );
		button.classList.toggle( 'is-linked', linked );
		button.setAttribute( 'aria-pressed', linked ? 'true' : 'false' );
		button.textContent = linked ? 'Linked ' + prefix : 'Link ' + prefix;
		button.title = linked ? 'Keep all four ' + prefix + ' values synchronized' : 'Link all four ' + prefix + ' values';
	}

	function run() {
		if ( destroyed ) return;
		var inspector = document.querySelector( '.cc-inspector' );
		if ( ! inspector ) return;
		inspector.classList.add( 'cc-control-engine' );
		inspector.querySelectorAll( '.components-base-control' ).forEach( function ( control ) { enhanceControl( control, inspector ); } );
		inspector.querySelectorAll( '.cc-inspector-section' ).forEach( function ( section ) {
			enhanceLinking( inspector, section, 'padding' );
			enhanceLinking( inspector, section, 'margin' );
		} );
	}

	function schedule() {
		if ( scheduled || destroyed ) return;
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			run();
		} );
	}

	function mutationNeedsRun( records ) {
		return Array.prototype.some.call( records || [], function ( record ) {
			var target = record && record.target ? record.target : null;
			if ( ! target || ! target.closest ) return true;
			return !! target.closest( '.cc-inspector, .cc-standalone-left-content' );
		} );
	}

	function observeHost() {
		var nextRoot = document.querySelector( '.cc-standalone-left-content' );
		if ( ! nextRoot ) return false;
		if ( observerRoot === nextRoot && observer ) return true;
		if ( observer ) observer.disconnect();
		observerRoot = nextRoot;
		if ( window.MutationObserver ) {
			observer = new window.MutationObserver( function ( records ) { if ( mutationNeedsRun( records ) ) schedule(); } );
			observer.observe( observerRoot, { childList: true, subtree: true } );
		}
		return true;
	}

	function handleClick( event ) {
		if ( event.target && event.target.closest && event.target.closest( '.cc-inspector-device-switcher' ) ) schedule();
	}

	function destroy() {
		if ( destroyed ) return;
		destroyed = true;
		window.clearTimeout( bootTimer );
		if ( observer ) observer.disconnect();
		document.removeEventListener( 'click', handleClick );
		window.removeEventListener( 'pagehide', destroy );
	}

	function boot() {
		if ( destroyed ) return;
		if ( ! observeHost() ) {
			bootTimer = window.setTimeout( boot, 80 );
			return;
		}
		schedule();
		document.addEventListener( 'click', handleClick );
		window.addEventListener( 'pagehide', destroy );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot, { once: true } );
	else boot();
} )( window, document );
