/** @type {import('eslint').Linter.FlatConfig[]} */
const tsParser = require('@typescript-eslint/parser');
const tsPlugin = require('@typescript-eslint/eslint-plugin');
const angularTemplateParser = require('@angular-eslint/template-parser');
const angularTemplatePlugin = require('@angular-eslint/eslint-plugin-template');

module.exports = [
  {
    ignores: [
      'dist/**',
      'coverage/**',
      'storybook-static/**',
      'cypress/**',
      '**/*.mdx',
      'node_modules/**',
      '.storybook/**',
      'cypress.config.ts',
      'jest.config.cjs',
      'src/environments/**',
      '**/*.scss',
      '**/*.stories.ts'
    ]
  },
  {
    files: ['src/app/features/auth/**/*.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module'
      }
    },
    plugins: {
      '@typescript-eslint': tsPlugin
    },
    rules: {
      '@typescript-eslint/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_'
        }
      ]
    }
  },
  {
    files: ['src/app/features/auth/**/*.html'],
    languageOptions: {
      parser: angularTemplateParser
    },
    plugins: {
      '@angular-eslint/template': angularTemplatePlugin
    },
    rules: {}
  }
];
