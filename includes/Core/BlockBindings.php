<?php
/**
 * Class BlockBindings
 *
 * Gère l'enregistrement des sources personnalisées pour la Block Bindings API (WordPress 6.5+ / 7.1).
 *
 * @package EG_MEDIA\Core
 */

declare(strict_types=1);

namespace EG_MEDIA\Core;

/**
 * Enregistre les sources personnalisées Block Bindings pour connecter les blocs natifs WordPress
 * aux métadonnées des galeries et médias EG Media.
 */
class BlockBindings {

	/**
	 * Enregistre les hooks WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_bindings_sources' ] );
	}

	/**
	 * Enregistre les sources Block Bindings auprès de WordPress.
	 *
	 * @return void
	 */
	public function register_bindings_sources(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		register_block_bindings_source(
			'eg-media/gallery-data',
			[
				'label'              => __( "Données de Galerie (EG Media)", 'eg-media' ),
				'get_value_callback' => [ $this, 'get_gallery_data_value' ],
				'uses_context'       => [ 'postId', 'postType' ],
			]
		);
	}

	/**
	 * Callback pour résoudre la valeur dynamique de la source de liaison.
	 *
	 * @param array<string, mixed> $source_args  Arguments passés à la source de liaison (ex: key, galleryId).
	 * @param \WP_Block            $block_instance Instance du bloc WordPress en cours de rendu.
	 * @param string               $attribute_name Nom de l'attribut du bloc à renseigner.
	 * @return mixed Valeur résolue pour l'attribut du bloc.
	 */
	public function get_gallery_data_value( array $source_args, \WP_Block $block_instance, string $attribute_name ): mixed {
		$key = $source_args['key'] ?? '';
		$gallery_id = isset( $source_args['galleryId'] ) ? (int) $source_args['galleryId'] : 0;

		// Si aucun galleryId n'est fourni, tenter de le déduire du post en cours
		if ( ! $gallery_id && isset( $block_instance->context['postId'] ) ) {
			$post_id = (int) $block_instance->context['postId'];
			$terms = get_the_terms( $post_id, 'eg_media_gallery' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$first_term = reset( $terms );
				if ( $first_term instanceof \WP_Term ) {
					$gallery_id = $first_term->term_id;
				}
			}
		}

		if ( ! $gallery_id ) {
			return null;
		}

		$term = get_term( $gallery_id, 'eg_media_gallery' );
		if ( ! $term || is_wp_error( $term ) || ! ( $term instanceof \WP_Term ) ) {
			return null;
		}

		return match ( $key ) {
			'gallery_name'        => $term->name,
			'gallery_description' => $term->description,
			'image_count'         => (string) $term->count,
			'featured_image_url'  => $this->get_featured_image_url( $gallery_id ),
			default               => null,
		};
	}

	/**
	 * Récupère l'URL de l'image de référence pour une galerie donnée.
	 *
	 * @param int $gallery_id ID du terme de taxonomie.
	 * @return string|null URL de l'image ou null.
	 */
	private function get_featured_image_url( int $gallery_id ): ?string {
		$attachment_id = (int) get_term_meta( $gallery_id, 'eg_media_featured_attachment_id', true );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		// Fallback : première image de la galerie
		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'tax_query'      => [
				[
					'taxonomy' => 'eg_media_gallery',
					'field'    => 'term_id',
					'terms'    => $gallery_id,
				],
			],
		] );

		if ( ! empty( $attachments ) ) {
			return wp_get_attachment_image_url( $attachments[0]->ID, 'full' ) ?: null;
		}

		return null;
	}
}
