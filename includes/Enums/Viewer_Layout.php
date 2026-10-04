<?php
/**
 * Énumération des modes d'affichage de la visionneuse pour EG Media Manager.
 *
 * @package EG_MEDIA\Enums
 */

declare(strict_types=1);

namespace EG_MEDIA\Enums;

/**
 * Layouts disponibles pour le bloc visionneuse.
 */
enum Viewer_Layout: string {
	case VIEWER    = 'viewer';
	case JUSTIFIED = 'justified';

	/**
	 * Retourne le label lisible pour l'interface.
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::VIEWER    => __( 'Visionneuse standard avec miniatures', 'eg-media' ),
			self::JUSTIFIED => __( 'Grille justifiée dynamique', 'eg-media' ),
		};
	}
}
