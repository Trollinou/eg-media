<?php
/**
 * Custom Taxonomy Galleries.
 *
 * @package EG_Media
 */

declare(strict_types=1);

namespace EG_MEDIA\CPT;

/**
 * Class Galleries
 *
 * Gère l'enregistrement de la Custom Taxonomy 'eg_media_gallery' pour les pièces jointes (attachments).
 *
 * @package EG_MEDIA\CPT
 */
class Galleries {

	/**
	 * Enregistre les actions WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_taxonomy' ] );
		add_action( 'set_object_terms', [ $this, 'on_set_object_terms' ], 10, 4 );
	}

	/**
	 * Invalide le cache Object Cache des galeries lors d'une modification d'association de termes.
	 *
	 * @param int               $object_id  ID de l'objet (attachement).
	 * @param array<int|string> $terms      Nouveaux termes.
	 * @param array<int|string> $tt_ids     Term taxonomy IDs.
	 * @param string            $taxonomy   Nom de la taxonomie.
	 * @return void
	 */
	public function on_set_object_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
		if ( 'eg_media_gallery' !== $taxonomy ) {
			return;
		}

		foreach ( $terms as $term_id ) {
			if ( is_numeric( $term_id ) ) {
				\EG_MEDIA\Repositories\MediaRepository::flush_gallery_cache( (int) $term_id );
			}
		}
	}

	/**
	 * Enregistre la Custom Taxonomy 'eg_media_gallery'.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = [
			'name'              => 'Galeries',
			'singular_name'     => 'Galerie',
			'search_items'      => 'Rechercher des galeries',
			'all_items'         => 'Toutes les galeries',
			'parent_item'       => 'Galerie parente',
			'parent_item_colon' => 'Galerie parente :',
			'edit_item'         => 'Modifier la galerie',
			'update_item'       => 'Mettre à jour la galerie',
			'add_new_item'      => 'Ajouter une nouvelle galerie',
			'new_item_name'     => 'Nom de la nouvelle galerie',
			'menu_name'         => 'Galeries',
		];

		$args = [
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_admin_column'     => true,
			'query_var'             => true,
			'rewrite'               => [ 'slug' => 'galerie' ],
			'show_in_rest'          => true,
			'public'                => true,
			'update_count_callback' => '_update_generic_term_count',
		];

		register_taxonomy( 'eg_media_gallery', [ 'attachment' ], $args );

		// Associe explicitement la taxonomie à l'objet attachment pour l'API REST de Gutenberg.
		register_taxonomy_for_object_type( 'eg_media_gallery', 'attachment' );
	}
}
