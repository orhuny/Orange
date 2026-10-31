import { defineConfig } from 'vitest/config';

// Separate from vite.config.js so the Laravel plugin stays out of the test run.
export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
    },
});
