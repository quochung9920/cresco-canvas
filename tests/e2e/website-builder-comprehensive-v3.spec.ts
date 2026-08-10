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

async function openBuilder( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-builder-app' ) ).toBeVisible();
	await expect( page.locator( '.cc-v3-launch' ) ).toBeVisible();
}

test.describe.serial( 'Website Builder Comprehensive V3', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openBuilder( page );
	} );

	test( 'opens portable interchange and exports a page package', async ( { page } ) => {
		await page.locator( '.cc-v3-launch' ).click();
		await expect( page.locator( '.cc-v3-modal' ) ).toContainText( 'Import / Export' );
		await page.locator( '#cc-v3-scope' ).selectOption( 'page' );
		await page.getByRole( 'button', { name: 'Generate Export' } ).click();
		await expect( page.locator( '#cc-v3-export' ) ).toHaveValue( /cresco-interchange\/v1/ );
	} );

	test( 'provides pixel preview and accessibility scan tools', async ( { page } ) => {
		await page.locator( '.cc-v3-launch' ).click();
		await page.getByRole( 'button', { name: 'Builder' } ).click();
		await page.getByRole( 'button', { name: 'Pixel 100%' } ).click();
		await expect( page.locator( '.cc-builder-viewport-toolbar select[aria-label="Zoom"]' ) ).toHaveValue( /1|100/ );
		await page.locator( '.cc-v3-launch' ).click();
		await page.getByRole( 'button', { name: 'Builder' } ).click();
		await page.getByRole( 'button', { name: 'Scan Canvas Accessibility' } ).click();
		await expect( page.locator( '#cc-v3-a11y' ) ).not.toHaveText( /Live scoped Custom CSS is active/ );
	} );

	test( 'runs production diagnostics and exposes commercial readiness checks', async ( { page } ) => {
		await page.locator( '.cc-v3-launch' ).click();
		await page.getByRole( 'button', { name: 'Production' } ).click();
		await expect( page.locator( '.cc-v3-checklist' ) ).toContainText( 'Portable interchange' );
		await page.getByRole( 'button', { name: 'Run Diagnostics' } ).click();
		await expect( page.locator( '#cc-v3-prod-report' ) ).toContainText( /nodes|Document health/ );
	} );

	test( 'keeps import preview separate from apply', async ( { page } ) => {
		await page.locator( '.cc-v3-launch' ).click();
		await expect( page.getByRole( 'button', { name: 'Apply to Editor' } ) ).toBeDisabled();
		await expect( page.getByRole( 'button', { name: 'Preview Diff' } ) ).toBeEnabled();
	} );
} );
