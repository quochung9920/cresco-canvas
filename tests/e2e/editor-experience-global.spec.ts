import { expect, test, type Page } from '@playwright/test';

async function loginAndOpen( page: Page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app' ) ).toBeVisible();
}

test.describe( 'Editor Experience Global Design and navigation sync', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAndOpen( page );
	} );

	test( 'keeps Canvas and Structure location feedback synchronized', async ( { page } ) => {
		await page
			.locator( '.cc-standalone-tabs button' )
			.filter( { hasText: 'Widgets' } )
			.click();
		await page
			.locator( '.cc-standalone-widget' )
			.filter( { hasText: 'Heading' } )
			.click();

		const heading = page.locator( '.cc-canvas-widget-heading' ).last();
		const id = await heading.getAttribute( 'data-cresco-id' );
		expect( id ).toBeTruthy();
		const item = page
			.locator( '.cc-standalone-structure-item' )
			.filter( { has: page.locator( 'small', { hasText: id || '' } ) } )
			.first();
		await expect( item ).toHaveClass( /cc-experience-canvas-selected/ );

		await heading.hover();
		await expect( item ).toHaveClass( /cc-experience-canvas-hover/ );
		await item.hover();
		await expect( heading ).toHaveClass( /cc-experience-structure-hover/ );
	} );

	test( 'turns Global Design into a searchable token workspace', async ( { page } ) => {
		await page
			.locator( '.cc-standalone-tabs button' )
			.filter( { hasText: 'Global' } )
			.click();

		const search = page.getByLabel( 'Search Global Design tokens' );
		await expect( search ).toBeVisible();
		await expect( page.locator( '[data-experience-token]' ).first() ).toBeVisible();
		await expect( page.locator( '.cc-experience-contrast' ) ).toBeVisible();

		await search.fill( 'primary' );
		await expect(
			page.locator( '[data-experience-token]:not(.cc-experience-filter-hidden)' )
		).toHaveCount( 1 );
	} );
} );
