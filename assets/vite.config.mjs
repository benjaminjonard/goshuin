import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import symfony from '@symfony/reprise/vite';

export default defineConfig(({ mode }) => ({
    resolve: { preserveSymlinks: true },
    build: {
        emptyOutDir: true,
        sourcemap: mode !== 'production',
        rollupOptions: {
            input: { app: './app.js' },
            output: mode === 'production' ? {} : {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
    plugins: [
        tailwindcss(),
        symfony({ outputPath: '../public/build', publicPath: '/build/', stimulus: 'controllers.json' }),
    ],
}));
