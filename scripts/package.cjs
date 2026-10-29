const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );
const AdmZip = require( 'adm-zip' );
const { Minimatch } = require( 'minimatch' );

const rootDir = path.resolve( __dirname, '..' );
const mainFile = path.join( rootDir, 'eg-media.php' );
const distIgnorePath = path.join( rootDir, '.distignore' );
const buildDir = path.join( rootDir, 'dist-temp' );
const pluginSlug = 'eg-media';

console.log( '🔍 Extraction de la version du plugin...' );
if ( ! fs.existsSync( mainFile ) ) {
	console.error( `❌ Erreur : Fichier principal ${ mainFile } introuvable.` );
	process.exit( 1 );
}

const mainContent = fs.readFileSync( mainFile, 'utf8' );
const versionMatch = mainContent.match( /Version:\s*([^\s\r\n]+)/ );
if ( ! versionMatch ) {
	console.error(
		`❌ Erreur : Impossible de trouver la version dans ${ mainFile }`
	);
	process.exit( 1 );
}
const version = versionMatch[ 1 ].trim();
console.log( `ℹ️  Version détectée : ${ version }` );

const zipName = `${ pluginSlug }-v${ version }.zip`;
const tempDestDir = path.join( buildDir, pluginSlug );

// Validation QA obligatoire avant build et packaging
console.log( '🧪 Exécution de la suite QA complète...' );
try {
	execSync(
		'npm run type-check && npm run lint:js && vendor/bin/phpstan analyze --debug --memory-limit=2G && vendor/bin/phpcs && vendor/bin/phpunit',
		{ cwd: rootDir, stdio: 'inherit' }
	);
} catch ( _error ) {
	console.error( '❌ Erreur : La validation QA a échoué. Packaging annulé.' );
	process.exit( 1 );
}

// Compilation des assets locaux (Production)
const packageJsonPath = path.join( rootDir, 'package.json' );
let hasBuildScript = false;
if ( fs.existsSync( packageJsonPath ) ) {
	try {
		const pkg = JSON.parse( fs.readFileSync( packageJsonPath, 'utf8' ) );
		if ( pkg.scripts && pkg.scripts.build ) {
			hasBuildScript = true;
		}
	} catch ( _e ) {
		console.warn( '⚠️  Impossible de lire ou parser package.json.' );
	}
}

if ( hasBuildScript ) {
	console.log( '🏗️  Compilation des assets locaux (Production)...' );
	try {
		execSync( 'npm run build', { cwd: rootDir, stdio: 'inherit' } );
	} catch ( _error ) {
		console.error( '❌ Erreur : Le build a échoué. Packaging annulé.' );
		process.exit( 1 );
	}
}

// Nettoyage préalable
if ( fs.existsSync( path.join( rootDir, zipName ) ) ) {
	fs.unlinkSync( path.join( rootDir, zipName ) );
}
if ( fs.existsSync( buildDir ) ) {
	fs.rmSync( buildDir, { recursive: true, force: true } );
}
fs.mkdirSync( tempDestDir, { recursive: true } );

// Chargement des règles .distignore
const ignoreRules = [];
if ( fs.existsSync( distIgnorePath ) ) {
	fs.readFileSync( distIgnorePath, 'utf8' )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line && ! line.startsWith( '#' ) )
		.forEach( ( pattern ) => {
			let matchPath = pattern;
			let isRootRelative = false;
			if ( pattern.startsWith( '/' ) ) {
				matchPath = pattern.slice( 1 );
				isRootRelative = true;
			}

			if ( matchPath.endsWith( '/' ) ) {
				const dirPath = matchPath.slice( 0, -1 );
				ignoreRules.push( {
					mm: new Minimatch( dirPath, {
						dot: true,
						matchBase: ! isRootRelative,
						nocomment: true,
					} ),
				} );
				ignoreRules.push( {
					mm: new Minimatch( dirPath + '/**', {
						dot: true,
						matchBase: ! isRootRelative,
						nocomment: true,
					} ),
				} );
			} else {
				ignoreRules.push( {
					mm: new Minimatch( matchPath, {
						dot: true,
						matchBase: ! isRootRelative,
						nocomment: true,
					} ),
				} );
				ignoreRules.push( {
					mm: new Minimatch( matchPath + '/**', {
						dot: true,
						matchBase: ! isRootRelative,
						nocomment: true,
					} ),
				} );
			}
		} );
}

function isIgnored( relPath ) {
	const normalizedPath = relPath.replace( /\\/g, '/' );
	if ( ! normalizedPath ) {
		return false;
	}

	if (
		normalizedPath === 'dist-temp' ||
		normalizedPath.startsWith( 'dist-temp/' ) ||
		normalizedPath === '.DS_Store' ||
		normalizedPath.endsWith( '/.DS_Store' ) ||
		normalizedPath.endsWith( '.sh' ) ||
		normalizedPath.endsWith( '.zip' ) ||
		normalizedPath === 'vendor' ||
		normalizedPath.startsWith( 'vendor/' ) ||
		normalizedPath === 'scripts' ||
		normalizedPath.startsWith( 'scripts/' )
	) {
		return true;
	}

	for ( const rule of ignoreRules ) {
		if ( rule.mm.match( normalizedPath ) ) {
			return true;
		}
	}
	return false;
}

function copyFiltered( src, dest ) {
	if ( ! fs.existsSync( src ) ) {
		return;
	}
	const relPath = path.relative( rootDir, src );

	if ( isIgnored( relPath ) ) {
		return;
	}

	const stats = fs.statSync( src );

	if ( stats.isDirectory() ) {
		fs.mkdirSync( dest, { recursive: true } );
		const entries = fs.readdirSync( src );
		for ( const entry of entries ) {
			copyFiltered( path.join( src, entry ), path.join( dest, entry ) );
		}
	} else {
		const destDir = path.dirname( dest );
		if ( ! fs.existsSync( destDir ) ) {
			fs.mkdirSync( destDir, { recursive: true } );
		}
		fs.copyFileSync( src, dest );
	}
}

console.log(
	'📦 Préparation du répertoire temporaire et copie des fichiers...'
);
const rootEntries = fs.readdirSync( rootDir );
for ( const entry of rootEntries ) {
	copyFiltered(
		path.join( rootDir, entry ),
		path.join( tempDestDir, entry )
	);
}

console.log( '🤐 Création du ZIP...' );
try {
	const zip = new AdmZip();
	zip.addLocalFolder( tempDestDir, pluginSlug );
	zip.writeZip( path.join( rootDir, zipName ) );
} catch ( error ) {
	console.error( '❌ Erreur lors de la création du ZIP :', error );
	fs.rmSync( buildDir, { recursive: true, force: true } );
	process.exit( 1 );
}

console.log( '🧹 Nettoyage des fichiers temporaires...' );
if ( fs.existsSync( buildDir ) ) {
	fs.rmSync( buildDir, { recursive: true, force: true } );
}

console.log( `✅ Package créé avec succès : ${ zipName }` );
