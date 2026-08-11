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
	await expect( page.locator( '#cresco-canvas-standalone-editor' ) ).toBeVisible();
	await expect( page.locator( '.cc-studio-app' ) ).toBeVisible();
	await expect( page.locator( '.cc-studio-canvas' ) ).toBeVisible();
}

async function tool( page: Page, title: string ) {
	const button = page.locator( `.cc-studio-rail button[title="${ title }"]` );
	await expect( button ).toBeVisible();
	await button.click();
}

async function add( page: Page, name: string ) {
	await tool( page, 'Add' );
	const button = page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: name } ).first();
	await expect( button ).toBeVisible();
	await button.click();
}

test.describe.serial( 'Cresco Studio editor shell', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openStudio( page );
	} );

	test( 'mounts Navigator, Canvas, Inspector tools and the canonical runtime', async ( { page } ) => {
		await expect( page.locator( '.cc-studio-rail' ) ).toBeVisible();
		await expect( page.locator( '.cc-studio-left' ) ).toBeVisible();
		await expect( page.locator( '.cc-studio-center' ) ).toBeVisible();
		await expect( page.locator( '.cc-studio-structure' ) ).toBeVisible();
		await tool( page, 'Add' );
		await expect( page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: 'Container' } ).first() ).toBeVisible();
		await expect( page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: 'Heading' } ).first() ).toBeVisible();
		const owner = await page.evaluate( () => ( window as typeof window & { crescoCanonicalRuntimeOwner?: Record<string, unknown> } ).crescoCanonicalRuntimeOwner );
		expect( owner?.expectedRuntime ).toBe( 'studio' );
	} );

	test( 'edits one Session across Canvas, Inspector, responsive state, and Structure', async ( { page } ) => {
		await add( page, 'Container' );
		await add( page, 'Heading' );
		await expect( page.locator( '.cc-studio-tree-row[data-cresco-node-id]' ) ).toHaveCount( 2 );
		await expect( page.locator( '.cc-studio-structure' ) ).toContainText( 'Container' );
		await expect( page.locator( '.cc-studio-structure' ) ).toContainText( 'Heading' );

		await tool( page, 'Edit' );
		const text = page.locator( '.cc-studio-field' ).filter( { hasText: /^Text/ } ).locator( 'textarea,input' ).first();
		await text.fill( 'Cresco Studio heading' );
		await expect( page.locator( '.cc-studio-canvas' ) ).toContainText( 'Cresco Studio heading' );

		await page.getByRole( 'button', { name: 'mobile' } ).last().click();
		await expect( page.locator( '.cc-studio-width' ) ).toHaveText( '390px' );
		await page.locator( '.cc-studio-inspector-tabs button' ).filter( { hasText: 'style' } ).click();
		const fontSize = page.locator( '.cc-studio-style-field' ).filter( { hasText: 'fontSize' } ).locator( 'input' ).last();
		await fontSize.fill( '36px' );
		await expect( page.getByRole( 'button', { name: 'Update' } ) ).toBeEnabled();
	} );

	test( 'keeps node management in Structure instead of Inspector', async ( { page } ) => {
		await add( page, 'Heading' );
		await tool( page, 'Edit' );
		await expect( page.locator( '.cc-studio-meta-grid' ) ).toBeHidden();
		await expect( page.locator( '.cc-studio-tree-actions' ) ).toHaveCount( 1 );
		const row = page.locator( '.cc-studio-tree-row[data-cresco-node-id]' ).first();
		await row.hover();
		await expect( row.getByRole( 'button', { name: 'Rename' } ) ).toBeVisible();
		await expect( row.getByRole( 'button', { name: /Hide|Show/ } ) ).toBeVisible();
		await expect( row.getByRole( 'button', { name: /Lock|Unlock/ } ) ).toBeVisible();
	} );

	test( 'validates and applies a complete AI Session without direct persistence', async ( { page } ) => {
		await tool( page, 'AI' );
		const imported = {
			schema: 'cresco-session/v1',
			version: 1,
			documentId: 'e2e-ai-session',
			nodes: [ {
				id: 'hero-title',
				type: 'heading',
				props: { text: 'Imported by ChatGPT', level: 1 },
				style: { textAlign: 'center' },
				responsive: {},
				states: {},
				customCSS: {},
				meta: { label: '', componentId: 0, locked: false, hidden: false },
				children: [],
			} ],
		};
		await page.locator( '.cc-studio-left textarea' ).fill( JSON.stringify( imported, null, 2 ) );
		await page.getByRole( 'button', { name: 'Validate & Preview' } ).click();
		await expect( page.locator( '.cc-studio-ai-preview' ) ).toContainText( 'Validated changes' );
		await page.getByRole( 'button', { name: 'Apply to editor' } ).click();
		await expect( page.locator( '.cc-studio-canvas' ) ).toContainText( 'Imported by ChatGPT' );
		await expect( page.getByRole( 'button', { name: 'Update' } ) ).toBeEnabled();
	} );
} );
