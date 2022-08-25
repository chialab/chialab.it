/* eslint-env node */
import { hmrCssPlugin } from '@chialab/wds-plugin-hmr';
import { hmrPlugin } from '@chialab/wds-plugin-hmr-dna';

const isProduction = process.env.NODE_ENV === 'production';

/**
 * @type {import('@chialab/rna-config-loader').Config}
 */
const config = {
    entrypoints: [
        {
            input: [
                './plugins/Design/resources/scripts/index.ts',
                './plugins/Design/resources/styles/index.css',
            ],
            publicPath: '/build/',
            output: 'plugins/Design/webroot/build/',
            manifestPath: 'plugins/Design/webroot/build/manifest.json',
            entrypointsPath: 'plugins/Design/webroot/build/entrypoints.json',
        },
    ],
    servePlugins: [
        hmrPlugin(),
        hmrCssPlugin(),
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
