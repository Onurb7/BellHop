import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

/**
 * Deliberately separate from vite.config.js, not a `test` block bolted
 * onto it — that config includes laravel-vite-plugin, which manages
 * public/hot's lifecycle (created when its dev server starts, deleted
 * when it stops). Vitest reuses whatever config it's given for its own
 * internal Vite instance, so sharing the file meant a `test:js` run
 * deleted the real dev server's public/hot out from under it the moment
 * the tests finished — killing HMR/asset serving for whoever had `npm
 * run dev` open, not just the test process itself.
 */
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
    },
});
