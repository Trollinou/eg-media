<?php
/**
 * Points de terminaison REST API pour l'intégration Piwigo.
 *
 * @package    EG_MEDIA
 * @subpackage API
 * @author     EG
 */

declare(strict_types=1);

namespace EG_MEDIA\API;

use EG_MEDIA\Services\Piwigo as PiwigoService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Classe Piwigo REST API.
 */
class Piwigo {

	/**
	 * Instance du service Piwigo.
	 *
	 * @var PiwigoService
	 */
	private PiwigoService $piwigo_service;

	/**
	 * Constructeur.
	 */
	public function __construct() {
		$this->piwigo_service = new PiwigoService();
	}

	/**
	 * Enregistre les routes REST API de WordPress.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'eg-media/v1',
			'/piwigo/albums',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_piwigo_albums' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'eg-media/v1',
			'/piwigo/album-images',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_piwigo_album_images' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'eg-media/v1',
			'/piwigo/import-featured-image',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'import_featured_image' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'eg-media/v1',
			'/piwigo/import-image',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'import_image' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Callback pour récupérer les albums de Piwigo.
	 *
	 * @param WP_REST_Request $request La requête REST WordPress.
	 * @return WP_REST_Response|WP_Error La réponse REST ou une erreur.
	 */
	public function get_piwigo_albums( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
		$force_refresh = '1' === $request->get_param( 'force' );
		$albums = $this->piwigo_service->get_albums( $force_refresh );

		return new WP_REST_Response( $albums, 200 );
	}

	/**
	 * Callback pour récupérer les images d'un album Piwigo.
	 *
	 * @param WP_REST_Request $request La requête REST WordPress.
	 * @return WP_REST_Response|WP_Error La réponse REST ou une erreur.
	 */
	public function get_piwigo_album_images( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
		$album_id = (int) $request->get_param( 'id' );
		if ( $album_id <= 0 ) {
			return new WP_Error( 'invalid_id', 'ID d\'album invalide ou manquant.', [ 'status' => 400 ] );
		}

		$images = $this->piwigo_service->get_album_images( $album_id );
		return new WP_REST_Response( $images, 200 );
	}

	/**
	 * Télécharge et importe une image Piwigo dans la médiathèque locale.
	 * Associe également le média à une galerie locale nommée "Piwigo - [Nom de l'album]".
	 *
	 * @param int    $piwigo_image_id ID de l'image sur Piwigo.
	 * @param int    $post_id         ID du post auquel associer le téléversement.
	 * @param string $album_name      Nom de l'album Piwigo pour catégorisation.
	 * @return int|\WP_Error ID de la pièce jointe créée ou erreur.
	 */
	private function import_piwigo_image_to_media_library( int $piwigo_image_id, int $post_id, string $album_name = '' ) : int|\WP_Error {
		// Récupérer les informations de l'image sur Piwigo
		$image_info = $this->piwigo_service->get_image_info( $piwigo_image_id );
		if ( ! is_array( $image_info ) || empty( $image_info['element_url'] ) ) {
			return new \WP_Error( 'piwigo_error', 'Impossible de récupérer les informations de l\'image depuis Piwigo.' );
		}

		$image_url = (string) $image_info['element_url'];
		$image_name = (string) ( $image_info['name'] ?? $image_info['file'] ?? 'piwigo-image' );

		// Charger les utilitaires de WordPress
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Télécharger l'image dans un dossier temporaire
		$tmp_file = download_url( $image_url );
		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Préparer le fichier simulé pour sideload
		$file_array = [
			'name'     => basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) ) ?: 'image.jpg',
			'tmp_name' => $tmp_file,
		];

		// Insérer dans la médiathèque
		$attachment_id = media_handle_sideload( $file_array, $post_id, $image_name );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_file );
			return $attachment_id;
		}

		// Rangement dans la galerie locale "Piwigo - [Nom de l'album]"
		if ( ! empty( $album_name ) ) {
			$gallery_name = 'Piwigo - ' . $album_name;
			$term = get_term_by( 'name', $gallery_name, 'eg_media_gallery' );

			if ( ! $term ) {
				$new_term = wp_insert_term( $gallery_name, 'eg_media_gallery' );
				if ( is_array( $new_term ) && isset( $new_term['term_id'] ) ) {
					$term_id = (int) $new_term['term_id'];
				} else {
					$term_id = 0;
				}
			} else {
				$term_id = $term->term_id;
			}

			if ( $term_id > 0 ) {
				wp_set_object_terms( $attachment_id, [ $term_id ], 'eg_media_gallery' );
			}
		}

		return $attachment_id;
	}

	/**
	 * Callback pour importer une image Piwigo et la définir comme image mise en avant.
	 *
	 * @param WP_REST_Request $request La requête REST WordPress.
	 * @return WP_REST_Response|WP_Error La réponse REST ou une erreur.
	 */
	public function import_featured_image( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
		$post_id         = (int) $request->get_param( 'post_id' );
		$piwigo_image_id = (int) $request->get_param( 'piwigo_image_id' );
		$album_name      = sanitize_text_field( (string) $request->get_param( 'album_name' ) );

		if ( $post_id <= 0 || $piwigo_image_id <= 0 ) {
			return new WP_Error( 'invalid_params', 'Paramètres post_id ou piwigo_image_id manquants.', [ 'status' => 400 ] );
		}

		$attachment_id = $this->import_piwigo_image_to_media_library( $piwigo_image_id, $post_id, $album_name );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'import_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Définir comme image mise en avant
		$set_thumbnail = set_post_thumbnail( $post_id, $attachment_id );
		if ( ! $set_thumbnail ) {
			return new WP_Error( 'thumbnail_association_failed', 'Impossible d\'associer l\'image mise en avant au post.', [ 'status' => 500 ] );
		}

		$response_data = [
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
		];

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Callback pour importer simplement une image Piwigo.
	 *
	 * @param WP_REST_Request $request La requête REST WordPress.
	 * @return WP_REST_Response|WP_Error La réponse REST ou une erreur.
	 */
	public function import_image( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
		$post_id         = (int) $request->get_param( 'post_id' );
		$piwigo_image_id = (int) $request->get_param( 'piwigo_image_id' );
		$album_name      = sanitize_text_field( (string) $request->get_param( 'album_name' ) );

		if ( $piwigo_image_id <= 0 ) {
			return new WP_Error( 'invalid_params', 'Paramètre piwigo_image_id manquant.', [ 'status' => 400 ] );
		}

		$attachment_id = $this->import_piwigo_image_to_media_library( $piwigo_image_id, $post_id, $album_name );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'import_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		$response_data = [
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
		];

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Vérifie les permissions de l'utilisateur pour appeler l'API REST.
	 *
	 * @return bool Vrai si l'utilisateur a les droits.
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}
}
