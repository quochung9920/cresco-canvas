import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
}

test( 'non-Canvas Pages do not receive Canvas frontend assets or scope', async ( {
	page,
} ) => {
	await page.goto( '/?pagename=cresco-e2e-plain' );
	await expect( page.locator( 'body' ) ).not.toHaveClass(
		/cresco-canvas-page/
	);
	await expect( page.locator( '#cresco-canvas-frontend-css' ) ).toHaveCount(
		0
	);
} );

test( 'legacy Canvas block Pages retain scoped frontend output', async ( {
	page,
} ) => {
	await page.goto( '/?pagename=cresco-e2e-canvas' );
	await expect( page.locator( 'body' ) ).toHaveClass( /cresco-canvas-page/ );
	await expect( page.locator( '#cresco-canvas-frontend-css' ) ).toHaveCount(
		1
	);
	await expect( page.getByText( 'Canvas fixture' ) ).toBeVisible();
} );

test( 'both editor actions, Canvas shell, and Safe Mode remain recoverable', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr', { hasText: 'Cresco E2E Canvas' } );
	await expect(
		row.getByRole( 'link', { name: 'Edit in Canvas' } )
	).toBeVisible();
	await expect(
		row.getByRole( 'link', { name: 'WordPress Editor' } )
	).toBeVisible();
	await row.getByRole( 'link', { name: 'Edit in Canvas' } ).click();
	await expect( page.locator( '.cc-app' ) ).toBeVisible();

	const safeModeUrl = await page.evaluate(
		() => window.crescoCanvasSettings.safeModeUrl
	);
	await page.goto( safeModeUrl );
	await expect(
		page.getByRole( 'heading', { name: 'Cresco Canvas Safe Mode' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'link', { name: 'Open WordPress Editor' } )
	).toBeVisible();
} );

test( 'Canvas foundation has no serious automated accessibility violations', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr', { hasText: 'Cresco E2E Canvas' } );
	await row.getByRole( 'link', { name: 'Edit in Canvas' } ).click();
	await expect( page.locator( '.cc-app' ) ).toBeVisible();

	const results = await new AxeBuilder( { page } )
		.include( '.cresco-canvas-scope' )
		.analyze();
	const releaseBlocking = results.violations.filter( ( violation ) =>
		[ 'critical', 'serious' ].includes( violation.impact || '' )
	);
	expect( releaseBlocking ).toEqual( [] );
} );
