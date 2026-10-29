<?php
/**
 * Nettoyage propre des options et caches lors de la désinstallation du plugin EG Media Manager.
 *
 * @package EG_Media
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$eg_media_options = [
	'eg_media_resize_max_width',
	'eg_media_compression_quality',
	'eg_media_png_compression',
	'eg_media_unsharp_mask',
	'eg_media_auto_orient',
	'eg_media_chrominance',
	'eg_media_interlace',
	'eg_media_backup_originals',
	'eg_media_piwigo_url',
	'eg_media_piwigo_api_key',
	'eg_media_piwigo_api_secret',
	'eg_media_processed_count',
	'eg_media_bytes_saved',
];

foreach ( $eg_media_options as $eg_media_option ) {
	delete_option( $eg_media_option );
}

delete_transient( 'eg_media_unoptimized_count' );
delete_transient( 'eg_media_piwigo_albums' );

if ( function_exists( 'wp_cache_flush_group' ) ) {
	wp_cache_flush_group( 'eg_media_cache' );
}
