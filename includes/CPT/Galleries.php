<?php
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
	}

	/**
	 * Enregistre la Custom Taxonomy 'eg_media_gallery'.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = [
			'name'              => "Galeries",
			'singular_name'     => "Galerie",
			'search_items'      => "Rechercher des galeries",
			'all_items'         => "Toutes les galeries",
			'parent_item'       => "Galerie parente",
			'parent_item_colon' => "Galerie parente :",
			'edit_item'         => "Modifier la galerie",
			'update_item'       => "Mettre à jour la galerie",
			'add_new_item'      => "Ajouter une nouvelle galerie",
			'new_item_name'     => "Nom de la nouvelle galerie",
			'menu_name'         => "Galeries",
		];

		$args = [
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'query_var'             => true,
			'rewrite'               => [ 'slug' => 'galerie' ],
			'show_in_rest'          => true,
			'public'                => true,
			'update_count_callback' => '_update_generic_term_count',
		];

		register_taxonomy( 'eg_media_gallery', [ 'attachment' ], $args );

		// Associe explicitement la taxonomie à l'objet attachment pour l'API REST de Gutenberg
		register_taxonomy_for_object_type( 'eg_media_gallery', 'attachment' );
	}
}
