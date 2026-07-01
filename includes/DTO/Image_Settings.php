<?php
declare(strict_types=1);

namespace EG_MEDIA\DTO;

/**
 * Class Image_Settings
 *
 * Data Transfer Object pour les paramètres d'optimisation d'image.
 *
 * @package EG_MEDIA\DTO
 */
readonly class Image_Settings {

	public const DEFAULT_MAX_WIDTH = 2000;
	public const DEFAULT_COMPRESSION_QUALITY = 80;
	public const DEFAULT_PNG_COMPRESSION = 'moyenne';
	public const DEFAULT_UNSHARP_MASK = true;
	public const DEFAULT_AUTO_ORIENT = true;
	public const DEFAULT_CHROMINANCE = false;
	public const DEFAULT_INTERLACE = true;

	/**
	 * Constructeur avec Constructor Property Promotion.
	 *
	 * @param int    $max_width          Largeur maximale de redimensionnement (0 pour désactiver).
	 * @param int    $compression_quality Qualité de compression JPEG/WebP (1-100).
	 * @param string $png_compression    Niveau de compression PNG ('faible', 'moyenne', 'forte').
	 * @param bool   $use_unsharp_mask   Indique s'il faut appliquer un masque de netteté (Unsharp Mask).
	 * @param bool   $use_auto_orient    Indique s'il faut redresser automatiquement l'image via EXIF.
	 * @param bool   $use_chrominance    Indique s'il faut utiliser le sous-échantillonnage de la chrominance 4:2:0.
	 * @param bool   $use_interlace      Indique s'il faut activer l'entrelacement (mode progressif).
	 */
	public function __construct(
		public int $max_width,
		public int $compression_quality,
		public string $png_compression,
		public bool $use_unsharp_mask,
		public bool $use_auto_orient,
		public bool $use_chrominance,
		public bool $use_interlace
	) {}

	/**
	 * Charge les paramètres d'optimisation depuis la base de données WordPress avec les valeurs par défaut globales.
	 *
	 * @return self
	 */
	public static function load_from_options(): self {
		return new self(
			max_width: (int) get_option( 'eg_media_resize_max_width', self::DEFAULT_MAX_WIDTH ),
			compression_quality: (int) get_option( 'eg_media_compression_quality', self::DEFAULT_COMPRESSION_QUALITY ),
			png_compression: (string) get_option( 'eg_media_png_compression', self::DEFAULT_PNG_COMPRESSION ),
			use_unsharp_mask: (bool) get_option( 'eg_media_unsharp_mask', self::DEFAULT_UNSHARP_MASK ),
			use_auto_orient: (bool) get_option( 'eg_media_auto_orient', self::DEFAULT_AUTO_ORIENT ),
			use_chrominance: (bool) get_option( 'eg_media_chrominance', self::DEFAULT_CHROMINANCE ),
			use_interlace: (bool) get_option( 'eg_media_interlace', self::DEFAULT_INTERLACE )
		);
	}
}
