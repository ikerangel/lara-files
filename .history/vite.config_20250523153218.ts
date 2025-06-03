import { fileURLToPath, URL } from "url";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";
import VueI18nPlugin from "@intlify/unplugin-vue-i18n/vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/js/src/main.ts"],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    includeAbsolute: false,
                },
            },
        }),
        VueI18nPlugin({
            include: path.resolve("resources/js/src/locales/**"),
        }),
    ],
    resolve: {
        alias: {
            "@": path.resolve("resources/js/src"),
        },
    },
    optimizeDeps: {
        include: ["quill"],
    },
    // Homestead-specific server configuration
    server: {
        host: '0.0.0.0', // Allow external connections
        port: 5173,
        hmr: {
            host: 'localhost', // Connect via host machine's localhost
            port: 5173,
            protocol: 'ws'
        },
        watch: {
            usePolling: true, // Required for file watching in Homestead
            interval: 1000
        },
        // proxy: {
        //     '/api': {
        //         target: 'http://lara-vue.test',
        //         changeOrigin: true,
        //         secure: false,
        //     }
        // },

    },
    // Build configuration for Homestead compatibility
    build: {
        manifest: 'manifest.json',
        outDir: 'public/build',
        assetsDir: 'assets'
    }
});
