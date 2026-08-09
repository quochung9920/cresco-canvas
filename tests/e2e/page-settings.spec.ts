import { expect, test, type Locator, type Page } from '@playwright/test';

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

async function openSiteSettingsView( dialog: Locator, name: string ) {
	if ( await dialog.getByRole( 'button', { name: 'Back to Site Settings' } ).isVisible().catch( () => false ) ) {
		await dialog.getByRole( 'button', { name: 'Back to Site Settings' } ).click();
	}
	await dialog.getByRole( 'button', { name } ).click();
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
		await expect( dialog.getByRole( 'button', { name: 'Global Colors' } ) ).toBeVisible();
		await expect( dialog.getByRole( 'button', { name: 'Layout' } ) ).toBeVisible();

		await openSiteSettingsView( dialog, 'Layout' );
		const layout = dialog.locator( '[name="layout"]' );
		const title = dialog.locator( '[name="pageTitle"]' );
		const root = dialog.locator( '[name="contentRoot"]' );

		await expect( layout ).toHaveValue( 'full-width' );
		await expect( title ).toHaveValue( 'hide' );
		await expect( root ).toHaveValue( 'viewport' );
		await expect( root ).toBeDisabled();

		await layout.selectOption( 'theme-default' );
		await expect( root ).toBeEnabled();
		await root.selectOption( 'theme' );
		await title.selectOption( 'show' );

		await openSiteSettingsView( dialog, 'Page Header' );
		await dialog.locator( '[name="header"]' ).selectOption( 'hide' );
		await openSiteSettingsView( dialog, 'Page Footer' );
		await dialog.locator( '[name="footer"]' ).selectOption( 'hide' );
		await dialog.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( dialog.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );

		await openSiteSettingsView( dialog, 'Layout' );
		await layout.selectOption( 'full-width' );
		await title.selectOption( 'hide' );
		await expect( title ).toHaveValue( 'hide' );
		await openSiteSettingsView( dialog, 'Page Header' );
		await dialog.locator( '[name="header"]' ).selectOption( 'inherit' );
		await openSiteSettingsView( dialog, 'Page Footer' );
		await dialog.locator( '[name="footer"]' ).selectOption( 'inherit' );
		await dialog.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( dialog.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );
		await dialog.getByRole( 'button', { name: 'Close Page Settings' } ).click();

		const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
		expect( previewHref ).toBeTruthy();
		const preview = await context.newPage();
		await preview.goto( previewHref! );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-layout-full-width/ );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-root-viewport/ );
		await expect( preview.locator( '.cresco-session-root' ) ).toBeVisible();
		await preview.close();
	} );
} );
