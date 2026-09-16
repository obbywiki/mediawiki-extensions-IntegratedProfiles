'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const { FlatCompat } = require( '@eslint/eslintrc' );
const js = require( '@eslint/js' );

const compat = new FlatCompat( {
	baseDirectory: __dirname,
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all,
} );

module.exports = defineConfig( [
	globalIgnores( [
		'**/node_modules/',
		'**/coverage/',
		'resources/dist/',
		'vendor/',
		'**/*.test.js',
	] ),

	// avoid wikimedia/client
	...compat.extends(
		'wikimedia/client/common',
		'wikimedia/language/es2019'
	),

	{
		languageOptions: {
			ecmaVersion: 2022,
			globals: {
				mw: 'readonly',
				OO: 'readonly',
			},
		},
		rules: {
			camelcase: 'off',
			'no-use-before-define': 'off',
			'jsdoc/no-undefined-types': 'off',
            'max-statements-per-line': 'off',
            'brace-style': 'off',
			'no-unused-vars': [ 'warn', { args: 'none' } ],
			'linebreak-style': 'off',
			'preserve-caught-error': 'off',
		},
	},

	{
		files: [ 'src/**/*.ts', 'src/**/*.d.ts' ],
		extends: compat.extends( 'wikimedia/typescript' ),
		languageOptions: {
			sourceType: 'module',
			parserOptions: {
				project: './tsconfig.json',
				tsconfigRootDir: __dirname,
			},
		},
		rules: {
			camelcase: 'off',
			'linebreak-style': 'off',
			'no-var': 'off',
			'es-x/no-optional-chaining': 'off',
			'es-x/no-optional-catch-binding': 'off',
			'@typescript-eslint/no-unused-vars': [ 'error', {
				argsIgnorePattern: '^_',
				caughtErrorsIgnorePattern: '^_',
			} ],
		},
	},

	{
		files: [ 'tests/**/*.{js}' ],
		languageOptions: {
			sourceType: 'module',
		},
	},
] );
