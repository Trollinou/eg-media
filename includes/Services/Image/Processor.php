<?php
/**
 * Service de traitement et d'optimisation d'images.
 *
 * @package EG_MEDIA\Services\Image
 */

declare(strict_types=1);

namespace EG_MEDIA\Services\Image;

use EG_MEDIA\DTO\Image_Settings;
use EG_MEDIA\Enums\Png_Compression;
use EG_MEDIA\Services\Backup\Locations;
use EG_MEDIA\Utils\Logger;

/**
 * Class Processor
 *
 * Gère le retraitement et l'optimisation automatique des images JPEG, PNG, WebP et AVIF.
 */
class Processor {

	/**
	 * MIME types supportés pour l'optimisation.
	 */
	public const array SUPPORTED_MIMES = [
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/avif',
	];

	/**
	 * Enregistre le hook de traitement d'image.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_handle_upload', [ $this, 'process_upload' ] );
		add_action( 'add_attachment', [ $this, 'flag_new_attachment_as_optimized' ] );
		add_action( 'delete_attachment', [ $this, 'invalidate_unoptimized_count_cache' ] );
	}

	/**
	 * Marque un nouvel attachement comme optimisé s'il s'agit d'une image gérée par le plugin.
	 *
	 * @param int $post_id L'ID de l'attachement créé.
	 * @return void
	 */
	public function flag_new_attachment_as_optimized( int $post_id ): void {
		$mime_type = get_post_mime_type( $post_id );

		if ( is_string( $mime_type ) && in_array( $mime_type, self::SUPPORTED_MIMES, true ) ) {
			update_post_meta( $post_id, '_eg_media_optimized', '1' );
			$this->invalidate_unoptimized_count_cache();
		}
	}

	/**
	 * Invalide le cache du décompte d'images non optimisées.
	 *
	 * @return void
	 */
	public function invalidate_unoptimized_count_cache(): void {
		\EG_MEDIA\Repositories\MediaRepository::flush_unoptimized_count_cache();
	}

	/**
	 * Traite et optimise l'image après son téléversement si Imagick ou GD est disponible.
	 *
	 * @param array<string, mixed> $upload Les informations du fichier téléversé.
	 * @return array<string, mixed> Les informations éventuellement modifiées ou d'origine.
	 */
	public function process_upload( array $upload ): array {
		if ( isset( $upload['error'] ) && ! empty( $upload['error'] ) ) {
			return $upload;
		}

		$file_path = $upload['file'] ?? '';
		$mime_type = $upload['type'] ?? '';

		if ( ! in_array( $mime_type, self::SUPPORTED_MIMES, true ) ) {
			return $upload;
		}

		if ( ! is_string( $file_path ) || '' === $file_path || ! file_exists( $file_path ) ) {
			return $upload;
		}

		$bytes_saved = $this->optimize_image_file( $file_path );

		if ( null !== $bytes_saved ) {
			$processed_count = (int) get_option( 'eg_media_processed_count', 0 );
			update_option( 'eg_media_processed_count', $processed_count + 1, false );

			if ( $bytes_saved > 0 ) {
				$total_saved = (int) get_option( 'eg_media_bytes_saved', 0 );
				update_option( 'eg_media_bytes_saved', $total_saved + $bytes_saved, false );
			}
		}

		return $upload;
	}

