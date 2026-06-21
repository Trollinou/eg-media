<?php
declare(strict_types=1);

namespace EG_MEDIA\Services\Image;

/**
 * Class Processor
 *
 * Gère le retraitement et l'optimisation automatique des images JPEG, PNG et WebP lors de leur téléversement.
 *
 * @package EG_MEDIA\Services\Image
 */
class Processor {

	/**
	 * Enregistre le hook de traitement d'image.
	 *
	 * @return void
	 */
	public function register() : void {
		add_filter( 'wp_handle_upload', [ $this, 'process_upload' ] );
		add_action( 'add_attachment', [ $this, 'flag_new_attachment_as_optimized' ] );
	}

	/**
	 * Marque un nouvel attachement comme optimisé s'il s'agit d'une image gérée par le plugin.
	 *
	 * @param int $post_id L'ID de l'attachement créé.
	 * @return void
	 */
	public function flag_new_attachment_as_optimized( int $post_id ) : void {
		$mime_type = get_post_mime_type( $post_id );
		$allowed_mimes = [ 'image/jpeg', 'image/png', 'image/webp' ];

		if ( is_string( $mime_type ) && in_array( $mime_type, $allowed_mimes, true ) ) {
			update_post_meta( $post_id, '_eg_media_optimized', '1' );
		}
	}

	/**
	 * Traite et optimise l'image (JPEG, PNG, WebP) après son téléversement si Imagick est disponible.
	 *
	 * @param array<string, mixed> $upload Les informations du fichier téléversé.
	 * @return array<string, mixed> Les informations éventuellement modifiées ou d'origine.
	 */
	public function process_upload( array $upload ) : array {
		// S'il y a déjà une erreur lors du téléversement, on ignore.
		if ( isset( $upload['error'] ) && ! empty( $upload['error'] ) ) {
			return $upload;
		}

		$file_path = $upload['file'] ?? '';
		$mime_type = $upload['type'] ?? '';

		// On applique le traitement uniquement sur les fichiers JPEG, PNG et WebP.
		$allowed_mimes = [ 'image/jpeg', 'image/png', 'image/webp' ];
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			return $upload;
		}

		// Si le chemin du fichier est vide ou si le fichier n'existe pas physiquement.
		if ( ! is_string( $file_path ) || '' === $file_path || ! file_exists( $file_path ) ) {
			return $upload;
		}

		$bytes_saved = $this->optimize_image_file( $file_path );

		if ( null !== $bytes_saved ) {
			// Incrémenter le nombre total de fichiers traités.
			$processed_count = (int) get_option( 'eg_media_processed_count', 0 );
			update_option( 'eg_media_processed_count', $processed_count + 1 );

			// Si de l'espace a été économisé, ajouter à la somme globale.
			if ( $bytes_saved > 0 ) {
				$total_saved = (int) get_option( 'eg_media_bytes_saved', 0 );
				update_option( 'eg_media_bytes_saved', $total_saved + $bytes_saved );
			}
		}

		return $upload;
	}

	/**
	 * Optimise un fichier image spécifique sur le disque avec Imagick.
	 *
	 * @param string $file_path Chemin absolu du fichier.
	 * @return int|null Nombre d'octets économisés, ou null en cas d'erreur/non applicable.
	 */
	public function optimize_image_file( string $file_path ) : ?int {
		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		if ( ! class_exists( 'Imagick' ) ) {
			error_log( "EG Media Manager : Le traitement d'image a été ignoré car la classe native 'Imagick' n'est pas installée." );
			return null;
		}

		try {
			clearstatcache( true, $file_path );
			$original_size = (int) @filesize( $file_path );

			$imagick = new \Imagick( $file_path );

			// Récupération des options avec valeurs par défaut.
			$max_width          = (int) get_option( 'eg_media_resize_max_width', 2000 );
			$compression_qty    = (int) get_option( 'eg_media_compression_quality', 80 );
			$png_compression    = (string) get_option( 'eg_media_png_compression', 'moyenne' );
			$use_unsharp_mask   = (bool) get_option( 'eg_media_unsharp_mask', true );
			$use_auto_orient    = (bool) get_option( 'eg_media_auto_orient', true );
			$use_chrominance    = (bool) get_option( 'eg_media_chrominance', false );
			$use_interlace      = (bool) get_option( 'eg_media_interlace', true );

			// 1. Redressement automatique
			if ( $use_auto_orient ) {
				$imagick->autoOrient();
			}

			// 2. Redimensionnement
			if ( $max_width > 0 && $imagick->getImageWidth() > $max_width ) {
				$imagick->resizeImage( $max_width, 0, \Imagick::FILTER_LANCZOS, 1.0 );
			}

			// 3. Unsharp Mask
			if ( $use_unsharp_mask ) {
				// -unsharp 0x0.75+0.75+0.008
				$imagick->unsharpMaskImage( 0.0, 0.75, 0.75, 0.008 );
			}

			// 4. Compression en fonction du format
			$format = strtoupper( $imagick->getImageFormat() );
			if ( 'PNG' === $format ) {
				// Niveau de compression PNG : traduis en modifiant le niveau (valeur d'unité des dizaines) ou directement
				// Imagick utilise typiquement un entier à deux chiffres :
				// Les dizaines (compression type, e.g. 3, 6, 9) et l'unité (filter type).
				// e.g. 32 = compression level 3 (faible), filter Adaptive
				// Nous pouvons simplifier en traduisant : faible -> 30, moyenne -> 60, forte -> 90.
				$png_level = 60;
				if ( 'faible' === $png_compression ) {
					$png_level = 30;
				} elseif ( 'forte' === $png_compression ) {
					$png_level = 90;
				}
				$imagick->setCompressionQuality( $png_level );
			} else {
				// JPEG ou WebP
				$imagick->setImageCompressionQuality( $compression_qty );
			}

			// 5. Chrominance 4:2:0 (Sampling factors)
			if ( $use_chrominance ) {
				$imagick->setSamplingFactors( [ '2x2', '1x1', '1x1' ] );
			}

			// 6. Mode progressif (Interlace)
			if ( $use_interlace ) {
				$imagick->setImageInterlaceScheme( \Imagick::INTERLACE_PLANE );
			}

			// Écrire les modifications dans le fichier d'origine.
			$imagick->writeImage( $file_path );

			// Libérer proprement les ressources mémoire.
			$imagick->clear();
			$imagick->destroy();

			// Forcer le recalcul de la taille sur le disque.
			clearstatcache( true, $file_path );
			$new_size = (int) @filesize( $file_path );
			
			return $original_size - $new_size;

		} catch ( \ImagickException $e ) {
			error_log( "EG Media Manager - Erreur Imagick lors du traitement de {$file_path} : " . $e->getMessage() );
		} catch ( \Exception $e ) {
			error_log( "EG Media Manager - Erreur générale lors du traitement de {$file_path} : " . $e->getMessage() );
		}

		return null;
	}
}
