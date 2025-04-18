/* eslint-env node */

module.exports = {
    root: true,
    extends: ['@chialab/eslint-config'],
    env: {
        browser: true,
    },
    ignorePatterns: [
        'vendor/**/*',
        'webroot/build/**/*',
        'webroot/interactive-ebook-promo/**/*',
        'plugins/*/webroot/build/**/*',
    ],
};
