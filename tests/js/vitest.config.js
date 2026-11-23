import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    // Specs mount the real admin SFCs; vue and the plugin resolve from the project root.
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../../resources', import.meta.url)),
            // The FluentCommunity menu script imports FluentCommunity's shared runtime.
            '@fluentcommunity/vue': 'vue',
            '@fluentcommunity/element-plus': fileURLToPath(new URL('./stubs/fluentcommunity-element-plus.js', import.meta.url)),
            '@fluentcommunity/ui': fileURLToPath(new URL('./stubs/fluentcommunity-ui.js', import.meta.url))
        },
        // Matches vite.config.mjs; admin SFCs import .vue files without the extension.
        extensions: ['.mjs', '.js', '.json', '.vue']
    },
    test: {
        include: ['specs/**/*.test.js']
    }
});
