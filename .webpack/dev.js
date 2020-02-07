/**
 * Webpack development build configuration.
 */
const {
	externals,
	helpers,
	loaders,
	presets,
} = require('@humanmade/webpack-helpers');
const { choosePort, cleanOnExit, filePath } = helpers;

// Clean up manifests on exit.
cleanOnExit([filePath('dist/asset-manifest.json')]);

// Mutate the loader defaults.
loaders.css.exclude = /(bower_components|node_modules|vendor)/;
loaders.eslint.exclude = /(bower_components|node_modules|vendor)/;
loaders.js.exclude = /(bower_components|node_modules|vendor)/;

module.exports = choosePort(8080).then((port) => [
	presets.development({
		name: 'meow',
		devServer: {
			port,
		},
		externals,
		entry: {
			meow: filePath('src/index.js'),
		},
		output: {
			path: filePath('dist/'),
			publicPath: `http://localhost:${port}/cef/`,
		},
	}),
]);
