/**
 * Block editor webpack config.
 *
 * Extends @wordpress/scripts default config but replaces MiniCSSExtractPlugin
 * with style-loader so all SCSS is inlined into fs_block.js — no separate CSS
 * files are emitted, matching the release bundle structure.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCSSExtractPlugin = require( 'mini-css-extract-plugin' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const webpack = require( 'webpack' );

// Swap out every MiniCSSExtractPlugin.loader reference in the rule chain
// with style-loader so CSS is injected at runtime via <style> tags.
const rulesWithStyleLoader = defaultConfig.module.rules.map( ( rule ) => {
	if ( ! Array.isArray( rule.use ) ) return rule;

	const usesMiniCss = rule.use.some(
		( u ) =>
			u === MiniCSSExtractPlugin.loader ||
			( u && u.loader === MiniCSSExtractPlugin.loader )
	);

	if ( ! usesMiniCss ) return rule;

	return {
		...rule,
		use: rule.use.map( ( u ) => {
			if (
				u === MiniCSSExtractPlugin.loader ||
				( u && u.loader === MiniCSSExtractPlugin.loader )
			) {
				return require.resolve( 'style-loader' );
			}
			return u;
		} ),
	};
} );

module.exports = {
	...defaultConfig,
	// Remove MiniCSSExtractPlugin and RtlCssPlugin (no separate CSS files needed).
	// Add BannerPlugin to inject React license comment so TerserPlugin extracts it
	// to index.js.LICENSE.txt (which the build script renames to fs_block.js.LICENSE.txt).
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) => ! ( plugin instanceof MiniCSSExtractPlugin )
		),
		new webpack.BannerPlugin( {
			banner: `@license React\nreact.production.min.js\n\nCopyright (c) Facebook, Inc. and its affiliates.\n\nThis source code is licensed under the MIT license found in the\nLICENSE file in the root directory of this source tree.`,
			raw: false,
		} ),
	],
	module: {
		...defaultConfig.module,
		rules: rulesWithStyleLoader,
	},
	// Disable the CSS-specific splitChunks group that targets extracted CSS
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
		splitChunks: {
			...( defaultConfig.optimization.splitChunks || {} ),
			cacheGroups: Object.fromEntries(
				Object.entries(
					( defaultConfig.optimization.splitChunks || {} ).cacheGroups || {}
				).filter( ( [ key ] ) => key !== 'style' )
			),
		},
	},
};
