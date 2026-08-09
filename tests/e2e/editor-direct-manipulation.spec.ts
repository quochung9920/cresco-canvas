import { expect, test, type Page } from '@playwright/test';

async function openEditor( page: Page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app' ) ).toBeVisible();
}

async function addHeading( page: Page ) {
	await page
		.locator( '.cc-standalone-tabs button' )
		.filter( { hasText: 'Widgets' } )
		.click();
	await page
		.locator( '.cc-standalone-widget' )
		.filter( { hasText: 'Heading' } )
		.click();
	await expect( page.locator( '.cc-canvas-widget-heading' ).last() ).toBeVisible();
}

test.describe.serial( 'Editor direct manipulation', () => {
	test.beforeEach( async ( { page } ) => {
		await openEditor( page );
	} );

	test( 'uses slash as a quick-add shortcut', async ( { page } ) => {
		await page.locator( '.cc-standalone-stage' ).click( { position: { x: 20, y: 20 } } );
		await page.keyboard.press( '/' );
		const search = page.locator(
			'.cc-standalone-left-content input[placeholder="Search widgets"]'
		);
		await expect( search ).toBeFocused();
		await search.fill( 'heading' );
		await expect(
			page.locator( '.cc-standalone-widget' ).filter( { hasText: 'Heading' } )
		).toBeVisible();
	} );

	test( 'opens Structure actions from the context menu', async ( { page } ) => {
		await addHeading( page );
		const heading = page.locator( '.cc-canvas-widget-heading' ).last();
		const id = await heading.getAttribute( 'data-cresco-id' );
		const item = page
			.locator( '.cc-standalone-structure-item' )
			.filter( { hasText: id || '' } )
			.first();
		await item.click( { button: 'right' } );
		const menu = page.locator( '.cc-experience-structure-menu' );
		await expect( menu ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Edit' } ) ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Duplicate' } ) ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Edit with AI' } ) ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Delete' } ) ).toBeVisible();
	} );

	test( 'resizes the selected widget through structured Inspector controls', async ( {
		page,
	} ) => {
		await addHeading( page );
		await page.locator( '.cc-inspector-v2-tab[data-tab="style"]' ).click();
		const handle = page.getByRole( 'button', { name: 'Resize selected widget' } );
		await expect( handle ).toBeVisible();

		const before = await page.getByLabel( 'Width', { exact: true } ).inputValue();
		const box = await handle.boundingBox();
		expect( box ).toBeTruthy();
		if ( ! box ) return;
		await page.mouse.move( box.x + box.width / 2, box.y + box.height / 2 );
		await page.mouse.down();
		await page.mouse.move( box.x + box.width / 2 + 60, box.y + box.height / 2 + 24 );
		await page.mouse.up();

		await expect( page.getByLabel( 'Width', { exact: true } ) ).toHaveValue( /px$/ );
		await expect( page.getByLabel( 'Minimum height', { exact: true } ) ).toHaveValue( /px$/ );
		expect( await page.getByLabel( 'Width', { exact: true } ).inputValue() ).not.toBe( before );
	} );
} );
