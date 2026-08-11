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
}

test( 'current theme respects Studio page shell, header/footer suppression, full width, and preview', async ( { page, context } ) => {
	await login( page );
	await openStudio( page );

	await page.locator( '.cc-studio-rail button[title="Add"]' ).click();
	await page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: 'Heading' } ).first().click();
	await page.locator( '.cc-studio-rail button[title="Edit"]' ).click();
	const text = page.locator( '.cc-studio-field' ).filter( { hasText: /^Text/ } ).locator( 'textarea,input' ).first();
	await text.fill( 'Theme matrix preview' );
	await page.getByRole( 'button', { name: 'Update' } ).click();
	await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();

	await page.locator( '.cc-studio-rail button[title="Page"]' ).click();
	const panel = page.locator( '.cc-studio-left' );
	await expect( panel ).toContainText( 'Page Settings 2.0' );
	await panel.locator( '.cc-studio-field' ).filter( { hasText: /^Layout/ } ).locator( 'select' ).selectOption( 'full-width' );
	await panel.locator( '.cc-studio-field' ).filter( { hasText: /^Header/ } ).locator( 'select' ).selectOption( 'hide' );
	await panel.locator( '.cc-studio-field' ).filter( { hasText: /^Footer/ } ).locator( 'select' ).selectOption( 'hide' );
	await page.getByRole( 'button', { name: 'Save Page Settings' } ).click();
	await expect( page.locator( '.cc-studio-notice.is-success' ) ).toContainText( 'Page Settings saved' );

	const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
	expect( previewHref ).toBeTruthy();
	const preview = await context.newPage();
	await preview.goto( previewHref! );
	await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-layout-full-width/ );
	await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-header-hidden/ );
	await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-footer-hidden/ );
	await expect( preview.locator( '.cresco-session-root' ) ).toBeVisible();
	await expect( preview.getByText( 'Theme matrix preview' ) ).toBeVisible();
	const themeChrome = preview.locator( 'header.wp-block-template-part, footer.wp-block-template-part, #masthead, #colophon, .site-header, .site-footer' );
	for ( let index = 0; index < await themeChrome.count(); index += 1 ) await expect( themeChrome.nth( index ) ).toBeHidden();
	await preview.close();
} );
