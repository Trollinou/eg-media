const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const webpack = require( 'webpack' );
const rtlcss = require( 'rtlcss' );

const standaloneEntries = {
	'admin-dashboard': [
		path.resolve( __dirname, 'src/ts/admin-dashboard.ts' ),
		path.resolve( __dirname, 'src/scss/admin-dashboard.scss' ),
	],
	'admin-album-metabox': [
		path.resolve( __dirname, 'src/ts/admin-album-metabox.ts' ),
		path.resolve( __dirname, 'src/scss/admin-album-metabox.scss' ),
	],
	'admin-upload': [
		path.resolve( __dirname, 'src/ts/admin-upload.ts' ),
		path.resolve( __dirname, 'src/scss/admin-upload.scss' ),
	],
	'admin-media-fields': path.resolve( __dirname, 'src/scss/admin-media-fields.scss' ),
	'public-album': [
		path.resolve( __dirname, 'src/ts/public-album.ts' ),
		path.resolve( __dirname, 'src/scss/public-album.scss' ),
	],
};

const standaloneNames = Object.keys( standaloneEntries );
const cssOnly = ( filename ) => filename.endsWith( '.css' ) && ! filename.endsWith( '-rtl.css' );

function applyCustomConfig( config ) {
	const originalEntry = config.entry;
	config.entry = async () => {
		const entries = typeof originalEntry === 'function' ? await originalEntry() : ( originalEntry || {} );
		return {
			...entries,
			...standaloneEntries,
		};
	};

	config.output = {
		...config.output,
		filename: ( pathData ) => {
			if ( standaloneNames.includes( pathData.chunk.name ) ) {
				return '../assets/js/[name].js';
			}
			return '[name].js';
		},
	};

	// Configuration pour extraire le CSS vers ../assets/css/[name].css
	const miniCssPlugin = config.plugins.find(
		( p ) => p && p.constructor && p.constructor.name === 'MiniCssExtractPlugin'
	);
	if ( miniCssPlugin ) {
		miniCssPlugin.options.filename = ( pathData ) => {
			if ( standaloneNames.includes( pathData.chunk.name ) ) {
				return '../assets/css/[name].css';
			}
			return '[name].css';
		};
	}

	// Ajustement de RtlCssPlugin pour rediriger les assets standalone vers ../assets/css/[name]-rtl.css
	const rtlCssIndex = config.plugins.findIndex(
		( p ) => p && p.constructor && p.constructor.name === 'RtlCssPlugin'
	);
	if ( rtlCssIndex !== -1 ) {
		config.plugins[ rtlCssIndex ] = {
			apply( compiler ) {
				compiler.hooks.compilation.tap( 'CustomRtlCssPlugin', ( compilation ) => {
					compilation.hooks.processAssets.tapAsync(
						{
							name: 'CustomRtlCssPlugin',
							stage: compilation.PROCESS_ASSETS_STAGE_OPTIMIZE,
						},
						( chunks, callback ) => {
							const allChunks = Array.from( compilation.chunks );
							allChunks.forEach( ( chunk ) => {
								const files = Array.from( chunk.files );
								files.filter( cssOnly ).forEach( ( filename ) => {
									const assetObj = compilation.assets[ filename ];
									if ( ! assetObj ) {
										return;
									}
									const src = assetObj.source();
									const dst = rtlcss.process( typeof src === 'string' ? src : src.toString() );
									const isStandalone = standaloneNames.includes( chunk.name );
									const dstFileName = isStandalone
										? `../assets/css/${ chunk.name }-rtl.css`
										: compilation.getPath( '[name]-rtl.css', {
												chunk,
												cssFileName: filename,
										  } );

									compilation.assets[ dstFileName ] = new webpack.sources.RawSource( dst );
									chunk.files.add( dstFileName );
								} );
							} );
							callback();
						}
					);
				} );
			},
		};
	}
}

if ( Array.isArray( defaultConfig ) ) {
	applyCustomConfig( defaultConfig[ 0 ] );
	module.exports = defaultConfig;
} else {
	applyCustomConfig( defaultConfig );
	module.exports = defaultConfig;
}
