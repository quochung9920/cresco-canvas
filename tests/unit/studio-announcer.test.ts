/**
 * @jest-environment jsdom
 */
import { announce, registerAnnouncer } from '../../src/studio/announcer';

const politeRegion = () =>
	document.getElementById( 'cresco-studio-announcer-polite' );
const assertiveRegion = () =>
	document.getElementById( 'cresco-studio-announcer-assertive' );

describe( 'announce', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'creates a live region that exists before the text arrives', () => {
		announce( 'Saved.' );

		const node = politeRegion();
		expect( node ).not.toBeNull();
		expect( node?.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( node?.getAttribute( 'aria-atomic' ) ).toBe( 'true' );
		// Text lands after the region is established, which is what makes it
		// announce at all.
		expect( node?.textContent ).toBe( '' );

		jest.advanceTimersByTime( 60 );
		expect( node?.textContent ).toBe( 'Saved.' );
	} );

	it( 'reuses one region rather than stacking nodes', () => {
		announce( 'One.' );
		jest.advanceTimersByTime( 60 );
		announce( 'Two.' );
		jest.advanceTimersByTime( 60 );

		expect(
			document.querySelectorAll( '#cresco-studio-announcer-polite' )
		).toHaveLength( 1 );
		expect( politeRegion()?.textContent ).toBe( 'Two.' );
	} );

	it( 'keeps assertive messages in a separate region', () => {
		announce( 'Save failed.', 'assertive' );
		jest.advanceTimersByTime( 60 );

		expect( assertiveRegion()?.getAttribute( 'aria-live' ) ).toBe(
			'assertive'
		);
		expect( assertiveRegion()?.textContent ).toBe( 'Save failed.' );
	} );

	it( 'clears stale text so the region stops describing a passed state', () => {
		announce( 'Saved.' );
		jest.advanceTimersByTime( 60 );
		expect( politeRegion()?.textContent ).toBe( 'Saved.' );

		jest.advanceTimersByTime( 8000 );
		expect( politeRegion()?.textContent ).toBe( '' );
	} );

	it( 'ignores empty and whitespace-only messages', () => {
		announce( '' );
		announce( '   ' );
		jest.advanceTimersByTime( 60 );
		expect( politeRegion() ).toBeNull();
	} );
} );

describe( 'registerAnnouncer', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'does nothing when the editor root is absent', () => {
		const teardown = registerAnnouncer();
		expect( politeRegion() ).toBeNull();
		expect( typeof teardown ).toBe( 'function' );
		teardown();
	} );

	it( 'mirrors a notice that appears after load', async () => {
		const root = document.createElement( 'div' );
		root.id = 'cresco-canvas-standalone-editor';
		document.body.appendChild( root );

		const teardown = registerAnnouncer();

		const notice = document.createElement( 'div' );
		notice.className = 'cc-studio-notice is-success';
		notice.textContent = 'Website document saved.';
		root.appendChild( notice );

		// MutationObserver callbacks are delivered as microtasks.
		await Promise.resolve();
		jest.advanceTimersByTime( 60 );

		expect( politeRegion()?.textContent ).toBe( 'Website document saved.' );
		teardown();
	} );

	it( 'treats an error notice as assertive', async () => {
		const root = document.createElement( 'div' );
		root.id = 'cresco-canvas-standalone-editor';
		document.body.appendChild( root );

		const teardown = registerAnnouncer();

		const notice = document.createElement( 'div' );
		notice.className = 'cc-studio-notice is-error';
		notice.textContent = 'Save failed.';
		root.appendChild( notice );

		await Promise.resolve();
		jest.advanceTimersByTime( 60 );

		expect( assertiveRegion()?.textContent ).toBe( 'Save failed.' );
		teardown();
	} );

	it( 'excludes the dismiss button label from what it speaks', async () => {
		const root = document.createElement( 'div' );
		root.id = 'cresco-canvas-standalone-editor';
		document.body.appendChild( root );

		const teardown = registerAnnouncer();

		const notice = document.createElement( 'div' );
		notice.className = 'cc-studio-notice is-info';
		notice.innerHTML = 'Recovered draft.<button>Dismiss</button>';
		root.appendChild( notice );

		await Promise.resolve();
		jest.advanceTimersByTime( 60 );

		expect( politeRegion()?.textContent ).toBe( 'Recovered draft.' );
		teardown();
	} );
} );
