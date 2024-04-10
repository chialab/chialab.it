import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);

export default {
    extends: ['@chialab/stylelint-config'],
    ignoreFiles: ['vendor/**/*', 'webroot/build/**/*', 'plugins/*/webroot/build/**/*'],
    rules: {
        'csstools/value-no-unknown-custom-properties': [
            true,
            {
                importFrom: [require.resolve('@chialab/cells/lib/index.css')],
            },
        ],
    },
};
