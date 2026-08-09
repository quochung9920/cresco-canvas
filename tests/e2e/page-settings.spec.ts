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

test.describe.serial( 'Cresco Page Settings', () => {
	test( 'owns the WordPress page shell separately from the Cresco Session', async ( { page, context } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		const trigger = page.getByRole( 'button', { name: 'Page Settings' } );
		await expect( trigger ).toBeVisible();
		await trigger.click();

		const dialog = page.getByRole( 'dialog', { name: 'Page Settings' } );
		await expect( dialog ).toBeVisible();
		const layout = dialog.locator( '[name="layout"]' );
		const title = dialog.locator( '[name="pageTitle"]' );
		const header = dialog.locator( '[name="header"]' );
		const footer = dialog.locator( '[name="footer"]' );
		const root = dialog.locator( '[name="contentRoot"]' );

		await expect( layout ).toHaveValue( 'full-width' );
		await expect( title ).toHaveValue( 'hide' );
		await expect( root ).toHaveValue( 'viewport' );
		await expect( root ).toBeDisabled();

		await layout.selectOption( 'theme-default' );
		await expect( root ).toBeEnabled();
		await root.selectOption( 'theme' );
		await title.selectOption( 'show' );
		await header.selectOption( 'hide' );
		await footer.selectOption( 'hide' );
		await dialog.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( dialog.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );

		await layout.selectOption( 'full-width' );
		await title.selectOption( 'hide' );
		await header.selectOption( 'inherit' );
		await footer.selectOption( 'inherit' );
		await dialog.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( dialog.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );
		await dialog.getByRole( 'button', { name: 'Close Page Settings' } ).click();

		const previewHref = await page.getByRole( 'link', { name: 'Preview' }).getAttribute( 'href' );
		expect( previewHref ).toBeTruthy();
		const preview = await context.newPage();
		await preview.goto( previewHref! );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-layout-full-width/ );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-root-viewport/ );
		await expect( preview.locator( '.cresco-session-root' ) ).toBeVisible();
		await preview.close();
	} );
} );
