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

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
