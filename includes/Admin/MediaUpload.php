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
		if ( 'media-new.php' !== $hook_suffix && 'upload.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'eg-media-admin-upload',
			plugins_url( 'assets/js/admin-upload.js', dirname( __FILE__, 3 ) . '/eg-media.php' ),
			[ 'jquery' ],
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
					];
				}
			}
		}

		wp_localize_script(
			'eg-media-admin-upload',
			'egMediaUploadData',
			[
				'galleries' => $galleries_data,
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
		$new_gallery = filter_input( INPUT_POST, 'eg_media_new_target_gallery', FILTER_DEFAULT );
		if ( null !== $new_gallery && '' !== trim( (string) $new_gallery ) ) {
			$new_gallery_name = sanitize_text_field( (string) $new_gallery );
			$term_info = wp_insert_term( $new_gallery_name, 'eg_media_gallery' );

			if ( ! is_wp_error( $term_info ) && is_array( $term_info ) && isset( $term_info['term_id'] ) ) {
				$gallery_id = (int) $term_info['term_id'];
				wp_set_object_terms( $post_id, $gallery_id, 'eg_media_gallery' );
			} elseif ( is_wp_error( $term_info ) && 'term_exists' === $term_info->get_error_code() ) {
				$existing_term_id = (int) $term_info->get_error_data();
				if ( $existing_term_id > 0 ) {
					wp_set_object_terms( $post_id, $existing_term_id, 'eg_media_gallery' );
				}
			}
			return;
		}

		// 2. Sinon, vérifier si une galerie existante est sélectionnée.
		$target_gallery = filter_input( INPUT_POST, 'eg_media_target_gallery', FILTER_DEFAULT );
		if ( null !== $target_gallery && '' !== $target_gallery ) {
			$gallery_id = (int) $target_gallery;
			if ( $gallery_id > 0 ) {
				wp_set_object_terms( $post_id, $gallery_id, 'eg_media_gallery' );
			}
		}
	}
}
