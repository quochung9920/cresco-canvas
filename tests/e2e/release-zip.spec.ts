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

async function openEditor( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
}

test( 'exact release ZIP activates, edits, saves, reloads, and previews', async ( { page, context } ) => {
	test.skip( process.env.CRESCO_RELEASE_ZIP_SMOKE !== '1', 'Only runs against the isolated ZIP install environment.' );
	await login( page );
	await openEditor( page );

	const widgets = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Widgets' } ).first();
	await widgets.click();
	await page.locator( '.cc-standalone-widget' ).filter( { hasText: 'Heading' } ).click();
	await page.getByLabel( 'Text', { exact: true } ).fill( 'ZIP install smoke verified' );
	await page.getByRole( 'button', { name: 'Update' } ).click();
	await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();

	await page.reload();
	await expect( page.locator( '.cc-session-canvas' ) ).toContainText( 'ZIP install smoke verified' );
	const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
	expect( previewHref ).toBeTruthy();
	const preview = await context.newPage();
	await preview.goto( previewHref! );
	await expect( preview.locator( '.cresco-session-root' ) ).toBeVisible();
	await expect( preview.getByText( 'ZIP install smoke verified' ) ).toBeVisible();
	await preview.close();
} );
