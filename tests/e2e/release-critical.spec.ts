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

test.describe.serial( 'commercial critical editor flow', () => {
	test( 'undo, redo, History, save/reload, responsive, Settings, and AI stay usable', async ( { page } ) => {
		await login( page );
		await openEditor( page );

		const widgets = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Widgets' } ).first();
		await widgets.click();
		await page.locator( '.cc-standalone-widget' ).filter( { hasText: 'Heading' } ).click();
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toHaveCount( 1 );

		const undo = page.getByRole( 'button', { name: 'Undo' } );
		const redo = page.getByRole( 'button', { name: 'Redo' } );
		await expect( undo ).toBeEnabled();
		await undo.click();
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toHaveCount( 0 );
		await expect( redo ).toBeEnabled();
		await redo.click();
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toHaveCount( 1 );

		await page.getByRole( 'button', { name: 'History' } ).click();
		const history = page.locator( '.cc-history-drawer' );
		await expect( history ).toBeVisible();
		await expect( history ).toContainText( /Add Heading|History/ );

		const edit = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Edit' } ).first();
		await edit.click();
		await page.getByLabel( 'Text', { exact: true } ).fill( 'Commercial gate heading' );
		await page.locator( '.cc-inspector-device-switcher button' ).filter( { hasText: 'Mobile' } ).click();
		await expect( page.locator( '.cc-standalone-width-label' ) ).toHaveText( '390px' );

		const settings = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Settings' } ).first();
		await settings.click();
		await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toBeVisible();

		const ai = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'AI' } ).first();
		await ai.click();
		await expect( page.locator( '.cc-ai-panel' ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Validate import' } ) ).toBeVisible();

		await edit.click();
		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		await page.reload();
		await expect( page.locator( '.cc-session-canvas' ) ).toContainText( 'Commercial gate heading' );

		await page.getByRole( 'button', { name: 'History' } ).click();
		await page.locator( '.cc-history-tabs button' ).filter( { hasText: 'Revisions' } ).click();
		await expect( page.locator( '.cc-history-drawer' ) ).toContainText( /Revisions|Current|Apply/ );
	} );
} );
