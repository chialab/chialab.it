import cssModulesPlugin from '@chialab/vite-plugin-css-modules';
import { defineConfig } from 'vite';
import { vitePhp } from './vendor/chialab/vite-cakephp/vite-plugin-php.js';

export default defineConfig({
    build: {
        outDir: './webroot/dist',
    },
    server: {
        origin: 'http://localhost:5173',
        fs: {
            allow: ['.'],
        },
        cors: {
            origin: [/\.localhost\.bedita\.cloud$/],
        },
    },
    plugins: [
        cssModulesPlugin({
            checkAttribute: false,
        }),
        vitePhp([
            {
                name: 'Chialab',
                outDir: './webroot/dist/chialab',
                publicPath: '/dist/chialab/',
                inputs: {
                    index: {
                        js: 'plugins/Chialab/resources/index.ts',
                        css: 'plugins/Chialab/resources/index.css',
                    },
                },
            },
            {
                name: 'Illustratorium',
                outDir: './webroot/dist/illustratorium',
                publicPath: '/dist/illustratorium/',
                inputs: {
                    index: {
                        js: 'plugins/Illustratorium/resources/index.ts',
                        css: 'plugins/Illustratorium/resources/index.css',
                    },
                },
            },
            {
                name: 'Skua',
                outDir: './webroot/dist/skua',
                publicPath: '/dist/skua/',
                inputs: {
                    'index': {
                        js: 'plugins/Skua/resources/index.ts',
                        css: 'plugins/Skua/resources/index.css',
                    },
                    'mapscroller': {
                        js: 'plugins/Skua/resources/mapscroller.tsx',
                    },
                    'journey-page': {
                        css: 'plugins/Skua/resources/journey-page.css',
                    },
                    'tracking-page': {
                        css: 'plugins/Skua/resources/tracking-page.css',
                    },
                },
            },
        ]),
    ],
});
