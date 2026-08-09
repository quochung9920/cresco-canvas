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

async function openStandaloneEditor( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
}

async function openPanel( page: Page, label: 'Widgets' | 'Edit' | 'Global' | 'AI' ) {
	const button = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: label } ).first();
	await button.click();
	await expect( button ).toHaveClass( /is-active/ );
}

test.describe( 'Cresco standalone UI v3 shell', () => {
	test( 'toggles desktop panels and exposes compact drawers', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		const app = page.locator( '.cc-standalone-app' );
		const tools = page.getByRole( 'button', { name: 'Toggle tools panel' } );
		const structure = page.getByRole( 'button', { name: 'Toggle structure panel' } );
		await expect( tools ).toBeVisible();
		await expect( structure ).toBeVisible();
		await expect( tools ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( structure ).toHaveAttribute( 'aria-expanded', 'true' );

		await tools.click();
		await expect( app ).toHaveClass( /cc-ui-v3-left-collapsed/ );
		await tools.click();
		await expect( app ).not.toHaveClass( /cc-ui-v3-left-collapsed/ );

		await page.setViewportSize( { width: 900, height: 900 } );
		await expect( app ).toHaveAttribute( 'data-cresco-ui-mode', 'compact' );
		await structure.click();
		await expect( app ).toHaveClass( /cc-ui-v3-right-drawer-open/ );
		await expect( page.locator( '.cc-ui-v3-backdrop' ) ).toHaveClass( /is-visible/ );

		await page.keyboard.press( 'Escape' );
		await expect( app ).not.toHaveClass( /cc-ui-v3-right-drawer-open/ );
		await expect( structure ).toBeFocused();
	} );

	test( 'keeps Global Design authoritative and isolated from Edit', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		const structureItem = page.locator( '.cc-standalone-structure-item' ).first();
		await expect( structureItem ).toBeVisible();
		await structureItem.click();
		await expect( page.locator( '.cc-inspector' ) ).toBeVisible();

		await openPanel( page, 'Global' );
		const globalPanel = page.locator( '.cc-global-panel' );
		await expect( globalPanel ).toBeVisible();
		await expect( globalPanel.locator( ':scope > .cc-global-simple-editor' ) ).toBeVisible();
		await expect( globalPanel ).toHaveClass( /cc-ui-v3-global-authoritative/ );
		await expect( globalPanel.locator( '.cc-inspector-v2-tabs' ) ).toHaveCount( 0 );
		await expect( globalPanel.locator( ':scope > .cc-global-simple-editor .cc-global-simple-header' ) ).toBeVisible();
		await expect( globalPanel.getByRole( 'button', { name: 'Copy Global Config' } ) ).toBeHidden();

		await openPanel( page, 'Edit' );
		const inspector = page.locator( '.cc-inspector' );
		await expect( inspector ).toBeVisible();
		await expect( inspector.locator( '.cc-global-simple-editor' ) ).toHaveCount( 0 );
		await expect( inspector.locator( '.cc-inspector-v2-tabs' ) ).toBeVisible();

		await openPanel( page, 'Global' );
		await expect( page.locator( '.cc-global-panel > .cc-global-simple-editor' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cc-global-panel .cc-inspector-v2-tabs' ) ).toHaveCount( 0 );
	} );
} );
