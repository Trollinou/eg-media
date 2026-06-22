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
			<p class="description" style="margin: 4px 0 0 0; font-style: italic;">
				<?php esc_html_e( 'Tous les fichiers téléversés dans cette session seront automatiquement associés à la galerie sélectionnée.', 'eg-media' ); ?>
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
	}

	/**
	 * Associe immédiatement le média téléversé à la galerie ciblée si présente en $_POST.
	 *
	 * @param int $post_id ID de la pièce jointe créée.
	 * @return void
	 */
	public function save_uploaded_media_gallery( int $post_id ): void {
		// Vérifier si le paramètre ciblant la galerie est défini.
		$target_gallery = filter_input( INPUT_POST, 'eg_media_target_gallery', FILTER_DEFAULT );
		if ( null === $target_gallery || '' === $target_gallery ) {
			return;
		}

		// Sécurisation : s'assurer que l'utilisateur a les droits d'import de fichiers.
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$gallery_id = (int) $target_gallery;
		if ( $gallery_id > 0 ) {
			wp_set_object_terms( $post_id, $gallery_id, 'eg_media_gallery' );
		}
	}
}
