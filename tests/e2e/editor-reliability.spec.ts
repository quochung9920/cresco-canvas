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
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
}

async function openPanel( page: Page, label: 'Widgets' | 'Edit' | 'Settings' | 'AI' ) {
	const button = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: label } ).first();
	await button.click();
	await expect( button ).toHaveClass( /is-active/ );
}

async function importSession( page: Page ) {
	const session = {
		schema: 'cresco-session/v1',
		version: 1,
		documentId: 'editor-reliability',
		nodes: [
			{
				id: 'reliability-container',
				type: 'container',
				props: {
					contentWidth: 'boxed',
					layout: 'block',
					direction: 'column',
					align: 'stretch',
					justify: 'flex-start',
					columns: 2,
				},
				style: { paddingTop: '24px' },
				responsive: { tablet: { paddingTop: '16px' } },
				customCSS: {},
				children: [
					{
						id: 'reliability-heading',
						type: 'heading',
						props: { text: 'Reliable heading', level: 2 },
						style: { fontSize: '56px' },
						responsive: { mobile: { fontSize: '36px' } },
						customCSS: { base: '&{letter-spacing:-0.02em;}' },
						children: [],
					},
				],
			},
		],
	};

	await openPanel( page, 'AI' );
	await page.locator( '.cc-ai-card textarea' ).fill( JSON.stringify( session ) );
	await page.getByRole( 'button', { name: 'Validate import' } ).click();
	await expect( page.locator( '.cc-ai-import-summary' ) ).toContainText( 'Ready to apply' );
	await page.getByRole( 'button', { name: 'Apply to Cresco Editor' } ).click();
	await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( 2 );
}

test.describe.serial( 'Cresco editor reliability', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openStandaloneEditor( page );
	} );

	test( 'duplicates a complete subtree, undo/redos it, saves, and reloads without data loss', async ( { page } ) => {
		test.slow();
		await importSession( page );

		await page.locator( '.cc-standalone-structure-item' ).filter( { hasText: 'reliability-container' } ).click();
		await expect( page.locator( '.cc-inspector' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Duplicate' } ).click();
		await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( 4 );
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toHaveCount( 2 );
		await expect( page.locator( '.cc-canvas-widget-heading' ).nth( 1 ) ).toContainText( 'Reliable heading' );

		await page.getByRole( 'button', { name: 'Undo' } ).click();
		await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( 2 );
		await page.getByRole( 'button', { name: 'Redo' } ).click();
		await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( 4 );

		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		await page.reload();
		await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
		await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( 4 );
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toHaveCount( 2 );
	} );

	test( 'restores a saved Session revision through History', async ( { page } ) => {
		test.slow();
		await importSession( page );
		await page.locator( '.cc-standalone-structure-item' ).filter( { hasText: 'reliability-heading' } ).click();
		const text = page.getByLabel( 'Text', { exact: true } );
		await text.fill( 'History version A' );
		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		await text.fill( 'History version B' );
		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		await expect( page.locator( '.cc-session-canvas' ) ).toContainText( 'History version B' );

		await page.getByRole( 'button', { name: 'History', exact: true } ).click();
		await expect( page.locator( '.cc-history-drawer' ) ).toHaveClass( /is-open/ );
		await page.locator( '.cc-history-tabs' ).getByRole( 'button', { name: 'Revisions' } ).click();
		await expect( page.locator( '.cc-history-revision' ).nth( 1 ) ).toBeVisible( { timeout: 10_000 } );
		await page.locator( '.cc-history-revision' ).nth( 1 ).click();
		await page.locator( '.cc-history-revision-toolbar' ).getByRole( 'button', { name: 'Apply' } ).click();
		await page.waitForLoadState( 'domcontentloaded' );
		await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
		await expect( page.locator( '.cc-session-canvas' ) ).toContainText( 'History version A' );
	} );

} );
