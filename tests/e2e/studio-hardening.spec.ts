import { expect, test, type Page } from '@playwright/test';

async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

async function openStudio( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-studio-app' ) ).toBeVisible();
	await expect( page.locator( '.cc-studio-structure' ) ).toBeVisible();
}

async function addWidget( page: Page, label: string ) {
	await page.locator( '.cc-studio-rail button[title="Add"]' ).click();
	const widget = page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: label } ).first();
	await expect( widget ).toBeVisible();
	await widget.click();
}

test.describe.serial( 'Studio hardening', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openStudio( page );
	} );

	test( 'boots the direct Studio runtime with consistency protection', async ( { page } ) => {
		const state = await page.evaluate( () => ( {
			owner: ( window as typeof window & { crescoCanonicalRuntimeOwner?: Record<string, unknown> } ).crescoCanonicalRuntimeOwner,
			consistency: ( window as typeof window & { crescoStudioConsistencyGuard?: Record<string, unknown> } ).crescoStudioConsistencyGuard,
		} ) );
		expect( state.owner?.expectedRuntime ).toBe( 'studio' );
		expect( state.owner?.runtimeTransport ).toBe( 'direct-content-addressed-asset' );
		expect( state.owner?.legacyWatchdog ).toBe( false );
		expect( state.consistency?.installed ).toBe( true );
		expect( String( state.consistency?.checksum || '' ) ).not.toBe( '' );
	} );

	test( 'Structure rows expose one canonical native move path', async ( { page } ) => {
		await addWidget( page, 'Heading' );
		await addWidget( page, 'Text' );
		const rows = page.locator( '.cc-studio-tree-row[data-cresco-node-id]' );
		await expect( rows ).toHaveCount( 2 );
		await expect( rows.nth( 0 ) ).toHaveAttribute( 'draggable', 'true' );
		await expect( rows.nth( 1 ) ).toHaveAttribute( 'draggable', 'true' );
		const firstId = await rows.nth( 0 ).getAttribute( 'data-cresco-node-id' );
		const secondId = await rows.nth( 1 ).getAttribute( 'data-cresco-node-id' );
		expect( firstId ).toBeTruthy();
		expect( secondId ).toBeTruthy();
		expect( firstId ).not.toBe( secondId );
	} );

	test( 'keeps newer local edits when a save response arrives late', async ( { page } ) => {
		await addWidget( page, 'Heading' );
		let delayed = false;
		await page.route( '**/wp-json/cresco-canvas/v1/website-builder/session/*', async ( route ) => {
			if ( route.request().method() !== 'POST' || delayed ) return route.continue();
			delayed = true;
			await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
			await route.continue();
		} );
		await page.getByRole( 'button', { name: 'Update' } ).click();
		await addWidget( page, 'Text' );
		await expect( page.locator( '.cc-studio-notice.is-error' ) ).toContainText( 'Newer edits were kept locally' );
		await expect( page.getByRole( 'button', { name: 'Update' } ) ).toBeEnabled();
		await expect( page.locator( '.cc-studio-tree-row[data-cresco-node-id]' ) ).toHaveCount( 2 );
	} );

	test( 'rejects stale saves from a second Studio tab', async ( { page, context } ) => {
		const second = await context.newPage();
		await openStudio( second );
		await addWidget( page, 'Heading' );
		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		await addWidget( second, 'Text' );
		await second.getByRole( 'button', { name: 'Update' } ).click();
		await expect( second.locator( '.cc-studio-notice.is-error' ) ).toContainText( /changed|newer version|conflict/i );
		await expect( second.getByRole( 'button', { name: 'Update' } ) ).toBeEnabled();
		await second.close();
	} );
} );
