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
		delete_transient( 'eg_media_unoptimized_count' );
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
			update_option( 'eg_media_processed_count', $processed_count + 1 );

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
	public function optimize_image_file( string $file_path ): ?int {
		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		if ( ! class_exists( 'Imagick' ) ) {
			return $this->optimize_image_file_fallback( $file_path );
		}

		try {
			clearstatcache( true, $file_path );
			$original_size = (int) @filesize( $file_path );

			$imagick = new \Imagick( $file_path );
			$settings = Image_Settings::load_from_options();

			// 1. Redressement automatique
			if ( $settings->use_auto_orient ) {
				$imagick->autoOrient();
			}

			// 2. Redimensionnement
			if ( $settings->max_width > 0 && $imagick->getImageWidth() > $settings->max_width ) {
				$imagick->resizeImage( $settings->max_width, 0, \Imagick::FILTER_LANCZOS, 1.0 );
			}

			// 3. Unsharp Mask
			if ( $settings->use_unsharp_mask ) {
				$imagick->unsharpMaskImage( 0.0, 0.75, 0.75, 0.008 );
			}

			// 4. Compression en fonction du format
			$format = strtoupper( $imagick->getImageFormat() );
			if ( 'PNG' === $format ) {
				$png_level = $settings->get_png_compression_enum()->to_imagick_level() * 10;
				$imagick->setCompressionQuality( $png_level );
			} elseif ( in_array( $format, [ 'JPEG', 'JPG', 'WEBP', 'AVIF' ], true ) ) {
				$imagick->setImageCompressionQuality( $settings->compression_quality );
			}

			// 5. Chrominance 4:2:0 (Sampling factors pour JPEG/WebP)
			if ( $settings->use_chrominance && in_array( $format, [ 'JPEG', 'JPG' ], true ) ) {
				$imagick->setSamplingFactors( [ '2x2', '1x1', '1x1' ] );
			}

			// 6. Mode progressif (Interlace)
			if ( $settings->use_interlace && in_array( $format, [ 'JPEG', 'JPG', 'PNG' ], true ) ) {
				$imagick->setImageInterlaceScheme( \Imagick::INTERLACE_PLANE );
			}

			$imagick->writeImage( $file_path );
			$imagick->clear();
			$imagick->destroy();

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

	/**
	 * Optimise un fichier image spécifique sur le disque avec WP_Image_Editor / GD en tant que fallback.
	 *
	 * @param string $file_path Chemin absolu du fichier.
	 * @return int|null Nombre d'octets économisés, ou null en cas d'erreur/non applicable.
	 */
	public function optimize_image_file_fallback( string $file_path ): ?int {
		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		if ( ! function_exists( 'gd_info' ) ) {
			error_log( "EG Media Manager - Fallback GD non disponible : l'extension GD est absente du serveur." );
			return null;
		}

		try {
			clearstatcache( true, $file_path );
			$original_size = (int) @filesize( $file_path );

			$info = @getimagesize( $file_path );
			if ( ! $info ) {
				return null;
			}

			$mime  = $info['mime'];
			$image = match ( $mime ) {
				'image/jpeg' => @imagecreatefromjpeg( $file_path ),
				'image/png'  => @imagecreatefrompng( $file_path ),
				'image/webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file_path ) : null,
				'image/avif' => function_exists( 'imagecreatefromavif' ) ? @imagecreatefromavif( $file_path ) : null,
				default      => null,
			};

			if ( ! $image ) {
				return null;
			}

			$settings = Image_Settings::load_from_options();

			// 1. Redressement automatique via EXIF
			if ( $settings->use_auto_orient && 'image/jpeg' === $mime && function_exists( 'exif_read_data' ) ) {
				$exif = @exif_read_data( $file_path );
				if ( ! empty( $exif['Orientation'] ) ) {
					$orientation = (int) $exif['Orientation'];
					$rotated     = match ( $orientation ) {
						3 => @imagerotate( $image, 180, 0 ),
						6 => @imagerotate( $image, -90, 0 ),
						8 => @imagerotate( $image, 90, 0 ),
						default => null,
					};
					if ( $rotated ) {
						imagedestroy( $image );
						$image = $rotated;
					}
				}
			}

			// 2. Redimensionnement
			$width  = imagesx( $image );
			$height = imagesy( $image );
			if ( $settings->max_width > 0 && $width > $settings->max_width ) {
				$new_width  = $settings->max_width;
				$new_height = (int) round( $height * ( $settings->max_width / $width ) );
				$resized    = @imagescale( $image, $new_width, $new_height, IMG_BILINEAR_FIXED );
				if ( $resized ) {
					imagedestroy( $image );
					$image = $resized;
				}
			}

			// 3. Unsharp Mask
			if ( $settings->use_unsharp_mask ) {
				$matrix = [
					[ -1.0, -1.0, -1.0 ],
					[ -1.0,  9.0, -1.0 ],
					[ -1.0, -1.0, -1.0 ],
				];
				@imageconvolution( $image, $matrix, 1.0, 0.0 );
			}

			// 4. Mode progressif (Interlace)
			if ( $settings->use_interlace && in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) {
				@imageinterlace( $image, true );
			}

			// 5. Sauvegarde selon le format
			$saved = match ( $mime ) {
				'image/jpeg' => @imagejpeg( $image, $file_path, $settings->compression_quality ),
				'image/webp' => function_exists( 'imagewebp' ) ? @imagewebp( $image, $file_path, $settings->compression_quality ) : false,
				'image/avif' => function_exists( 'imageavif' ) ? @imageavif( $image, $file_path, $settings->compression_quality ) : false,
				'image/png'  => @imagepng( $image, $file_path, match ( $settings->get_png_compression_enum() ) {
					Png_Compression::LOW    => 3,
					Png_Compression::MEDIUM => 6,
					Png_Compression::HIGH   => 9,
				} ),
				default      => false,
			};

			imagedestroy( $image );

			if ( ! $saved ) {
				error_log( "EG Media Manager - Fallback : Impossible de sauvegarder l'image optimisée : {$file_path}" );
				return null;
			}

			clearstatcache( true, $file_path );
			$new_size = (int) @filesize( $file_path );

			return $original_size - $new_size;

		} catch ( \Exception $e ) {
			error_log( "EG Media Manager - Fallback : Erreur lors du traitement de {$file_path} : " . $e->getMessage() );
		}

		return null;
	}
}
