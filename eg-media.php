<?php
/**
 * Plugin Name:       EG Media Manager
 * Plugin URI:        https://example.com/eg-media
 * Description:       Gestionnaire de Média by EG
 * Version:           1.1.5
 * Requires at least: 7.1
 * Requires PHP:      8.4
 * Author:            Etienne Gagnon
 * Author URI:        https://github.com/Trollinou/eg-media
 * License:           GPLv2 or later
 * Text Domain:       eg-media
 * Domain Path:       /languages
 *
 * @package EG_Media
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Version du plugin.
define( 'EG_MEDIA_VERSION', '1.1.5' );

// Autoloader SPL natif pour le namespace EG_MEDIA.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'EG_MEDIA\\';
		$base_dir = plugin_dir_path( __FILE__ ) . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Démarrage du plugin via l'orchestrateur de cycle de vie.
\EG_MEDIA\Core\Plugin::boot();
