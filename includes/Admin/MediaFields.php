<?php
declare(strict_types=1);

namespace EG_MEDIA\Admin;

/**
 * Class MediaFields
 *
 * Gère l'affichage et la sauvegarde des champs personnalisés pour les pièces jointes (attachments),
 * spécifiquement pour la taxonomie 'eg_media_gallery'.
 *
 * @package EG_MEDIA\Admin
 */
class MediaFields {

	/**
	 * Enregistre les filtres WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_gallery_fields' ], 10, 2 );
		add_filter( 'attachment_fields_to_save', [ $this, 'save_gallery_fields' ], 10, 2 );
	}

	/**
	 * Injecte l'interface de sélection et de création de galerie dans le panneau de détails du média.
	 *
	 * @param array<string, array<string, mixed>> $form_fields Champs du formulaire existants.
	 * @param \WP_Post                            $post        Objet du post de la pièce jointe.
	 * @return array<string, array<string, mixed>> Les champs du formulaire modifiés.
	 */
	public function add_gallery_fields( array $form_fields, \WP_Post $post ): array {
		// Récupérer toutes les galeries existantes.
		$terms = get_terms( [
			'taxonomy'   => 'eg_media_gallery',
			'hide_empty' => false,
		] );

		// S'assurer que $terms est bien un tableau de WP_Term.
		$galleries = is_array( $terms ) ? $terms : [];

		// Récupérer la galerie actuellement associée à ce média.
		$associated_terms = get_the_terms( $post->ID, 'eg_media_gallery' );
		$current_gallery_id = 0;

		if ( is_array( $associated_terms ) && ! empty( $associated_terms ) ) {
			// On prend la première galerie associée.
			$first_term = reset( $associated_terms );
			if ( $first_term instanceof \WP_Term ) {
				$current_gallery_id = $first_term->term_id;
			}
		}

		// Supprimer le champ de taxonomie par défaut généré automatiquement par WordPress pour éviter les doublons et les conflits.
		if ( isset( $form_fields['eg_media_gallery'] ) ) {
			unset( $form_fields['eg_media_gallery'] );
		}

		// Construction du HTML pour le sélecteur et l'ajout rapide.
		ob_start();
		?>
		<div class="eg-media-gallery-fields">
			<select name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][eg_media_gallery_select]" id="attachments-<?php echo esc_attr( (string) $post->ID ); ?>-eg_media_gallery_select" style="width: 100%; margin-bottom: 8px;">
				<option value=""><?php esc_html_e( '-- Aucune galerie --', 'eg-media' ); ?></option>
				<?php foreach ( $galleries as $gallery ) : ?>
					<?php if ( $gallery instanceof \WP_Term ) : ?>
						<option value="<?php echo esc_attr( (string) $gallery->term_id ); ?>" <?php selected( $current_gallery_id, $gallery->term_id ); ?>>
							<?php echo esc_html( $gallery->name ); ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>

			<input type="text" 
				   name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][eg_media_new_gallery]" 
				   id="attachments-<?php echo esc_attr( (string) $post->ID ); ?>-eg_media_new_gallery" 
				   placeholder="<?php esc_attr_e( 'Nouvelle galerie...', 'eg-media' ); ?>" 
				   style="width: 100%;" />
			<p class="description" style="margin-top: 4px; font-style: italic;">
				<?php esc_html_e( 'Sélectionnez une galerie existante ou saisissez un nom pour en créer une nouvelle.', 'eg-media' ); ?>
			</p>
		</div>
		<?php
		$html_content = ob_get_clean();

		$form_fields['eg_media_gallery_select'] = [
			'label' => __( 'Galerie', 'eg-media' ),
			'input' => 'html',
			'html'  => (string) $html_content,
		];

		return $form_fields;
	}

	/**
	 * Traite et sauvegarde les données de galerie associées au média.
	 *
	 * @param array<string, mixed> $post       Tableau des attributs du post de pièce jointe.
	 * @param array<string, mixed> $attachment Champs soumis pour la pièce jointe.
	 * @return array<string, mixed> Les attributs du post modifiés ou non.
	 */
	public function save_gallery_fields( array $post, array $attachment ): array {
		$post_id = isset( $post['ID'] ) ? (int) $post['ID'] : 0;
		if ( 0 === $post_id ) {
			return $post;
		}

		// 1. Vérification si une nouvelle galerie doit être créée à la volée.
		if ( ! empty( $attachment['eg_media_new_gallery'] ) ) {
			$new_gallery_name = sanitize_text_field( (string) $attachment['eg_media_new_gallery'] );
			if ( '' !== trim( $new_gallery_name ) ) {
				// Création ou récupération du terme.
				$term_info = wp_insert_term( $new_gallery_name, 'eg_media_gallery' );

				if ( ! is_wp_error( $term_info ) && is_array( $term_info ) && isset( $term_info['term_id'] ) ) {
					$term_id = (int) $term_info['term_id'];
					wp_set_object_terms( $post_id, $term_id, 'eg_media_gallery' );
				} elseif ( 'term_exists' === $term_info->get_error_code() ) {
					// Si le terme existe déjà, on récupère son ID.
					$existing_term_id = (int) $term_info->get_error_data();
					if ( $existing_term_id > 0 ) {
						wp_set_object_terms( $post_id, $existing_term_id, 'eg_media_gallery' );
					}
				}
			}
		}
		// 2. Sinon, on utilise la galerie existante sélectionnée.
		elseif ( isset( $attachment['eg_media_gallery_select'] ) ) {
			$gallery_val = sanitize_text_field( (string) $attachment['eg_media_gallery_select'] );

			if ( '' !== $gallery_val ) {
				$term_id = (int) $gallery_val;
				wp_set_object_terms( $post_id, $term_id, 'eg_media_gallery' );
			} else {
				// Si l'option vide "-- Aucune galerie --" est choisie, on dissocie.
				wp_set_object_terms( $post_id, [], 'eg_media_gallery' );
			}
		}

		return $post;
	}
}
