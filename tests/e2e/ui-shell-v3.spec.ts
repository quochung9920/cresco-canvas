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
} );
