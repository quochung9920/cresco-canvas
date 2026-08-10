import { expect, test, type Page } from '@playwright/test';

async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
}

async function openBuilder( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-builder-app' ) ).toBeVisible();
}

test.describe( 'Website Builder V3 workflow extensions', () => {
	test.beforeEach( async ( { page } ) => { await login( page ); await openBuilder( page ); } );

	test( 'renders dependency mapping for portable packages with dependencies', async ( { page } ) => {
		await page.locator( '.cc-v3-launch' ).click();
		const pkg = { schema: 'cresco-interchange/v1', version: 1, content: { node: { id: 'image-1', type: 'image', props: { url: 'https://example.test/a.jpg', alt: '' }, style: { color: '{colors.primary}' }, responsive: {}, states: {}, customCSS: {}, meta: {}, children: [] } }, dependencies: { tokens: [ { path: 'colors.primary', fallback: '#000' } ], media: [ { nodeId: 'image-1', url: 'https://example.test/a.jpg', alt: '' } ] } };
		await page.locator( '#cc-v3-import' ).fill( JSON.stringify( pkg ) );
		await expect( page.locator( '.cc-v3-mapping-panel' ) ).toContainText( 'Global Design tokens' );
		await expect( page.locator( '[data-token-source="colors.primary"]' ) ).toBeVisible();
		await expect( page.locator( '[data-media-node="image-1"]' ) ).toBeVisible();
	} );

	test( 'exposes Woo Single Product template workflow only when WooCommerce is available', async ( { page } ) => {
		await page.locator( '.cc-v3-launch' ).click();
		await page.getByRole( 'button', { name: 'Builder' } ).click();
		const button = page.locator( '.cc-v3-woo-template' );
		await expect( button ).toBeVisible();
		const label = await button.textContent();
		expect( label || '' ).toMatch( /Single Product Template/ );
	} );
} );
