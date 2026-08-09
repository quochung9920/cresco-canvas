import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: './tests/performance',
	globalSetup: './tests/e2e/global-setup.ts',
	fullyParallel: false,
	forbidOnly: Boolean( process.env.CI ),
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI
		? [ [ 'line' ], [ 'html', { open: 'never', outputFolder: 'playwright-report-performance' } ] ]
		: 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		trace: 'retain-on-failure',
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
