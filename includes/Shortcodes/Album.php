<?php
/**
 * Album Shortcode Renderer.
 *
 * @package EG_Media
 */

declare(strict_types=1);

namespace EG_MEDIA\Shortcodes;

/**
 * Class Album
 *
 * Gère le shortcode [eg_media_album id="XX"] pour le rendu d'un album de galeries.
 *
 * @package EG_MEDIA\Shortcodes
 */
class Album {

	/**
	 * Enregistre le shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'eg_media_album', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Callback de rendu pour le shortcode.
	 *
	 * @param array<string, mixed>|string $atts Attributs du shortcode.
	 * @return string Code HTML généré.
	 */
	public function render_shortcode( array|string $atts ): string {
		$args = shortcode_atts(
			array(
				'id' => 0,
			),
			is_array( $atts ) ? $atts : array()
		);

		$album_id = (int) $args['id'];
		if ( $album_id <= 0 ) {
			return '<p>' . esc_html__( "ID d'album invalide.", 'eg-media' ) . '</p>';
		}

		$post = get_post( $album_id );
		if ( ! $post || 'eg_media_album' !== $post->post_type ) {
			return '<p>' . esc_html__( 'Album non trouvé.', 'eg-media' ) . '</p>';
		}

		// Récupérer le tri et les éléments.
		$sort_mode  = get_post_meta( $album_id, '_eg_media_album_sort', true ) ?: 'manual';
		$items_meta = get_post_meta( $album_id, '_eg_media_album_items', true );
		$items      = ! empty( $items_meta ) ? json_decode( $items_meta, true ) : array();

		if ( ! is_array( $items ) || empty( $items ) ) {
			return '<p>' . esc_html__( 'Cet album ne contient aucune galerie.', 'eg-media' ) . '</p>';
		}

		// Charger les détails de couverture et trier si nécessaire.
		$resolved_items = array();
		$piwigo_service = new \EG_MEDIA\Services\Piwigo();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['type'], $item['id'] ) ) {
				continue;
			}

			$type       = (string) $item['type'];
			$id         = (int) $item['id'];
			$name       = (string) ( $item['name'] ?? '' );
			$image_sort = (string) ( $item['image_sort'] ?? $item['imageSort'] ?? 'date_asc' );
			$sort_by    = str_starts_with( $image_sort, 'name' ) ? 'name' : 'date';
			$sort_order = str_ends_with( $image_sort, '_desc' ) ? 'DESC' : 'ASC';
			$cover_url  = '';

			if ( 'local' === $type ) {
				// Récupérer le nom à jour.
				$term = get_term( $id, 'eg_media_gallery' );
				if ( $term instanceof \WP_Term ) {
					$name = $term->name;
				}

				// Récupérer la couverture.
				$ref_id = (int) get_term_meta( $id, '_eg_media_featured_image_id', true );
				if ( $ref_id > 0 ) {
					$cover_url = wp_get_attachment_image_url( $ref_id, 'medium_large' )
						?: wp_get_attachment_image_url( $ref_id, 'medium' )
						?: wp_get_attachment_image_url( $ref_id, 'thumbnail' )
						?: '';
				}

				if ( empty( $cover_url ) ) {
					// Fallback: première image selon le tri configuré.
					$query_args  = array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => -1,
						'tax_query'      => array(
							array(
								'taxonomy' => 'eg_media_gallery',
								'field'    => 'term_id',
								'terms'    => $id,
							),
						),
					);
					$attachments = get_posts( $query_args );
					if ( ! empty( $attachments ) ) {
						usort(
							$attachments,
							function ( \WP_Post $a, \WP_Post $b ) use ( $sort_by, $sort_order ): int {
								if ( 'name' === $sort_by ) {
									$val_a      = ! empty( $a->post_title ) ? $a->post_title : basename( (string) get_attached_file( $a->ID ) );
									$val_b      = ! empty( $b->post_title ) ? $b->post_title : basename( (string) get_attached_file( $b->ID ) );
									$comparison = strcasecmp( (string) $val_a, (string) $val_b );
								} else {
									$meta_a = wp_get_attachment_metadata( $a->ID );
									$meta_b = wp_get_attachment_metadata( $b->ID );

									$time_a = ! empty( $meta_a['image_meta']['created_timestamp'] ) ? (int) $meta_a['image_meta']['created_timestamp'] : strtotime( $a->post_date );
									$time_b = ! empty( $meta_b['image_meta']['created_timestamp'] ) ? (int) $meta_b['image_meta']['created_timestamp'] : strtotime( $b->post_date );

									$comparison = $time_a <=> $time_b;
								}

								return 'DESC' === $sort_order ? -$comparison : $comparison;
							}
						);

						$first_att = $attachments[0];
						$cover_url = wp_get_attachment_image_url( $first_att->ID, 'medium_large' )
							?: wp_get_attachment_image_url( $first_att->ID, 'medium' )
							?: '';
					}
				}
			} elseif ( 'piwigo' === $type ) {
				// Fallback cover: première image Piwigo selon le tri configuré.
				$p_images = $piwigo_service->get_album_images( $id );
				if ( ! empty( $p_images ) ) {
					usort(
						$p_images,
						function ( array $a, array $b ) use ( $sort_by, $sort_order ): int {
							if ( 'name' === $sort_by ) {
								$val_a      = ! empty( $a['name'] ) ? $a['name'] : $a['file'];
								$val_b      = ! empty( $b['name'] ) ? $b['name'] : $b['file'];
								$comparison = strcasecmp( (string) $val_a, (string) $val_b );
							} else {
								$time_a = ! empty( $a['date_creation'] ) ? strtotime( (string) $a['date_creation'] ) : ( ! empty( $a['date_available'] ) ? strtotime( (string) $a['date_available'] ) : (int) $a['id'] );
								$time_b = ! empty( $b['date_creation'] ) ? strtotime( (string) $b['date_creation'] ) : ( ! empty( $b['date_available'] ) ? strtotime( (string) $b['date_available'] ) : (int) $b['id'] );

								$comparison = $time_a <=> $time_b;
							}

							return 'DESC' === $sort_order ? -$comparison : $comparison;
						}
					);

					$first_img   = $p_images[0];
					$derivatives = $first_img['derivatives'] ?? array();
					$cover_url   = (string) ( $derivatives['medium']['url'] ?? $derivatives['small']['url'] ?? $first_img['element_url'] ?? '' );
				}
			}

			// Si toujours pas de couverture, on met un placeholder en ligne SVG simple.
			if ( empty( $cover_url ) ) {
				$cover_url = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"><rect width="300" height="200" fill="%23eee"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="14" fill="%23aaa">Aucune image</text></svg>';
			}

			$resolved_items[] = array(
				'type'       => $type,
				'id'         => $id,
				'name'       => $name,
				'cover_url'  => $cover_url,
				'sort_by'    => $sort_by,
				'sort_order' => $sort_order,
			);
		}

		// Tri alphabétique si demandé.
		if ( 'alphabetical' === $sort_mode ) {
			usort(
				$resolved_items,
				function ( array $a, array $b ): int {
					return strcasecmp( (string) $a['name'], (string) $b['name'] );
				}
			);
		}

		// Enfiler les scripts et styles requis.
		wp_enqueue_style( 'eg-media-public-album' );
		wp_enqueue_script( 'eg-media-public-album' );

		// Préparer le rendu.
		ob_start();
		?>
		<div class="eg-album" data-album-id="<?php echo esc_attr( (string) $album_id ); ?>">
			<div class="eg-album__grid">
				<?php
				foreach ( $resolved_items as $index => $item ) :
					$unique_item_id = $item['type'] . '-' . $item['id'];
					?>
					<div class="eg-album__card" data-target-viewer="<?php echo esc_attr( $unique_item_id ); ?>">
						<div class="eg-album__card-cover-wrap">
							<img src="<?php echo esc_url( $item['cover_url'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" class="eg-album__card-cover" loading="lazy" />
						</div>
						<div class="eg-album__card-info">
							<h3 class="eg-album__card-title"><?php echo esc_html( $item['name'] ); ?></h3>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Rendu des visionneuses masquées en overlay -->
			<?php
			foreach ( $resolved_items as $item ) :
				$unique_item_id = $item['type'] . '-' . $item['id'];
				// Générer le bloc Gutenberg de visionneuse.
				$viewer_block_html = render_block(
					array(
						'blockName' => 'eg-media/viewer',
						'attrs'     => array(
							'galleryId'     => $item['id'],
							'gallerySource' => $item['type'],
							'sortBy'        => $item['sort_by'],
							'sortOrder'     => $item['sort_order'],
							'layout'        => 'justified',
							'imagesPerPage' => 30,
						),
					)
				);
				?>
				<div id="eg-viewer-overlay-<?php echo esc_attr( $unique_item_id ); ?>" class="eg-album__overlay">
					<div class="eg-album__overlay-content">
						<button class="eg-album__overlay-close" aria-label="<?php esc_attr_e( 'Fermer', 'eg-media' ); ?>">&times;</button>
						<h2 class="eg-album__overlay-title"><?php echo esc_html( $item['name'] ); ?></h2>
						<div class="eg-album__overlay-body">
							<?php echo wp_kses_post( $viewer_block_html ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		$html = ob_get_clean();

		if ( class_exists( 'WP_HTML_Tag_Processor' ) && is_string( $html ) ) {
			$processor = new \WP_HTML_Tag_Processor( $html );

			while ( $processor->next_tag( array( 'tag_name' => 'img' ) ) ) {
				$processor->set_attribute( 'loading', 'lazy' );
				$processor->set_attribute( 'decoding', 'async' );
			}

			$html = $processor->get_updated_html();
		}

		return (string) $html;
	}
}
