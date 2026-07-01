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
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'prepare_attachment_for_js' ], 10, 3 );
		add_filter( 'manage_media_columns', [ $this, 'register_custom_media_columns' ], 99, 1 );
		add_action( 'manage_media_custom_column', [ $this, 'render_custom_media_column' ], 10, 2 );
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

		// Déterminer si cette image est l'image de référence de la galerie courante.
		$is_reference = false;
		if ( $current_gallery_id > 0 ) {
			$reference_image_id = (int) get_term_meta( $current_gallery_id, '_eg_media_featured_image_id', true );
			if ( $reference_image_id === $post->ID ) {
				$is_reference = true;
			}
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
				   style="width: 100%; margin-bottom: 8px;" />

			<div style="margin-bottom: 8px;">
				<label for="attachments-<?php echo esc_attr( (string) $post->ID ); ?>-eg_media_is_reference" style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
					<input type="checkbox" 
						   name="attachments[<?php echo esc_attr( (string) $post->ID ); ?>][eg_media_is_reference]" 
						   id="attachments-<?php echo esc_attr( (string) $post->ID ); ?>-eg_media_is_reference" 
						   value="1" 
						   <?php checked( $is_reference ); ?> 
						   <?php disabled( 0 === $current_gallery_id ); ?> />
					<?php esc_html_e( 'Image de référence de la galerie', 'eg-media' ); ?>
				</label>
			</div>

			<p class="description" style="margin-top: 4px; font-style: italic;">
				<?php esc_html_e( 'Sélectionnez une galerie existante ou saisissez un nom pour en créer une nouvelle, puis cochez la case si vous souhaitez en faire la référence.', 'eg-media' ); ?>
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

		// Vérifier si l'utilisateur courant est autorisé à éditer ce média.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post;
		}

		// Récupérer l'objet de taxonomie pour obtenir les permissions requises.
		$taxonomy = get_taxonomy( 'eg_media_gallery' );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return $post;
		}

		$can_create_terms = current_user_can( $taxonomy->cap->edit_terms );

		$target_gallery_id = 0;
		$old_gallery_id = 0;

		// Récupérer la galerie actuelle associée avant traitement.
		$associated_terms = get_the_terms( $post_id, 'eg_media_gallery' );
		if ( is_array( $associated_terms ) && ! empty( $associated_terms ) ) {
			$first_term = reset( $associated_terms );
			if ( $first_term instanceof \WP_Term ) {
				$old_gallery_id = $first_term->term_id;
			}
		}

		// 1. Vérification si une nouvelle galerie doit être créée à la volée.
		if ( ! empty( $attachment['eg_media_new_gallery'] ) ) {
			$new_gallery_name = sanitize_text_field( (string) $attachment['eg_media_new_gallery'] );
			if ( '' !== trim( $new_gallery_name ) ) {
				if ( $can_create_terms ) {
					$term_info = wp_insert_term( $new_gallery_name, 'eg_media_gallery' );

					if ( is_wp_error( $term_info ) ) {
						if ( 'term_exists' === $term_info->get_error_code() ) {
							// Si le terme existe déjà, on récupère son ID.
							$existing_term_id = (int) $term_info->get_error_data();
							if ( $existing_term_id > 0 ) {
								$target_gallery_id = $existing_term_id;
								wp_set_object_terms( $post_id, $target_gallery_id, 'eg_media_gallery' );
							}
						}
					} elseif ( is_array( $term_info ) && isset( $term_info['term_id'] ) ) {
						$target_gallery_id = (int) $term_info['term_id'];
						wp_set_object_terms( $post_id, $target_gallery_id, 'eg_media_gallery' );
					}
				} else {
					// Fallback si l'utilisateur ne peut pas créer de termes : on utilise la galerie existante si sélectionnée.
					if ( isset( $attachment['eg_media_gallery_select'] ) ) {
						$gallery_val = sanitize_text_field( (string) $attachment['eg_media_gallery_select'] );
						if ( '' !== $gallery_val ) {
							$target_gallery_id = (int) $gallery_val;
							wp_set_object_terms( $post_id, $target_gallery_id, 'eg_media_gallery' );
						}
					}
				}
			}
		}
		// 2. Sinon, on utilise la galerie existante sélectionnée.
		elseif ( isset( $attachment['eg_media_gallery_select'] ) ) {
			$gallery_val = sanitize_text_field( (string) $attachment['eg_media_gallery_select'] );

			if ( '' !== $gallery_val ) {
				$target_gallery_id = (int) $gallery_val;
				wp_set_object_terms( $post_id, $target_gallery_id, 'eg_media_gallery' );
			} else {
				// Si l'option vide "-- Aucune galerie --" est choisie, on dissocie.
				wp_set_object_terms( $post_id, [], 'eg_media_gallery' );
			}
		} else {
			$target_gallery_id = $old_gallery_id;
		}

		// Gestion de l'image de référence.
		$is_reference_checked = ! empty( $attachment['eg_media_is_reference'] );

		// Si l'image change de galerie, elle ne doit plus être la référence de l'ancienne.
		if ( $old_gallery_id > 0 && $old_gallery_id !== $target_gallery_id ) {
			$old_ref_id = (int) get_term_meta( $old_gallery_id, '_eg_media_featured_image_id', true );
			if ( $old_ref_id === $post_id ) {
				if ( $can_create_terms ) {
					delete_term_meta( $old_gallery_id, '_eg_media_featured_image_id' );
				}
			}
		}

		// Mettre à jour l'image de référence pour la galerie ciblée.
		if ( $target_gallery_id > 0 ) {
			if ( $is_reference_checked ) {
				if ( $can_create_terms ) {
					update_term_meta( $target_gallery_id, '_eg_media_featured_image_id', $post_id );
				}
			} else {
				$current_ref_id = (int) get_term_meta( $target_gallery_id, '_eg_media_featured_image_id', true );
				if ( $current_ref_id === $post_id ) {
					if ( $can_create_terms ) {
						delete_term_meta( $target_gallery_id, '_eg_media_featured_image_id' );
					}
				}
			}
		}

		return $post;
	}

	/**
	 * Ajoute des informations sur le statut de référence de l'image aux données JS du média.
	 *
	 * @param array<string, mixed> $response   Données JSON renvoyées pour le média.
	 * @param \WP_Post             $attachment Objet du post de la pièce jointe.
	 * @param mixed                $meta       Métadonnées de la pièce jointe.
	 * @return array<string, mixed> Données JSON modifiées.
	 */
	public function prepare_attachment_for_js( array $response, \WP_Post $attachment, mixed $meta ): array {
		$response['eg_media_is_reference'] = false;
		$response['eg_media_reference_gallery_name'] = '';

		$associated_terms = get_the_terms( $attachment->ID, 'eg_media_gallery' );
		if ( is_array( $associated_terms ) && ! empty( $associated_terms ) ) {
			foreach ( $associated_terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$ref_id = (int) get_term_meta( $term->term_id, '_eg_media_featured_image_id', true );
					if ( $ref_id === $attachment->ID ) {
						$response['eg_media_is_reference'] = true;
						$response['eg_media_reference_gallery_name'] = $term->name;
						break;
					}
				}
			}
		}

		return $response;
	}

	/**
	 * Remplace la colonne de taxonomie par défaut par une colonne personnalisée pour avoir le contrôle sur le rendu.
	 *
	 * @param array<string, string> $posts_columns Liste des colonnes de la médiathèque.
	 * @return array<string, string> Liste des colonnes modifiée.
	 */
	public function register_custom_media_columns( array $posts_columns ): array {
		if ( isset( $posts_columns['taxonomy-eg_media_gallery'] ) ) {
			unset( $posts_columns['taxonomy-eg_media_gallery'] );
		}
		$posts_columns['eg_media_gallery_custom'] = __( 'Galeries', 'eg-media' );
		return $posts_columns;
	}

	/**
	 * Rendu de la colonne personnalisée des galeries avec l'indicateur d'image de référence.
	 *
	 * @param string $column_name Nom de la colonne.
	 * @param int    $post_id     ID du média.
	 * @return void
	 */
	public function render_custom_media_column( string $column_name, int $post_id ): void {
		if ( 'eg_media_gallery_custom' !== $column_name ) {
			return;
		}

		$terms = get_the_terms( $post_id, 'eg_media_gallery' );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			echo '—';
			return;
		}

		$out = [];
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$posts_in_term_args = [
					'post_type' => 'attachment',
					'eg_media_gallery' => $term->slug,
				];
				$url = esc_url( add_query_arg( $posts_in_term_args, 'upload.php' ) );
				$term_link = sprintf( '<a href="%s">%s</a>', $url, esc_html( $term->name ) );

				// Vérifier si ce post est l'image de référence de ce terme.
				$ref_id = (int) get_term_meta( $term->term_id, '_eg_media_featured_image_id', true );
				if ( $ref_id === $post_id ) {
					$star_html = ' <span class="dashicons dashicons-star-filled" style="color: #f3b007; font-size: 18px; width: 18px; height: 18px; vertical-align: text-top; margin-left: 2px;" title="' . esc_attr__( 'Image de référence de la galerie', 'eg-media' ) . '"></span>';
					$term_link .= $star_html;
				}
				$out[] = $term_link;
			}
		}

		echo implode( ', ', $out );
	}
}
