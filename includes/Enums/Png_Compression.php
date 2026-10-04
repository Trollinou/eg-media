<?php
/**
 * Énumération des niveaux de compression PNG pour EG Media Manager.
 *
 * @package EG_MEDIA\Enums
 */

declare(strict_types=1);

namespace EG_MEDIA\Enums;

/**
 * Niveaux de compression PNG supportés.
 */
enum Png_Compression: string {
	case LOW    = 'faible';
	case MEDIUM = 'moyenne';
	case HIGH   = 'forte';

	/**
	 * Retourne le niveau de compression Imagick correspondant (0-9).
	 *
	 * @return int
	 */
	public function to_imagick_level(): int {
		return match ( $this ) {
			self::LOW    => 3,
			self::MEDIUM => 6,
			self::HIGH   => 9,
		};
	}
}
