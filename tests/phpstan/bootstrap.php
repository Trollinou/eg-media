<?php
/**
 * Bootstrap for Unit Tests and PHPStan.
 *
 * @package EG_Media
 */

declare(strict_types=1);

if ( file_exists( dirname( __DIR__, 2 ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! defined( 'EG_MEDIA_VERSION' ) ) {
	define( 'EG_MEDIA_VERSION', '1.1.5' );
}

if ( ! defined( 'EG_MEDIA_FILE' ) ) {
	define( 'EG_MEDIA_FILE', dirname( __DIR__, 2 ) . '/eg-media.php' );
}

if ( ! defined( 'EG_MEDIA_DIR' ) ) {
	define( 'EG_MEDIA_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'EG_MEDIA_URL' ) ) {
	define( 'EG_MEDIA_URL', 'http://example.com/wp-content/plugins/eg-media/' );
}

