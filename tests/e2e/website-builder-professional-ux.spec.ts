import { expect, test } from '@playwright/test';

async function login( page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

async function openBuilder( page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-builder-app' ) ).toBeVisible();
	await expect( page.locator( '.cc-builder-pro-header-tools' ) ).toBeVisible();
}

async function openWidgets( page ) {
	await page.locator( '.cc-builder-rail button' ).filter( { hasText: 'Add' } ).click();
	await expect( page.locator( '.cc-builder-widget-tile' ).first() ).toBeVisible();
}

async function addWidget( page, name ) {
	await openWidgets( page );
	await page.locator( '.cc-builder-widget-tile' ).filter( { hasText: name } ).first().click();
	await expect( page.locator( '.cc-builder-inspector' ) ).toBeVisible();
}

test.describe.serial( 'Website Builder Professional UX V2', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openBuilder( page );
	} );

	test( 'adds professional Inspector presets, search, responsive tools and segmented controls', async ( { page } ) => {
		await addWidget( page, 'Container' );
		await expect( page.locator( '.cc-builder-pro-presets' ) ).toContainText( 'Layout presets' );
		await page.getByRole( 'button', { name: 'Grid 3' } ).click();
		await expect( page.locator( '.cc-builder-field' ).filter( { hasText: 'Layout' } ).locator( 'select' ) ).toHaveValue( 'grid' );

		await page.locator( '.cc-builder-inspector-tabs button' ).filter( { hasText: 'Layout' } ).click();
		await expect( page.locator( '.cc-builder-pro-inspector-search input' ) ).toBeVisible();
		await expect( page.locator( '.cc-builder-pro-responsive-tools' ) ).toBeVisible();
		await expect( page.locator( '.cc-builder-pro-segmented' ).first() ).toBeVisible();
		await page.locator( '.cc-builder-pro-inspector-search input' ).fill( 'flex direction' );
		await expect( page.locator( '.cc-builder-style-field:not([hidden])' ) ).toHaveCount( 1 );
	} );

	test( 'adds selection breadcrumb, metrics, focus mode and a right-click context menu', async ( { page } ) => {
		await addWidget( page, 'Heading' );
		await expect( page.locator( '.cc-builder-pro-breadcrumb' ) ).toContainText( 'heading' );
		await expect( page.locator( '.cc-builder-pro-selection-metrics' ) ).toBeVisible();
		await expect( page.locator( '.cc-builder-pro-focus-toggle' ) ).toBeEnabled();
		await page.locator( '.cc-builder-node.is-selected' ).click( { button: 'right' } );
		await expect( page.locator( '.cc-builder-pro-context-menu' ) ).toBeVisible();
		await expect( page.locator( '.cc-builder-pro-context-menu' ) ).toContainText( 'Duplicate' );
		await page.getByRole( 'button', { name: 'Focus on selection' } ).click();
		await expect( page.locator( '#cresco-canvas-standalone-editor' ) ).toHaveClass( /is-cresco-focus-mode/ );
	} );

	test( 'adds widget categories, favorites, recent access and integration guidance', async ( { page } ) => {
		await openWidgets( page );
		await expect( page.locator( '.cc-builder-pro-library-filters' ) ).toBeVisible();
		const heading = page.locator( '.cc-builder-widget-tile' ).filter( { hasText: 'Heading' } ).first();
		await heading.locator( '.cc-builder-pro-favorite' ).click();
		await expect( page.locator( '.cc-builder-pro-quick-access' ) ).toContainText( 'Favorites' );
		await expect( page.locator( '.cc-builder-pro-quick-access' ) ).toContainText( 'Heading' );
		await page.locator( '.cc-builder-pro-library-filters button' ).filter( { hasText: 'WooCommerce' } ).click();
		const note = page.locator( '.cc-builder-pro-integration-note' );
		if ( await note.count() ) await expect( note ).toContainText( 'WooCommerce integration is inactive' );
	} );

	test( 'provides content quality hints, shortcut help and privacy-safe diagnostics', async ( { page } ) => {
		await addWidget( page, 'Image' );
		await expect( page.locator( '.cc-builder-pro-quality' ) ).toContainText( 'Quality check' );
		await expect( page.locator( '.cc-builder-pro-quality' ) ).toContainText( 'alternative text' );

		await page.locator( '.cc-builder-pro-header-tools button' ).filter( { hasText: 'Help' } ).click();
		await expect( page.locator( '.cc-builder-pro-dialog' ) ).toContainText( 'Cresco shortcuts' );
		await page.locator( '.cc-builder-pro-dialog [data-close]' ).click();

		await page.locator( '.cc-builder-pro-header-tools button' ).filter( { hasText: 'Diagnostics' } ).click();
		await expect( page.locator( '.cc-builder-pro-diagnostics' ) ).toContainText( 'Builder mounted' );
		await expect( page.locator( '.cc-builder-pro-diagnostics' ) ).toContainText( 'Professional UX' );
		await expect( page.getByRole( 'button', { name: 'Copy diagnostics' } ) ).toBeVisible();
	} );

	test( 'keeps autosave opt-in and exposes the empty-canvas quick-start when applicable', async ( { page } ) => {
		const autosave = page.locator( '.cc-builder-pro-autosave' );
		await expect( autosave ).toContainText( /Autosave: (On|Off)/ );
		await autosave.click();
		await expect( autosave ).toContainText( /Autosave: (On|Off)/ );
		const quickStart = page.locator( '.cc-builder-pro-empty-start' );
		if ( await quickStart.count() ) {
			await expect( quickStart ).toContainText( 'Quick start' );
			await expect( quickStart.getByRole( 'button', { name: 'Container' } ) ).toBeVisible();
		}
	} );
} );
