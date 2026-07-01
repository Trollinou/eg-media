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
	 * Callback pour importer une image Piwigo et la définir comme image mise en avant.
	 *
	 * @param WP_REST_Request $request La requête REST WordPress.
	 * @return WP_REST_Response|WP_Error La réponse REST ou une erreur.
	 */
	public function import_featured_image( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
		$post_id         = (int) $request->get_param( 'post_id' );
		$piwigo_image_id = (int) $request->get_param( 'piwigo_image_id' );

		if ( $post_id <= 0 || $piwigo_image_id <= 0 ) {
			return new WP_Error( 'invalid_params', 'Paramètres post_id ou piwigo_image_id manquants.', [ 'status' => 400 ] );
		}

		// Récupérer les informations de l'image sur Piwigo
		$image_info = $this->piwigo_service->get_image_info( $piwigo_image_id );
		if ( ! is_array( $image_info ) || empty( $image_info['element_url'] ) ) {
			return new WP_Error( 'piwigo_error', 'Impossible de récupérer les informations de l\'image depuis Piwigo.', [ 'status' => 500 ] );
		}

		$image_url = (string) $image_info['element_url'];
		$image_name = (string) ( $image_info['name'] ?? $image_info['file'] ?? 'piwigo-image' );

		// Charger les utilitaires WordPress pour le téléchargement et l'import de médias
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Télécharger l'image dans un dossier temporaire
		$tmp_file = download_url( $image_url );
		if ( is_wp_error( $tmp_file ) ) {
			return new WP_Error( 'download_failed', 'Échec du téléchargement de l\'image : ' . $tmp_file->get_error_message(), [ 'status' => 500 ] );
		}

		// Préparer le tableau $_FILES simulé pour media_handle_sideload
		$file_array = [
			'name'     => basename( (string) wp_parse_url( $image_url, PHP_URL_PATH ) ) ?: 'image.jpg',
			'tmp_name' => $tmp_file,
		];

		// Insérer le fichier dans la bibliothèque de médias locale
		$attachment_id = media_handle_sideload( $file_array, $post_id, $image_name );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'sideload_failed', 'Échec de l\'enregistrement dans la bibliothèque de médias : ' . $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Définir comme image mise en avant du post
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
	 * Vérifie les permissions de l'utilisateur pour appeler l'API REST.
	 *
	 * @return bool Vrai si l'utilisateur a les droits.
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}
}
