<?php
/**
 * Repository pour l'accès aux données média et métadonnées SQL directes.
 *
 * @package EG_MEDIA\Repositories
 */

declare(strict_types=1);

namespace EG_MEDIA\Repositories;

use EG_MEDIA\Services\Image\Processor;

/**
 * Classe MediaRepository
 *
 * Isole les requêtes directes $wpdb du reste de l'application.
 */
class MediaRepository {

	/**
	 * Compte le nombre d'images non encore optimisées.
	 *
	 * @return int Nombre d'attachements non traités.
	 */
	public function get_unoptimized_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} AS p
				LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_eg_media_optimized'
				WHERE p.post_type = 'attachment'
				AND p.post_mime_type IN (%s, %s, %s, %s)
				AND p.post_status = 'inherit'
				AND pm.post_id IS NULL",
				'image/jpeg',
				'image/png',
				'image/webp',
				'image/avif'
			)
		);
	}

	/**
	 * Supprime la métadonnée d'optimisation pour tous les médias.
	 *
	 * @return int|false Nombre de lignes affectées ou false en cas d'erreur.
	 */
	public function reset_all_optimization_meta(): int|false {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_eg_media_optimized'
			)
		);
	}

	/**
	 * Supprime les entrées de transients liés à Piwigo dans la table wp_options.
	 *
	 * @return void
	 */
	public function clear_piwigo_transients(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_eg_media_piwigo_album_imgs_v2_%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_eg_media_piwigo_album_imgs_v2_%'
			)
		);
	}
}
