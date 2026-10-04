<?php
/**
 * Service pour l'intégration de Piwigo.
 *
 * @package    EG_MEDIA
 * @subpackage Services
 * @author     EG
 */

declare(strict_types=1);

namespace EG_MEDIA\Services;

use EG_MEDIA\Repositories\MediaRepository;
use EG_MEDIA\Utils\Logger;

/**
 * Classe Piwigo.
 * Gère les appels à l'API de Piwigo avec mise en cache par Transients.
 */
class Piwigo {

	/**
	 * Durée de vie du cache par défaut (1 heure).
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Récupère l'URL de l'API de Piwigo nettoyée.
	 *
	 * @return string URL de l'API ws.php ou vide.
	 */
	public function get_api_url(): string {
		$url = (string) get_option( 'eg_media_piwigo_url', '' );
		if ( empty( $url ) ) {
			return '';
		}

		// S'assurer que l'URL se termine par ws.php.
		$url = rtrim( $url, '/' );
		if ( ! str_ends_with( strtolower( $url ), 'ws.php' ) ) {
			$url .= '/ws.php';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Récupère la clé API Piwigo.
	 *
	 * @return string Clé API.
	 */
	public function get_api_key(): string {
		return trim( (string) get_option( 'eg_media_piwigo_api_key', '' ) );
	}

	/**
	 * Effectue une requête HTTP POST vers l'API de Piwigo.
	 *
	 * @param string               $method Méthode API Piwigo (ex: pwg.categories.getList).
	 * @param array<string, mixed> $body_args Arguments du corps de la requête.
	 * @return array<string, mixed>|null Résultat de la requête décodé, ou null en cas d'erreur.
	 */
	private function make_request( string $method, array $body_args = array() ): ?array {
		$api_url = $this->get_api_url();
		$api_key = $this->get_api_key();

		if ( empty( $api_url ) ) {
			return null;
		}

		$api_url = add_query_arg( 'format', 'json', $api_url );

		$headers = array(
			'Accept' => 'application/json',
		);

		$api_secret = trim( (string) get_option( 'eg_media_piwigo_api_secret', '' ) );

		if ( ! empty( $api_key ) && ! empty( $api_secret ) ) {
			$headers['X-PIWIGO-API'] = $api_key . ':' . $api_secret;
		} elseif ( ! empty( $api_key ) ) {
			$headers['X-PIWIGO-API'] = $api_key;
		}

		$body = array_merge(
			array(
				'method' => $method,
				'format' => 'json',
			),
			$body_args
		);

		$args = array(
			'body'      => $body,
			'headers'   => $headers,
			'timeout'   => 15,
			'sslverify' => true,
		);

		$response = wp_remote_post( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::error( 'Piwigo API Error: ' . $response->get_error_message() );
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			Logger::error( 'Piwigo API HTTP Error Code: ' . $response_code );
			return null;
		}

		$response_body = wp_remote_retrieve_body( $response );
		if ( empty( $response_body ) ) {
			Logger::error( 'Piwigo API Empty Response Body' );
			return null;
		}

		$data = json_decode( $response_body, true );
		if ( ! is_array( $data ) ) {
			Logger::error( 'Piwigo API JSON Decode Error. Response body: ' . substr( $response_body, 0, 1000 ) );
			return null;
		}

		if ( 'ok' !== ( $data['stat'] ?? '' ) ) {
			Logger::error( 'Piwigo API Error Response: ' . (string) wp_json_encode( $data ) );
			return null;
		}

		return $data;
	}

	/**
	 * Récupère la liste des albums (categories) depuis Piwigo.
	 *
	 * @param bool $force_refresh Si vrai, force l'appel API en contournant le cache.
	 * @return array<int, array<string, mixed>> Liste des albums.
	 */
	public function get_albums( bool $force_refresh = false ): array {
		$cache_key = 'eg_media_piwigo_albums';

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$data = $this->make_request( 'pwg.categories.getList' );
		if ( null === $data || ! isset( $data['result']['categories'] ) ) {
			return array();
		}

		$albums = array();
		foreach ( $data['result']['categories'] as $category ) {
			if ( isset( $category['id'], $category['name'] ) ) {
				$albums[] = array(
					'id'   => (int) $category['id'],
					'name' => (string) $category['name'],
				);
			}
		}

		set_transient( $cache_key, $albums, self::CACHE_TTL );

		return $albums;
	}

	/**
	 * Récupère la liste des images d'un album Piwigo.
	 *
	 * @param int  $album_id ID de l'album Piwigo.
	 * @param bool $force_refresh Si vrai, force l'appel API en contournant le cache.
	 * @return array<int, array<string, mixed>> Liste des images formatées.
	 */
	public function get_album_images( int $album_id, bool $force_refresh = false ): array {
		if ( $album_id <= 0 ) {
			return array();
		}

		$cache_key = 'eg_media_piwigo_album_imgs_v2_' . $album_id;

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$data = $this->make_request(
			'pwg.categories.getImages',
			array(
				'cat_id'   => $album_id,
				'per_page' => 500, // On récupère un maximum d'images de l'album.
			)
		);

		if ( null === $data || ! isset( $data['result']['images'] ) ) {
			return array();
		}

		$images = array();
		foreach ( $data['result']['images'] as $image ) {
			if ( ! isset( $image['id'] ) ) {
				continue;
			}

			$images[] = array(
				'id'             => (int) $image['id'],
				'name'           => (string) ( $image['name'] ?? '' ),
				'file'           => (string) ( $image['file'] ?? '' ),
				'date_creation'  => (string) ( $image['date_creation'] ?? '' ),
				'date_available' => (string) ( $image['date_available'] ?? '' ),
				'element_url'    => (string) ( $image['element_url'] ?? '' ),
				'width'          => (int) ( $image['width'] ?? 150 ),
				'height'         => (int) ( $image['height'] ?? 150 ),
				'derivatives'    => $image['derivatives'] ?? array(),
			);
		}

		set_transient( $cache_key, $images, self::CACHE_TTL );

		return $images;
	}

	/**
	 * Récupère les informations d'une image spécifique de Piwigo.
	 *
	 * @param int $image_id ID de l'image.
	 * @return array<string, mixed>|null Infos de l'image.
	 */
	public function get_image_info( int $image_id ): ?array {
		if ( $image_id <= 0 ) {
			return null;
		}

		$data = $this->make_request(
			'pwg.images.getInfo',
			array( 'image_id' => $image_id )
		);

		if ( null === $data || ! isset( $data['result'] ) ) {
			return null;
		}

		return $data['result'];
	}

	/**
	 * Invalide le cache d'un album Piwigo spécifique.
	 *
	 * @param int $album_id ID de l'album Piwigo.
	 * @return void
	 */
	public function clear_album_cache( int $album_id ): void {
		if ( $album_id <= 0 ) {
			return;
		}
		delete_transient( 'eg_media_piwigo_album_imgs_v2_' . $album_id );
		/**
		 * Déclenché lorsqu'un cache d'album Piwigo est invalidé.
		 *
		 * @param int $album_id ID de l'album.
		 */
		do_action( 'eg_media_piwigo_album_cache_cleared', $album_id );
	}

	/**
	 * Supprime tous les caches Transients liés à Piwigo.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( 'eg_media_piwigo_albums' );

		$repository = new MediaRepository();
		$repository->clear_piwigo_transients();

		/**
		 * Déclenché lorsque l'intégralité du cache Piwigo est nettoyé.
		 */
		do_action( 'eg_media_piwigo_cache_cleared' );
	}
}
