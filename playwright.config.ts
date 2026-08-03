import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: './tests/e2e',
	globalSetup: './tests/e2e/global-setup.ts',
	fullyParallel: false,
	forbidOnly: Boolean( process.env.CI ),
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI
		? [ [ 'line' ], [ 'html', { open: 'never' } ] ]
		: 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		trace: 'retain-on-failure',
	},
	projects: [
		{ name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'firefox', use: { ...devices[ 'Desktop Firefox' ] } },
		{ name: 'webkit', use: { ...devices[ 'Desktop Safari' ] } },
	],
	webServer: process.env.CI
		? undefined
		: {
				command: 'npx wp-env start',
				url: 'http://localhost:8888/wp-login.php',
				reuseExistingServer: true,
				timeout: 180_000,
		  },
} );
