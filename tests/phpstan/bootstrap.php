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
	define( 'EG_MEDIA_VERSION', '1.3.1' );
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

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/**
	 * Stub for wp_upload_dir.
	 *
	 * @return array<string, mixed>
	 */
	function wp_upload_dir(): array {
		return array(
			'path'    => '/tmp/wp-content/uploads/2026/10',
			'url'     => 'http://example.com/wp-content/uploads/2026/10',
			'subdir'  => '/2026/10',
			'basedir' => '/tmp/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		global $wp_test_options;
		return $wp_test_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value ): bool {
		global $wp_test_options;
		$wp_test_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( string $target ): bool {
		return is_dir( $target ) || mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $file, callable $callback ): void {}
}


