import { defineConfig, devices } from '@playwright/test';
import { randomBytes } from 'node:crypto';
import path from 'node:path';

const run =
    (process.env.P01_E2E_RUN ??= `${Date.now()}-${randomBytes(4).toString('hex')}`);
const fixtures = (process.env.P01_E2E_FIXTURES ??= path.resolve(
    `.p01-work/e2e-${run}.json`,
));

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8001',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php tests/e2e/serve.php',
        url: 'http://127.0.0.1:8001/up',
        reuseExistingServer: false,
        env: {
            APP_ENV: 'e2e',
            APP_DEBUG: 'false',
            APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
            APP_URL: 'http://127.0.0.1:8001',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: path.resolve(`.p01-work/e2e-${run}.sqlite`),
            DB_URL: '',
            P01_E2E_FIXTURES: fixtures,
            SESSION_DRIVER: 'database',
            SESSION_SECURE_COOKIE: 'false',
            CACHE_STORE: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'array',
        },
    },
});
