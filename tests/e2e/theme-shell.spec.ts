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

async function openEditor( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
}

async function openView( center: Locator, name: string ) {
	const back = center.getByRole( 'button', { name: /Back to (Settings|Site Settings)/ } );
	if ( await back.isVisible().catch( () => false ) ) await back.click();
	await center.getByRole( 'button', { name } ).click();
}

test( 'current theme respects Cresco page shell, header/footer suppression, full width, and preview', async ( { page, context } ) => {
	await login( page );
	await openEditor( page );

	const widgets = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Widgets' } ).first();
	await widgets.click();
	await page.locator( '.cc-standalone-widget' ).filter( { hasText: 'Heading' } ).click();
	await page.getByLabel( 'Text', { exact: true } ).fill( 'Theme matrix preview' );
	await page.getByRole( 'button', { name: 'Update' } ).click();
	await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();

	const settings = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Settings' } ).first();
	await settings.click();
	const center = page.getByRole( 'region', { name: 'Settings Center' } );
	await openView( center, 'Layout' );
	await center.locator( '[name="layout"]' ).selectOption( 'full-width' );
	await openView( center, 'Page Header' );
	await center.locator( '[name="header"]' ).selectOption( 'hide' );
	await openView( center, 'Page Footer' );
	await center.locator( '[name="footer"]' ).selectOption( 'hide' );
	await center.getByRole( 'button', { name: 'Save Page Settings' } ).click();
	await expect( center.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );

	const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
	expect( previewHref ).toBeTruthy();
	const preview = await context.newPage();
	await preview.goto( previewHref! );
	await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-layout-full-width/ );
	await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-header-hide/ );
	await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-footer-hide/ );
	await expect( preview.locator( '.cresco-session-root' ) ).toBeVisible();
	await expect( preview.getByText( 'Theme matrix preview' ) ).toBeVisible();
	const themeChrome = preview.locator( 'header.wp-block-template-part, footer.wp-block-template-part, #masthead, #colophon, .site-header, .site-footer' );
	for ( let index = 0; index < await themeChrome.count(); index += 1 ) await expect( themeChrome.nth( index ) ).toBeHidden();
	await preview.close();
} );
