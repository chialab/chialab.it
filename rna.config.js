import process from 'node:process';

const isProduction = process.env.NODE_ENV === 'production';

/**
 * @type {import('@chialab/rna-config-loader').Config}
 */
const config = {
    entrypoints: [
        {
            input: ['./plugins/Design/resources/index.ts', './plugins/Design/resources/index.css'],
            publicPath: '/design/build/',
            output: 'plugins/Design/webroot/build/',
            manifestPath: 'plugins/Design/webroot/build/manifest.json',
            entrypointsPath: 'plugins/Design/webroot/build/entrypoints.json',
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
