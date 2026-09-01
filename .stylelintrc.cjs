module.exports = {
	extends: 'stylelint-config-wikimedia/support-basic',
	rules: {
		'selector-class-pattern': null,
		'custom-property-pattern': null,
		'selector-max-id': 1,
		'plugin/no-unsupported-browser-features': null
	},
	ignoreFiles: [
		'coverage/**',
		'node_modules/**',
		'resources/dist/**'
	]
};
