<?php
declare(strict_types=1);

namespace EG_MEDIA\CPT;

/**
 * Class Albums
 *
 * Gère l'enregistrement du Custom Post Type 'eg_media_album'.
 *
 * @package EG_MEDIA\CPT
 */
class Albums {

	/**
	 * Enregistre les actions WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Enregistre le Custom Post Type 'eg_media_album'.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'               => "Albums",
			'singular_name'      => "Album",
			'menu_name'          => "Albums",
			'name_admin_bar'     => "Album",
			'add_new'            => "Ajouter un nouveau",
			'add_new_item'       => "Ajouter un nouvel album",
			'new_item'           => "Nouvel album",
			'edit_item'          => "Modifier l'album",
			'view_item'          => "Voir l'album",
			'all_items'          => "Tous les albums",
			'search_items'       => "Rechercher des albums",
			'parent_item_colon'  => "Albums parents :",
			'not_found'          => "Aucun album trouvé.",
			'not_found_in_trash' => "Aucun album trouvé dans la corbeille.",
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'upload.php',
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'album' ],
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 10,
			'menu_icon'          => 'dashicons-portfolio',
			'supports'           => [ 'title' ],
			'show_in_rest'       => true,
		];

		register_post_type( 'eg_media_album', $args );
	}
}
