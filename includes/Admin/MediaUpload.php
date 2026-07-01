<?php
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
		add_action( 'pre-upload-ui', [ $this, 'render_gallery_selector' ], 10, 0 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_upload_scripts' ], 10, 1 );
		add_action( 'add_attachment', [ $this, 'save_uploaded_media_gallery' ], 10, 1 );
		add_filter( 'bulk_actions-upload', [ $this, 'register_bulk_actions' ], 10, 1 );
		add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'show_bulk_action_notice' ] );
		add_action( 'admin_head', [ $this, 'print_inline_styles' ] );
		add_action( 'wp_ajax_eg_media_get_galleries', [ $this, 'ajax_get_galleries' ] );
	}

	/**
	 * Rendu HTML du sélecteur de galerie par défaut au-dessus de la zone de drag-and-drop.
	 *
	 * @return void
	 */
	public function render_gallery_selector(): void {
		$terms = get_terms( [
			'taxonomy'   => 'eg_media_gallery',
			'hide_empty' => false,
		] );

		$galleries = is_array( $terms ) ? $terms : [];
		?>
		<div class="eg-media-upload-gallery-container" style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<label for="eg_media_target_gallery" style="font-weight: 600; display: block; margin-bottom: 8px;">
				<?php esc_html_e( 'Associer les fichiers importés à cette galerie :', 'eg-media' ); ?>
			</label>
			<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 8px;">
				<select name="eg_media_target_gallery" id="eg_media_target_gallery" style="max-width: 300px; width: 100%;">
					<option value=""><?php esc_html_e( '— Aucune galerie par défaut —', 'eg-media' ); ?></option>
					<?php foreach ( $galleries as $gallery ) : ?>
						<?php if ( $gallery instanceof \WP_Term ) : ?>
							<option value="<?php echo esc_attr( (string) $gallery->term_id ); ?>">
								<?php echo esc_html( $gallery->name ); ?>
							</option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<span style="font-size: 13px; color: #646970;"><?php esc_html_e( 'ou', 'eg-media' ); ?></span>
				<input type="text" 
					   name="eg_media_new_target_gallery" 
					   id="eg_media_new_target_gallery" 
					   placeholder="<?php esc_attr_e( 'Créer et associer à une nouvelle galerie...', 'eg-media' ); ?>" 
					   style="max-width: 300px; width: 100%; height: 30px;" />
			</div>
			<p class="description" style="margin: 4px 0 0 0; font-style: italic;">
				<?php esc_html_e( 'Sélectionnez une galerie existante ou tapez un nom pour en créer une nouvelle lors du téléversement.', 'eg-media' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Charge le script JS d'interception d'upload.
	 *
	 * @param string $hook_suffix Le nom de la page courante dans le back-office.
	 * @return void
	 */
	public function enqueue_upload_scripts( string $hook_suffix ): void {
		$allowed_hooks = [
			'post.php',
			'post-new.php',
			'media-new.php',
			'upload.php',
		];

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_script(
			'eg-media-admin-upload',
			plugins_url( 'assets/js/admin-upload.js', dirname( __FILE__, 3 ) . '/eg-media.php' ),
			[ 'jquery', 'media-views' ],
			EG_MEDIA_VERSION,
			true
		);

		$terms = get_terms( [
			'taxonomy'   => 'eg_media_gallery',
			'hide_empty' => false,
		] );

		$galleries_data = [];
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$galleries_data[] = [
						'term_id' => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
					];
				}
			}
		}

		wp_localize_script(
			'eg-media-admin-upload',
			'egMediaUploadData',
			[
				'galleries' => $galleries_data,
				'nonce'     => wp_create_nonce( 'eg-media-upload-nonce' ),
			]
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

		// 1. Vérifier si une nouvelle galerie doit être créée à la volée.
		$new_gallery = isset( $_POST['eg_media_new_target_gallery'] ) ? $_POST['eg_media_new_target_gallery'] : null;
		if ( null !== $new_gallery && '' !== trim( (string) $new_gallery ) ) {
			$new_gallery_name = sanitize_text_field( (string) $new_gallery );
			$term_info = wp_insert_term( $new_gallery_name, 'eg_media_gallery' );

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
			return;
		}

		// 2. Sinon, vérifier si une galerie existante est sélectionnée.
		$target_gallery = isset( $_POST['eg_media_target_gallery'] ) ? $_POST['eg_media_target_gallery'] : null;
		if ( null !== $target_gallery && '' !== $target_gallery ) {
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
	 * @param string       $redirect_to URL de redirection.
	 * @param string       $action      Nom de l'action exécutée.
	 * @param array<int>   $post_ids    Liste des IDs des posts sélectionnés.
	 * @return string URL de redirection finale.
	 */
	public function handle_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'eg_media_bulk_assign' !== $action ) {
			return $redirect_to;
		}

		$gallery_id  = isset( $_REQUEST['eg_media_bulk_gallery'] ) ? (string) $_REQUEST['eg_media_bulk_gallery'] : '';
		$new_gallery = isset( $_REQUEST['eg_media_bulk_new_gallery'] ) ? (string) $_REQUEST['eg_media_bulk_new_gallery'] : '';

		$final_gallery_id = 0;

		// 1. Si saisie d'une nouvelle galerie
		if ( '' !== trim( $new_gallery ) ) {
			$new_gallery_name = sanitize_text_field( $new_gallery );
			$term_info = wp_insert_term( $new_gallery_name, 'eg_media_gallery' );

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
		}
		// 2. Sinon, si sélection d'une galerie existante
		elseif ( '' !== $gallery_id && 'orphan' !== $gallery_id ) {
			$final_gallery_id = (int) $gallery_id;
		}

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id > 0 && current_user_can( 'upload_files' ) ) {
				if ( 'orphan' === $gallery_id && '' === trim( $new_gallery ) ) {
					wp_set_object_terms( $post_id, [], 'eg_media_gallery' );
				} elseif ( $final_gallery_id > 0 ) {
					wp_set_object_terms( $post_id, $final_gallery_id, 'eg_media_gallery' );
				}
				$count++;
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
	 * Imprime les styles CSS personnalisés pour ajuster la disposition des filtres dans la médiathèque.
	 *
	 * @return void
	 */
	public function print_inline_styles(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$allowed_bases = [ 'post', 'upload', 'media' ];
		if ( ! in_array( $screen->base, $allowed_bases, true ) ) {
			return;
		}

		?>
		<style id="eg-media-modal-filters-style">
			/* Élargir le conteneur des filtres Backbone dans les modales pour afficher 3 filtres côte à côte */
			.media-modal .media-frame .media-toolbar-secondary {
				display: grid !important;
				grid-template-rows: auto auto !important;
				grid-auto-flow: column !important;
				gap: 4px 12px !important;
				max-width: 85% !important;
				float: left !important;
				position: relative !important;
			}
			/* Afficher correctement les labels au-dessus de chaque sélecteur */
			.media-modal .media-toolbar-secondary label {
				position: static !important;
				display: block !important;
				font-size: 12px !important;
				font-weight: 600 !important;
				color: #1e1e1e !important;
				margin: 0 !important;
				padding: 0 !important;
				width: auto !important;
				height: auto !important;
				clip: auto !important;
				clip-path: none !important;
				white-space: nowrap !important;
			}
			/* Adapter la taille des sélecteurs de filtres */
			.media-modal select.attachment-filters {
				width: 100% !important;
				max-width: 200px !important;
				margin: 0 !important;
				float: none !important;
				display: block !important;
				height: 32px !important;
			}
			/* Sortir le spinner du flux de la grille pour ne pas perturber les colonnes */
			.media-modal .media-toolbar-secondary .spinner {
				position: absolute !important;
				left: 100% !important;
				top: 50% !important;
				transform: translateY(-50%) !important;
				margin: 0 0 0 10px !important;
				display: inline-block !important;
			}
			/* Styles pour l'image de référence dans la galerie */
			.eg-media-star-badge {
				position: absolute;
				top: 8px;
				left: 8px;
				background: #f3b007;
				color: #fff;
				width: 22px;
				height: 22px;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 14px;
				line-height: 1;
				box-shadow: 0 2px 4px rgba(0,0,0,0.25);
				z-index: 10;
				pointer-events: none;
				font-family: dashicons, sans-serif;
			}
			<?php
			// Récupérer toutes les images de référence pour injecter l'étoile sur les miniatures en mode liste
			$terms = get_terms( [
				'taxonomy'   => 'eg_media_gallery',
				'hide_empty' => false,
			] );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( $term instanceof \WP_Term ) {
						$ref_id = (int) get_term_meta( $term->term_id, '_eg_media_featured_image_id', true );
						if ( $ref_id > 0 ) {
							?>
							#post-<?php echo (int) $ref_id; ?> .column-title .has-media-icon a {
								position: relative;
								display: inline-block;
							}
							#post-<?php echo (int) $ref_id; ?> .column-title .has-media-icon a::after {
								content: "★";
								position: absolute;
								top: -5px;
								left: -5px;
								background: #f3b007;
								color: #fff;
								width: 18px;
								height: 18px;
								border-radius: 50%;
								display: flex;
								align-items: center;
								justify-content: center;
								font-size: 11px;
								line-height: 1;
								box-shadow: 0 1px 3px rgba(0,0,0,0.3);
								z-index: 5;
							}
							<?php
						}
					}
				}
			}
			?>
		</style>
		<?php
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

		$terms = get_terms( [
			'taxonomy'   => 'eg_media_gallery',
			'hide_empty' => false,
		] );

		$galleries_data = [];
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$galleries_data[] = [
						'term_id' => $term->term_id,
						'name'    => $term->name,
					];
				}
			}
		}

		wp_send_json_success( $galleries_data );
	}
}
