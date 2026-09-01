import { defineConfig } from '@playwright/test';

const port = process.env.WP_ENV_PORT ?? '8888';

export default defineConfig( {
	forbidOnly: Boolean( process.env.CI ),
	fullyParallel: false,
	projects: [
		{
			name: 'chromium',
			use: {
				browserName: 'chromium',
			},
		},
	],
	reporter: process.env.CI ? 'github' : 'list',
	retries: process.env.CI ? 1 : 0,
	testDir: './tests/e2e',
	timeout: 60000,
	use: {
		baseURL: `http://localhost:${ port }`,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	workers: 1,
} );
