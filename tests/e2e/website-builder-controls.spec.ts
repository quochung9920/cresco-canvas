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
}

async function addWidget( page: Page, name: string ) {
	await page.locator( '.cc-builder-rail button' ).filter( { hasText: 'Add' } ).click();
	await page.locator( '.cc-builder-widget-tile' ).filter( { hasText: name } ).first().click();
	await expect( page.locator( '.cc-builder-inspector' ) ).toBeVisible();
}

test.describe.serial( 'Website Builder visual controls', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openBuilder( page );
	} );

	test( 'edits Form fields without exposing JSON as the primary UI', async ( { page } ) => {
		await addWidget( page, 'Form' );
		const visual = page.getByRole( 'button', { name: 'Edit visually' } );
		await expect( visual ).toBeVisible();
		await expect( page.locator( '.cc-builder-json-source' ) ).toBeHidden();
		await visual.click();
		await expect( page.locator( '.cc-builder-collection-dialog' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Add item' } ).click();
		await expect( page.locator( '.cc-builder-collection-item' ).last() ).toContainText( 'Form fields' );
		await expect( page.locator( '.cc-builder-collection-item select' ).last() ).toContainText( 'checkbox_group' );
		await page.getByRole( 'button', { name: 'Apply' } ).click();
		await expect( page.locator( '.cc-builder-collection-dialog' ) ).toBeHidden();
	} );

	test( 'provides visual color and linked spacing controls', async ( { page } ) => {
		await addWidget( page, 'Button' );
		await page.locator( '.cc-builder-inspector-tabs button' ).filter( { hasText: 'Style' } ).click();
		await expect( page.locator( '.cc-builder-color-picker' ).first() ).toBeVisible();
		await page.locator( '.cc-builder-inspector-tabs button' ).filter( { hasText: 'Advanced' } ).click();
		await expect( page.getByRole( 'button', { name: 'Link padding' } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Link margin' } ) ).toBeVisible();
	} );
} );
