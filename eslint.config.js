import js from '@eslint/js';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'bootstrap/ssr/**',
            'storage/**',
            'resources/js/components/ui/**',
            '.cursor/**',
            '.impeccable/**',
            'prompts/**',
            'docs/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['**/*.{ts,tsx,js,jsx}'],
        languageOptions: {
            globals: { ...globals.browser, ...globals.node },
            parserOptions: {
                ecmaFeatures: { jsx: true },
            },
        },
        plugins: {
            react,
            'react-hooks': reactHooks,
            'jsx-a11y': jsxA11y,
        },
        settings: {
            react: { version: 'detect' },
        },
        rules: {
            ...react.configs.flat.recommended.rules,
            ...react.configs.flat['jsx-runtime'].rules,
            ...reactHooks.configs.recommended.rules,
            ...jsxA11y.flatConfigs.recommended.rules,
            'react/prop-types': 'off',
            // Inertia finds scroll containers with a bare `scroll-region`
            // attribute; a data- prefix would not be matched.
            'react/no-unknown-property': ['error', { ignore: ['scroll-region'] }],
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },
);
