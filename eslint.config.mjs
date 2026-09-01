import js from '@eslint/js';
import globals from 'globals';

export default [
	{
		files: [ 'src/**/*.{js,mjs}', 'bin/**/*.mjs', 'tests/js/**/*.mjs' ],
		languageOptions: {
			ecmaVersion: 'latest',
			globals: {
				...globals.browser,
				...globals.node,
				wp: 'readonly',
			},
			parserOptions: {
				ecmaFeatures: {
					jsx: true,
				},
			},
			sourceType: 'module',
		},
		rules: {
			...js.configs.recommended.rules,
		},
	},
];
