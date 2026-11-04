/**
 * Block editor webpack config.
 *
 * Extends @wordpress/scripts default config. Entries come from block.json, and
 * the build writes block.json, index.js, index.css, index-rtl.css and
 * index.asset.php side by side so register_block_type() can read them from
 * assets/block-editor/customer-portal/.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const webpack = require( 'webpack' );

// @wordpress/scripts ships sass-loader 12, which only speaks Sass's legacy JS
// API. Silence that deprecation until wp-scripts moves to the modern API.
const rulesWithQuietSass = defaultConfig.module.rules.map( ( rule ) => {
	if ( ! Array.isArray( rule.use ) ) {
		return rule;
	}
	return {
		...rule,
		use: rule.use.map( ( use ) => {
			if ( ! use.loader || ! use.loader.includes( 'sass-loader' ) ) {
				return use;
			}
			return {
				...use,
				options: {
					...use.options,
					sassOptions: {
						...use.options?.sassOptions,
						silenceDeprecations: [ 'legacy-js-api' ],
					},
				},
			};
		} ),
	};
} );

module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: rulesWithQuietSass,
	},
	plugins: [
		...defaultConfig.plugins,
		new webpack.BannerPlugin( {
			banner: `@license React\nreact.production.min.js\n\nCopyright (c) Facebook, Inc. and its affiliates.\n\nThis source code is licensed under the MIT license found in the\nLICENSE file in the root directory of this source tree.`,
			raw: false,
		} ),
	],
	optimization: {
		...defaultConfig.optimization,
		minimizer: [
			new TerserPlugin( {
				extractComments: true,
				parallel: true,
				terserOptions: {
					output: { comments: false },
					compress: { passes: 2 },
					mangle: { reserved: [ '__', '_n', '_nx', '_x' ] },
				},
			} ),
		],
	},
};