	/**
	 * Calcule le chemin de destination pour la sauvegarde d'un fichier original.
	 *
	 * @param string $file_path Chemin absolu du fichier source.
	 * @return string Chemin absolu de la sauvegarde, ou chaîne vide en cas d'erreur.
	 */
	public function get_backup_path_for_file( string $file_path ): string {
		$originals_dir = Locations::get_originals_dir();
		if ( '' === $originals_dir ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		$basedir    = (string) ( $upload_dir['basedir'] ?? '' );

		$normalized_file    = wp_normalize_path( $file_path );
		$normalized_basedir = wp_normalize_path( $basedir );

		if ( '' !== $normalized_basedir && str_starts_with( $normalized_file, $normalized_basedir ) ) {
			$relative = ltrim( substr( $normalized_file, strlen( $normalized_basedir ) ), '/' );
		} else {
			$relative = basename( $file_path );
		}

		return wp_normalize_path( $originals_dir . '/' . $relative );
	}

	/**
	 * Sauvegarde le fichier original avant toute altération.
	 *
	 * @param string         $file_path Chemin absolu du fichier source.
	 * @param Image_Settings $settings  Paramètres d'optimisation.
	 * @return bool Vrai si la sauvegarde a réussi ou existait déjà, faux sinon.
	 */
	public function backup_original( string $file_path, Image_Settings $settings ): bool {
		if ( ! $settings->backup_originals ) {
			return true;
		}

		$backup_path = $this->get_backup_path_for_file( $file_path );
		if ( '' === $backup_path ) {
			return false;
		}

		// Si une sauvegarde existe déjà (ex. ré-optimisation ultérieure), on ne l'écrase pas pour conserver le vrai original.
		if ( file_exists( $backup_path ) ) {
			return true;
		}

		$backup_dir = dirname( $backup_path );
		if ( ! is_dir( $backup_dir ) && ! wp_mkdir_p( $backup_dir ) ) {
			Logger::error( "Impossible de créer le répertoire de sauvegarde des originaux : {$backup_dir}" );
			return false;
		}

		if ( ! copy( $file_path, $backup_path ) ) {
			Logger::error( "Échec de la copie de sauvegarde de l'original : {$file_path} vers {$backup_path}" );
			return false;
		}

		return true;
	}

	/**
	 * Restaure le fichier original depuis sa sauvegarde en cas d'erreur de traitement.
	 *
	 * @param string $file_path Chemin absolu du fichier source.
	 * @return bool Vrai si restauré avec succès, faux sinon.
	 */
	public function restore_original( string $file_path ): bool {
		$backup_path = $this->get_backup_path_for_file( $file_path );
		if ( '' === $backup_path || ! file_exists( $backup_path ) ) {
			return false;
		}

		if ( copy( $backup_path, $file_path ) ) {
			Logger::debug( "Restauration de l'original effectuée avec succès pour {$file_path}" );
			return true;
		}

		return false;
	}

	/**
	 * Optimise un fichier image spécifique sur le disque avec Imagick.
	 *
	 * @param string $file_path Chemin absolu du fichier.
	 * @return int|null Nombre d'octets économisés, ou null en cas d'erreur/non applicable.
	 */
	public function optimize_image_file( string $file_path ): ?int {
		if ( '' === $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return null;
		}

		if ( ! class_exists( 'Imagick' ) ) {
			return $this->optimize_image_file_fallback( $file_path );
		}

		try {
			clearstatcache( true, $file_path );
			$file_size     = filesize( $file_path );
			$original_size = false !== $file_size ? (int) $file_size : 0;

			$settings = Image_Settings::load_from_options();

			// Sauvegarde de sécurité de l'image originale avant optimisation.
			$this->backup_original( $file_path, $settings );

			$imagick = new \Imagick( $file_path );

			// 1. Redressement automatique.
			if ( $settings->use_auto_orient ) {
				$imagick->autoOrient();
			}

			// 2. Redimensionnement.
			if ( $settings->max_width > 0 && $imagick->getImageWidth() > $settings->max_width ) {
				$imagick->resizeImage( $settings->max_width, 0, \Imagick::FILTER_LANCZOS, 1.0 );
			}

			// 3. Unsharp Mask.
			if ( $settings->use_unsharp_mask ) {
				$imagick->unsharpMaskImage( 0.0, 0.75, 0.75, 0.008 );
			}

			// 4. Compression en fonction du format.
			$format = strtoupper( $imagick->getImageFormat() );
			if ( 'PNG' === $format ) {
				$png_level = $settings->get_png_compression_enum()->to_imagick_level() * 10;
				$imagick->setCompressionQuality( $png_level );
			} elseif ( in_array( $format, [ 'JPEG', 'JPG', 'WEBP', 'AVIF' ], true ) ) {
				$imagick->setImageCompressionQuality( $settings->compression_quality );
			}

			// 5. Chrominance 4:2:0 (Sampling factors pour JPEG/WebP).
			if ( $settings->use_chrominance && in_array( $format, [ 'JPEG', 'JPG' ], true ) ) {
				$imagick->setSamplingFactors( [ '2x2', '1x1', '1x1' ] );
			}

			// 6. Mode progressif (Interlace).
			if ( $settings->use_interlace && in_array( $format, [ 'JPEG', 'JPG', 'PNG' ], true ) ) {
				$imagick->setImageInterlaceScheme( \Imagick::INTERLACE_PLANE );
			}

			$imagick->writeImage( $file_path );
			$imagick->clear();
			$imagick->destroy();

			clearstatcache( true, $file_path );
			$new_file_size = filesize( $file_path );
			$new_size      = false !== $new_file_size ? (int) $new_file_size : 0;

			return $original_size - $new_size;

		} catch ( \ImagickException $e ) {
			$this->restore_original( $file_path );
			Logger::error( "Erreur Imagick lors du traitement de {$file_path} : " . $e->getMessage() );
		} catch ( \Throwable $e ) {
			$this->restore_original( $file_path );
			Logger::error( "Erreur générale lors du traitement de {$file_path} : " . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Optimise un fichier image spécifique sur le disque avec WP_Image_Editor / GD en tant que fallback.
	 *
	 * @param string $file_path Chemin absolu du fichier.
	 * @return int|null Nombre d'octets économisés, ou null en cas d'erreur/non applicable.
	 */
	public function optimize_image_file_fallback( string $file_path ): ?int {
		if ( '' === $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return null;
		}

		if ( ! function_exists( 'gd_info' ) ) {
			Logger::error( "Fallback GD non disponible : l'extension GD est absente du serveur." );
			return null;
		}

		try {
			clearstatcache( true, $file_path );
			$file_size     = filesize( $file_path );
			$original_size = false !== $file_size ? (int) $file_size : 0;

			$settings = Image_Settings::load_from_options();

			// Sauvegarde de sécurité de l'image originale avant optimisation.
			$this->backup_original( $file_path, $settings );

			$info = getimagesize( $file_path );
			if ( ! $info ) {
				return null;
			}

			$mime  = $info['mime'];
			$image = match ( $mime ) {
				'image/jpeg' => imagecreatefromjpeg( $file_path ),
				'image/png'  => imagecreatefrompng( $file_path ),
				'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $file_path ) : null,
				'image/avif' => function_exists( 'imagecreatefromavif' ) ? imagecreatefromavif( $file_path ) : null,
				default      => null,
			};

			if ( ! $image ) {
				return null;
			}

			// 1. Redressement automatique via EXIF.
			if ( $settings->use_auto_orient && 'image/jpeg' === $mime && function_exists( 'exif_read_data' ) ) {
				$exif = exif_read_data( $file_path );
				if ( is_array( $exif ) && ! empty( $exif['Orientation'] ) ) {
					$orientation = (int) $exif['Orientation'];
					$rotated     = match ( $orientation ) {
						3 => imagerotate( $image, 180, 0 ),
						6 => imagerotate( $image, -90, 0 ),
						8 => imagerotate( $image, 90, 0 ),
						default => null,
					};
					if ( $rotated ) {
						$image = $rotated;
					}
				}
			}

			// 2. Redimensionnement.
			$width  = imagesx( $image );
			$height = imagesy( $image );
			if ( $settings->max_width > 0 && $width > $settings->max_width ) {
				$new_width  = $settings->max_width;
				$new_height = (int) round( $height * ( $settings->max_width / $width ) );
				$resized    = imagescale( $image, $new_width, $new_height, IMG_BILINEAR_FIXED );
				if ( $resized ) {
					$image = $resized;
				}
			}

			// 3. Unsharp Mask.
			if ( $settings->use_unsharp_mask ) {
				$matrix = [
					[ -1.0, -1.0, -1.0 ],
					[ -1.0, 9.0, -1.0 ],
					[ -1.0, -1.0, -1.0 ],
				];
				imageconvolution( $image, $matrix, 1.0, 0.0 );
			}

			// 4. Mode progressif (Interlace).
			if ( $settings->use_interlace && in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) {
				imageinterlace( $image, true );
			}

			// 5. Sauvegarde selon le format.
			$saved = match ( $mime ) {
				'image/jpeg' => imagejpeg( $image, $file_path, $settings->compression_quality ),
				'image/webp' => function_exists( 'imagewebp' ) ? imagewebp( $image, $file_path, $settings->compression_quality ) : false,
				'image/avif' => function_exists( 'imageavif' ) ? imageavif( $image, $file_path, $settings->compression_quality ) : false,
				'image/png'  => imagepng(
					$image,
					$file_path,
					match ( $settings->get_png_compression_enum() ) {
						Png_Compression::LOW    => 3,
					Png_Compression::MEDIUM => 6,
					Png_Compression::HIGH   => 9,
					}
				),
				default      => false,
			};

			if ( ! $saved ) {
				$this->restore_original( $file_path );
				Logger::error( "Fallback : Impossible de sauvegarder l'image optimisée : {$file_path}" );
				return null;
			}

			clearstatcache( true, $file_path );
			$new_file_size = filesize( $file_path );
			$new_size      = false !== $new_file_size ? (int) $new_file_size : 0;

			return $original_size - $new_size;

		} catch ( \Throwable $e ) {
			$this->restore_original( $file_path );
			Logger::error( "Fallback : Erreur lors du traitement de {$file_path} : " . $e->getMessage() );
		}

		return null;
	}
}
