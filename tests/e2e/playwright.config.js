import { defineConfig } from '@playwright/test';
import { baseURL } from './lib/lab.js';

// The flows share one seeded data set and change it, so they run one at a time.
export default defineConfig({
    testDir: './specs',
    workers: 1,
    fullyParallel: false,
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [['list']],
    globalSetup: './global-setup.js',
    globalTeardown: './global-teardown.js',
    use: {
        baseURL,
        storageState: './.auth/admin.json',
        viewport: { width: 1440, height: 900 },
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
});
