import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/E2E',
    timeout: 30_000,
    expect: { timeout: 7_500 },
    fullyParallel: false,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'storage/app/playwright-report' }]],
    use: {
        baseURL: 'http://127.0.0.1:8765',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'chromium-mobile', use: { ...devices['Pixel 7'] } },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8765',
        url: 'http://127.0.0.1:8765/login',
        reuseExistingServer: false,
        timeout: 60_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
