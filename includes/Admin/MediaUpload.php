<?php
/**
 * Media Upload Admin Handler.
 *
 * @package EG_Media
 */

declare(strict_types=1);

namespace EG_MEDIA\Admin;

/**
 * Class MediaUpload
 *
 * Gère l'association d'une galerie par défaut lors du téléversement groupé (Bulk) de médias.
 *
 * @package EG_MEDIA\Admin
 */
class MediaUpload {

	/**
	 * Enregistre les hooks WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre-upload-ui', array( $this, 'render_gallery_selector' ), 10, 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_upload_scripts' ), 10, 1 );
		add_action( 'add_attachment', array( $this, 'save_uploaded_media_gallery' ), 10, 1 );
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_actions' ), 10, 1 );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'show_bulk_action_notice' ) );
		add_action( 'wp_ajax_eg_media_get_galleries', array( $this, 'ajax_get_galleries' ) );
	}

	/**
	 * Rendu HTML du sélecteur de galerie par défaut au-dessus de la zone de drag-and-drop.
	 *
	 * @return void
	 */
	public function render_gallery_selector(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'eg_media_gallery',
				'hide_empty' => false,
			)
		);

		$galleries = is_array( $terms ) ? $terms : array();
		?>
		<div class="eg-media-upload-gallery-container">
			<label for="eg_media_target_gallery" class="eg-media-upload-gallery-container__label">
				<?php esc_html_e( 'Associer les fichiers importés à cette galerie :', 'eg-media' ); ?>
			</label>
			<div class="eg-media-upload-gallery-container__controls">
				<select name="eg_media_target_gallery" id="eg_media_target_gallery" class="eg-media-upload-gallery-container__select">
					<option value=""><?php esc_html_e( '— Aucune galerie par défaut —', 'eg-media' ); ?></option>
					<?php foreach ( $galleries as $gallery ) : ?>
						<?php if ( $gallery instanceof \WP_Term ) : ?>
							<option value="<?php echo esc_attr( (string) $gallery->term_id ); ?>">
								<?php echo esc_html( $gallery->name ); ?>
							</option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<span class="eg-media-upload-gallery-container__separator"><?php esc_html_e( 'ou', 'eg-media' ); ?></span>
				<input type="text" 
						name="eg_media_new_target_gallery" 
						id="eg_media_new_target_gallery" 
						placeholder="<?php esc_attr_e( 'Créer et associer à une nouvelle galerie...', 'eg-media' ); ?>" 
						class="eg-media-upload-gallery-container__input" />
			</div>
			<p class="description eg-media-upload-gallery-container__description">
				<?php esc_html_e( 'Sélectionnez une galerie existante ou tapez un nom pour en créer une nouvelle lors du téléversement.', 'eg-media' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Charge les scripts et styles JS/CSS d'interception d'upload.
	 *
	 * @param string $hook_suffix Le nom de la page courante dans le back-office.
	 * @return void
	 */
	public function enqueue_upload_scripts( string $hook_suffix ): void {
		$allowed_hooks = array(
			'post.php',
			'post-new.php',
			'media-new.php',
			'upload.php',
		);

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'eg-media-admin-upload',
			plugins_url( 'assets/css/admin-upload.css', dirname( __DIR__, 2 ) . '/eg-media.php' ),
			array(),
			EG_MEDIA_VERSION
		);

		wp_enqueue_style(
			'eg-media-admin-media-fields',
			plugins_url( 'assets/css/admin-media-fields.css', dirname( __DIR__, 2 ) . '/eg-media.php' ),
			array(),
			EG_MEDIA_VERSION
		);

		wp_enqueue_script(
			'eg-media-admin-upload',
			plugins_url( 'assets/js/admin-upload.js', dirname( __DIR__, 2 ) . '/eg-media.php' ),
			array( 'jquery', 'media-views' ),
			EG_MEDIA_VERSION,
			true
		);

		$terms = get_terms(
			array(
				'taxonomy'   => 'eg_media_gallery',
				'hide_empty' => false,
			)
		);

		$galleries_data = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$galleries_data[] = array(
						'term_id' => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
					);
				}
			}
		}

		wp_localize_script(
			'eg-media-admin-upload',
			'egMediaUploadData',
			array(
				'galleries' => $galleries_data,
				'nonce'     => wp_create_nonce( 'eg-media-upload-nonce' ),
			)
		);
	}

	/**
	 * Associe immédiatement le média téléversé à la galerie ciblée si présente en $_POST.
	 *
	 * @param int $post_id ID de la pièce jointe créée.
	 * @return void
	 */
	public function save_uploaded_media_gallery( int $post_id ): void {
		// Sécurisation : s'assurer que l'utilisateur a les droits d'import de fichiers.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// Récupérer l'objet de taxonomie pour obtenir les permissions requises.
		$taxonomy = get_taxonomy( 'eg_media_gallery' );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return;
		}

		$can_create_terms = current_user_can( $taxonomy->cap->edit_terms );

		// 1. Vérifier si une nouvelle galerie doit être créée à la volée.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WordPress media upload handler.
		$new_gallery = isset( $_POST['eg_media_new_target_gallery'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['eg_media_new_target_gallery'] ) ) : '';
		if ( '' !== trim( $new_gallery ) ) {
			if ( $can_create_terms ) {
				$term_info = wp_insert_term( $new_gallery, 'eg_media_gallery' );

				if ( is_wp_error( $term_info ) ) {
					if ( 'term_exists' === $term_info->get_error_code() ) {
						$existing_term_id = (int) $term_info->get_error_data();
						if ( $existing_term_id > 0 ) {
							wp_set_object_terms( $post_id, $existing_term_id, 'eg_media_gallery' );
						}
					}
				} elseif ( is_array( $term_info ) && isset( $term_info['term_id'] ) ) {
					$gallery_id = (int) $term_info['term_id'];
					wp_set_object_terms( $post_id, $gallery_id, 'eg_media_gallery' );
				}
			} else {
				// Fallback si création impossible, on tente d'utiliser la galerie existante sélectionnée.
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WordPress media upload handler.
				$target_gallery = isset( $_POST['eg_media_target_gallery'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['eg_media_target_gallery'] ) ) : '';
				if ( '' !== $target_gallery ) {
					$gallery_id = (int) $target_gallery;
					if ( $gallery_id > 0 ) {
						wp_set_object_terms( $post_id, $gallery_id, 'eg_media_gallery' );
					}
				}
			}
			return;
		}

		// 2. Sinon, vérifier si une galerie existante est sélectionnée.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WordPress media upload handler.
		$target_gallery = isset( $_POST['eg_media_target_gallery'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['eg_media_target_gallery'] ) ) : '';
		if ( '' !== $target_gallery ) {
			$gallery_id = (int) $target_gallery;
			if ( $gallery_id > 0 ) {
				wp_set_object_terms( $post_id, $gallery_id, 'eg_media_gallery' );
			}
		}
	}

	/**
	 * Enregistre l'action groupée dans la liste des pièces jointes.
	 *
	 * @param array<string, string> $actions Liste des actions groupées.
	 * @return array<string, string> Liste des actions modifiée.
	 */
	public function register_bulk_actions( array $actions ): array {
		$actions['eg_media_bulk_assign'] = __( 'Associer à une galerie', 'eg-media' );
		return $actions;
	}

	/**
	 * Traite l'action groupée d'association de galerie.
	 *
	 * @param string     $redirect_to URL de redirection.
	 * @param string     $action      Nom de l'action exécutée.
	 * @param array<int> $post_ids    Liste des IDs des posts sélectionnés.
	 * @return string URL de redirection finale.
	 */
	public function handle_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'eg_media_bulk_assign' !== $action ) {
			return $redirect_to;
		}

		$taxonomy = get_taxonomy( 'eg_media_gallery' );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return $redirect_to;
		}

		$can_create_terms = current_user_can( $taxonomy->cap->edit_terms );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce already verified by WP list table handle_bulk_actions.
		$gallery_id  = isset( $_REQUEST['eg_media_bulk_gallery'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['eg_media_bulk_gallery'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce already verified by WP list table handle_bulk_actions.
		$new_gallery = isset( $_REQUEST['eg_media_bulk_new_gallery'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['eg_media_bulk_new_gallery'] ) ) : '';

		$final_gallery_id = 0;

		// 1. Si saisie d'une nouvelle galerie.
		if ( '' !== trim( $new_gallery ) ) {
			if ( $can_create_terms ) {
				$term_info = wp_insert_term( $new_gallery, 'eg_media_gallery' );

				if ( is_wp_error( $term_info ) ) {
					if ( 'term_exists' === $term_info->get_error_code() ) {
						$existing_term_id = (int) $term_info->get_error_data();
						if ( $existing_term_id > 0 ) {
							$final_gallery_id = $existing_term_id;
						}
					}
				} elseif ( is_array( $term_info ) && isset( $term_info['term_id'] ) ) {
					$final_gallery_id = (int) $term_info['term_id'];
				}
			} elseif ( '' !== $gallery_id && 'orphan' !== $gallery_id ) {
				// Fallback si création impossible.
				$final_gallery_id = (int) $gallery_id;
			}
		} elseif ( '' !== $gallery_id && 'orphan' !== $gallery_id ) {
			// 2. Sinon, si sélection d'une galerie existante.
			$final_gallery_id = (int) $gallery_id;
		}

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
				if ( 'orphan' === $gallery_id && '' === trim( $new_gallery ) ) {
					wp_set_object_terms( $post_id, array(), 'eg_media_gallery' );
				} elseif ( $final_gallery_id > 0 ) {
					wp_set_object_terms( $post_id, $final_gallery_id, 'eg_media_gallery' );
				}
				++$count;
			}
		}

		return add_query_arg( 'eg_media_bulk_assigned_count', $count, $redirect_to );
	}

	/**
	 * Affiche la notification de succès après traitement de l'action groupée.
	 *
	 * @return void
	 */
	public function show_bulk_action_notice(): void {
		$count = filter_input( INPUT_GET, 'eg_media_bulk_assigned_count', FILTER_VALIDATE_INT );
		if ( $count && $count > 0 ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of media files */
						esc_html( _n( '%d média a été associé avec succès.', '%d médias ont été associés avec succès.', $count, 'eg-media' ) ),
						(int) $count
					);
					?>
				</p>
			</div>
			<?php
		}
	}



	/**
	 * Récupère la liste des galeries en AJAX.
	 *
	 * @return void
	 */
	public function ajax_get_galleries(): void {
		check_ajax_referer( 'eg-media-upload-nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$taxonomy = get_taxonomy( 'eg_media_gallery' );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'eg_media_gallery',
				'hide_empty' => false,
			)
		);

		$galleries_data = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$galleries_data[] = array(
						'term_id' => $term->term_id,
						'name'    => $term->name,
					);
				}
			}
		}

		wp_send_json_success( $galleries_data );
	}
}
