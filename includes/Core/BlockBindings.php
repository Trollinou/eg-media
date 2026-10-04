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

use WP_Block;
use WP_Post;
use WP_Term;

/**
 * Enregistre les sources personnalisées Block Bindings pour connecter les blocs natifs WordPress
 * aux métadonnées des galeries, albums et médias EG Media.
 */
class BlockBindings {

	/**
	 * Enregistre les hooks WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_bindings_sources' ) );
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

		// 1. Source Données de Galerie
		register_block_bindings_source(
			'eg-media/gallery-data',
			array(
				'label'              => __( 'Données de Galerie (EG Media)', 'eg-media' ),
				'get_value_callback' => array( $this, 'get_gallery_data_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);

		// 2. Source Données d'Album
		register_block_bindings_source(
			'eg-media/album-data',
			array(
				'label'              => __( "Données d'Album (EG Media)", 'eg-media' ),
				'get_value_callback' => array( $this, 'get_album_data_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);

		// 3. Source Métadonnées de Média
		register_block_bindings_source(
			'eg-media/media-metadata',
			array(
				'label'              => __( 'Métadonnées Média (EG Media)', 'eg-media' ),
				'get_value_callback' => array( $this, 'get_media_metadata_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Callback pour résoudre les données d'une galerie.
	 *
	 * @param array<string, mixed> $source_args Arguments passés à la source de liaison.
	 * @param WP_Block             $block_instance Instance du bloc WordPress en cours de rendu.
	 * @param string               $attribute_name Nom de l'attribut du bloc à renseigner.
	 * @return mixed Valeur résolue pour l'attribut du bloc.
	 */
	public function get_gallery_data_value( array $source_args, WP_Block $block_instance, string $attribute_name ): mixed {
		$key        = (string) ( $source_args['key'] ?? '' );
		$gallery_id = isset( $source_args['galleryId'] ) ? (int) $source_args['galleryId'] : 0;

		if ( ! $gallery_id && isset( $block_instance->context['postId'] ) ) {
			$post_id = (int) $block_instance->context['postId'];
			$terms   = get_the_terms( $post_id, 'eg_media_gallery' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$first_term = reset( $terms );
				if ( $first_term instanceof WP_Term ) {
					$gallery_id = $first_term->term_id;
				}
			}
		}

		if ( ! $gallery_id ) {
			return null;
		}

		$term = get_term( $gallery_id, 'eg_media_gallery' );
		if ( ! $term || is_wp_error( $term ) || ! ( $term instanceof WP_Term ) ) {
			return null;
		}

		return match ( $key ) {
			'gallery_name'        => $term->name,
			'gallery_description' => $term->description,
			'image_count'         => (string) $term->count,
			'featured_image_url'  => $this->get_featured_image_url( $gallery_id ),
			'gallery_link'        => get_term_link( $term ),
			default               => null,
		};
	}

	/**
	 * Callback pour résoudre les données d'un album.
	 *
	 * @param array<string, mixed> $source_args Arguments passés à la source de liaison.
	 * @param WP_Block             $block_instance Instance du bloc WordPress en cours de rendu.
	 * @param string               $attribute_name Nom de l'attribut du bloc à renseigner.
	 * @return mixed Valeur résolue pour l'attribut du bloc.
	 */
	public function get_album_data_value( array $source_args, WP_Block $block_instance, string $attribute_name ): mixed {
		$key      = (string) ( $source_args['key'] ?? '' );
		$album_id = isset( $source_args['albumId'] ) ? (int) $source_args['albumId'] : 0;

		if ( ! $album_id && isset( $block_instance->context['postId'] ) ) {
			$album_id = (int) $block_instance->context['postId'];
		}

		if ( ! $album_id ) {
			return null;
		}

		$post = get_post( $album_id );
		if ( ! $post instanceof WP_Post || 'eg_media_album' !== $post->post_type ) {
			return null;
		}

		return match ( $key ) {
			'album_title'       => get_the_title( $post ),
			'album_description' => $post->post_excerpt ?: $post->post_content,
			'featured_image_url'=> get_the_post_thumbnail_url( $post, 'full' ) ?: null,
			'album_link'        => get_permalink( $post ),
			default             => null,
		};
	}

	/**
	 * Callback pour résoudre les métadonnées personnalisées d'une image/pièce jointe.
	 *
	 * @param array<string, mixed> $source_args Arguments passés à la source de liaison.
	 * @param WP_Block             $block_instance Instance du bloc WordPress en cours de rendu.
	 * @param string               $attribute_name Nom de l'attribut du bloc à renseigner.
	 * @return mixed Valeur résolue pour l'attribut du bloc.
	 */
	public function get_media_metadata_value( array $source_args, WP_Block $block_instance, string $attribute_name ): mixed {
		$key           = (string) ( $source_args['key'] ?? '' );
		$attachment_id = isset( $source_args['attachmentId'] ) ? (int) $source_args['attachmentId'] : 0;

		if ( ! $attachment_id && isset( $block_instance->context['postId'] ) ) {
			$attachment_id = (int) $block_instance->context['postId'];
		}

		if ( ! $attachment_id ) {
			return null;
		}

		return match ( $key ) {
			'credit'   => get_post_meta( $attachment_id, '_eg_media_credit', true ) ?: null,
			'location' => get_post_meta( $attachment_id, '_eg_media_location', true ) ?: null,
			'caption'  => wp_get_attachment_caption( $attachment_id ) ?: null,
			'status'   => get_post_meta( $attachment_id, '_eg_media_status', true ) ?: null,
			default    => null,
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

		// Fallback : première image de la galerie.
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'eg_media_gallery',
						'field'    => 'term_id',
						'terms'    => $gallery_id,
					),
				),
			)
		);

		if ( ! empty( $attachments ) ) {
			return wp_get_attachment_image_url( $attachments[0]->ID, 'full' ) ?: null;
		}

		return null;
	}
}
