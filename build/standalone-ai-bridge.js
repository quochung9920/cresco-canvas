( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch || ! wp.i18n ) return;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var settings = window.crescoCanvasStandaloneSettings || {};
	if ( ! settings.postId || ! settings.aiContextPath || ! settings.aiInterchangeContextPath || ! settings.aiInterchangeValidatePath ) return;

	var state = {
		mode: 'optimized',
		exportPayload: null,
		importText: '',
		validation: null,
		busy: false,
		message: '',
		error: false
	};
	var observer = null;
	var renderTimer = null;

	function clone( value ) { return JSON.parse( JSON.stringify( value ) ); }
	function text( value ) { return String( value === undefined || value === null ? '' : value ); }
	function el( tag, className, content ) {
		var node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( content !== undefined ) node.textContent = content;
		return node;
	}
	function button( label, variant, handler ) {
		var node = el( 'button', 'components-button cc-ai-bridge-button ' + ( variant || 'is-secondary' ), label );
		node.type = 'button';
		node.addEventListener( 'click', handler );
		return node;
	}
	function setMessage( message, error ) {
		state.message = message || '';
		state.error = !! error;
		renderSoon();
	}
	function preferredBridge() {
		var bridge = window.crescoCanvasAIInterchange;
		return bridge && typeof bridge.getSession === 'function' ? bridge : null;
	}
	function currentSelectedId() {
		var bridge = preferredBridge();
		if ( bridge && typeof bridge.getSelectedId === 'function' ) return bridge.getSelectedId() || '';
		var selected = document.querySelector( '.cc-canvas-node.is-selected[data-cresco-id]' );
		return selected ? selected.getAttribute( 'data-cresco-id' ) || '' : '';
	}
	function buttonText( node ) { return node ? text( node.textContent ).replace( /\s+/g, ' ' ).trim() : ''; }
	function legacyAiPanel() { return document.querySelector( '.cc-ai-panel' ); }
	function legacyButton( label ) {
		var panel = legacyAiPanel();
		if ( ! panel ) return null;
		var buttons = panel.querySelectorAll( '.cc-ai-card button' );
		for ( var i = 0; i < buttons.length; i += 1 ) if ( buttonText( buttons[ i ] ) === label ) return buttons[ i ];
		return null;
	}

	function captureLegacySession() {
		return new Promise( function ( resolve, reject ) {
			var copy = legacyButton( __( 'Copy Session', 'cresco-canvas' ) );
			if ( ! copy ) { reject( new Error( 'Legacy Session bridge is unavailable.' ) ); return; }
			var captured = '';
			var clipboard = window.navigator && window.navigator.clipboard;
			var originalWrite = clipboard && clipboard.writeText;
			var originalExec = document.execCommand;
			var restoreWrite = false;
			try {
				if ( clipboard && typeof originalWrite === 'function' ) {
					clipboard.writeText = function ( value ) { captured = text( value ); return Promise.resolve(); };
					restoreWrite = true;
				}
			} catch ( error ) {}
			try {
				document.execCommand = function ( command ) {
					if ( String( command ).toLowerCase() === 'copy' ) {
						var active = document.activeElement;
						if ( active && typeof active.value === 'string' ) captured = active.value;
						return true;
					}
					return typeof originalExec === 'function' ? originalExec.apply( document, arguments ) : false;
				};
			} catch ( error ) {}
			copy.click();
			window.setTimeout( function () {
				try { if ( restoreWrite ) clipboard.writeText = originalWrite; } catch ( error ) {}
				try { document.execCommand = originalExec; } catch ( error ) {}
				if ( ! captured ) { reject( new Error( 'Current Session could not be captured.' ) ); return; }
				try { resolve( JSON.parse( captured ) ); } catch ( error ) { reject( error ); }
			}, 0 );
		} );
	}

	function currentSession() {
		var bridge = preferredBridge();
		if ( bridge ) {
			try { return Promise.resolve( clone( bridge.getSession() ) ); } catch ( error ) { return Promise.reject( error ); }
		}
		return captureLegacySession().catch( function () {
			return apiFetch( { path: settings.aiContextPath } ).then( function ( value ) {
				if ( ! value || ! value.session ) throw new Error( 'Current Session is unavailable.' );
				return clone( value.session );
			} );
		} );
	}

	function copyJson( value ) {
		var payload = JSON.stringify( value, null, 2 );
		if ( navigator.clipboard && navigator.clipboard.writeText ) return navigator.clipboard.writeText( payload );
		return new Promise( function ( resolve, reject ) {
			try {
				var field = el( 'textarea' );
				field.value = payload;
				field.setAttribute( 'readonly', 'readonly' );
				field.style.position = 'fixed'; field.style.opacity = '0';
				document.body.appendChild( field ); field.select(); document.execCommand( 'copy' ); field.remove(); resolve();
			} catch ( error ) { reject( error ); }
		} );
	}

	function exportContext( scope ) {
		var nodeId = currentSelectedId();
		if ( scope !== 'page' && ! nodeId ) { setMessage( __( 'Select an element before exporting this scope.', 'cresco-canvas' ), true ); return; }
		state.busy = true; state.validation = null; setMessage( __( 'Building AI context…', 'cresco-canvas' ), false );
		currentSession().then( function ( session ) {
			var target = scope === 'selection' ? { nodeIds: [ nodeId ] } : ( scope === 'page' ? {} : { nodeId: nodeId } );
			return apiFetch( { path: settings.aiInterchangeContextPath, method: 'POST', data: { session: session, scope: scope, target: target, mode: state.mode } } );
		} ).then( function ( result ) {
			state.exportPayload = result;
			return copyJson( result );
		} ).then( function () {
			setMessage( __( 'AI Context copied as JSON.', 'cresco-canvas' ), false );
		} ).catch( function ( error ) {
			setMessage( error && error.message ? error.message : __( 'AI context export failed.', 'cresco-canvas' ), true );
		} ).finally( function () { state.busy = false; renderSoon(); } );
	}

	function detectSchema() {
		try {
			var parsed = JSON.parse( state.importText );
			if ( parsed && parsed.schema ) return parsed.schema;
			if ( parsed && parsed.session && parsed.session.schema ) return parsed.session.schema;
		} catch ( error ) {}
		return '';
	}

	function validateImport() {
		var parsed;
		try { parsed = JSON.parse( state.importText ); } catch ( error ) { setMessage( __( 'AI result is not valid JSON.', 'cresco-canvas' ), true ); return; }
		state.busy = true; state.validation = null; setMessage( __( 'Validating through Cresco contracts…', 'cresco-canvas' ), false );
		currentSession().then( function ( session ) {
			return apiFetch( { path: settings.aiInterchangeValidatePath, method: 'POST', data: { currentSession: session, result: parsed } } );
		} ).then( function ( result ) {
			state.validation = result;
			setMessage( __( 'Valid. Review the structured changes before applying.', 'cresco-canvas' ), false );
		} ).catch( function ( error ) {
			state.validation = null;
			setMessage( error && error.message ? error.message : __( 'AI result validation failed.', 'cresco-canvas' ), true );
		} ).finally( function () { state.busy = false; renderSoon(); } );
	}

	function applyValidated() {
		if ( ! state.validation || ! state.validation.session ) return;
		var parsed;
		try { parsed = JSON.parse( state.importText ); } catch ( error ) { setMessage( __( 'AI result is not valid JSON.', 'cresco-canvas' ), true ); return; }
		state.busy = true;
		setMessage( __( 'Rechecking the current Session before Apply…', 'cresco-canvas' ), false );
		currentSession().then( function ( session ) {
			return apiFetch( { path: settings.aiInterchangeValidatePath, method: 'POST', data: { currentSession: session, result: parsed } } );
		} ).then( function ( freshValidation ) {
			state.validation = freshValidation;
			applyCandidate( freshValidation.session );
		} ).catch( function ( error ) {
			setMessage( error && error.message ? error.message : __( 'AI result validation failed before Apply.', 'cresco-canvas' ), true );
		} ).finally( function () { state.busy = false; renderSoon(); } );
	}

	function applyCandidate( candidate ) {
		var bridge = preferredBridge();
		if ( bridge && typeof bridge.applyValidatedSession === 'function' ) {
			var applied = bridge.applyValidatedSession( clone( candidate ) );
			if ( applied !== false ) {
				state.validation = null;
				setMessage( __( 'AI changes applied to the editor. Use Undo to restore the pre-AI checkpoint. Nothing is saved until Update.', 'cresco-canvas' ), false );
				return;
			}
		}
		applyThroughLegacyImport();
	}

	function setNativeValue( textarea, value ) {
		var descriptor = Object.getOwnPropertyDescriptor( window.HTMLTextAreaElement.prototype, 'value' );
		if ( descriptor && descriptor.set ) descriptor.set.call( textarea, value ); else textarea.value = value;
		textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function applyThroughLegacyImport() {
		var panel = legacyAiPanel();
		var textarea = panel ? panel.querySelector( '.cc-ai-card textarea' ) : null;
		var validate = legacyButton( __( 'Validate import', 'cresco-canvas' ) );
		if ( ! textarea || ! validate ) { setMessage( __( 'Editor apply bridge is unavailable.', 'cresco-canvas' ), true ); return; }
		setNativeValue( textarea, JSON.stringify( state.validation.session ) );
		var attempts = 0;
		function waitForApply() {
			attempts += 1;
			var apply = legacyButton( __( 'Apply to Cresco Editor', 'cresco-canvas' ) );
			if ( apply ) {
				apply.click(); state.validation = null;
				setMessage( __( 'AI changes applied. Use Undo to roll back. Nothing is saved until Update.', 'cresco-canvas' ), false );
				return;
			}
			if ( attempts < 40 ) window.setTimeout( waitForApply, 50 );
			else setMessage( __( 'The editor could not apply the validated Session.', 'cresco-canvas' ), true );
		}
		window.setTimeout( function () {
			var freshValidate = legacyButton( __( 'Validate import', 'cresco-canvas' ) );
			if ( ! freshValidate ) { setMessage( __( 'Editor validation bridge is unavailable.', 'cresco-canvas' ), true ); return; }
			freshValidate.click();
			window.setTimeout( waitForApply, 25 );
		}, 0 );
	}

	function renderDiff( root ) {
		var diff = state.validation && state.validation.diff;
		if ( ! diff ) return;
		var review = el( 'section', 'cc-ai-bridge-card' );
		review.appendChild( el( 'h3', '', __( 'Review Changes', 'cresco-canvas' ) ) );
		var summary = diff.summary || {};
		review.appendChild( el( 'p', 'cc-ai-bridge-summary', text( summary.total || 0 ) + ' changes · +' + text( summary.inserted || 0 ) + ' inserted · −' + text( summary.removed || 0 ) + ' removed · ↕' + text( summary.moved || 0 ) + ' moved' ) );
		var list = el( 'div', 'cc-ai-bridge-diff' );
		( diff.items || [] ).slice( 0, 120 ).forEach( function ( item ) {
			var row = el( 'div', 'cc-ai-bridge-diff-row is-' + text( item.changeType ) );
			var head = el( 'strong', '', text( item.widgetLabel || item.widgetType ) + ' · ' + text( item.nodeId ) );
			row.appendChild( head );
			row.appendChild( el( 'code', '', text( item.field ) ) );
			if ( item.changeType === 'changed' ) row.appendChild( el( 'span', '', shortValue( item.before ) + ' → ' + shortValue( item.after ) ) );
			else row.appendChild( el( 'span', '', item.changeType ) );
			list.appendChild( row );
		} );
		review.appendChild( list );
		var actions = el( 'div', 'cc-ai-bridge-actions' );
		actions.appendChild( button( __( 'Apply', 'cresco-canvas' ), 'is-primary', applyValidated ) );
		review.appendChild( actions ); root.appendChild( review );
	}
	function shortValue( value ) {
		var output = typeof value === 'string' ? value : JSON.stringify( value );
		if ( output === undefined ) output = '∅';
		return output.length > 96 ? output.slice( 0, 93 ) + '…' : output;
	}

	function render() {
		var panel = legacyAiPanel();
		if ( ! panel ) return;
		panel.classList.add( 'cc-ai-bridge-active' );
		var old = panel.querySelector( ':scope > .cc-ai-bridge' );
		if ( old ) old.remove();
		var root = el( 'div', 'cc-ai-bridge' );

		var exportCard = el( 'section', 'cc-ai-bridge-card' );
		exportCard.appendChild( el( 'h3', '', __( 'Export for AI', 'cresco-canvas' ) ) );
		exportCard.appendChild( el( 'p', '', __( 'Export the live editor Session with only the contracts and design dependencies the selected scope needs.', 'cresco-canvas' ) ) );
		var modes = el( 'div', 'cc-ai-bridge-modes' );
		[ [ 'optimized', __( 'Optimized Context', 'cresco-canvas' ) ], [ 'full', __( 'Full Context', 'cresco-canvas' ) ] ].forEach( function ( option ) {
			var label = el( 'label' ); var radio = el( 'input' ); radio.type = 'radio'; radio.name = 'cresco-ai-context-mode'; radio.value = option[0]; radio.checked = state.mode === option[0];
			radio.addEventListener( 'change', function () { state.mode = option[0]; renderSoon(); } ); label.appendChild( radio ); label.appendChild( document.createTextNode( ' ' + option[1] ) ); modes.appendChild( label );
		} ); exportCard.appendChild( modes );
		var exports = el( 'div', 'cc-ai-bridge-actions cc-ai-bridge-actions--stack' );
		exports.appendChild( button( __( 'Export Current Page', 'cresco-canvas' ), 'is-primary', function () { exportContext( 'page' ); } ) );
		exports.appendChild( button( __( 'Export Selected Element', 'cresco-canvas' ), 'is-secondary', function () { exportContext( 'widget' ); } ) );
		exports.appendChild( button( __( 'Export Selected Subtree', 'cresco-canvas' ), 'is-secondary', function () { exportContext( 'subtree' ); } ) );
		exports.appendChild( button( __( 'Export Selection', 'cresco-canvas' ), 'is-secondary', function () { exportContext( 'selection' ); } ) );
		exportCard.appendChild( exports );
		var compatibility = el( 'div', 'cc-ai-bridge-actions cc-ai-bridge-compatibility' );
		compatibility.appendChild( button( __( 'Copy Current Session', 'cresco-canvas' ), 'is-tertiary', function () {
			currentSession().then( copyJson ).then( function () { setMessage( __( 'Current Session copied.', 'cresco-canvas' ), false ); } ).catch( function ( error ) { setMessage( error && error.message ? error.message : __( 'Current Session could not be copied.', 'cresco-canvas' ), true ); } );
		} ) );
		compatibility.appendChild( button( __( 'Copy Widget Contracts', 'cresco-canvas' ), 'is-tertiary', function () {
			apiFetch( { path: settings.aiContextPath } ).then( function ( value ) { return copyJson( value.widgets || value.contracts || {} ); } ).then( function () { setMessage( __( 'Widget Contracts copied.', 'cresco-canvas' ), false ); } ).catch( function ( error ) { setMessage( error && error.message ? error.message : __( 'Widget Contracts could not be copied.', 'cresco-canvas' ), true ); } );
		} ) );
		exportCard.appendChild( compatibility );
		if ( state.exportPayload ) {
			var meta = el( 'div', 'cc-ai-bridge-meta' );
			meta.appendChild( el( 'code', '', text( state.exportPayload.schema ) ) );
			meta.appendChild( el( 'span', '', text( state.exportPayload.scope ) + ' · ' + text( state.exportPayload.mode ) ) );
			exportCard.appendChild( meta );
			var copy = button( __( 'Copy JSON', 'cresco-canvas' ), 'is-secondary', function () { copyJson( state.exportPayload ).then( function () { setMessage( __( 'AI Context copied as JSON.', 'cresco-canvas' ), false ); } ); } ); exportCard.appendChild( copy );
		}
		root.appendChild( exportCard );

		var importCard = el( 'section', 'cc-ai-bridge-card' );
		importCard.appendChild( el( 'h3', '', __( 'Import AI Result', 'cresco-canvas' ) ) );
		importCard.appendChild( el( 'p', '', __( 'Paste cresco-patch/v1 for targeted edits or a complete cresco-session/v1 document. Cresco validates first; nothing is applied directly to DOM or WordPress data.', 'cresco-canvas' ) ) );
		var textarea = el( 'textarea', 'cc-ai-bridge-textarea' ); textarea.rows = 14; textarea.value = state.importText; textarea.placeholder = '{\n  "schema": "cresco-patch/v1",\n  "target": { "scope": "subtree", "nodeId": "hero" },\n  "operations": []\n}';
		textarea.addEventListener( 'input', function () { state.importText = textarea.value; state.validation = null; updateDetected( root ); } ); importCard.appendChild( textarea );
		var detected = el( 'div', 'cc-ai-bridge-detected' ); detected.dataset.aiDetected = 'true'; detected.textContent = __( 'Detected:', 'cresco-canvas' ) + ' ' + ( detectSchema() || '—' ); importCard.appendChild( detected );
		var importActions = el( 'div', 'cc-ai-bridge-actions' ); var validate = button( __( 'Validate', 'cresco-canvas' ), 'is-secondary', validateImport ); validate.disabled = !state.importText.trim() || state.busy; importActions.appendChild( validate ); importCard.appendChild( importActions ); root.appendChild( importCard );

		if ( state.message ) root.appendChild( el( 'div', 'cc-ai-bridge-notice ' + ( state.error ? 'is-error' : 'is-success' ), state.message ) );
		renderDiff( root );
		panel.insertBefore( root, panel.firstChild || null );
	}
	function updateDetected( root ) {
		var node = root && root.querySelector ? root.querySelector( '[data-ai-detected="true"]' ) : null;
		if ( node ) node.textContent = __( 'Detected:', 'cresco-canvas' ) + ' ' + ( detectSchema() || '—' );
	}
	function renderSoon() { window.clearTimeout( renderTimer ); renderTimer = window.setTimeout( render, 0 ); }
	function boot() {
		renderSoon();
		if ( window.MutationObserver && ! observer ) {
			observer = new MutationObserver( function () {
				var panel = legacyAiPanel();
				if ( panel && ! panel.querySelector( ':scope > .cc-ai-bridge' ) ) renderSoon();
			} );
			observer.observe( document.getElementById( 'cresco-canvas-standalone-editor' ) || document.body, { childList: true, subtree: true } );
		}
	}
	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot ); else boot();
} )( window.wp, window, document );
