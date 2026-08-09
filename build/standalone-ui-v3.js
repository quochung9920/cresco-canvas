( function ( window, document ) {
	'use strict';

	var settings = window.crescoCanvasStandaloneSettings || {};
	var STORAGE_KEY = 'cresco-ui-v3:' + String( settings.postId || 'page' );
	var OWNERSHIP_STYLE_ID = 'cresco-ui-v3-panel-ownership';
	var app = null;
	var backdrop = null;
	var leftButton = null;
	var rightButton = null;
	var leftDrawerOpen = false;
	var rightDrawerOpen = false;
	var state = {
		leftCollapsed: false,
		rightCollapsed: false
	};
	var resizeTimer = null;

	function readState() {
		try {
			var saved = JSON.parse( window.sessionStorage.getItem( STORAGE_KEY ) || '{}' );
			if ( saved && typeof saved === 'object' ) {
				state.leftCollapsed = saved.leftCollapsed === true;
				state.rightCollapsed = saved.rightCollapsed === true;
			}
		} catch ( error ) {}
	}

	function saveState() {
		try { window.sessionStorage.setItem( STORAGE_KEY, JSON.stringify( state ) ); } catch ( error ) {}
	}

	function mode() {
		if ( window.innerWidth < 960 ) return 'compact';
		if ( window.innerWidth < 1280 ) return 'medium';
		return 'desktop';
	}

	function ensureOwnershipStyles() {
		if ( document.getElementById( OWNERSHIP_STYLE_ID ) ) return;
		var style = document.createElement( 'style' );
		style.id = OWNERSHIP_STYLE_ID;
		style.textContent = [
			'.cc-global-panel.cc-ui-v3-global-authoritative > :not(.cc-global-simple-editor){display:none!important;}',
			'.cc-inspector .cc-global-simple-editor{display:none!important;}',
			'.cc-global-panel .cc-inspector-v2-tabs{display:none!important;}'
		].join( '' );
		document.head.appendChild( style );
	}

	function directGlobalEditor( panel ) {
		if ( ! panel ) return null;
		for ( var index = 0; index < panel.children.length; index += 1 ) {
			if ( panel.children[ index ].classList.contains( 'cc-global-simple-editor' ) ) return panel.children[ index ];
		}
		return null;
	}

	function syncPanelOwnership() {
		if ( ! app ) return;
		ensureOwnershipStyles();

		Array.prototype.forEach.call( app.querySelectorAll( '.cc-global-simple-editor' ), function ( editor ) {
			if ( ! editor.closest( '.cc-global-panel' ) ) editor.remove();
		} );

		Array.prototype.forEach.call( app.querySelectorAll( '.cc-global-panel' ), function ( panel ) {
			var editor = directGlobalEditor( panel );
			panel.classList.toggle( 'cc-ui-v3-global-authoritative', !! editor );
			Array.prototype.forEach.call( panel.querySelectorAll( '.cc-inspector-v2-tabs' ), function ( tabs ) { tabs.remove(); } );
		} );

		Array.prototype.forEach.call( app.querySelectorAll( '.cc-ui-v3-global-authoritative' ), function ( node ) {
			if ( ! node.classList.contains( 'cc-global-panel' ) ) node.classList.remove( 'cc-ui-v3-global-authoritative' );
		} );

		Array.prototype.forEach.call( app.querySelectorAll( '.cc-inspector' ), function ( inspector ) {
			Array.prototype.forEach.call( inspector.querySelectorAll( '.cc-global-simple-editor' ), function ( editor ) { editor.remove(); } );
		} );
	}

	function makeButton( panel, label, icon ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cc-ui-v3-panel-button';
		button.dataset.panel = panel;
		button.setAttribute( 'aria-label', 'Toggle ' + label.toLowerCase() + ' panel' );
		button.title = label + ' panel';

		var iconNode = document.createElement( 'span' );
		iconNode.className = 'dashicons dashicons-' + icon;
		iconNode.setAttribute( 'aria-hidden', 'true' );
		button.appendChild( iconNode );

		var labelNode = document.createElement( 'span' );
		labelNode.className = 'cc-ui-v3-panel-button__label';
		labelNode.textContent = label;
		button.appendChild( labelNode );

		button.addEventListener( 'click', function () { togglePanel( panel ); } );
		return button;
	}

	function ensureChrome() {
		if ( ! app ) return;
		ensureOwnershipStyles();
		var actions = app.querySelector( '.cc-standalone-header-actions' );
		if ( actions && ! actions.querySelector( '.cc-ui-v3-panel-controls' ) ) {
			var controls = document.createElement( 'div' );
			controls.className = 'cc-ui-v3-panel-controls';
			leftButton = makeButton( 'left', 'Tools', 'menu-alt' );
			rightButton = makeButton( 'right', 'Structure', 'list-view' );
			controls.appendChild( leftButton );
			controls.appendChild( rightButton );
			actions.insertBefore( controls, actions.firstChild || null );
		} else if ( actions ) {
			leftButton = actions.querySelector( '.cc-ui-v3-panel-button[data-panel="left"]' );
			rightButton = actions.querySelector( '.cc-ui-v3-panel-button[data-panel="right"]' );
		}

		if ( ! backdrop || ! document.body.contains( backdrop ) ) {
			backdrop = document.createElement( 'button' );
			backdrop.type = 'button';
			backdrop.className = 'cc-ui-v3-backdrop';
			backdrop.setAttribute( 'aria-label', 'Close editor panel' );
			backdrop.addEventListener( 'click', function () { closeDrawers( true ); } );
			app.appendChild( backdrop );
		}

		var left = app.querySelector( '.cc-standalone-left' );
		var right = app.querySelector( '.cc-standalone-right' );
		var stage = app.querySelector( '.cc-standalone-stage' );
		var structure = app.querySelector( '.cc-standalone-structure' );
		if ( left ) left.setAttribute( 'aria-label', 'Builder tools' );
		if ( right ) right.setAttribute( 'aria-label', 'Page structure panel' );
		if ( stage ) stage.setAttribute( 'aria-label', 'Cresco page canvas' );
		if ( structure ) structure.setAttribute( 'aria-label', 'Page structure' );
	}

	function closeDrawers( restoreFocus ) {
		var hadLeft = leftDrawerOpen;
		var hadRight = rightDrawerOpen;
		leftDrawerOpen = false;
		rightDrawerOpen = false;
		sync();
		if ( restoreFocus ) {
			if ( hadRight && rightButton ) rightButton.focus();
			else if ( hadLeft && leftButton ) leftButton.focus();
		}
	}

	function togglePanel( panel ) {
		var currentMode = mode();
		if ( currentMode === 'desktop' ) {
			if ( panel === 'left' ) state.leftCollapsed = ! state.leftCollapsed;
			else state.rightCollapsed = ! state.rightCollapsed;
			saveState();
		} else if ( currentMode === 'medium' ) {
			if ( panel === 'left' ) {
				state.leftCollapsed = ! state.leftCollapsed;
				saveState();
			} else {
				rightDrawerOpen = ! rightDrawerOpen;
				leftDrawerOpen = false;
			}
		} else if ( panel === 'left' ) {
			leftDrawerOpen = ! leftDrawerOpen;
			rightDrawerOpen = false;
		} else {
			rightDrawerOpen = ! rightDrawerOpen;
			leftDrawerOpen = false;
		}
		sync();
	}

	function buttonExpanded( panel, currentMode ) {
		if ( currentMode === 'desktop' ) return panel === 'left' ? ! state.leftCollapsed : ! state.rightCollapsed;
		if ( currentMode === 'medium' ) return panel === 'left' ? ! state.leftCollapsed : rightDrawerOpen;
		return panel === 'left' ? leftDrawerOpen : rightDrawerOpen;
	}

	function syncButton( button, expanded ) {
		if ( ! button ) return;
		button.classList.toggle( 'is-active', expanded );
		button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
	}

	function sync() {
		if ( ! app ) return;
		ensureChrome();
		var currentMode = mode();
		if ( currentMode === 'desktop' ) {
			leftDrawerOpen = false;
			rightDrawerOpen = false;
		} else if ( currentMode === 'medium' ) {
			leftDrawerOpen = false;
		}

		app.dataset.crescoUiMode = currentMode;
		app.classList.add( 'cc-ui-v3-ready' );
		app.classList.toggle( 'cc-ui-v3-left-collapsed', state.leftCollapsed );
		app.classList.toggle( 'cc-ui-v3-right-collapsed', state.rightCollapsed );
		app.classList.toggle( 'cc-ui-v3-left-drawer-open', leftDrawerOpen );
		app.classList.toggle( 'cc-ui-v3-right-drawer-open', rightDrawerOpen );

		var drawerVisible = leftDrawerOpen || rightDrawerOpen;
		if ( backdrop ) {
			backdrop.classList.toggle( 'is-visible', drawerVisible );
			backdrop.tabIndex = drawerVisible ? 0 : -1;
		}

		syncButton( leftButton, buttonExpanded( 'left', currentMode ) );
		syncButton( rightButton, buttonExpanded( 'right', currentMode ) );
		syncPanelOwnership();
	}

	function handleDocumentClick( event ) {
		if ( ! app || ! event.target || ! event.target.closest ) return;
		var currentMode = mode();
		if ( currentMode === 'compact' && event.target.closest( '.cc-standalone-stage' ) ) closeDrawers( false );
		if ( rightDrawerOpen && event.target.closest( '.cc-standalone-structure-item' ) ) closeDrawers( false );
		if ( event.target.closest( '.cc-standalone-tabs' ) ) window.requestAnimationFrame( syncPanelOwnership );
	}

	function handleKeydown( event ) {
		if ( event.key !== 'Escape' || ( ! leftDrawerOpen && ! rightDrawerOpen ) ) return;
		event.preventDefault();
		closeDrawers( true );
	}

	function handleResize() {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( function () {
			leftDrawerOpen = false;
			rightDrawerOpen = false;
			sync();
		}, 80 );
	}

	function boot() {
		app = document.querySelector( '.cc-standalone-app' );
		if ( ! app ) {
			window.setTimeout( boot, 80 );
			return;
		}
		readState();
		ensureChrome();
		sync();
		document.addEventListener( 'click', handleDocumentClick, true );
		document.addEventListener( 'keydown', handleKeydown );
		window.addEventListener( 'resize', handleResize );
		if ( window.MutationObserver ) {
			new window.MutationObserver( function () {
				ensureChrome();
				sync();
			} ).observe( app, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', boot );
	else boot();
} )( window, document );
