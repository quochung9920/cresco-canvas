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

test.describe.serial( 'Cresco Page Settings', () => {
	test( 'supports shell, body style, page custom CSS, and scroll snap independently from Session', async ( { page, context } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await openStandaloneEditor( page );

		const trigger = page.getByRole( 'button', { name: 'Page Settings' } );
		await expect( trigger ).toBeVisible();
		await trigger.click();

		const dialog = page.getByRole( 'dialog', { name: 'Page Settings' } );
		await expect( dialog ).toBeVisible();
		await expect( dialog.getByRole( 'tab', { name: 'Settings' } ) ).toHaveAttribute( 'aria-selected', 'true' );

		const layout = dialog.locator( '[name="layout"]' );
		const title = dialog.locator( '[name="pageTitle"]' );
		const header = dialog.locator( '[name="header"]' );
		const footer = dialog.locator( '[name="footer"]' );
		const root = dialog.locator( '[name="contentRoot"]' );
		await layout.selectOption( 'full-width' );
		await title.selectOption( 'hide' );
		await header.selectOption( 'inherit' );
		await footer.selectOption( 'inherit' );
		await expect( root ).toHaveValue( 'viewport' );
		await expect( root ).toBeDisabled();

		await dialog.getByRole( 'tab', { name: 'Style' } ).click();
		await expect( dialog.getByRole( 'button', { name: 'Body Style' } ) ).toHaveAttribute( 'aria-expanded', 'true' );
		const marginTop = dialog.locator( '[name="margin-desktop-top"]' );
		const marginRight = dialog.locator( '[name="margin-desktop-right"]' );
		await marginTop.fill( '12' );
		await expect( marginRight ).toHaveValue( '12' );
		await dialog.locator( '[data-spacing-link="margin"]' ).click();
		await dialog.locator( '[name="margin-desktop-bottom"]' ).fill( '24' );
		await dialog.locator( '[name="padding-desktop-top"]' ).fill( '20' );
		await expect( dialog.locator( '[name="padding-desktop-left"]' ) ).toHaveValue( '20' );
		await dialog.getByRole( 'button', { name: 'Classic' } ).click();
		await dialog.locator( '[name="backgroundColor"]' ).fill( '#f3f4f6' );

		await dialog.getByRole( 'tab', { name: 'Advanced' } ).click();
		await dialog.locator( '[name="customCSS"]' ).fill( 'selector { border-top: 3px solid #123456; }' );
		const snapToggle = dialog.getByRole( 'button', { name: 'Scroll Snap' } );
		await snapToggle.click();
		await dialog.locator( '[name="scrollSnapEnabled"]' ).check( { force: true } );
		await dialog.locator( '[name="scrollSnapStrictness"]' ).selectOption( 'mandatory' );
		await dialog.locator( '[name="scrollSnapStop"]' ).selectOption( 'always' );
		await dialog.locator( '[name="scrollSnapOffset"]' ).fill( '16' );

		await dialog.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( dialog.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );

		const canvas = page.locator( '.cc-session-canvas' );
		await expect( canvas ).toHaveCSS( 'padding-top', '20px' );
		await expect( canvas ).toHaveCSS( 'background-color', 'rgb(243, 244, 246)' );
		await expect( canvas ).toHaveCSS( 'border-top-width', '3px' );

		const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
		expect( previewHref ).toBeTruthy();
		const preview = await context.newPage();
		await preview.goto( previewHref! );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-layout-full-width/ );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-root-viewport/ );
		await expect( preview.locator( 'body' ) ).toHaveClass( /cresco-page-scroll-snap/ );
		const sessionRoot = preview.locator( '.cresco-session-root' );
		await expect( sessionRoot ).toBeVisible();
		await expect( sessionRoot ).toHaveCSS( 'padding-top', '20px' );
		await expect( sessionRoot ).toHaveCSS( 'background-color', 'rgb(243, 244, 246)' );
		await expect( sessionRoot ).toHaveCSS( 'border-top-width', '3px' );
		const snapType = await preview.evaluate( () => getComputedStyle( document.documentElement ).scrollSnapType );
		expect( snapType ).toContain( 'mandatory' );
		await expect( sessionRoot.locator( ':scope > .cresco-session-node' ).first() ).toHaveCSS( 'scroll-snap-stop', 'always' );
		await preview.close();

		// Restore neutral style values so this serial fixture remains reusable.
		await dialog.getByRole( 'tab', { name: 'Style' } ).click();
		for ( const side of [ 'top', 'right', 'bottom', 'left' ] ) {
			await dialog.locator( `[name="margin-desktop-${ side }"]` ).fill( '' );
			await dialog.locator( `[name="padding-desktop-${ side }"]` ).fill( '' );
		}
		await dialog.locator( '[name="backgroundColor"]' ).fill( '' );
		await dialog.getByRole( 'tab', { name: 'Advanced' } ).click();
		await dialog.locator( '[name="customCSS"]' ).fill( '' );
		await dialog.locator( '[name="scrollSnapEnabled"]' ).uncheck( { force: true } );
		await dialog.getByRole( 'button', { name: 'Save Page Settings' } ).click();
		await expect( dialog.locator( '.cc-page-settings-status' ) ).toContainText( 'Page Settings saved' );
	} );
} );
