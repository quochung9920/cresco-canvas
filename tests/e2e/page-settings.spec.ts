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

test.describe.serial( 'Cresco Studio Page Settings', () => {
	test( 'persists the WordPress page shell separately from the Session', async ( { page, context } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStudio( page );

		await page.locator( '.cc-studio-rail button[title="Page"]' ).click();
		const panel = page.locator( '.cc-studio-left' );
		await expect( panel ).toContainText( 'Page Settings 2.0' );
		const layout = panel.locator( '.cc-studio-field' ).filter( { hasText: /^Layout/ } ).locator( 'select' );
		const title = panel.locator( '.cc-studio-field' ).filter( { hasText: /^Page title/ } ).locator( 'select' );
		const header = panel.locator( '.cc-studio-field' ).filter( { hasText: /^Header/ } ).locator( 'select' );
		const footer = panel.locator( '.cc-studio-field' ).filter( { hasText: /^Footer/ } ).locator( 'select' );

		await layout.selectOption( 'full-width' );
		await title.selectOption( 'hide' );
		await header.selectOption( 'hide' );
		await footer.selectOption( 'hide' );
		await page.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( page.locator( '.cc-studio-notice.is-success' ) ).toContainText( 'Page Settings saved' );

		await page.reload();
		await expect( page.locator( '.cc-studio-app' ) ).toBeVisible();
		await page.locator( '.cc-studio-rail button[title="Page"]' ).click();
		await expect( page.locator( '.cc-studio-field' ).filter( { hasText: /^Layout/ } ).locator( 'select' ) ).toHaveValue( 'full-width' );
		await expect( page.locator( '.cc-studio-field' ).filter( { hasText: /^Page title/ } ).locator( 'select' ) ).toHaveValue( 'hide' );
		await expect( page.locator( '.cc-studio-field' ).filter( { hasText: /^Header/ } ).locator( 'select' ) ).toHaveValue( 'hide' );
		await expect( page.locator( '.cc-studio-field' ).filter( { hasText: /^Footer/ } ).locator( 'select' ) ).toHaveValue( 'hide' );

		const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
		expect( previewHref ).toBeTruthy();
		const preview = await context.newPage();
		await preview.goto( previewHref! );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-layout-full-width/ );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-header-hidden/ );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-footer-hidden/ );
		await preview.close();
	} );
} );
