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
	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '#cresco-canvas-standalone-editor[data-cresco-architecture="v1"]' ) ).toBeVisible();
}

test.describe.serial( 'Cresco Core architecture UX', () => {
	test.beforeEach( async ( { page } ) => openBuilder( page ) );

	test( 'keeps stable shell zones and status breadcrumb', async ( { page } ) => {
		await expect( page.locator( '[data-cresco-zone="activity-rail"]' ) ).toBeVisible();
		await expect( page.locator( '[data-cresco-zone="canvas"]' ) ).toBeVisible();
		await expect( page.locator( '.cc-arch-statusbar' ) ).toBeVisible();
		await expect( page.locator( '[data-cresco-activity="navigator"]' ) ).toBeVisible();
	} );

	test( 'opens command palette and exposes scoped AI commands', async ( { page } ) => {
		await page.keyboard.press( process.platform === 'darwin' ? 'Meta+K' : 'Control+K' );
		await expect( page.locator( '.cc-arch-palette.is-open' ) ).toBeVisible();
		await page.locator( '.cc-arch-palette [data-query]' ).fill( 'AI' );
		await expect( page.locator( '.cc-arch-command-list' ) ).toContainText( 'Edit selected widget' );
		await expect( page.locator( '.cc-arch-command-list' ) ).toContainText( 'Redesign selected section' );
	} );

	test( 'opens scoped AI dialog from a selected widget', async ( { page } ) => {
		const node = page.locator( '.cc-builder-node[data-cresco-id]' ).first();
		await node.click();
		await page.keyboard.press( process.platform === 'darwin' ? 'Meta+K' : 'Control+K' );
		await page.locator( '.cc-arch-palette [data-query]' ).fill( 'AI: Edit selected widget' );
		await page.locator( '.cc-arch-command-list [data-command="ai.widget"]' ).click();
		await expect( page.locator( '.cc-arch-ai.is-open' ) ).toBeVisible();
		await expect( page.locator( '.cc-arch-ai [data-scope]' ) ).toHaveValue( 'widget' );
	} );

	test( 'exposes authoritative renderer preview command', async ( { page } ) => {
		await page.keyboard.press( process.platform === 'darwin' ? 'Meta+K' : 'Control+K' );
		await page.locator( '.cc-arch-palette [data-query]' ).fill( 'Renderer Preview' );
		await expect( page.locator( '.cc-arch-command-list' ) ).toContainText( 'Authoritative Renderer Preview' );
	} );
} );
