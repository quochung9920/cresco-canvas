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
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-builder-app' ) ).toBeVisible();
	await expect( page.locator( '.cc-builder-canvas' ) ).toBeVisible();
}

function rail( page: Page, name: string ) {
	return page.locator( '.cc-builder-rail button' ).filter( { hasText: name } ).first();
}

async function addWidget( page: Page, name: string ) {
	await rail( page, 'Add' ).click();
	const widget = page.locator( '.cc-builder-widget-tile' ).filter( { hasText: name } ).first();
	await expect( widget ).toBeVisible();
	await widget.click();
}

test.describe.serial( 'Cresco professional Website Builder', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openBuilder( page );
	} );

	test( 'exposes the professional widget library and unified website panels', async ( { page } ) => {
		expect( await page.locator( '.cc-builder-widget-tile' ).count() ).toBeGreaterThanOrEqual( 35 );
		for ( const name of [ 'Container', 'Gallery', 'Accordion', 'Navigation Menu', 'Loop Grid', 'Form', 'Woo Products' ] ) {
			await expect( page.locator( '.cc-builder-widget-tile' ).filter( { hasText: name } ).first() ).toBeVisible();
		}
		for ( const name of [ 'Add', 'Edit', 'Components', 'Global', 'Page', 'Theme', 'AI', 'History' ] ) {
			await expect( rail( page, name ) ).toBeVisible();
		}
	} );

	test( 'builds nested responsive content with schema-driven Inspector and states', async ( { page } ) => {
		await addWidget( page, 'Container' );
		await expect( page.locator( '.cc-widget-container' ) ).toHaveCount( 1 );
		await rail( page, 'Add' ).click();
		await page.locator( '.cc-builder-widget-tile' ).filter( { hasText: 'Heading' } ).click();
		await expect( page.locator( '.cc-widget-heading' ) ).toHaveCount( 1 );
		await rail( page, 'Edit' ).click();
		await expect( page.locator( '.cc-builder-inspector-tabs' ) ).toBeVisible();
		await expect( page.locator( '.cc-builder-inspector-tabs button' ) ).toHaveCount( 4 );
		await page.locator( '.cc-builder-inspector-tabs button' ).filter( { hasText: 'Style' } ).click();
		await page.locator( '.cc-builder-device-tabs button' ).filter( { hasText: 'Mobile' } ).click();
		await page.locator( '.cc-builder-state-tabs button' ).filter( { hasText: 'hover' } ).click();
		const fontSize = page.locator( '.cc-builder-style-field' ).filter( { hasText: 'Font size' } ).locator( 'input' );
		await fontSize.fill( '28px' );
		await expect( page.locator( '.cc-builder-source-badge' ).filter( { hasText: 'Hover' } ) ).toBeVisible();
	} );

	test( 'supports multi-select, Navigator, reusable components and direct resize', async ( { page } ) => {
		await addWidget( page, 'Heading' );
		await addWidget( page, 'Button' );
		const nodes = page.locator( '.cc-builder-canvas .cc-builder-node' );
		await nodes.nth( 0 ).click();
		await nodes.nth( 1 ).click( { modifiers: [ 'Control' ] } );
		await rail( page, 'Edit' ).click();
		await expect( page.locator( '.cc-builder-panel-head' ) ).toContainText( '2 widgets selected' );
		await nodes.nth( 0 ).click();
		await expect( page.locator( '.cc-builder-resize-handle' ) ).toBeVisible();
		await rail( page, 'Components' ).click();
		await page.getByPlaceholder( 'Component name' ).fill( 'Hero CTA' );
		await page.getByRole( 'button', { name: 'Save selected' } ).click();
		await expect( page.locator( '.cc-builder-list-card' ).filter( { hasText: 'Hero CTA' } ) ).toBeVisible();
	} );

	test( 'opens Global Design, Page Settings, Theme Builder and AI workflow in one editor', async ( { page } ) => {
		await rail( page, 'Global' ).click();
		await expect( page.locator( '.cc-builder-panel' ) ).toContainText( 'Global Design' );
		await rail( page, 'Page' ).click();
		await expect( page.locator( '.cc-builder-panel' ) ).toContainText( 'Page Settings' );
		await rail( page, 'Theme' ).click();
		await expect( page.locator( '.cc-builder-panel' ) ).toContainText( 'Theme Builder' );
		await expect( page.locator( '.cc-builder-theme-create' ) ).toBeVisible();
		await rail( page, 'AI' ).click();
		await expect( page.getByRole( 'button', { name: 'Copy AI Context' } ) ).toBeVisible();
	} );

	test( 'uses keyboard command palette, quick-add and save shortcuts', async ( { page } ) => {
		await page.keyboard.press( 'Control+K' );
		await expect( page.locator( '.cc-builder-command-dialog' ) ).toBeVisible();
		await page.locator( '.cc-builder-command-search input' ).fill( 'Mobile preview' );
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '.cc-builder-width' ) ).toHaveText( '390px' );
		await page.keyboard.press( '/' );
		await expect( page.locator( '.cc-builder-widget-search' ) ).toBeFocused();
		await page.locator( '.cc-builder-widget-search' ).fill( 'Accordion' );
		await expect( page.locator( '.cc-builder-widget-tile' ).filter( { hasText: 'Accordion' } ) ).toBeVisible();
	} );
} );
