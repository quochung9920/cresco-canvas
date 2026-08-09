( function ( window, document ) {
	'use strict';

	var TOKEN_PRESETS = {
		width: [ [ 'Content', '{layout.contentMax}' ], [ 'Container', '{layout.containerMax}' ] ],
		maxWidth: [ [ 'Content', '{layout.contentMax}' ], [ 'Container', '{layout.containerMax}' ] ],
		fontSize: [ [ 'Body', '{typography.sizes.base}' ], [ 'H1', '{typography.sizes.h1}' ], [ 'H2', '{typography.sizes.h2}' ], [ 'H3', '{typography.sizes.h3}' ], [ 'H4', '{typography.sizes.h4}' ], [ 'H5', '{typography.sizes.h5}' ], [ 'H6', '{typography.sizes.h6}' ] ],
		color: [ [ 'Text', '{colors.text}' ], [ 'Muted', '{colors.muted}' ], [ 'Primary', '{colors.primary}' ], [ 'Background', '{colors.background}' ] ],
		background: [ [ 'Background', '{colors.background}' ], [ 'Primary', '{colors.primary}' ], [ 'Text', '{colors.text}' ] ],
		borderRadius: [ [ 'Small', '{radius.sm}' ], [ 'Medium', '{radius.md}' ], [ 'Large', '{radius.lg}' ], [ 'Pill', '{radius.pill}' ] ],
		boxShadow: [ [ 'Small', '{shadows.sm}' ], [ 'Medium', '{shadows.md}' ], [ 'Large', '{shadows.lg}' ] ],
		gap: spacingTokens(),
		paddingTop: spacingTokens(), paddingRight: spacingTokens(), paddingBottom: spacingTokens(), paddingLeft: spacingTokens(),
		marginTop: spacingTokens(), marginRight: spacingTokens(), marginBottom: spacingTokens(), marginLeft: spacingTokens(),
	};

	var UNITS = {
		width: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch', 'auto' ],
		maxWidth: [ 'px', '%', 'rem', 'em', 'vw', 'ch', 'auto' ],
		minHeight: [ 'px', '%', 'rem', 'em', 'vh', 'vw' ],
		fontSize: [ 'px', 'rem', 'em', 'vw' ],
		letterSpacing: [ 'px', 'rem', 'em' ],
		borderRadius: [ 'px', '%', 'rem', 'em' ],
		gap: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch' ],
		paddingTop: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch' ], paddingRight: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch' ], paddingBottom: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch' ], paddingLeft: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch' ],
		marginTop: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch', 'auto' ], marginRight: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch', 'auto' ], marginBottom: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch', 'auto' ], marginLeft: [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch', 'auto' ],
		top: [ 'px', '%', 'rem', 'em', 'vw', 'vh' ], right: [ 'px', '%', 'rem', 'em', 'vw', 'vh' ], bottom: [ 'px', '%', 'rem', 'em', 'vw', 'vh' ], left: [ 'px', '%', 'rem', 'em', 'vw', 'vh' ],
	};

	var LABEL_TO_KEY = {
		'width': 'width', 'maximum width': 'maxWidth', 'minimum height': 'minHeight', 'gap': 'gap',
		'padding top': 'paddingTop', 'padding right': 'paddingRight', 'padding bottom': 'paddingBottom', 'padding left': 'paddingLeft',
		'margin top': 'marginTop', 'margin right': 'marginRight', 'margin bottom': 'marginBottom', 'margin left': 'marginLeft',
		'text color': 'color', 'background': 'background', 'font size': 'fontSize', 'font weight': 'fontWeight', 'line height': 'lineHeight', 'letter spacing': 'letterSpacing', 'text align': 'textAlign',
		'border radius': 'borderRadius', 'box shadow': 'boxShadow', 'opacity': 'opacity', 'position': 'position', 'top': 'top', 'right': 'right', 'bottom': 'bottom', 'left': 'left', 'z-index': 'zIndex', 'overflow': 'overflow',
	};
	var scheduled = false;

	function spacingTokens() {
		return [ [ '2XS', '{spacing.2xs}' ], [ 'XS', '{spacing.xs}' ], [ 'SM', '{spacing.sm}' ], [ 'MD', '{spacing.md}' ], [ 'LG', '{spacing.lg}' ], [ 'XL', '{spacing.xl}' ], [ '2XL', '{spacing.2xl}' ], [ '3XL', '{spacing.3xl}' ] ];
	}

	function normalize( value ) {
		return String( value || '' ).replace( /\s+/g, ' ' ).trim().toLowerCase();
	}

	function labelText( label ) {
		if ( ! label ) return '';
		var clone = label.cloneNode( true );
		clone.querySelectorAll( '.cc-inspector-v2-responsive-badge' ).forEach( function ( node ) { node.remove(); } );
		return normalize( clone.textContent || '' );
	}

	function currentDevice( inspector ) {
		var active = inspector.querySelector( '.cc-inspector-device-switcher button.is-active' );
		return active ? normalize( active.textContent ) : 'widescreen';
	}

	function emitInput( input ) {
		input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
	}

	function setValue( input, value ) {
		var descriptor = Object.getOwnPropertyDescriptor( window.HTMLInputElement.prototype, 'value' );
		if ( descriptor && descriptor.set ) descriptor.set.call( input, value );
		else input.value = value;
		emitInput( input );
	}

	function splitUnit( value ) {
		var match = String( value || '' ).trim().match( /^(-?\d+(?:\.\d+)?)(px|%|rem|em|vw|vh|ch)$/i );
		return match ? { number: match[ 1 ], unit: match[ 2 ] } : null;
	}

	function ensureActions( control, key, input, inspector ) {
		var wrap = control.querySelector( '.cc-control-engine-actions' );
		if ( ! wrap ) {
			wrap = document.createElement( 'div' );
			wrap.className = 'cc-control-engine-actions';
			control.appendChild( wrap );
		}
		wrap.textContent = '';

		var presets = TOKEN_PRESETS[ key ] || [];
		if ( presets.length ) {
			var select = document.createElement( 'select' );
			select.className = 'cc-control-engine-token';
			select.setAttribute( 'aria-label', 'Global token preset' );
			var placeholder = document.createElement( 'option' );
			placeholder.value = '';
			placeholder.textContent = 'Global';
			select.appendChild( placeholder );
			presets.forEach( function ( item ) {
				var option = document.createElement( 'option' );
				option.value = item[ 1 ];
				option.textContent = item[ 0 ];
				select.appendChild( option );
			} );
			select.addEventListener( 'change', function () {
				if ( select.value ) setValue( input, select.value );
				select.value = '';
			} );
			wrap.appendChild( select );
		}

		var units = UNITS[ key ] || [];
		if ( units.length ) {
			var unitSelect = document.createElement( 'select' );
			unitSelect.className = 'cc-control-engine-unit';
			unitSelect.setAttribute( 'aria-label', 'CSS unit' );
			units.forEach( function ( unit ) {
				var option = document.createElement( 'option' );
				option.value = unit;
				option.textContent = unit;
				unitSelect.appendChild( option );
			} );
			var parsed = splitUnit( input.value );
			if ( parsed && units.indexOf( parsed.unit ) !== -1 ) unitSelect.value = parsed.unit;
			unitSelect.addEventListener( 'change', function () {
				var current = splitUnit( input.value );
				if ( unitSelect.value === 'auto' ) setValue( input, 'auto' );
				else if ( current ) setValue( input, current.number + unitSelect.value );
				else if ( /^-?\d+(?:\.\d+)?$/.test( String( input.value ).trim() ) ) setValue( input, String( input.value ).trim() + unitSelect.value );
			} );
			wrap.appendChild( unitSelect );
		}

		var reset = document.createElement( 'button' );
		reset.type = 'button';
		reset.className = 'cc-control-engine-reset';
		reset.textContent = currentDevice( inspector ) === 'widescreen' ? 'Reset' : 'Inherit';
		reset.title = currentDevice( inspector ) === 'widescreen' ? 'Clear this value' : 'Remove this device override and inherit from the larger breakpoint';
		reset.addEventListener( 'click', function () { setValue( input, '' ); } );
		wrap.appendChild( reset );
	}

	function enhanceControl( control, inspector ) {
		var label = control.querySelector( '.components-base-control__label, label' );
		var key = LABEL_TO_KEY[ labelText( label ) ];
		if ( ! key ) return;
		var input = control.querySelector( 'input[type="text"], input[type="number"]' );
		if ( ! input ) return;
		control.dataset.crescoControlKey = key;
		control.classList.add( 'cc-control-engine-field' );
		ensureActions( control, key, input, inspector );
	}

	function ensureLinkButton( section, prefix ) {
		if ( ! section || section.querySelector( '.cc-control-engine-link[data-prefix="' + prefix + '"]' ) ) return;
		var fields = [ 'Top', 'Right', 'Bottom', 'Left' ].map( function ( side ) {
			return section.querySelector( '[data-cresco-control-key="' + prefix + side + '"] input[type="text"], [data-cresco-control-key="' + prefix + side + '"] input[type="number"]' );
		} );
		if ( fields.some( function ( field ) { return ! field; } ) ) return;
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cc-control-engine-link';
		button.dataset.prefix = prefix;
		button.textContent = 'Link ' + prefix.toLowerCase();
		button.addEventListener( 'click', function () {
			var source = fields.find( function ( field ) { return field.value !== ''; } ) || fields[ 0 ];
			fields.forEach( function ( field ) { if ( field !== source ) setValue( field, source.value ); } );
		} );
		var grid = section.querySelector( '.cc-inspector-grid' );
		if ( grid ) grid.insertBefore( button, grid.firstChild );
	}

	function enhanceInspector( inspector ) {
		if ( ! inspector ) return;
		inspector.classList.add( 'cc-control-engine' );
		inspector.querySelectorAll( '.components-base-control' ).forEach( function ( control ) { enhanceControl( control, inspector ); } );
		inspector.querySelectorAll( '.cc-inspector-section' ).forEach( function ( section ) {
			ensureLinkButton( section, 'padding' );
			ensureLinkButton( section, 'margin' );
		} );
	}

	function schedule() {
		if ( scheduled ) return;
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			enhanceInspector( document.querySelector( '.cc-inspector' ) );
		} );
	}

	function boot() {
		schedule();
		if ( window.MutationObserver ) new window.MutationObserver( schedule ).observe( document.body, { childList: true, subtree: true } );
		document.addEventListener( 'click', function ( event ) {
			if ( event.target && event.target.closest && event.target.closest( '.cc-inspector-device-switcher' ) ) schedule();
		} );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )( window, document );
