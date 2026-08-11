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
	await expect( page.locator( '.cc-studio-app' ) ).toBeVisible();
}

async function openTool( page: Page, title: string ) {
	const button = page.locator( `.cc-studio-rail button[title="${ title }"]` );
	await expect( button ).toBeVisible();
	await button.click();
}

test.describe.serial( 'commercial critical Studio flow', () => {
	test( 'undo, redo, History, save/reload, responsive, Page Settings, and AI stay usable', async ( { page } ) => {
		await login( page );
		await openEditor( page );

		await openTool( page, 'Add' );
		await page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: 'Heading' } ).first().click();
		await expect( page.locator( '.cc-studio-canvas-node' ).filter( { hasText: 'Heading' } ).first() ).toBeVisible();

		const undo = page.getByRole( 'button', { name: 'Undo' } );
		const redo = page.getByRole( 'button', { name: 'Redo' } );
		await expect( undo ).toBeEnabled();
		await undo.click();
		await expect( page.locator( '.cc-studio-tree-row[data-cresco-node-id]' ) ).toHaveCount( 0 );
		await expect( redo ).toBeEnabled();
		await redo.click();
		await expect( page.locator( '.cc-studio-tree-row[data-cresco-node-id]' ) ).toHaveCount( 1 );

		await openTool( page, 'Edit' );
		const text = page.locator( '.cc-studio-field' ).filter( { hasText: /^Text/ } ).locator( 'textarea,input' ).first();
		await expect( text ).toBeVisible();
		await text.fill( 'Commercial gate heading' );
		await expect( page.locator( '.cc-studio-canvas' ) ).toContainText( 'Commercial gate heading' );

		await page.getByRole( 'button', { name: 'mobile' } ).last().click();
		await expect( page.locator( '.cc-studio-width' ) ).toHaveText( '390px' );

		await openTool( page, 'Page' );
		await expect( page.locator( '.cc-studio-left' ) ).toContainText( 'Page Settings 2.0' );
		await expect( page.getByRole( 'button', { name: 'Save Page Settings' } ) ).toBeVisible();

		await openTool( page, 'AI' );
		await expect( page.locator( '.cc-studio-left' ) ).toContainText( 'AI Studio' );
		await expect( page.getByRole( 'button', { name: 'Validate & Preview' } ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		await page.reload();
		await expect( page.locator( '.cc-studio-app' ) ).toBeVisible();
		await expect( page.locator( '.cc-studio-canvas' ) ).toContainText( 'Commercial gate heading' );

		await openTool( page, 'History' );
		await expect( page.locator( '.cc-studio-left' ) ).toContainText( 'History & Recovery' );
		await page.getByRole( 'button', { name: 'Load revisions' } ).click();
		await expect( page.locator( '.cc-studio-left' ) ).toContainText( /Current version|Saved revision|History & Recovery/ );
	} );
} );
