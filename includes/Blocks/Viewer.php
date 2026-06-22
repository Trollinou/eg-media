<?php
/**
 * Class Viewer
 *
 * Gère l'enregistrement du bloc Gutenberg Visionneuse de Galerie.
 *
 * @package EG_MEDIA\Blocks
 */

declare(strict_types=1);

namespace EG_MEDIA\Blocks;

/**
 * Classe Viewer pour le bloc Gutenberg.
 */
class Viewer {

	/**
	 * Enregistre les actions WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Enregistre le bloc Gutenberg 'eg-media/viewer'.
	 *
	 * @return void
	 */
	public function register_block(): void {
		// Le chemin pointe vers le répertoire de build contenant block.json
		$block_dir = plugin_dir_path( dirname( __DIR__ ) ) . 'build/blocks/viewer';
		
		if ( file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir );
		}
	}
}
