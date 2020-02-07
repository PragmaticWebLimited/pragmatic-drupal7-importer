/**
 * Webpack production build configuration.
 */
const {
	externals,
	helpers,
	loaders,
	presets,
} = require('@humanmade/webpack-helpers');
const { filePath } = helpers;

// Mutate the loader defaults.
loaders.css.exclude = /(bower_components|node_modules|vendor)/;
loaders.eslint.exclude = /(bower_components|node_modules|vendor)/;
loaders.js.exclude = /(bower_components|node_modules|vendor)/;

module.exports = [
	presets.production({
		name: 'meow',
		externals,
		entry: {
			meow: filePath('src/index.js'),
		},
		output: {
			path: filePath('dist/'),
		},
	}),
];
