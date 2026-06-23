<?php
declare(strict_types=1);

namespace EG_MEDIA\Admin;

/**
 * Class MediaFilter
 *
 * Gère le filtrage des pièces jointes (attachments) par galerie (taxonomie 'eg_media_gallery')
 * dans la liste des médias du back-office WordPress.
 *
 * @package EG_MEDIA\Admin
 */
class MediaFilter {

	/**
	 * Enregistre les hooks WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'restrict_manage_posts', [ $this, 'add_gallery_dropdown' ], 10, 1 );
		add_action( 'parse_query', [ $this, 'filter_attachments_query' ], 10, 1 );
		add_filter( 'ajax_query_attachments_args', [ $this, 'filter_ajax_attachments_query' ], 10, 1 );
	}

	/**
	 * Affiche le menu déroulant des galeries dans la liste des médias.
	 *
	 * @param string $post_type Le type de post actuel (doit être 'attachment').
	 * @return void
	 */
	public function add_gallery_dropdown( string $post_type ): void {
		if ( 'attachment' !== $post_type ) {
			// S'assure que nous sommes sur l'écran des médias (upload) ou similaire pour les pièces jointes.
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || 'upload' !== $screen->base ) {
				return;
			}
		}

		// Récupérer toutes les galeries existantes.
		$terms = get_terms( [
			'taxonomy'   => 'eg_media_gallery',
			'hide_empty' => false,
		] );

		$galleries = is_array( $terms ) ? $terms : [];
		$selected  = filter_input( INPUT_GET, 'eg_media_gallery_filter', FILTER_DEFAULT );
		$selected  = is_string( $selected ) ? $selected : '';

		if ( '' === $selected ) {
			$gallery_slug = filter_input( INPUT_GET, 'eg_media_gallery', FILTER_DEFAULT );
			if ( is_string( $gallery_slug ) && '' !== $gallery_slug ) {
				$term = get_term_by( 'slug', $gallery_slug, 'eg_media_gallery' );
				if ( $term instanceof \WP_Term ) {
					$selected = (string) $term->term_id;
				}
			}
		}

		?>
		<label class="screen-reader-text" for="eg_media_gallery_filter">
			<?php esc_html_e( 'Filtrer par galerie', 'eg-media' ); ?>
		</label>
		<select name="eg_media_gallery_filter" id="eg_media_gallery_filter">
			<option value=""><?php esc_html_e( 'Toutes les galeries', 'eg-media' ); ?></option>
			<option value="orphan" <?php selected( $selected, 'orphan' ); ?>>
				<?php esc_html_e( '— Sans affectation —', 'eg-media' ); ?>
			</option>
			<?php foreach ( $galleries as $gallery ) : ?>
				<?php if ( $gallery instanceof \WP_Term ) : ?>
					<option value="<?php echo esc_attr( (string) $gallery->term_id ); ?>" <?php selected( $selected, (string) $gallery->term_id ); ?>>
						<?php echo esc_html( $gallery->name ); ?>
					</option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Modifie la requête WordPress de l'affichage des médias pour filtrer par galerie.
	 *
	 * @param \WP_Query $query L'objet WP_Query.
	 * @return void
	 */
	public function filter_attachments_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Vérifier si nous sommes bien dans la bibliothèque de médias en mode liste.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->base ) {
			return;
		}

		$gallery_id = filter_input( INPUT_GET, 'eg_media_gallery_filter', FILTER_DEFAULT );
		if ( null === $gallery_id || '' === $gallery_id ) {
			return;
		}

		$gallery_id = (string) $gallery_id;
		$tax_query  = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		if ( 'orphan' === $gallery_id ) {
			$tax_query[] = [
				'taxonomy' => 'eg_media_gallery',
				'operator' => 'NOT EXISTS',
			];
		} else {
			$term_id = (int) $gallery_id;
			if ( $term_id > 0 ) {
				$tax_query[] = [
					'taxonomy' => 'eg_media_gallery',
					'field'    => 'term_id',
					'terms'    => $term_id,
				];
			}
		}

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Modifie les arguments de la requête AJAX pour filtrer par galerie dans le mode grille.
	 *
	 * @param array<string, mixed> $query_args Arguments de la requête WP_Query.
	 * @return array<string, mixed> Arguments de la requête modifiés.
	 */
	public function filter_ajax_attachments_query( array $query_args ): array {
		$query = isset( $_POST['query'] ) && is_array( $_POST['query'] ) ? $_POST['query'] : [];
		$gallery_id = isset( $query['eg_media_gallery_filter'] ) ? (string) $query['eg_media_gallery_filter'] : '';

		if ( '' === $gallery_id ) {
			return $query_args;
		}

		if ( ! isset( $query_args['tax_query'] ) || ! is_array( $query_args['tax_query'] ) ) {
			$query_args['tax_query'] = [];
		}

		if ( 'orphan' === $gallery_id ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'eg_media_gallery',
				'operator' => 'NOT EXISTS',
			];
		} else {
			$term_id = (int) $gallery_id;
			if ( $term_id > 0 ) {
				$query_args['tax_query'][] = [
					'taxonomy' => 'eg_media_gallery',
					'field'    => 'term_id',
					'terms'    => $term_id,
				];
			}
		}

		return $query_args;
	}
}
