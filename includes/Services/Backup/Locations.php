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
 * Gère la préparation du répertoire de sauvegarde des images originales et son enregistrement
 * conditionnel via le hook 'eg_backup_registered_locations' selon les préférences de l'utilisateur.
 */
class Locations {

	/**
	 * Sous-répertoire relatif à wp-content/uploads pour les images originales.
	 */
	public const string SUBDIR_ORIGINALS = 'eg-media/originals';

	/**
	 * Enregistre les hooks WordPress si l'utilisateur a activé la sauvegarde des originaux.
	 *
	 * @return void
	 */
	public function register(): void {
		$backup_originals = (bool) get_option( 'eg_media_backup_originals', true );

		// Si l'utilisateur ne souhaite pas conserver les originaux, le hook n'est pas publié.
		if ( ! $backup_originals ) {
			return;
		}

		add_filter( 'eg_backup_registered_locations', [ $this, 'register_backup_locations' ] );
	}

	/**
	 * Déclenché lors de l'activation du plugin pour préparer le répertoire des originaux si l'option est active.
	 *
	 * @return void
	 */
	public static function on_activation(): void {
		$backup_originals = (bool) get_option( 'eg_media_backup_originals', true );

		if ( $backup_originals ) {
			self::ensure_directory_exists();
		}
	}

	/**
	 * S'assure de l'existence du répertoire de stockage des originaux avec un fichier index.php de protection.
	 *
	 * @return bool Vrai si le répertoire existe ou a été créé avec succès, faux sinon.
	 */
	public static function ensure_directory_exists(): bool {
		$originals_dir = self::get_originals_dir();
		if ( '' === $originals_dir ) {
			return false;
		}

		if ( ! is_dir( $originals_dir ) ) {
			if ( ! wp_mkdir_p( $originals_dir ) ) {
				return false;
			}
		}

		$index_file = $originals_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct creation of empty stub.
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		return true;
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
	 * Déclare l'emplacement des originaux auprès de EG Backup & Restore si l'option est active et le dossier présent.
	 *
	 * @param array<string, array<string, mixed>> $locations Liste des emplacements déjà enregistrés.
	 * @return array<string, array<string, mixed>> Liste enrichie de l'emplacement des originaux.
	 */
	public function register_backup_locations( array $locations ): array {
		$backup_originals = (bool) get_option( 'eg_media_backup_originals', true );
		if ( ! $backup_originals ) {
			return $locations;
		}

		$originals_dir = self::get_originals_dir();
		if ( '' === $originals_dir || ! is_dir( $originals_dir ) ) {
			return $locations;
		}

		$locations['eg_media_originals'] = [
			'id'          => 'eg_media_originals',
			'label'       => __( 'EG Media — Sauvegarde des Images Originales', 'eg-media' ),
			'plugin_name' => 'EG Media Manager',
			'path'        => $originals_dir,
			'category'    => 'media',
			'recommended' => true,
			'description' => __( 'Copies de sécurité des fichiers images originaux conservées avant optimisation et compression destructive.', 'eg-media' ),
			'exclude'     => [
				'*.tmp',
				'temp',
				'.cache',
			],
		];

		return $locations;
	}
}
