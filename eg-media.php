<?php
/**
 * Plugin Name:       EG Media Manager
 * Plugin URI:        https://example.com/eg-media
 * Description:       Gestionnaire de Média by EG
 * Version:           1.1.2
 * Requires at least: 6.0
 * Requires PHP:      8.4
 * Author:            Etienne Gagnon
 * Author URI:        https://github.com/Trollinou/eg-media
 * License:           GPLv2 or later
 * Text Domain:       eg-media
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Version du plugin.
define( 'EG_MEDIA_VERSION', '1.1.2' );

// Autoloader SPL natif.
spl_autoload_register( function ( string $class ) : void {
	// Prefix du projet
	$prefix = 'EG_MEDIA\\';

	// Base directory pour le prefix (dossier includes/)
	$base_dir = plugin_dir_path( __FILE__ ) . 'includes/';

	// La classe utilise-t-elle le prefix ?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	// Récupérer le nom relatif de la classe
	$relative_class = substr( $class, $len );

	// Remplacer le prefix par le base_dir, remplacer les antislashs par des slashs, ajouter .php
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	// Si le fichier existe, le charger
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Augmenter la limite de mémoire pour le traitement d'images sous WordPress (évite les crashs lors d'uploads en lot).
add_filter( 'image_memory_limit', function () : string {
	return '512M';
} );

// Initialiser et démarrer les services.
$eg_media_image_processor = new \EG_MEDIA\Services\Image\Processor();
$eg_media_image_processor->register();

$eg_media_bulk_processor = new \EG_MEDIA\Services\Image\BulkProcessor();
$eg_media_bulk_processor->register();

$eg_media_dashboard = new \EG_MEDIA\Admin\Dashboard\Main();
$eg_media_dashboard->init();

// Initialisation des galeries et des champs de média au chargement des plugins.
add_action( 'plugins_loaded', function () : void {
	$eg_media_galleries = new \EG_MEDIA\CPT\Galleries();
	$eg_media_galleries->register();

	$eg_media_albums = new \EG_MEDIA\CPT\Albums();
	$eg_media_albums->register();

	$eg_media_album_metabox = new \EG_MEDIA\Admin\AlbumMetabox();
	$eg_media_album_metabox->register();

	$eg_media_album_shortcode = new \EG_MEDIA\Shortcodes\Album();
	$eg_media_album_shortcode->register();

	$eg_media_fields = new \EG_MEDIA\Admin\MediaFields();
	$eg_media_fields->register();

	$eg_media_filter = new \EG_MEDIA\Admin\MediaFilter();
	$eg_media_filter->register();

	$eg_media_upload = new \EG_MEDIA\Admin\MediaUpload();
	$eg_media_upload->register();

	$eg_media_viewer_block = new \EG_MEDIA\Blocks\Viewer();
	$eg_media_viewer_block->register();

	add_action( 'rest_api_init', function () : void {
		$eg_media_piwigo_api = new \EG_MEDIA\API\Piwigo();
		$eg_media_piwigo_api->register_routes();
	} );
} );

// Enregistrement des scripts et styles publics pour les Albums
add_action( 'wp_enqueue_scripts', function() : void {
	wp_register_style(
		'eg-media-public-album',
		plugins_url( 'assets/css/public-album.css', __FILE__ ),
		[],
		EG_MEDIA_VERSION
	);
	wp_register_script(
		'eg-media-public-album',
		plugins_url( 'assets/js/public-album.js', __FILE__ ),
		[],
		EG_MEDIA_VERSION,
		true
	);
} );

