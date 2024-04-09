/* eslint-env node */

module.exports = {
    root: true,
    extends: ['@chialab/eslint-config'],
    env: {
        browser: true,
    },
    ignorePatterns: ['webroot/build/*'],
};
