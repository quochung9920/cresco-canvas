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
	const row = page
		.locator( 'tr' )
		.filter( { hasText: 'Cresco E2E Session' } )
		.first();
	await expect( row ).toBeVisible();
	await row.hover();
	const link = row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } );
	await expect( link ).toBeVisible();
	await link.click();
	await expect( page.locator( '#cresco-canvas-standalone-editor' ) ).toBeVisible();
	await expect( page.locator( '.cc-standalone-app' ) ).toBeVisible();
	await expect( page.locator( '.cc-session-canvas' ) ).toBeVisible();
}

async function openPanel( page: Page, label: 'Widgets' | 'Edit' | 'Global' | 'AI' ) {
	const button = page
		.locator( '.cc-standalone-tabs button' )
		.filter( { hasText: label } )
		.first();
	await button.click();
	await expect( button ).toHaveClass( /is-active/ );
}

test.describe.serial( 'Cresco Session standalone editor', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openStandaloneEditor( page );
	} );

	test( 'mounts the polished standalone workspace and compact widget catalog', async ( {
		page,
	} ) => {
		await expect( page.locator( '.cc-standalone-left' ) ).toBeVisible();
		await expect( page.locator( '.cc-standalone-center' ) ).toBeVisible();
		await expect( page.locator( '.cc-standalone-right' ) ).toBeVisible();
		await expect( page.locator( '.cc-standalone-tabs button' ) ).toHaveCount( 4 );
		await expect( page.locator( '.cc-standalone-widget' ) ).toHaveCount( 9 );
		await expect(
			page.locator( '.cc-standalone-widget' ).filter( { hasText: 'Container' } )
		).toBeVisible();
		await expect(
			page.locator( '.cc-standalone-widget' ).filter( { hasText: 'Heading' } )
		).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'AI', exact: true } ).first() ).toBeVisible();
	} );

	test( 'edits one Cresco document across Canvas, Inspector, responsive state, and Structure', async ( {
		page,
	} ) => {
		await openPanel( page, 'Widgets' );
		await page
			.locator( '.cc-standalone-widget' )
			.filter( { hasText: 'Container' } )
			.click();
		await expect( page.locator( '.cc-canvas-widget-container' ) ).toHaveCount( 1 );

		await openPanel( page, 'Widgets' );
		await page
			.locator( '.cc-standalone-widget' )
			.filter( { hasText: 'Heading' } )
			.click();
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toHaveCount( 1 );
		await expect( page.locator( '.cc-inspector' ) ).toBeVisible();

		await page.getByLabel( 'Text', { exact: true } ).fill( 'Cresco Session heading' );
		await page.getByLabel( 'Font size' ).fill( '{typography.sizes.h1}' );
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toContainText(
			'Cresco Session heading'
		);

		await page
			.locator( '.cc-inspector-device-switcher button' )
			.filter( { hasText: 'Mobile' } )
			.click();
		await page.getByLabel( 'Font size' ).fill( '36px' );
		await expect( page.locator( '.cc-standalone-width-label' ) ).toHaveText( '390px' );

		await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( 2 );
		await expect( page.locator( '.cc-standalone-structure' ) ).toContainText( 'Container' );
		await expect( page.locator( '.cc-standalone-structure' ) ).toContainText( 'Heading' );
	} );

	test( 'validates and applies ChatGPT output directly to the current Cresco Session', async ( {
		page,
	} ) => {
		await openPanel( page, 'AI' );
		await expect( page.getByRole( 'button', { name: 'Copy AI Context' } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Copy Session' } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Copy Widgets' } ) ).toBeVisible();

		const importedSession = {
			schema: 'cresco-session/v1',
			version: 1,
			documentId: 'e2e-ai-session',
			nodes: [
				{
					id: 'hero',
					type: 'container',
					props: {
						layout: 'flex',
						direction: 'column',
						align: 'center',
						justify: 'center',
						columns: 2,
					},
					style: {
						paddingTop: '{spacing.2xl}',
						paddingBottom: '{spacing.2xl}',
						background: '{colors.background}',
					},
					responsive: {
						mobile: {
							paddingTop: '{spacing.xl}',
							paddingBottom: '{spacing.xl}',
						},
					},
					customCSS: {},
					children: [
						{
							id: 'hero-title',
							type: 'heading',
							props: { text: 'Imported by ChatGPT', level: 1 },
							style: {
								fontSize: '{typography.sizes.h1}',
								textAlign: 'center',
							},
							responsive: {},
							customCSS: {
								base: '& { text-wrap: balance; }',
							},
							children: [],
						},
					],
				},
			],
		};

		await page.locator( '.cc-ai-card textarea' ).fill(
			JSON.stringify( importedSession, null, 2 )
		);
		await page.getByRole( 'button', { name: 'Validate import' } ).click();
		await expect( page.locator( '.cc-ai-import-summary' ) ).toContainText(
			'Ready to apply'
		);
		await page.getByRole( 'button', { name: 'Apply to Cresco Editor' } ).click();
		await expect( page.locator( '.cc-session-canvas' ) ).toContainText(
			'Imported by ChatGPT'
		);
		await expect( page.getByRole( 'button', { name: 'Update' } ) ).toBeEnabled();

		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		await page.reload();
		await expect( page.locator( '.cc-session-canvas' ) ).toContainText(
			'Imported by ChatGPT'
		);
		await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( 2 );
	} );

	test( 'rejects AI Custom CSS that escapes the widget contract', async ( {
		page,
	} ) => {
		await openPanel( page, 'AI' );
		const invalidSession = {
			schema: 'cresco-session/v1',
			version: 1,
			documentId: 'unsafe',
			nodes: [
				{
					id: 'unsafe-heading',
					type: 'heading',
					props: { text: 'Unsafe', level: 2 },
					style: {},
					responsive: {},
					customCSS: { base: 'body { display: none; }' },
					children: [],
				},
			],
		};
		await page.locator( '.cc-ai-card textarea' ).fill(
			JSON.stringify( invalidSession, null, 2 )
		);
		await page.getByRole( 'button', { name: 'Validate import' } ).click();
		await expect( page.locator( '.components-notice' ) ).toContainText(
			'Every Widget Custom CSS selector must include &.'
		);
		await expect(
			page.getByRole( 'button', { name: 'Apply to Cresco Editor' } )
		).toHaveCount( 0 );
	} );
} );
