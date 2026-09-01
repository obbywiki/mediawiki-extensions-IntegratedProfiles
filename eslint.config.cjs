'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const { FlatCompat } = require( '@eslint/eslintrc' );
const js = require( '@eslint/js' );
const ts_parser = require( '@typescript-eslint/parser' );
const vue = require( 'eslint-plugin-vue' );
const vue_parser = require( 'vue-eslint-parser' );

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
			'vue/prop-name-casing': 'off',
			'preserve-caught-error': 'off',
		},
	},

	...vue.configs[ 'flat/recommended' ].map( ( cfg ) => (
		cfg.rules ?
			{ ...cfg, files: cfg.files || [ '**/*.vue' ] } :
			cfg
	) ),
	...compat.extends( 'wikimedia/vue/wrappers' ).map( ( cfg ) => ( {
		...cfg,
		files: [ '**/*.vue' ],
	} ) ),

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
		files: [ 'src/**/*.vue' ],
		languageOptions: {
			parser: vue_parser,
			sourceType: 'module',
			parserOptions: {
				parser: ts_parser,
				extraFileExtensions: [ '.vue' ],
			},
		},
		rules: {
			camelcase: 'off',
			'linebreak-style': 'off',
            'max-statements-per-line': 'off',
            'brace-style': 'off',
			'vue/prop-name-casing': 'off',
			'func-call-spacing': 'off',
			'es-x/no-optional-chaining': 'off',
			'@typescript-eslint/explicit-function-return-type': 'off',
			'@typescript-eslint/explicit-module-boundary-types': 'off',
			'vue/block-order': [ 'error', {
				order: [ 'template', 'script', 'style' ],
			} ],
			'vue/html-indent': [ 'error', 'tab' ],
			'vue/html-closing-bracket-newline': 'off',
			'vue/max-attributes-per-line': [ 'warn', {
				singleline: 2,
				multiline: 1,
			} ],
			'vue/component-name-in-template-casing': [ 'error', 'PascalCase' ],
		},
	},

	{
		files: [ 'tests/**/*.{js,vue}' ],
		languageOptions: {
			sourceType: 'module',
		},
	},
] );
