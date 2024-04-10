/* eslint-env node */

module.exports = {
    root: true,
    extends: ['@chialab/eslint-config'],
    env: {
        browser: true,
    },
    ignorePatterns: ['vendor/**/*', 'webroot/build/**/*', 'plugins/*/webroot/build/**/*'],
};
