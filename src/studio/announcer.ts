/**
 * Screen reader announcements for Studio's asynchronous work.
 *
 * Studio does mark its notice element `role="status"`, but it renders that
 * element conditionally: the node is created at the same moment its text
 * appears. A live region inserted together with its content is generally not
 * announced, because assistive technology watches established regions for
 * changes rather than re-scanning newly inserted subtrees. The practical result
 * is that saving, save failures, and recovery are silent.
 *
 * The fix is a region that exists from load and receives text afterwards. This
 * module mirrors Studio's own notice into such a region, so a screen reader user
 * hears exactly what a sighted user reads, with no editor internals touched.
 */

import { __ } from '@wordpress/i18n';

type Politeness = 'polite' | 'assertive';

const REGION_PREFIX = 'cresco-studio-announcer';
const NOTICE_SELECTOR = '.cc-studio-notice';

/**
 * Create, or reuse, a live region that is present before any message arrives.
 *
 * @param politeness Announcement urgency.
 */
const region = ( politeness: Politeness ): HTMLElement => {
	const id = `${ REGION_PREFIX }-${ politeness }`;
	const existing = document.getElementById( id );
	if ( existing ) {
		return existing;
	}

	const node = document.createElement( 'div' );
	node.id = id;
	node.className = 'cc-sr-only';
	node.setAttribute( 'aria-live', politeness );
	node.setAttribute( 'aria-atomic', 'true' );
	document.body.appendChild( node );
	return node;
};

let lastMessage = '';
let clearTimer = 0;

/**
 * Speak a message.
 *
 * @param message    Text to announce.
 * @param politeness `assertive` interrupts the user; reserve it for failures.
 */
export const announce = (
	message: string,
	politeness: Politeness = 'polite'
): void => {
	const text = message.trim();
	if ( ! text ) {
		return;
	}

	const node = region( politeness );

	// Assigning an identical string is not a content change, so the region stays
	// silent. Clearing first makes a repeated message announce again.
	if ( text === lastMessage ) {
		node.textContent = '';
	}
	lastMessage = text;

	window.setTimeout( () => {
		node.textContent = text;
	}, 50 );

	// Text left behind keeps describing a state that has already passed.
	window.clearTimeout( clearTimer );
	clearTimer = window.setTimeout( () => {
		node.textContent = '';
		lastMessage = '';
	}, 8000 );
};

/**
 * Read a notice element, ignoring the text of its dismiss button.
 *
 * @param element Notice node.
 */
const noticeText = ( element: Element ): string => {
	const clone = element.cloneNode( true ) as HTMLElement;
	clone.querySelectorAll( 'button' ).forEach( ( button ) => button.remove() );
	return ( clone.textContent || '' ).trim();
};

/**
 * Failures must interrupt, because the user's work is not persisted and they
 * would otherwise carry on believing it was.
 *
 * @param element Notice node.
 */
const politenessFor = ( element: Element ): Politeness =>
	element.classList.contains( 'is-error' ) ||
	element.classList.contains( 'is-warning' )
		? 'assertive'
		: 'polite';

/**
 * Mirror Studio notices and lifecycle events into the live regions.
 *
 * Returns a teardown function so the subscription can be released in tests.
 */
export const registerAnnouncer = (): ( () => void ) => {
	const root = document.getElementById( 'cresco-canvas-standalone-editor' );
	if ( ! root ) {
		return () => undefined;
	}

	const onReady = () => announce( __( 'Editor ready.', 'cresco-canvas' ) );
	window.addEventListener( 'cresco:studio-ready', onReady );

	let announced = '';
	const observer = new MutationObserver( () => {
		const notice = root.querySelector( NOTICE_SELECTOR );
		if ( ! notice ) {
			announced = '';
			return;
		}
		const text = noticeText( notice );
		if ( ! text || text === announced ) {
			return;
		}
		announced = text;
		announce( text, politenessFor( notice ) );
	} );

	observer.observe( root, {
		childList: true,
		subtree: true,
		characterData: true,
	} );

	return () => {
		observer.disconnect();
		window.removeEventListener( 'cresco:studio-ready', onReady );
	};
};
