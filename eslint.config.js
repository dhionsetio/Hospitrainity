import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'storage/**',
            'standalone/**',
            'resources/standalone/**',
            'curriculum/**',
        ],
    },
    js.configs.recommended,
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: globals.browser,
        },
        linterOptions: {
            reportUnusedDisableDirectives: 'error',
        },
    },
    {
        files: ['*.js', '*.mjs', 'scripts/**/*.mjs', 'tests/**/*.mjs'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: globals.node,
        },
        linterOptions: {
            reportUnusedDisableDirectives: 'error',
        },
    },
    {
        files: ['tests/Node/**/*.mjs'],
        languageOptions: {
            globals: {
                ...globals.node,
                ...globals.browser,
            },
        },
    },
];
