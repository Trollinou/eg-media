<?php
/**
 * Service de traitement en lot des médias.
 *
 * @package EG_MEDIA\Services\Image
 */

declare(strict_types=1);

namespace EG_MEDIA\Services\Image;

use WP_Query;

/**
 * Class BulkProcessor
 *
 * Gère l'optimisation en masse des images existantes via AJAX.
 */
class BulkProcessor {

	/**
	 * Enregistre l'action AJAX pour l'optimisation en masse.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_eg_media_process_bulk_batch', [ $this, 'eg_media_process_bulk_batch' ] );
	}

	/**
	 * Compte le nombre total de médias restants à optimiser (JPEG, PNG, WebP, AVIF).
	 *
	 * @return int Nombre d'images non optimisées.
	 */
	public function get_unoptimized_count(): int {
		$count = get_transient( 'eg_media_unoptimized_count' );

		if ( false === $count ) {
			global $wpdb;

			$mimes_in = "'" . implode( "','", array_map( 'esc_sql', Processor::SUPPORTED_MIMES ) ) . "'";

			$count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} AS p
				LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_eg_media_optimized'
				WHERE p.post_type = 'attachment'
				AND p.post_mime_type IN ({$mimes_in})
				AND p.post_status = 'inherit'
				AND pm.post_id IS NULL"
			);

			set_transient( 'eg_media_unoptimized_count', $count, 12 * HOUR_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Action AJAX pour traiter un lot de 5 images.
	 *
	 * @return void
	 */
	public function eg_media_process_bulk_batch(): void {
		check_ajax_referer( 'eg-media-bulk-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => esc_html__( "Vous n'avez pas les permissions nécessaires.", 'eg-media' ),
			] );
		}

		$query = new WP_Query( [
			'post_type'      => 'attachment',
			'post_mime_type' => Processor::SUPPORTED_MIMES,
			'post_status'    => 'inherit',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => '_eg_media_optimized',
					'compare' => 'NOT EXISTS',
				],
			],
		] );

		/** @var int[] $attachment_ids */
		$attachment_ids = $query->posts;
		$processed_in_this_batch = 0;
		$bytes_saved_in_this_batch = 0;

		$processor = new Processor();

		foreach ( $attachment_ids as $id ) {
			$file_path = get_attached_file( $id );

			if ( is_string( $file_path ) && '' !== $file_path && file_exists( $file_path ) ) {
				$bytes_saved = $processor->optimize_image_file( $file_path );

				if ( null !== $bytes_saved ) {
					$processed_in_this_batch++;
					if ( $bytes_saved > 0 ) {
						$bytes_saved_in_this_batch += $bytes_saved;
					}

					// Mettre à jour les dimensions de l'image dans les métadonnées WP
					$metadata = wp_get_attachment_metadata( $id );
					if ( is_array( $metadata ) ) {
						$image_size = @getimagesize( $file_path );
						if ( is_array( $image_size ) ) {
							$metadata['width']  = (int) $image_size[0];
							$metadata['height'] = (int) $image_size[1];
							wp_update_attachment_metadata( $id, $metadata );
						}
					}
				}
			}

			// Toujours marquer comme optimisé pour éviter une boucle infinie
			update_post_meta( $id, '_eg_media_optimized', '1' );
		}

		// Mettre à jour les statistiques globales
		if ( $processed_in_this_batch > 0 ) {
			$total_processed = (int) get_option( 'eg_media_processed_count', 0 );
			update_option( 'eg_media_processed_count', $total_processed + $processed_in_this_batch );

			$current_unoptimized = get_transient( 'eg_media_unoptimized_count' );
			if ( false !== $current_unoptimized ) {
				$new_unoptimized = max( 0, (int) $current_unoptimized - $processed_in_this_batch );
				set_transient( 'eg_media_unoptimized_count', $new_unoptimized, 12 * HOUR_IN_SECONDS );
			}
		}

		if ( $bytes_saved_in_this_batch > 0 ) {
			$total_saved = (int) get_option( 'eg_media_bytes_saved', 0 );
			update_option( 'eg_media_bytes_saved', $total_saved + $bytes_saved_in_this_batch );
		}

		$remaining = $this->get_unoptimized_count();

		wp_send_json_success( [
			'remaining' => $remaining,
			'processed' => $processed_in_this_batch,
		] );
	}
}
