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
	 * Groupe de cache Object Cache du plugin.
	 */
	public const string CACHE_GROUP = 'eg_media_cache';

	/**
	 * Compte le nombre d'images non encore optimisées.
	 *
	 * @return int Nombre d'attachements non traités.
	 */
	public function get_unoptimized_count(): int {
		$cached = wp_cache_get( 'unoptimized_count', self::CACHE_GROUP );
		if ( false !== $cached && is_numeric( $cached ) ) {
			return (int) $cached;
		}

		global $wpdb;

		$count = (int) $wpdb->get_var(
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

		wp_cache_set( 'unoptimized_count', $count, self::CACHE_GROUP, 12 * HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * Invalide le cache du décompte des images non optimisées.
	 *
	 * @return void
	 */
	public static function flush_unoptimized_count_cache(): void {
		wp_cache_delete( 'unoptimized_count', self::CACHE_GROUP );
		delete_transient( 'eg_media_unoptimized_count' );
	}

	/**
	 * Récupère et trie les pièces jointes d'une galerie locale avec mise en cache Object Cache.
	 *
	 * @param int    $gallery_id ID du terme de la taxonomie eg_media_gallery.
	 * @param string $sort_by    Critère de tri ('date' ou 'name').
	 * @param string $sort_order Ordre de tri ('ASC' ou 'DESC').
	 * @return array<int, \WP_Post> Liste triée des pièces jointes.
	 */
	public function get_sorted_gallery_attachments( int $gallery_id, string $sort_by = 'date', string $sort_order = 'ASC' ): array {
		if ( $gallery_id <= 0 ) {
			return [];
		}

		$normalized_sort_by    = 'name' === $sort_by ? 'name' : 'date';
		$normalized_sort_order = 'DESC' === strtoupper( $sort_order ) ? 'DESC' : 'ASC';
		$cache_key             = sprintf( 'gallery_attachments_%d_%s_%s', $gallery_id, $normalized_sort_by, $normalized_sort_order );

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$attachments = get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => 'eg_media_gallery',
						'field'    => 'term_id',
						'terms'    => $gallery_id,
					],
				],
			]
		);

		if ( ! empty( $attachments ) ) {
			usort(
				$attachments,
				static function ( \WP_Post $a, \WP_Post $b ) use ( $normalized_sort_by, $normalized_sort_order ): int {
					if ( 'name' === $normalized_sort_by ) {
						$val_a      = ! empty( $a->post_title ) ? $a->post_title : basename( (string) get_attached_file( $a->ID ) );
						$val_b      = ! empty( $b->post_title ) ? $b->post_title : basename( (string) get_attached_file( $b->ID ) );
						$comparison = strcasecmp( (string) $val_a, (string) $val_b );
					} else {
						$meta_a = wp_get_attachment_metadata( $a->ID );
						$meta_b = wp_get_attachment_metadata( $b->ID );

						$time_a = is_array( $meta_a ) && ! empty( $meta_a['image_meta']['created_timestamp'] )
							? (int) $meta_a['image_meta']['created_timestamp']
							: (int) strtotime( $a->post_date );
						$time_b = is_array( $meta_b ) && ! empty( $meta_b['image_meta']['created_timestamp'] )
							? (int) $meta_b['image_meta']['created_timestamp']
							: (int) strtotime( $b->post_date );

						$comparison = $time_a <=> $time_b;
					}

					return 'DESC' === $normalized_sort_order ? -$comparison : $comparison;
				}
			);
		}

		wp_cache_set( $cache_key, $attachments, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $attachments;
	}

	/**
	 * Invalide les entrées de cache Object Cache d'une galerie locale pour toutes les variantes de tri.
	 *
	 * @param int $gallery_id ID du terme de la taxonomie eg_media_gallery.
	 * @return void
	 */
	public static function flush_gallery_cache( int $gallery_id ): void {
		if ( $gallery_id <= 0 ) {
			return;
		}

		foreach ( [ 'date', 'name' ] as $sort_by ) {
			foreach ( [ 'ASC', 'DESC' ] as $sort_order ) {
				wp_cache_delete( sprintf( 'gallery_attachments_%d_%s_%s', $gallery_id, $sort_by, $sort_order ), self::CACHE_GROUP );
			}
		}
	}

	/**
	 * Supprime la métadonnée d'optimisation pour tous les médias.
	 *
	 * @return int|false Nombre de lignes affectées ou false en cas d'erreur.
	 */
	public function reset_all_optimization_meta(): int|false {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_eg_media_optimized'
			)
		);

		self::flush_unoptimized_count_cache();

		return $result;
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
