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
	const row = page
		.locator( 'tr' )
		.filter( { hasText: 'Cresco E2E Session' } )
		.first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app' ) ).toBeVisible();
	await expect( page.locator( '.cc-experience-command-trigger' ) ).toBeVisible();
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
	await expect( page.locator( '.cc-inspector' ) ).toBeVisible();
}

test.describe.serial( 'Editor Experience v2', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openStandaloneEditor( page );
	} );

	test( 'opens the command palette and runs responsive commands', async ( {
		page,
	} ) => {
		await page.keyboard.press( 'Control+K' );
		const palette = page.locator( '.cc-experience-palette' );
		await expect( palette ).toBeVisible();
		await palette.getByLabel( 'Search commands' ).fill( 'Mobile preview' );
		await palette
			.locator( '.cc-experience-palette__item' )
			.filter( { hasText: 'Mobile preview' } )
			.click();
		await expect( page.locator( '.cc-standalone-width-label' ) ).toHaveText( '390px' );
	} );

	test( 'adds Inspector workflow tools without changing control labels', async ( {
		page,
	} ) => {
		await addHeading( page );
		await expect( page.locator( '.cc-experience-inspector-tools' ) ).toBeVisible();
		await expect( page.getByLabel( 'Filter Inspector controls' ) ).toBeVisible();
		await expect( page.getByTitle( 'Copy styles' ) ).toBeVisible();
		await expect( page.getByTitle( 'Paste styles' ) ).toBeVisible();

		await page
			.locator( '.cc-inspector-v2-tab[data-tab="style"]' )
			.click();
		await expect( page.getByLabel( 'Font size', { exact: true } ) ).toBeVisible();
		await expect(
			page.locator( '.cc-experience-source-badge' ).first()
		).toHaveAttribute( 'aria-hidden', 'true' );

		await page.getByLabel( 'Filter Inspector controls' ).fill( 'font size' );
		await expect( page.getByLabel( 'Font size', { exact: true } ) ).toBeVisible();
		await expect( page.getByLabel( 'Box shadow', { exact: true } ) ).toBeHidden();
	} );

	test( 'searches Structure and exposes canvas context actions', async ( { page } ) => {
		await addHeading( page );
		const search = page.getByLabel( 'Search Structure' );
		await expect( search ).toBeVisible();
		await search.fill( 'heading' );
		await expect(
			page.locator( '.cc-standalone-structure-item:not(.cc-experience-filter-hidden)' )
		).toHaveCount( 1 );

		const heading = page.locator( '.cc-canvas-widget-heading' ).last();
		await heading.click( { button: 'right' } );
		const menu = page.locator( '.cc-experience-context-menu' );
		await expect( menu ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Edit' } ) ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Duplicate' } ) ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Copy styles' } ) ).toBeVisible();
		await expect( menu.getByRole( 'button', { name: 'Edit with AI' } ) ).toBeVisible();
	} );

	test( 'supports keyboard focus mode and keyboard-selectable canvas nodes', async ( {
		page,
	} ) => {
		await addHeading( page );
		const heading = page.locator( '.cc-canvas-widget-heading' ).last();
		await expect( heading ).toHaveAttribute( 'tabindex', '0' );
		await heading.focus();
		await page.keyboard.press( 'Enter' );
		await expect( heading ).toHaveClass( /is-selected/ );

		await page.keyboard.press( 'Shift+F' );
		await expect( page.locator( '.cc-standalone-app' ) ).toHaveClass(
			/cc-experience-focus-mode/
		);
		await page.keyboard.press( 'Shift+F' );
		await expect( page.locator( '.cc-standalone-app' ) ).not.toHaveClass(
			/cc-experience-focus-mode/
		);
	} );
} );
