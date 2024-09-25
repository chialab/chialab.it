import process from 'node:process';

const isProduction = process.env.NODE_ENV === 'production';

/**
 * @type {import('@chialab/rna-config-loader').Config}
 */
const config = {
    entrypoints: [
        {
            input: ['./plugins/Chialab/resources/index.ts', './plugins/Chialab/resources/index.css'],
            publicPath: '/chialab/build/',
            output: 'plugins/Chialab/webroot/build/',
            manifestPath: 'plugins/Chialab/webroot/build/manifest.json',
            entrypointsPath: 'plugins/Chialab/webroot/build/entrypoints.json',
        },
        {
            input: ['./plugins/Illustratorium/resources/index.ts', './plugins/Illustratorium/resources/index.css'],
            publicPath: '/illustratorium/build/',
            output: 'plugins/Illustratorium/webroot/build/',
            manifestPath: 'plugins/Illustratorium/webroot/build/manifest.json',
            entrypointsPath: 'plugins/Illustratorium/webroot/build/entrypoints.json',
        },
        {
            input: ['./plugins/OpenSource/resources/index.ts', './plugins/OpenSource/resources/index.css'],
            publicPath: '/open_source/build/',
            output: 'plugins/OpenSource/webroot/build/',
            manifestPath: 'plugins/OpenSource/webroot/build/manifest.json',
            entrypointsPath: 'plugins/OpenSource/webroot/build/entrypoints.json',
        },
    ],
    clean: true,
    sourcemap: !isProduction,
    entryNames: isProduction ? '[name]-[hash]' : '[name]',
    chunkNames: isProduction ? '[name]-[hash]' : '[name]',
    assetNames: isProduction ? '[name]-[hash]' : '[name]',
    minify: isProduction,
    bundle: isProduction,
    jsx: 'automatic',
    jsxImportSource: '@chialab/dna',
};

export default config;
