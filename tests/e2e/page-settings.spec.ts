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

async function openSiteSettingsView( settingsCenter: Locator, name: string ) {
	const back = settingsCenter.getByRole( 'button', { name: /Back to (Settings|Site Settings)/ } );
	if ( await back.isVisible().catch( () => false ) ) await back.click();
	await settingsCenter.getByRole( 'button', { name } ).click();
}

test.describe.serial( 'Cresco Page Settings', () => {
	test( 'owns the WordPress page shell separately from the Cresco Session', async ( { page, context } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		await expect( page.locator( '.cc-page-settings-trigger' ) ).toBeHidden();
		const settingsTab = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Settings' } ).first();
		await settingsTab.click();
		await expect( settingsTab ).toHaveClass( /is-active/ );

		const settingsCenter = page.getByRole( 'region', { name: 'Settings Center' } );
		await expect( settingsCenter ).toBeVisible();
		await expect( settingsCenter.getByRole( 'button', { name: 'Global Colors' } ) ).toBeVisible();
		await expect( settingsCenter.getByRole( 'button', { name: 'Layout' } ) ).toBeVisible();

		await openSiteSettingsView( settingsCenter, 'Layout' );
		const layout = settingsCenter.locator( '[name="layout"]' );
		const title = settingsCenter.locator( '[name="pageTitle"]' );
		const root = settingsCenter.locator( '[name="contentRoot"]' );

		await expect( layout ).toHaveValue( 'full-width' );
		await expect( title ).toHaveValue( 'hide' );
		await expect( root ).toHaveValue( 'viewport' );
		await expect( root ).toBeDisabled();

		await layout.selectOption( 'theme-default' );
		await expect( root ).toBeEnabled();
		await root.selectOption( 'theme' );
		await title.selectOption( 'show' );

		await openSiteSettingsView( settingsCenter, 'Page Header' );
		await settingsCenter.locator( '[name="header"]' ).selectOption( 'hide' );
		await openSiteSettingsView( settingsCenter, 'Page Footer' );
		await settingsCenter.locator( '[name="footer"]' ).selectOption( 'hide' );
		await settingsCenter.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( settingsCenter.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );

		await openSiteSettingsView( settingsCenter, 'Layout' );
		await layout.selectOption( 'full-width' );
		await title.selectOption( 'hide' );
		await expect( title ).toHaveValue( 'hide' );
		await openSiteSettingsView( settingsCenter, 'Page Header' );
		await settingsCenter.locator( '[name="header"]' ).selectOption( 'inherit' );
		await openSiteSettingsView( settingsCenter, 'Page Footer' );
		await settingsCenter.locator( '[name="footer"]' ).selectOption( 'inherit' );
		await settingsCenter.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( settingsCenter.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );

		const widgetsTab = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Widgets' } ).first();
		await widgetsTab.click();
		await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toHaveCount( 0 );

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
