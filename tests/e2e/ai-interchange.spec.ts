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
	await expect( page.locator( '.cc-standalone-app' ) ).toBeVisible();
}

test.describe( 'Cresco AI Interchange v1', () => {
	test( 'exports a selected subtree, reviews a checksum-free patch, applies it, and Undo restores the prior Session', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		const firstStructure = page.locator( '.cc-standalone-structure-item' ).first();
		await expect( firstStructure ).toBeVisible();
		const nodeId = ( await firstStructure.locator( 'small' ).textContent() || '' ).trim();
		expect( nodeId ).not.toBe( '' );
		await firstStructure.click();

		const aiTab = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'AI' } ).first();
		await aiTab.click();
		await expect( page.getByText( 'Export for AI', { exact: true } ) ).toBeVisible();

		const contextResponsePromise = page.waitForResponse( ( response ) =>
			response.request().method() === 'POST' && /\/cresco-canvas\/v1\/ai-interchange\/\d+\/context/.test( response.url() )
		);
		await page.getByRole( 'button', { name: 'Export Selected Subtree' } ).click();
		const contextResponse = await contextResponsePromise;
		expect( contextResponse.ok() ).toBeTruthy();
		const context = await contextResponse.json();
		expect( context.schema ).toBe( 'cresco-ai-context/v1' );
		expect( context.scope ).toBe( 'subtree' );
		expect( context.target.nodeId ).toBe( nodeId );
		expect( context.baseChecksum ).toBeUndefined();

		const patch = {
			schema: 'cresco-patch/v1',
			target: { scope: 'subtree', nodeId },
			operations: [ { op: 'setStyle', nodeId, style: { opacity: '0.77' } } ],
		};
		const textarea = page.locator( '.cc-ai-bridge-textarea' );
		await textarea.fill( JSON.stringify( patch, null, 2 ) );
		await expect( page.locator( '[data-ai-detected="true"]' ) ).toContainText( 'cresco-patch/v1' );
		await page.getByRole( 'button', { name: 'Validate', exact: true } ).click();
		await expect( page.getByText( 'Review Changes', { exact: true } ) ).toBeVisible();
		await expect( page.locator( '.cc-ai-bridge-diff' ) ).toContainText( 'style.opacity' );

		const canvasNode = page.locator( `[data-cresco-id="${ nodeId }"]` ).first();
		const beforeOpacity = await canvasNode.evaluate( ( node: HTMLElement ) => node.style.opacity );
		await page.getByRole( 'button', { name: 'Apply', exact: true } ).click();
		await expect.poll( () => canvasNode.evaluate( ( node: HTMLElement ) => node.style.opacity ) ).toBe( '0.77' );

		await page.locator( '.cc-standalone-header-actions button' ).filter( { hasText: 'Undo' } ).first().click();
		await expect.poll( () => canvasNode.evaluate( ( node: HTMLElement ) => node.style.opacity ) ).toBe( beforeOpacity );
	} );
} );
