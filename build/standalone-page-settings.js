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

	function element( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( text !== undefined ) node.textContent = text;
		return node;
	}

	function makeButton( label, variant ) {
		var node = element( 'button', 'components-button is-' + ( variant || 'secondary' ), label );
		node.type = 'button';
		return node;
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

	function status( text, error ) {
		if ( ! form ) return;
		var node = form.querySelector( '.cc-page-settings-status' );
		if ( ! node ) return;
		node.textContent = text || '';
		node.classList.toggle( 'is-error', !! error );
	}

	function values() {
		var result = {};
		if ( ! form ) return result;
		[ 'layout', 'pageTitle', 'header', 'footer', 'contentRoot' ].forEach( function ( key ) {
			var input = form.querySelector( '[name="' + key + '"]' );
			if ( input ) result[ key ] = input.value;
		} );
		return result;
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
					? __( 'Full Width keeps the theme shell but makes the Cresco root span the browser viewport.', 'cresco-canvas' )
					: __( 'Theme Default keeps the active theme content flow. You may still override the Cresco root below.', 'cresco-canvas' );
		}
	}

	function populate( data ) {
		if ( ! form ) return;
		current = data && data.settings ? data.settings : data || {};
		var defaults = {
			layout: 'full-width',
			pageTitle: 'hide',
			header: 'inherit',
			footer: 'inherit',
			contentRoot: 'viewport'
		};
		Object.keys( defaults ).forEach( function ( key ) {
			var input = form.querySelector( '[name="' + key + '"]' );
			if ( input ) input.value = current[ key ] || defaults[ key ];
		} );
		syncLayoutControls();
	}

	function closeDialog() {
		if ( ! overlay || overlay.hidden ) return;
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
			populate( result );
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
		var save = form.querySelector( '.cc-page-settings-save' );
		if ( save ) save.disabled = true;
		status( __( 'Saving…', 'cresco-canvas' ), false );
		apiFetch( { path: settings.pageSettingsPath, method: 'POST', data: { settings: values() } } ).then( function ( result ) {
			populate( result );
			status( __( 'Page Settings saved. Preview will use the new page shell.', 'cresco-canvas' ), false );
			window.dispatchEvent( new CustomEvent( 'cresco:page-settings-saved', { detail: result } ) );
		} ).catch( function ( error ) {
			status( error && error.message ? error.message : __( 'Page Settings could not be saved.', 'cresco-canvas' ), true );
		} ).finally( function () {
			loading = false;
			if ( save ) save.disabled = false;
		} );
	}

	function buildDialog() {
		if ( overlay && document.body.contains( overlay ) ) return;
		overlay = element( 'div', 'cc-page-settings-overlay' );
		overlay.hidden = true;
		overlay.addEventListener( 'mousedown', function ( event ) { if ( event.target === overlay ) closeDialog(); } );

		var dialog = element( 'section', 'cc-page-settings-dialog' );
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-labelledby', 'cc-page-settings-title' );
		overlay.appendChild( dialog );

		var header = element( 'header', 'cc-page-settings-dialog__header' );
		var headingWrap = element( 'div', 'cc-page-settings-dialog__heading' );
		var title = element( 'h2', '', __( 'Page Settings', 'cresco-canvas' ) );
		title.id = 'cc-page-settings-title';
		headingWrap.appendChild( title );
		headingWrap.appendChild( element( 'p', '', __( 'Control how the WordPress theme hosts this Cresco Page.', 'cresco-canvas' ) ) );
		header.appendChild( headingWrap );
		var close = makeButton( '×', 'tertiary' );
		close.classList.add( 'cc-page-settings-close' );
		close.setAttribute( 'aria-label', __( 'Close Page Settings', 'cresco-canvas' ) );
		close.addEventListener( 'click', closeDialog );
		header.appendChild( close );
		dialog.appendChild( header );

		form = element( 'div', 'cc-page-settings-form' );
		var layoutSection = element( 'section', 'cc-page-settings-section' );
		layoutSection.appendChild( element( 'h3', '', __( 'Page Layout', 'cresco-canvas' ) ) );
		layoutSection.appendChild( selectField( __( 'Layout', 'cresco-canvas' ), 'layout', [
			{ label: __( 'Theme Default', 'cresco-canvas' ), value: 'theme-default' },
			{ label: __( 'Full Width', 'cresco-canvas' ), value: 'full-width' },
			{ label: __( 'Canvas', 'cresco-canvas' ), value: 'canvas' }
		] ) );
		layoutSection.appendChild( element( 'p', 'cc-page-settings-layout-note' ) );
		form.appendChild( layoutSection );

		var elementsSection = element( 'section', 'cc-page-settings-section' );
		elementsSection.appendChild( element( 'h3', '', __( 'Page Elements', 'cresco-canvas' ) ) );
		elementsSection.appendChild( selectField( __( 'Page Title', 'cresco-canvas' ), 'pageTitle', [
			{ label: __( 'Show', 'cresco-canvas' ), value: 'show' },
			{ label: __( 'Hide', 'cresco-canvas' ), value: 'hide' }
		] ) );
		elementsSection.appendChild( selectField( __( 'Site Header', 'cresco-canvas' ), 'header', [
			{ label: __( 'Inherit theme', 'cresco-canvas' ), value: 'inherit' },
			{ label: __( 'Show', 'cresco-canvas' ), value: 'show' },
			{ label: __( 'Hide', 'cresco-canvas' ), value: 'hide' }
		] ) );
		elementsSection.appendChild( selectField( __( 'Site Footer', 'cresco-canvas' ), 'footer', [
			{ label: __( 'Inherit theme', 'cresco-canvas' ), value: 'inherit' },
			{ label: __( 'Show', 'cresco-canvas' ), value: 'show' },
			{ label: __( 'Hide', 'cresco-canvas' ), value: 'hide' }
		] ) );
		form.appendChild( elementsSection );

		var rootSection = element( 'section', 'cc-page-settings-section' );
		rootSection.appendChild( element( 'h3', '', __( 'Cresco Content', 'cresco-canvas' ) ) );
		rootSection.appendChild( selectField( __( 'Cresco Root', 'cresco-canvas' ), 'contentRoot', [
			{ label: __( 'Theme Content Width', 'cresco-canvas' ), value: 'theme' },
			{ label: __( 'Full Viewport', 'cresco-canvas' ), value: 'viewport' }
		], __( 'Full Width and Canvas always use Full Viewport. Container Full Width still means 100% of its parent.', 'cresco-canvas' ) ) );
		form.appendChild( rootSection );

		form.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.name === 'layout' ) syncLayoutControls();
		} );
		dialog.appendChild( form );

		var footer = element( 'footer', 'cc-page-settings-dialog__footer' );
		var statusNode = element( 'div', 'cc-page-settings-status' );
		statusNode.setAttribute( 'aria-live', 'polite' );
		footer.appendChild( statusNode );
		var actions = element( 'div', 'cc-page-settings-actions' );
		var cancel = makeButton( __( 'Cancel', 'cresco-canvas' ), 'secondary' );
		cancel.addEventListener( 'click', closeDialog );
		var save = makeButton( __( 'Save Page Settings', 'cresco-canvas' ), 'primary' );
		save.classList.add( 'cc-page-settings-save' );
		save.addEventListener( 'click', saveSettings );
		actions.appendChild( cancel );
		actions.appendChild( save );
		footer.appendChild( actions );
		dialog.appendChild( footer );
		app.appendChild( overlay );
	}

	function openDialog() {
		buildDialog();
		lastFocus = document.activeElement;
		overlay.hidden = false;
		overlay.classList.add( 'is-open' );
		document.body.classList.add( 'cc-page-settings-open' );
		if ( current ) {
			populate( current );
			var first = form.querySelector( '[name="layout"]' );
			if ( first ) first.focus();
			return;
		}
		loadSettings().then( function () {
			var first = form && form.querySelector( '[name="layout"]' );
			if ( first ) first.focus();
		} );
	}

	function ensureButton() {
		if ( ! app ) return;
		var actions = app.querySelector( '.cc-standalone-header-actions' );
		if ( ! actions ) return;
		var existing = actions.querySelector( '.cc-page-settings-trigger' );
		if ( existing ) {
			trigger = existing;
			return;
		}
		trigger = makeButton( '', 'secondary' );
		trigger.classList.add( 'cc-page-settings-trigger' );
		trigger.setAttribute( 'aria-label', __( 'Page Settings', 'cresco-canvas' ) );
		trigger.title = __( 'Page Settings', 'cresco-canvas' );
		var icon = element( 'span', 'dashicons dashicons-admin-settings' );
		icon.setAttribute( 'aria-hidden', 'true' );
		var label = element( 'span', 'cc-page-settings-trigger__label', __( 'Page', 'cresco-canvas' ) );
		trigger.appendChild( icon );
		trigger.appendChild( label );
		trigger.addEventListener( 'click', openDialog );
		var preview = Array.prototype.find.call( actions.querySelectorAll( 'a,button' ), function ( node ) { return String( node.textContent || '' ).trim() === __( 'Preview', 'cresco-canvas' ); } );
		actions.insertBefore( trigger, preview || null );
	}

	function handleKeydown( event ) {
		if ( event.key === 'Escape' && overlay && ! overlay.hidden ) {
			event.preventDefault();
			closeDialog();
		}
	}

	function boot() {
		app = document.querySelector( '.cc-standalone-app' );
		if ( ! app ) {
			window.setTimeout( boot, 80 );
			return;
		}
		ensureButton();
		document.addEventListener( 'keydown', handleKeydown );
		if ( window.MutationObserver ) new window.MutationObserver( ensureButton ).observe( app, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )( window.wp, window, document );
