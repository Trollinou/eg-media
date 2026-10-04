<?php
/**
 * Utilitaire de gestion des requêtes HTTP (Request Helper).
 *
 * @package EG_MEDIA\Utils
 */

declare(strict_types=1);

namespace EG_MEDIA\Utils;

/**
 * Classe Request
 *
 * Fournit des méthodes sécurisées et typées pour accéder aux données des requêtes ($_POST, $_GET, $_REQUEST).
 */
final class Request {

	/**
	 * Récupère une chaîne de caractères depuis $_POST, nettoyée et typée.
	 *
	 * @param string $key Clé de la variable.
	 * @param string $default_value Valeur par défaut si absente.
	 * @return string Valeur nettoyée.
	 */
	public static function post_string( string $key, string $default_value = '' ): string {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default_value;
		}

		$raw = wp_unslash( $_POST[ $key ] );
		if ( is_string( $raw ) || is_numeric( $raw ) ) {
			return sanitize_text_field( (string) $raw );
		}

		return $default_value;
	}

	/**
	 * Récupère un tableau depuis $_POST, nettoyé.
	 *
	 * @param string               $key Clé de la variable.
	 * @param array<string, mixed> $default_value Valeur par défaut si absente.
	 * @return array<string, mixed> Tableau nettoyé ou vide.
	 */
	public static function post_array( string $key, array $default_value = array() ): array {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return $default_value;
		}

		$raw = wp_unslash( $_POST[ $key ] );
		return is_array( $raw ) ? $raw : $default_value;
	}

	/**
	 * Récupère une chaîne de caractères depuis $_GET, nettoyée et typée.
	 *
	 * @param string $key Clé de la variable.
	 * @param string $default_value Valeur par défaut si absente.
	 * @return string Valeur nettoyée.
	 */
	public static function get_string( string $key, string $default_value = '' ): string {
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default_value;
		}

		$raw = wp_unslash( $_GET[ $key ] );
		if ( is_string( $raw ) || is_numeric( $raw ) ) {
			return sanitize_text_field( (string) $raw );
		}

		return $default_value;
	}

	/**
	 * Récupère une chaîne de caractères depuis $_REQUEST, nettoyée et typée.
	 *
	 * @param string $key Clé de la variable.
	 * @param string $default_value Valeur par défaut si absente.
	 * @return string Valeur nettoyée.
	 */
	public static function request_string( string $key, string $default_value = '' ): string {
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return $default_value;
		}

		$raw = wp_unslash( $_REQUEST[ $key ] );
		if ( is_string( $raw ) || is_numeric( $raw ) ) {
			return sanitize_text_field( (string) $raw );
		}

		return $default_value;
	}
}
