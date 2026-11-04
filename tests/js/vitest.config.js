import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    // Specs mount the real admin SFCs; vue and the plugin resolve from the project root.
    plugins: [vue()],
    resolve: {
        alias: { '@': fileURLToPath(new URL('../../resources', import.meta.url)) },
        // Matches vite.config.mjs; admin SFCs import .vue files without the extension.
        extensions: ['.mjs', '.js', '.json', '.vue']
    },
    test: {
        include: ['specs/**/*.test.js']
    }
});
