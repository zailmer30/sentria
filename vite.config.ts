import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { Plugin } from 'vite';
import { defineConfig } from 'vite';

const dirname = path.dirname(fileURLToPath(import.meta.url));

function copyPdfiumWasm(): Plugin {
    const src = path.resolve(dirname, 'node_modules/@embedpdf/pdfium/dist/pdfium.wasm');
    const destDir = path.resolve(dirname, 'public/wasm');
    const dest = path.join(destDir, 'pdfium.wasm');

    const copy = () => {
        if (!existsSync(src)) {
            throw new Error(`PDFium WASM not found at ${src}`);
        }

        mkdirSync(destDir, { recursive: true });
        copyFileSync(src, dest);
    };

    return {
        name: 'copy-pdfium-wasm',
        buildStart() {
            copy();
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
            fonts: [bunny('Space Grotesk', { weights: [400, 500, 600, 700] })],
        }),
        react(),
        tailwindcss(),
        copyPdfiumWasm(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(dirname, 'resources/js'),
        },
    },
    assetsInclude: ['**/*.wasm'],
    optimizeDeps: {
        exclude: ['@embedpdf/pdfium', '@embedpdf/engines'],
    },
    ssr: {
        external: ['@embedpdf/react-pdf-viewer'],
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        allowedHosts: true,
        hmr: {
            host: '127.0.0.1',
            overlay: false,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
