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

async function expectSettingsOverlayAligned( page: Page ) {
	const panelBox = await page.locator( '.cc-global-panel.cc-settings-center-host' ).boundingBox();
	const overlayBox = await page.locator( '.cc-page-settings-overlay.cc-settings-center-inline' ).boundingBox();
	expect( panelBox ).not.toBeNull();
	expect( overlayBox ).not.toBeNull();
	if ( ! panelBox || ! overlayBox ) return;
	expect( Math.abs( panelBox.x - overlayBox.x ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( panelBox.y - overlayBox.y ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( panelBox.width - overlayBox.width ) ).toBeLessThanOrEqual( 2 );
	expect( Math.abs( panelBox.height - overlayBox.height ) ).toBeLessThanOrEqual( 2 );
}

test.describe( 'Cresco standalone UI v3 shell', () => {
	test( 'toggles desktop panels and exposes compact drawers', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		const app = page.locator( '.cc-standalone-app' );
		const tools = page.getByRole( 'button', { name: 'Toggle tools panel' } );
		const structure = page.getByRole( 'button', { name: 'Toggle structure panel' } );
		await expect( tools ).toBeVisible();
		await expect( structure ).toBeVisible();
		await expect( tools ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( structure ).toHaveAttribute( 'aria-expanded', 'true' );

		await tools.click();
		await expect( app ).toHaveClass( /cc-ui-v3-left-collapsed/ );
		await tools.click();
		await expect( app ).not.toHaveClass( /cc-ui-v3-left-collapsed/ );

		await page.setViewportSize( { width: 900, height: 900 } );
		await expect( app ).toHaveAttribute( 'data-cresco-ui-mode', 'compact' );
		await structure.click();
		await expect( app ).toHaveClass( /cc-ui-v3-right-drawer-open/ );
		await expect( page.locator( '.cc-ui-v3-backdrop' ) ).toHaveClass( /is-visible/ );

		await page.keyboard.press( 'Escape' );
		await expect( app ).not.toHaveClass( /cc-ui-v3-right-drawer-open/ );
		await expect( structure ).toBeFocused();
	} );

	test( 'uses one non-reparented Settings Center and keeps Edit isolated', async ( { page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		const tabs = page.locator( '.cc-standalone-tabs button' );
		await expect( tabs.filter( { hasText: 'Settings' } ) ).toHaveCount( 1 );
		await expect( tabs.filter( { hasText: 'Global' } ) ).toHaveCount( 0 );
		await expect( tabs.filter( { hasText: 'Settings' } ) ).toHaveAttribute( 'data-cresco-settings-tab', 'true' );
		await expect( page.locator( '.cc-page-settings-trigger' ) ).toBeHidden();

		const structureItem = page.locator( '.cc-standalone-structure-item' ).first();
		await expect( structureItem ).toBeVisible();
		await structureItem.click();
		await expect( page.locator( '.cc-inspector' ) ).toBeVisible();

		await openPanel( page, 'Settings' );
		const settingsPanel = page.locator( '.cc-global-panel.cc-settings-center-host' );
		await expect( settingsPanel ).toBeVisible();
		const settingsCenter = page.getByRole( 'region', { name: 'Settings Center' } );
		await expect( settingsCenter ).toBeVisible();
		await expect( settingsCenter.getByRole( 'button', { name: 'Global Colors' } ) ).toBeVisible();
		await expect( settingsCenter.getByRole( 'button', { name: 'Layout' } ) ).toBeVisible();
		await expect( page.locator( '.cc-page-settings-overlay' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cc-page-settings-dialog' ) ).toHaveCount( 1 );
		expect(
			await page.locator( '.cc-page-settings-overlay' ).evaluate( ( node ) =>
				node.parentElement?.classList.contains( 'cc-standalone-app' ) || false
			)
		).toBe( true );
		await expectSettingsOverlayAligned( page );

		await page.keyboard.press( 'Escape' );
		await expect( settingsCenter ).toBeVisible();

		await openPanel( page, 'Edit' );
		await expect( tabs.filter( { hasText: 'Edit' } ).first() ).toBeFocused();
		const inspector = page.locator( '.cc-inspector' );
		await expect( inspector ).toBeVisible();
		await expect( inspector.locator( '.cc-global-simple-editor' ) ).toHaveCount( 0 );
		await expect( inspector.locator( '.cc-inspector-v2-tabs' ) ).toBeVisible();
		await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toHaveCount( 0 );
		await expect( page.locator( 'body' ) ).not.toHaveClass( /cc-page-settings-open/ );
	} );

	test( 'keeps Settings responsive across repeated tab switches without DOM growth', async ( { page } ) => {
		test.slow();
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		for ( let index = 0; index < 30; index += 1 ) {
			await openPanel( page, 'Settings' );
			await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toBeVisible();
			await expect( page.locator( '.cc-page-settings-overlay' ) ).toHaveCount( 1 );
			await expect( page.locator( '.cc-page-settings-dialog' ) ).toHaveCount( 1 );
			await expect( page.locator( '.cc-ui-v3-panel-controls' ) ).toHaveCount( 1 );
			await openPanel( page, index % 3 === 0 ? 'AI' : index % 2 === 0 ? 'Edit' : 'Widgets' );
			await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toHaveCount( 0 );
		}

		await openPanel( page, 'Settings' );
		await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toBeVisible();
		await expect( page.locator( '.cc-page-settings-overlay' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cc-page-settings-dialog' ) ).toHaveCount( 1 );
		await expect( page.locator( '#cresco-ui-v3-panel-ownership' ) ).toHaveCount( 1 );
		await expectSettingsOverlayAligned( page );
		await expect( page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Edit' } ) ).toBeEnabled();
		await expect( page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Widgets' } ) ).toBeEnabled();
	} );
} );
