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

	function button( label, primary ) {
		var node = element( 'button', 'components-button ' + ( primary ? 'is-primary' : 'is-secondary' ), label );
		node.type = 'button';
		return node;
	}

	function renderPreview( root, result ) {
		root.innerHTML = '';
		var heading = element( 'div', 'cc-global-import-summary__heading' );
		heading.appendChild( element( 'strong', '', __( 'Ready to apply', 'cresco-canvas' ) ) );
		heading.appendChild( element( 'span', 'cc-global-import-format', String( result.format || 'config' ).toUpperCase() ) );
		root.appendChild( heading );

		var mappings = Array.isArray( result.mapping ) ? result.mapping : [];
		var list = element( 'div', 'cc-global-import-mapping' );
		mappings.forEach( function ( item ) {
			var row = element( 'div', 'cc-global-import-mapping__row' );
			row.appendChild( element( 'code', '', item.source || '' ) );
			row.appendChild( element( 'span', '', '→' ) );
			row.appendChild( element( 'code', '', item.target || '' ) );
			if ( item.value ) row.appendChild( element( 'small', '', item.value ) );
			list.appendChild( row );
		} );
		root.appendChild( list );

		var ignored = Array.isArray( result.ignored ) ? result.ignored : [];
		if ( ignored.length ) root.appendChild( element( 'p', 'cc-global-import-ignored', __( 'Ignored:', 'cresco-canvas' ) + ' ' + ignored.join( ', ' ) ) );
	}

	function mount( panel ) {
		if ( panel.querySelector( '.cc-global-import-card' ) ) return;

		var card = element( 'section', 'cc-global-card cc-global-import-card' );
		card.appendChild( element( 'h3', '', __( 'Import Global Config', 'cresco-canvas' ) ) );
		card.appendChild( element( 'p', 'cc-standalone-help', __( 'Paste CSS variables or a Cresco Global JSON object. Colors may use HEX, rgb(), hsl(), oklab(), or oklch(). Cresco previews the mapping before anything is saved.', 'cresco-canvas' ) ) );

		if ( settings.canManageGlobal === false ) {
			card.appendChild( element( 'p', 'cc-global-import-ignored', __( 'Your account cannot change site-wide Global Design settings.', 'cresco-canvas' ) ) );
			panel.appendChild( card );
			return;
		}

		var textarea = element( 'textarea', 'cc-global-import-input' );
		textarea.rows = 13;
		textarea.placeholder = '--bg: oklch(98% 0.005 250);\n--ink: oklch(22% 0.02 250);\n--blue: oklch(55% 0.15 235);\nfont-family: Poppins, sans-serif;\ncolor: var(--ink);';
		card.appendChild( textarea );

		var actions = element( 'div', 'cc-global-import-actions' );
		var previewButton = button( __( 'Preview import', 'cresco-canvas' ), false );
		var applyButton = button( __( 'Apply Global Config', 'cresco-canvas' ), true );
		applyButton.disabled = true;
		actions.appendChild( previewButton );
		actions.appendChild( applyButton );
		card.appendChild( actions );

		var status = element( 'div', 'cc-global-import-status' );
		var summary = element( 'div', 'cc-global-import-summary' );
		card.appendChild( status );
		card.appendChild( summary );
		panel.appendChild( card );

		var preview = null;
		function setBusy( busy ) {
			previewButton.disabled = busy || ! textarea.value.trim();
			applyButton.disabled = busy || ! preview;
		}
		textarea.addEventListener( 'input', function () {
			preview = null;
			summary.innerHTML = '';
			status.textContent = '';
			setBusy( false );
		} );
		setBusy( false );

		previewButton.addEventListener( 'click', function () {
			preview = null;
			setBusy( true );
			status.textContent = __( 'Validating Global Config…', 'cresco-canvas' );
			apiFetch( { path: settings.settingsImportPreviewPath, method: 'POST', data: { input: textarea.value } } ).then( function ( result ) {
				preview = result;
				status.textContent = __( 'Config is valid. Review the mapping below.', 'cresco-canvas' );
				renderPreview( summary, result );
			} ).catch( function ( error ) {
				status.textContent = error && error.message ? error.message : __( 'Global Config could not be parsed.', 'cresco-canvas' );
				status.classList.add( 'is-error' );
			} ).finally( function () { setBusy( false ); } );
		} );

		applyButton.addEventListener( 'click', function () {
			if ( ! preview || ! preview.settings ) return;
			setBusy( true );
			status.classList.remove( 'is-error' );
			status.textContent = __( 'Saving Global Design…', 'cresco-canvas' );
			apiFetch( { path: settings.settingsPath, method: 'POST', data: preview.settings } ).then( function () {
				status.textContent = __( 'Global Design saved. Reloading Cresco Editor…', 'cresco-canvas' );
				window.setTimeout( function () { window.location.reload(); }, 350 );
			} ).catch( function ( error ) {
				status.textContent = error && error.message ? error.message : __( 'Global Design could not be saved.', 'cresco-canvas' );
				status.classList.add( 'is-error' );
				setBusy( false );
			} );
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
