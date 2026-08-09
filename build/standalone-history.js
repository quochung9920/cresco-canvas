( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch || ! wp.i18n ) return;

	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var settings = window.crescoCanvasStandaloneSettings || {};
	var editorRoot = document.getElementById( 'cresco-canvas-standalone-editor' );
	if ( ! editorRoot || ! settings.postId ) return;

	var historyPath = settings.historyPath || '/cresco-canvas/v1/history/' + settings.postId;
	var state = {
		open: false,
		tab: 'actions',
		actions: [],
		actionIndex: -1,
		revisions: [],
		selectedRevision: 0,
		loadingRevisions: false,
		applyingRevision: false,
		error: '',
		busy: false,
	};
	var actionTimer = null;
	var drawer = null;
	var observer = null;

	function escapeText( value ) {
		return String( value === undefined || value === null ? '' : value );
	}

	function buttonText( button ) {
		return button ? String( button.textContent || '' ).replace( /\s+/g, ' ' ).trim() : '';
	}

	function findToolbarButton( label ) {
		var buttons = editorRoot.querySelectorAll( '.cc-standalone-header-actions button' );
		for ( var index = 0; index < buttons.length; index += 1 ) {
			if ( buttonText( buttons[ index ] ) === label ) return buttons[ index ];
		}
		return null;
	}

	function actionLabelFromTarget( target ) {
		if ( ! target || ! target.closest ) return '';
		if ( target.closest( '.cc-history-drawer, .cc-history-launcher' ) ) return '';

		var widget = target.closest( '.cc-standalone-widget' );
		if ( widget ) return sprintfSimple( __( 'Add %s', 'cresco-canvas' ), buttonText( widget ) || __( 'widget', 'cresco-canvas' ) );

		var button = target.closest( 'button' );
		if ( button ) {
			var text = buttonText( button );
			var aria = button.getAttribute( 'aria-label' ) || '';
			if ( text === __( 'Undo', 'cresco-canvas' ) || text === __( 'Redo', 'cresco-canvas' ) ) return '';
			if ( /delete/i.test( text + ' ' + aria ) ) return __( 'Delete widget', 'cresco-canvas' );
			if ( /duplicate/i.test( text + ' ' + aria ) ) return __( 'Duplicate widget', 'cresco-canvas' );
			if ( /reset device overrides/i.test( text ) ) return __( 'Reset responsive overrides', 'cresco-canvas' );
			if ( /apply to cresco editor/i.test( text ) ) return __( 'Apply imported session', 'cresco-canvas' );
			if ( /choose from media library/i.test( text ) ) return __( 'Choose image', 'cresco-canvas' );
		}

		if ( target.classList && target.classList.contains( 'cc-standalone-title' ) ) return __( 'Edit page title', 'cresco-canvas' );
		if ( target.matches && target.matches( '.cc-standalone-left-content input, .cc-standalone-left-content textarea, .cc-standalone-left-content select' ) ) {
			var control = target.closest( '.components-base-control' );
			var label = control ? control.querySelector( 'label' ) : null;
			return label && label.textContent ? sprintfSimple( __( 'Change %s', 'cresco-canvas' ), label.textContent.trim() ) : __( 'Edit widget settings', 'cresco-canvas' );
		}

		return '';
	}

	function sprintfSimple( template, value ) {
		return String( template ).replace( '%s', value );
	}

	function recordAction( label ) {
		if ( ! label || state.busy ) return;
		state.actions = state.actions.slice( 0, state.actionIndex + 1 );
		state.actions.push( { label: label, at: Date.now() } );
		if ( state.actions.length > 80 ) state.actions.shift();
		state.actionIndex = state.actions.length - 1;
		renderDrawer();
	}

	function scheduleAction( label ) {
		if ( ! label || state.busy ) return;
		window.clearTimeout( actionTimer );
		actionTimer = window.setTimeout( function () { recordAction( label ); }, 500 );
	}

	function onEditorClick( event ) {
		if ( state.busy ) return;
		var button = event.target && event.target.closest ? event.target.closest( 'button' ) : null;
		if ( button && button.closest( '.cc-standalone-header-actions' ) ) {
			var text = buttonText( button );
			if ( text === __( 'Undo', 'cresco-canvas' ) ) {
				window.clearTimeout( actionTimer );
				if ( state.actionIndex >= 0 ) state.actionIndex -= 1;
				renderDrawer();
				return;
			}
			if ( text === __( 'Redo', 'cresco-canvas' ) ) {
				window.clearTimeout( actionTimer );
				if ( state.actionIndex < state.actions.length - 1 ) state.actionIndex += 1;
				renderDrawer();
				return;
			}
		}
		var label = actionLabelFromTarget( event.target );
		if ( label ) scheduleAction( label );
	}

	function onEditorInput( event ) {
		var label = actionLabelFromTarget( event.target );
		if ( label ) scheduleAction( label );
	}

	function restoreAction( targetIndex ) {
		if ( targetIndex < 0 || targetIndex >= state.actions.length || targetIndex === state.actionIndex ) return;
		window.clearTimeout( actionTimer );
		var direction = targetIndex < state.actionIndex ? 'Undo' : 'Redo';
		var steps = Math.abs( targetIndex - state.actionIndex );
		var button = findToolbarButton( __( direction, 'cresco-canvas' ) );
		if ( ! button ) return;
		state.busy = true;
		for ( var index = 0; index < steps; index += 1 ) button.click();
		state.actionIndex = targetIndex;
		state.busy = false;
		renderDrawer();
	}

	function ensureLauncher() {
		var actions = editorRoot.querySelector( '.cc-standalone-header-actions' );
		if ( ! actions || actions.querySelector( '.cc-history-launcher' ) ) return;
		var launcher = document.createElement( 'button' );
		launcher.type = 'button';
		launcher.className = 'components-button is-tertiary cc-history-launcher';
		launcher.innerHTML = '<span class="dashicons dashicons-backup" aria-hidden="true"></span><span>' + __( 'History', 'cresco-canvas' ) + '</span>';
		launcher.addEventListener( 'click', function () {
			state.open = ! state.open;
			if ( state.open && state.tab === 'revisions' ) loadRevisions();
			renderDrawer();
		} );
		var aiButton = null;
		Array.prototype.forEach.call( actions.querySelectorAll( 'button' ), function ( item ) {
			if ( buttonText( item ) === __( 'AI', 'cresco-canvas' ) ) aiButton = item;
		} );
		if ( aiButton ) actions.insertBefore( launcher, aiButton );
		else actions.insertBefore( launcher, actions.firstChild );
	}

	function ensureDrawer() {
		if ( drawer && document.body.contains( drawer ) ) return drawer;
		drawer = document.createElement( 'aside' );
		drawer.className = 'cc-history-drawer';
		drawer.setAttribute( 'aria-label', __( 'History', 'cresco-canvas' ) );
		document.body.appendChild( drawer );
		return drawer;
	}

	function relativeTime( timestamp ) {
		var date = new Date( timestamp );
		if ( Number.isNaN( date.getTime() ) ) return '';
		var seconds = Math.max( 0, Math.floor( ( Date.now() - date.getTime() ) / 1000 ) );
		if ( seconds < 60 ) return __( 'just now', 'cresco-canvas' );
		var minutes = Math.floor( seconds / 60 );
		if ( minutes < 60 ) return minutes + ' ' + ( minutes === 1 ? __( 'minute ago', 'cresco-canvas' ) : __( 'minutes ago', 'cresco-canvas' ) );
		var hours = Math.floor( minutes / 60 );
		if ( hours < 24 ) return hours + ' ' + ( hours === 1 ? __( 'hour ago', 'cresco-canvas' ) : __( 'hours ago', 'cresco-canvas' ) );
		var days = Math.floor( hours / 24 );
		if ( days < 30 ) return days + ' ' + ( days === 1 ? __( 'day ago', 'cresco-canvas' ) : __( 'days ago', 'cresco-canvas' ) );
		var months = Math.max( 1, Math.floor( days / 30 ) );
		return months + ' ' + ( months === 1 ? __( 'month ago', 'cresco-canvas' ) : __( 'months ago', 'cresco-canvas' ) );
	}

	function formatDate( value ) {
		var date = new Date( value );
		if ( Number.isNaN( date.getTime() ) ) return '';
		return date.toLocaleString( undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' } );
	}

	function loadRevisions() {
		if ( state.loadingRevisions ) return;
		state.loadingRevisions = true;
		state.error = '';
		renderDrawer();
		apiFetch( { path: historyPath } ).then( function ( result ) {
			state.revisions = result && Array.isArray( result.revisions ) ? result.revisions : [];
			if ( ! state.selectedRevision && state.revisions.length ) state.selectedRevision = state.revisions[ 0 ].id || 0;
		} ).catch( function ( error ) {
			state.error = error && error.message ? error.message : __( 'Revision history could not be loaded.', 'cresco-canvas' );
		} ).finally( function () {
			state.loadingRevisions = false;
			renderDrawer();
		} );
	}

	function applyRevision() {
		if ( ! state.selectedRevision || state.applyingRevision ) return;
		state.applyingRevision = true;
		state.error = '';
		renderDrawer();
		apiFetch( { path: historyPath + '/' + state.selectedRevision + '/restore', method: 'POST' } ).then( function () {
			window.location.reload();
		} ).catch( function ( error ) {
			state.applyingRevision = false;
			state.error = error && error.message ? error.message : __( 'Revision could not be applied.', 'cresco-canvas' );
			renderDrawer();
		} );
	}

	function createNode( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) node.className = className;
		if ( text !== undefined ) node.textContent = text;
		return node;
	}

	function renderActions( content ) {
		if ( ! state.actions.length ) {
			var empty = createNode( 'div', 'cc-history-empty' );
			var icon = createNode( 'span', 'dashicons dashicons-backup cc-history-empty__icon' );
			icon.setAttribute( 'aria-hidden', 'true' );
			empty.appendChild( icon );
			empty.appendChild( createNode( 'strong', '', __( 'No History Yet', 'cresco-canvas' ) ) );
			empty.appendChild( createNode( 'p', '', __( 'Once you start working, you’ll be able to redo / undo any action you make in the editor.', 'cresco-canvas' ) ) );
			empty.appendChild( createNode( 'p', 'cc-history-empty__hint', __( 'Switch to Revisions tab for older versions.', 'cresco-canvas' ) ) );
			content.appendChild( empty );
			return;
		}

		var list = createNode( 'div', 'cc-history-list' );
		state.actions.slice().reverse().forEach( function ( action, reverseIndex ) {
			var index = state.actions.length - 1 - reverseIndex;
			var item = createNode( 'button', 'cc-history-card' + ( index === state.actionIndex ? ' is-selected' : '' ) );
			item.type = 'button';
			var body = createNode( 'span', 'cc-history-card__body' );
			body.appendChild( createNode( 'strong', '', action.label ) );
			body.appendChild( createNode( 'small', '', relativeTime( action.at ) ) );
			item.appendChild( body );
			if ( index === state.actionIndex ) item.appendChild( createNode( 'span', 'dashicons dashicons-yes-alt cc-history-card__check' ) );
			item.addEventListener( 'click', function () { restoreAction( index ); } );
			list.appendChild( item );
		} );
		content.appendChild( list );
	}

	function renderRevisions( content ) {
		var toolbar = createNode( 'div', 'cc-history-revision-toolbar' );
		var discard = createNode( 'button', 'cc-history-text-button', __( 'Discard', 'cresco-canvas' ) );
		discard.type = 'button';
		discard.disabled = ! state.selectedRevision;
		discard.addEventListener( 'click', function () { state.selectedRevision = 0; renderDrawer(); } );
		var apply = createNode( 'button', 'cc-history-apply-button', state.applyingRevision ? __( 'Applying…', 'cresco-canvas' ) : __( 'Apply', 'cresco-canvas' ) );
		apply.type = 'button';
		apply.disabled = ! state.selectedRevision || state.applyingRevision;
		apply.addEventListener( 'click', applyRevision );
		toolbar.appendChild( discard );
		toolbar.appendChild( apply );
		content.appendChild( toolbar );

		if ( state.loadingRevisions ) {
			content.appendChild( createNode( 'div', 'cc-history-status', __( 'Loading revisions…', 'cresco-canvas' ) ) );
			return;
		}
		if ( state.error ) {
			content.appendChild( createNode( 'div', 'cc-history-status is-error', state.error ) );
			return;
		}
		if ( ! state.revisions.length ) {
			content.appendChild( createNode( 'div', 'cc-history-status', __( 'No saved revisions yet. Update the page to create one.', 'cresco-canvas' ) ) );
			return;
		}

		var list = createNode( 'div', 'cc-history-list cc-history-revisions' );
		state.revisions.forEach( function ( revision ) {
			var selected = Number( revision.id ) === Number( state.selectedRevision ) || ( revision.current && ! state.selectedRevision );
			var item = createNode( 'button', 'cc-history-card cc-history-revision' + ( selected ? ' is-selected' : '' ) );
			item.type = 'button';
			var avatar = createNode( 'span', 'cc-history-avatar dashicons dashicons-admin-users' );
			avatar.setAttribute( 'aria-hidden', 'true' );
			var body = createNode( 'span', 'cc-history-card__body' );
			var when = relativeTime( revision.dateGmt || revision.dateLocal );
			var exact = formatDate( revision.dateGmt || revision.dateLocal );
			body.appendChild( createNode( 'strong', '', ( revision.current ? __( 'Current Version', 'cresco-canvas' ) : __( 'Revision', 'cresco-canvas' ) ) + ( when ? ' · ' + when : '' ) ) );
			body.appendChild( createNode( 'small', '', exact ) );
			var author = revision.author || {};
			body.appendChild( createNode( 'small', '', ( author.name || __( 'Unknown user', 'cresco-canvas' ) ) + ( author.email ? ' · ' + author.email : '' ) + ( revision.id ? ' (#' + revision.id + ')' : '' ) ) );
			body.appendChild( createNode( 'small', 'cc-history-node-count', revision.nodeCount + ' ' + __( 'widgets', 'cresco-canvas' ) ) );
			item.appendChild( avatar );
			item.appendChild( body );
			if ( selected ) item.appendChild( createNode( 'span', 'dashicons dashicons-yes-alt cc-history-card__check' ) );
			item.addEventListener( 'click', function () {
				state.selectedRevision = revision.current ? 0 : Number( revision.id );
				renderDrawer();
			} );
			list.appendChild( item );
		} );
		content.appendChild( list );
	}

	function renderDrawer() {
		ensureLauncher();
		var panel = ensureDrawer();
		panel.classList.toggle( 'is-open', state.open );
		panel.innerHTML = '';
		if ( ! state.open ) return;

		var header = createNode( 'div', 'cc-history-header' );
		header.appendChild( createNode( 'strong', '', __( 'History', 'cresco-canvas' ) ) );
		var close = createNode( 'button', 'cc-history-close dashicons dashicons-no-alt' );
		close.type = 'button';
		close.setAttribute( 'aria-label', __( 'Close History', 'cresco-canvas' ) );
		close.addEventListener( 'click', function () { state.open = false; renderDrawer(); } );
		header.appendChild( close );
		panel.appendChild( header );

		var tabs = createNode( 'div', 'cc-history-tabs' );
		[ [ 'actions', __( 'Actions', 'cresco-canvas' ) ], [ 'revisions', __( 'Revisions', 'cresco-canvas' ) ] ].forEach( function ( tab ) {
			var button = createNode( 'button', state.tab === tab[0] ? 'is-active' : '', tab[1] );
			button.type = 'button';
			button.addEventListener( 'click', function () {
				state.tab = tab[0];
				if ( state.tab === 'revisions' ) loadRevisions();
				renderDrawer();
			} );
			tabs.appendChild( button );
		} );
		panel.appendChild( tabs );

		var content = createNode( 'div', 'cc-history-content' );
		if ( state.tab === 'actions' ) renderActions( content );
		else renderRevisions( content );
		panel.appendChild( content );
	}

	editorRoot.addEventListener( 'click', onEditorClick, true );
	editorRoot.addEventListener( 'input', onEditorInput, true );
	editorRoot.addEventListener( 'change', onEditorInput, true );

	observer = new MutationObserver( function () { ensureLauncher(); } );
	observer.observe( editorRoot, { childList: true, subtree: true } );
	ensureLauncher();
	renderDrawer();
} )( window.wp, window, document );
