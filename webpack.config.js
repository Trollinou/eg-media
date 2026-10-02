const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const standaloneEntries = {
	'admin-dashboard': path.resolve( __dirname, 'src/ts/admin-dashboard.ts' ),
	'admin-album-metabox': path.resolve( __dirname, 'src/ts/admin-album-metabox.ts' ),
	'admin-upload': path.resolve( __dirname, 'src/ts/admin-upload.ts' ),
	'public-album': path.resolve( __dirname, 'src/ts/public-album.ts' ),
};

const standaloneNames = Object.keys( standaloneEntries );

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
}

if ( Array.isArray( defaultConfig ) ) {
	applyCustomConfig( defaultConfig[ 0 ] );
	module.exports = defaultConfig;
} else {
	applyCustomConfig( defaultConfig );
	module.exports = defaultConfig;
}
