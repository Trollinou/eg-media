<?php
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
		add_shortcode( 'eg_media_album', [ $this, 'render_shortcode' ] );
	}

	/**
	 * Callback de rendu pour le shortcode.
	 *
	 * @param array<string, mixed>|string $atts Attributs du shortcode.
	 * @return string Code HTML généré.
	 */
	public function render_shortcode( array|string $atts ): string {
		$args = shortcode_atts(
			[
				'id' => 0,
			],
			is_array( $atts ) ? $atts : []
		);

		$album_id = (int) $args['id'];
		if ( $album_id <= 0 ) {
			return '<p>' . esc_html__( "ID d'album invalide.", 'eg-media' ) . '</p>';
		}

		$post = get_post( $album_id );
		if ( ! $post || 'eg_media_album' !== $post->post_type ) {
			return '<p>' . esc_html__( "Album non trouvé.", 'eg-media' ) . '</p>';
		}

		// Récupérer le tri et les éléments
		$sort_mode = get_post_meta( $album_id, '_eg_media_album_sort', true ) ?: 'manual';
		$items_meta = get_post_meta( $album_id, '_eg_media_album_items', true );
		$items = ! empty( $items_meta ) ? json_decode( $items_meta, true ) : [];

		if ( ! is_array( $items ) || empty( $items ) ) {
			return '<p>' . esc_html__( "Cet album ne contient aucune galerie.", 'eg-media' ) . '</p>';
		}

		// Charger les détails de couverture et trier si nécessaire
		$resolved_items = [];
		$piwigo_service = new \EG_MEDIA\Services\Piwigo();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['type'], $item['id'] ) ) {
				continue;
			}

			$type = (string) $item['type'];
			$id = (int) $item['id'];
			$name = (string) ( $item['name'] ?? '' );
			$cover_url = '';

			if ( 'local' === $type ) {
				// Récupérer le nom à jour
				$term = get_term( $id, 'eg_media_gallery' );
				if ( $term instanceof \WP_Term ) {
					$name = $term->name;
				}

				// Récupérer la couverture
				$ref_id = (int) get_term_meta( $id, '_eg_media_featured_image_id', true );
				if ( $ref_id > 0 ) {
					$cover_url = wp_get_attachment_image_url( $ref_id, 'medium_large' )
						?: wp_get_attachment_image_url( $ref_id, 'medium' )
						?: wp_get_attachment_image_url( $ref_id, 'thumbnail' )
						?: '';
				}

				if ( empty( $cover_url ) ) {
					// Fallback: première image
					$query_args = [
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => 1,
						'tax_query'      => [
							[
								'taxonomy' => 'eg_media_gallery',
								'field'    => 'term_id',
								'terms'    => $id,
							],
						],
					];
					$attachments = get_posts( $query_args );
					if ( ! empty( $attachments ) ) {
						$cover_url = wp_get_attachment_image_url( $attachments[0]->ID, 'medium_large' )
							?: wp_get_attachment_image_url( $attachments[0]->ID, 'medium' )
							?: '';
					}
				}
			} elseif ( 'piwigo' === $type ) {
				// Fallback cover: première image Piwigo
				$p_images = $piwigo_service->get_album_images( $id );
				if ( ! empty( $p_images ) ) {
					$first_img   = $p_images[0];
					$derivatives = $first_img['derivatives'] ?? [];
					$cover_url   = (string) ( $derivatives['medium']['url'] ?? $derivatives['small']['url'] ?? $first_img['element_url'] ?? '' );
				}
			}

			// Si toujours pas de couverture, on met un placeholder en ligne SVG simple
			if ( empty( $cover_url ) ) {
				$cover_url = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"><rect width="300" height="200" fill="%23eee"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="14" fill="%23aaa">Aucune image</text></svg>';
			}

			$resolved_items[] = [
				'type'      => $type,
				'id'        => $id,
				'name'      => $name,
				'cover_url' => $cover_url,
			];
		}

		// Tri alphabétique si demandé
		if ( 'alphabetical' === $sort_mode ) {
			usort( $resolved_items, function ( array $a, array $b ) : int {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			} );
		}

		// Enfiler les scripts et styles requis
		wp_enqueue_style( 'eg-media-public-album' );
		wp_enqueue_script( 'eg-media-public-album' );

		// Préparer le rendu
		ob_start();
		?>
		<div class="eg-album" data-album-id="<?php echo $album_id; ?>">
			<div class="eg-album__grid">
				<?php foreach ( $resolved_items as $index => $item ) :
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
			<?php foreach ( $resolved_items as $item ) :
				$unique_item_id = $item['type'] . '-' . $item['id'];
				// Générer le bloc Gutenberg de visionneuse
				$viewer_block_html = render_block( [
					'blockName' => 'eg-media/viewer',
					'attrs'     => [
						'galleryId'     => $item['id'],
						'gallerySource' => $item['type'],
						'layout'        => 'justified',
						'imagesPerPage' => 30,
					],
				] );
			?>
				<div id="eg-viewer-overlay-<?php echo esc_attr( $unique_item_id ); ?>" class="eg-album__overlay" style="display: none;">
					<div class="eg-album__overlay-content">
						<button class="eg-album__overlay-close" aria-label="<?php esc_attr_e( 'Fermer', 'eg-media' ); ?>">&times;</button>
						<h2 class="eg-album__overlay-title"><?php echo esc_html( $item['name'] ); ?></h2>
						<div class="eg-album__overlay-body">
							<?php echo $viewer_block_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
