import AxeBuilder from '@axe-core/playwright';
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

async function openStandaloneEditor( page: Page, title: string ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: title } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app' ) ).toBeVisible();
}

async function openAiPanel( page: Page ) {
	await page
		.locator( '.cc-standalone-tabs button' )
		.filter( { hasText: 'AI' } )
		.first()
		.click();
	await expect( page.locator( '.cc-ai-panel' ) ).toBeVisible();
}

test( 'non-Cresco Pages do not receive Cresco frontend scope or assets', async ( {
	page,
} ) => {
	await page.goto( '/?pagename=cresco-e2e-plain' );
	await expect( page.locator( 'body' ) ).not.toHaveClass( /cresco-canvas-page/ );
	await expect( page.locator( '#cresco-canvas-frontend-css' ) ).toHaveCount( 0 );
	await expect( page.getByText( 'Plain Core content' ) ).toBeVisible();
} );

test( 'legacy Cresco block Pages retain their native fallback output', async ( {
	page,
} ) => {
	await page.goto( '/?pagename=cresco-e2e-canvas' );
	await expect( page.locator( 'body' ) ).toHaveClass( /cresco-canvas-page/ );
	await expect( page.locator( '#cresco-canvas-frontend-css' ) ).toHaveCount( 1 );
	await expect( page.getByText( 'Canvas fixture' ) ).toBeVisible();
	await expect( page.locator( '.cresco-session-root' ) ).toHaveCount( 0 );
} );

test( 'Page row action opens the standalone Cresco Editor instead of replacing Gutenberg', async ( {
	page,
} ) => {
	await login( page );
	await openStandaloneEditor( page, 'Cresco E2E Foundation Session' );
	await expect( page.locator( '#cresco-canvas-standalone-editor' ) ).toBeVisible();
	await expect( page.locator( '.cc-standalone-tabs button' ) ).toHaveCount( 4 );
	await expect( page.locator( '.cc-session-canvas' ) ).toBeVisible();
	await expect( page.locator( '.block-editor-page' ) ).toHaveCount( 0 );
} );

test( 'saved Cresco Session renders the frontend while preserving WordPress fallback content', async ( {
	page,
} ) => {
	await login( page );
	await openStandaloneEditor( page, 'Cresco E2E Foundation Session' );
	await openAiPanel( page );

	const session = {
		schema: 'cresco-session/v1',
		version: 1,
		documentId: 'foundation-e2e',
		nodes: [
			{
				id: 'foundation-shell',
				type: 'container',
				props: {
					layout: 'flex',
					direction: 'column',
					align: 'center',
					justify: 'center',
					columns: 2,
				},
				style: {
					paddingTop: '{spacing.xl}',
					paddingBottom: '{spacing.xl}',
					background: '{colors.background}',
				},
				responsive: {},
				customCSS: {},
				children: [
					{
						id: 'foundation-title',
						type: 'heading',
						props: { text: 'Cresco Session frontend verified', level: 1 },
						style: { color: '{colors.text}' },
						responsive: { mobile: { fontSize: '36px' } },
						customCSS: {
							base: '& { text-wrap: balance; }',
						},
						children: [],
					},
				],
			},
		],
	};

	await page.locator( '.cc-ai-card textarea' ).fill( JSON.stringify( session ) );
	await page.getByRole( 'button', { name: 'Validate import' } ).click();
	await expect( page.locator( '.cc-ai-import-summary' ) ).toBeVisible();
	await page.getByRole( 'button', { name: 'Apply to Cresco Editor' } ).click();
	await page.getByRole( 'button', { name: 'Update' } ).click();
	await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();

	const fallbackContent = await page.evaluate( async () => {
		const runtime = window as unknown as {
			wp: { apiFetch: ( options: { path: string } ) => Promise< any > };
			crescoCanvasStandaloneSettings: { postId: number };
		};
		const result = await runtime.wp.apiFetch( {
			path: `/wp/v2/pages/${ runtime.crescoCanvasStandaloneSettings.postId }?context=edit&_fields=content`,
		} );
		return result.content?.raw || '';
	} );
	expect( fallbackContent ).toContain( 'Foundation fallback content' );

	await page.goto( '/?pagename=cresco-e2e-foundation-session' );
	await expect( page.locator( 'body' ) ).toHaveClass( /cresco-canvas-page/ );
	await expect( page.locator( '#cresco-canvas-frontend-css' ) ).toHaveCount( 1 );
	await expect(
		page.locator( '.cresco-session-root[data-cresco-document="foundation-e2e"]' )
	).toBeVisible();
	await expect( page.getByText( 'Cresco Session frontend verified' ) ).toBeVisible();
	await expect( page.getByText( 'Foundation fallback content' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-cresco-id="foundation-title"]' ) ).toBeVisible();
} );

test( 'the retired custom Page REST collection remains unavailable', async ( {
	page,
} ) => {
	await login( page );
	const response = await page.request.get( '/wp-json/cresco-canvas/v1/pages' );
	expect( response.status() ).toBe( 404 );
	expect( await response.json() ).toMatchObject( { code: 'rest_no_route' } );
} );

test( 'standalone Cresco Editor has no serious automated accessibility violations', async ( {
	page,
} ) => {
	await login( page );
	await openStandaloneEditor( page, 'Cresco E2E Foundation Session' );
	await expect( page.locator( '#cresco-canvas-standalone-editor' ) ).toBeVisible();

	const results = await new AxeBuilder( { page } )
		.include( '#cresco-canvas-standalone-editor' )
		.analyze();
	const releaseBlocking = results.violations.filter( ( violation ) =>
		[ 'critical', 'serious' ].includes( violation.impact || '' )
	);
	expect( releaseBlocking ).toEqual( [] );
} );
