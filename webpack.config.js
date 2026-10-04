const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Configuration Webpack standard pour les Blocs Gutenberg (build/blocks/).
 * Les assets autonomes (assets/js/ et assets/css/) sont gérés de manière optimisée via esbuild et sass.
 */
module.exports = defaultConfig;
