import { expect, test } from '@playwright/test';

async function login( page: import( '@playwright/test' ).Page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

async function openCanvasFixture( page: import( '@playwright/test' ).Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Canvas' } ).first();
	await expect( row ).toBeVisible();
	const editUrl = await row.locator( 'a.row-title' ).getAttribute( 'href' );
	expect( editUrl ).toBeTruthy();
	await page.goto( editUrl as string );
	await expect( page.locator( '#cresco-canvas-app-shell' ) ).toBeVisible();
}

test.describe( 'Cresco unified editor', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openCanvasFixture( page );
	} );

	test( 'selects a widget and opens the Edit view', async ( { page } ) => {
		const result = await page.evaluate( () => {
			const runtime = window as unknown as {
				wp: any;
				CrescoCanvas: any;
			};
			const first = runtime.wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ];
			runtime.wp.data.dispatch( 'core/block-editor' ).selectBlock( first.clientId );
			return {
				clientId: first.clientId,
				foundation: runtime.CrescoCanvas?.version,
			};
		} );

		expect( result.clientId ).toBeTruthy();
		expect( result.foundation ).toContain( 'foundation' );
		await expect( page.locator( '.cc-app-shell__tabs button' ).filter( { hasText: 'Edit' } ) ).toHaveAttribute( 'aria-current', 'page' );
		await expect( page.locator( '.cc-persistent-inspector__header' ) ).toBeVisible();
		await expect( page.locator( '.cc-persistent-inspector__title strong' ) ).not.toBeEmpty();
	} );

	test( 'inserts through the shared controller and keeps Structure synchronized', async ( { page } ) => {
		const result = await page.evaluate( () => {
			const runtime = window as unknown as {
				wp: any;
				CrescoCanvas: any;
			};
			const selector = runtime.wp.data.select( 'core/block-editor' );
			const before = selector.getBlocks().length;
			const inserted = runtime.CrescoCanvas.dragDrop.insertElement( 'heading' );
			const selected = selector.getSelectedBlock();
			return {
				after: selector.getBlocks().length,
				before,
				ok: inserted.ok,
				selectedName: selected?.name,
			};
		} );

		expect( result.ok ).toBe( true );
		expect( result.after ).toBe( result.before + 1 );
		expect( result.selectedName ).toBe( 'core/heading' );
		await expect( page.locator( '.cc-structure-navigator' ) ).toBeVisible();
		await expect( page.locator( '.cc-structure-navigator__footer' ) ).toContainText( 'widget' );
	} );

	test( 'switches to native Gutenberg controls without losing the editor', async ( { page } ) => {
		const switcher = page.locator( '.cc-app-shell__footer button' );
		await expect( switcher ).toContainText( 'Cresco canvas' );
		await switcher.click();
		await expect( page.locator( 'body' ) ).toHaveClass( /cresco-visual-canvas-native/ );
		await expect( switcher ).toContainText( 'Native controls' );
		await expect( page.locator( '.interface-interface-skeleton__content' ) ).toBeVisible();
	} );
} );
