<?php
/**
 * Utilitaire de journalisation (Logger) pour EG Media Manager.
 *
 * @package EG_MEDIA\Utils
 */

declare(strict_types=1);

namespace EG_MEDIA\Utils;

/**
 * Classe Logger
 *
 * Encapsule les messages d'erreur et d'avertissement pour le débogage WordPress.
 */
final class Logger {

	/**
	 * Logue un message d'erreur si le débogage WordPress est actif.
	 *
	 * @param string $message Message d'erreur à journaliser.
	 * @return void
	 */
	public static function error( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[EG Media Manager] ' . $message );
		}
	}

	/**
	 * Logue un message de débogage si le mode debug est activé.
	 *
	 * @param string $message Message de débogage.
	 * @return void
	 */
	public static function debug( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[EG Media Manager DEBUG] ' . $message );
		}
	}
}
