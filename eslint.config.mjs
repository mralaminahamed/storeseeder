import wordpress from '@wordpress/eslint-plugin';
import tseslint from 'typescript-eslint';
import globals from 'globals';

/**
 * ESLint flat configuration.
 *
 * Replaces .eslintrc.js. The rule set is the same one that file expressed, with
 * two differences forced by the format: `env` blocks are now explicit `globals`
 * imports, and the type-aware TypeScript rules are scoped to `src/` because that
 * is all tsconfig.json includes — pointing them at tests/ would fail with
 * "file not found in project".
 *
 * @wordpress/eslint-plugin 25's package root exports flat configs as arrays to
 * spread; its `./eslintrc` subpath still carries the legacy versions. Its
 * `recommended` already pulls in esnext, jsdoc, jsx-a11y and react, so only i18n
 * is added on top of it here.
 */
export default [
  {
    ignores: [
      'build/**',
      'vendor/**',
      'release/**',
      'node_modules/**',
      'tests/e2e/report/**',
      'test-results/**',
      '**/*.min.js',
    ],
  },

  ...wordpress.configs.recommended,
  ...wordpress.configs.i18n,

  {
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.jquery,
        // Injected by WordPress or by the plugin's own wp_localize_script call.
        wp: 'readonly',
        storeseederApi: 'readonly',
        ajaxurl: 'readonly',
      },
    },
    rules: {
      'no-console': 'warn',
      'no-debugger': 'error',
      'prefer-const': 'error',
      'no-var': 'error',
      'object-shorthand': 'error',
      'prefer-arrow-callback': 'error',
      'arrow-spacing': 'error',
      'prefer-template': 'error',

      // Formatting is not enforced through ESLint in this project; Prettier runs
      // on its own when it runs at all.
      'prettier/prettier': 'off',

      '@wordpress/no-unused-vars-before-return': 'error',
      '@wordpress/valid-sprintf': 'error',
      '@wordpress/i18n-text-domain': ['error', { allowedTextDomain: 'storeseeder' }],
      '@wordpress/i18n-translator-comments': 'error',
      '@wordpress/i18n-no-variables': 'error',
      '@wordpress/i18n-no-placeholders-only': 'error',
      '@wordpress/i18n-ellipsis': 'error',
    },
  },

  // The admin app: type-aware linting, since tsconfig.json includes src.
  ...tseslint.configs.recommendedTypeChecked.map((config) => ({
    ...config,
    files: ['src/**/*.ts', 'src/**/*.tsx'],
  })),
  {
    files: ['src/**/*.ts', 'src/**/*.tsx'],
    languageOptions: {
      parserOptions: {
        project: './tsconfig.json',
        tsconfigRootDir: import.meta.dirname,
      },
    },
    rules: {
      '@typescript-eslint/no-unused-vars': 'error',
      '@typescript-eslint/explicit-function-return-type': 'off',
      '@typescript-eslint/explicit-module-boundary-types': 'off',
      '@typescript-eslint/no-explicit-any': 'warn',

      // The signature is the types, not a comment above them. On a component
      // taking destructured props these ask for @param root0, root0.size,
      // root0.className — names that appear nowhere in the source and document
      // nothing. Prose JSDoc is still expected; only the tag audit is off.
      'jsdoc/require-param': 'off',
      'jsdoc/require-param-type': 'off',
      'jsdoc/require-returns': 'off',
      'jsdoc/require-returns-type': 'off',
    },
  },

  // Playwright specs and their helpers sit outside tsconfig's include, so they
  // get the syntactic TypeScript rules only.
  ...tseslint.configs.recommended.map((config) => ({
    ...config,
    files: ['tests/**/*.ts', '*.config.ts'],
  })),
  ...wordpress.configs['test-playwright'].map((config) => ({
    ...config,
    files: ['tests/e2e/**/*.ts'],
  })),
];
