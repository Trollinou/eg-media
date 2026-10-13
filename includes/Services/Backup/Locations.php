<?php
/**
 * Service de déclaration des emplacements de sauvegarde pour EG Backup & Restore.
 *
 * @package EG_MEDIA\Services\Backup
 */

declare(strict_types=1);

namespace EG_MEDIA\Services\Backup;

/**
 * Class Locations
 *
 * Enregistre les répertoires physiques spécifiques de EG Media Manager via le hook
 * 'eg_backup_registered_locations' pour inclusion automatique dans les sauvegardes.
 */
class Locations {

	/**
	 * Sous-répertoire relatif à wp-content/uploads pour les données du plugin.
	 */
	public const string SUBDIR_BASE = 'eg-media';

	/**
	 * Sous-répertoire relatif à wp-content/uploads pour les images originales.
	 */
	public const string SUBDIR_ORIGINALS = 'eg-media/originals';

	/**
	 * Enregistre les hooks WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'eg_backup_registered_locations', array( $this, 'register_backup_locations' ) );
	}

	/**
	 * Fournit le chemin absolu vers le répertoire central de stockage de EG Media.
	 *
	 * @return string Chemin absolu vers wp-content/uploads/eg-media.
	 */
	public static function get_base_dir(): string {
		$upload_dir = wp_upload_dir();
		$basedir    = (string) ( $upload_dir['basedir'] ?? '' );

		return '' !== $basedir ? $basedir . '/' . self::SUBDIR_BASE : '';
	}

	/**
	 * Fournit le chemin absolu vers le répertoire des images originales.
	 *
	 * @return string Chemin absolu vers wp-content/uploads/eg-media/originals.
	 */
	public static function get_originals_dir(): string {
		$upload_dir = wp_upload_dir();
		$basedir    = (string) ( $upload_dir['basedir'] ?? '' );

		return '' !== $basedir ? $basedir . '/' . self::SUBDIR_ORIGINALS : '';
	}

	/**
	 * Déclare les emplacements physiques de EG Media auprès de EG Backup & Restore.
	 *
	 * @param array<string, array<string, mixed>> $locations Liste des emplacements déjà enregistrés.
	 * @return array<string, array<string, mixed>> Liste enrichie des emplacements de EG Media.
	 */
	public function register_backup_locations( array $locations ): array {
		$base_dir      = self::get_base_dir();
		$originals_dir = self::get_originals_dir();

		if ( '' === $base_dir || '' === $originals_dir ) {
			return $locations;
		}

		$locations['eg_media_originals'] = array(
			'id'          => 'eg_media_originals',
			'label'       => __( 'EG Media — Sauvegarde des Images Originales', 'eg-media' ),
			'plugin_name' => 'EG Media Manager',
			'path'        => $originals_dir,
			'category'    => 'media',
			'recommended' => true,
			'description' => __( 'Copies de sécurité des fichiers images originaux conservées avant optimisation et compression destructive.', 'eg-media' ),
			'exclude'     => array(
				'*.tmp',
				'temp',
				'.cache',
			),
		);

		$locations['eg_media_data'] = array(
			'id'          => 'eg_media_data',
			'label'       => __( 'EG Media — Données et Galeries', 'eg-media' ),
			'plugin_name' => 'EG Media Manager',
			'path'        => $base_dir,
			'category'    => 'documents',
			'recommended' => true,
			'description' => __( 'Dossier central des données, exports et fichiers générés par EG Media Manager.', 'eg-media' ),
			'exclude'     => array(
				'*.tmp',
				'temp',
				'.cache',
				'transients',
			),
		);

		return $locations;
	}
}
