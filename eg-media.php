<?php
/**
 * Plugin Name:       EG Media Manager
 * Plugin URI:        https://example.com/eg-media
 * Description:       Gestionnaire de Média by EG
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      8.4
 * Author:            EG
 * Author URI:        https://example.com
 * License:           Proprietary
 * Text Domain:       eg-media
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Version du plugin.
define( 'EG_MEDIA_VERSION', '1.0.2' );

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

	$eg_media_fields = new \EG_MEDIA\Admin\MediaFields();
	$eg_media_fields->register();

	$eg_media_filter = new \EG_MEDIA\Admin\MediaFilter();
	$eg_media_filter->register();

	$eg_media_upload = new \EG_MEDIA\Admin\MediaUpload();
	$eg_media_upload->register();
} );

