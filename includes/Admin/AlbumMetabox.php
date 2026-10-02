<?php
declare(strict_types=1);

namespace EG_MEDIA\Admin;

/**
 * Class AlbumMetabox
 *
 * Gère l'affichage et l'enregistrement de la Metabox pour la composition de l'album (CPT eg_media_album).
 *
 * @package EG_MEDIA\Admin
 */
class AlbumMetabox {

	/**
	 * Enregistre les actions WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_eg_media_album', [ $this, 'add_album_meta_box' ] );
		add_action( 'save_post_eg_media_album', [ $this, 'save_album_meta_box' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Charge les assets nécessaires dans l'administration (styles spécifiques à la metabox).
	 *
	 * @param string $hook La page courante de l'admin.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		global $post_type;
		if ( 'eg_media_album' !== $post_type ) {
			return;
		}

		// Ajout de styles en ligne minimalistes pour la metabox
		wp_add_inline_style( 'wp-admin', "
			.eg-album-metabox { font-family: sans-serif; }
			.eg-album-row { display: flex; gap: 15px; margin-bottom: 15px; align-items: flex-end; }
			.eg-album-col { flex: 1; }
			.eg-album-col label { display: block; font-weight: bold; margin-bottom: 5px; }
			.eg-album-col select { width: 100%; height: 35px; }
			.eg-album-btn { background: #007cba; border: none; color: white; padding: 8px 16px; border-radius: 3px; cursor: pointer; height: 35px; }
			.eg-album-btn:hover { background: #006ba1; }
			.eg-album-items-list { border: 1px solid #ccc; background: #fff; padding: 10px; border-radius: 4px; min-height: 50px; margin-top: 15px; }
			.eg-album-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border: 1px solid #dfdfdf; background: #f9f9f9; margin-bottom: 6px; border-radius: 3px; cursor: grab; }
			.eg-album-item.dragging { opacity: 0.5; border: 1px dashed #007cba; }
			.eg-album-item-title { font-weight: 500; }
			.eg-album-item-type { font-size: 11px; background: #e0e0e0; padding: 2px 6px; border-radius: 10px; text-transform: uppercase; color: #555; }
			.eg-album-item-remove { color: #d63638; cursor: pointer; text-decoration: underline; font-size: 13px; }
		" );

		wp_enqueue_script(
			'eg-media-admin-album-metabox',
			plugins_url( 'assets/js/admin-album-metabox.js', dirname( __DIR__, 2 ) . '/eg-media.php' ),
			[],
			EG_MEDIA_VERSION,
			true
		);
	}

	/**
	 * Ajoute la metabox sur l'écran d'édition de l'album.
	 *
	 * @return void
	 */
	public function add_album_meta_box(): void {
		add_meta_box(
			'eg_media_album_settings',
			"Contenu et Organisation de l'Album",
			[ $this, 'render_meta_box' ],
			'eg_media_album',
			'normal',
			'high'
		);
	}

	/**
	 * Rendu de la metabox.
	 *
	 * @param \WP_Post $post L'objet du post courant.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'eg_media_save_album_meta', 'eg_media_album_nonce' );

		// Récupérer les valeurs existantes
		$sort_mode = get_post_meta( $post->ID, '_eg_media_album_sort', true );
		if ( empty( $sort_mode ) ) {
			$sort_mode = 'manual';
		}

		$items_meta = get_post_meta( $post->ID, '_eg_media_album_items', true );
		$items = ! empty( $items_meta ) ? json_decode( $items_meta, true ) : [];
		if ( ! is_array( $items ) ) {
			$items = [];
		}

		// Récupérer les galeries locales
		$local_terms = get_terms( [
			'taxonomy'   => 'eg_media_gallery',
			'hide_empty' => false,
		] );
		$local_galleries = is_array( $local_terms ) ? $local_terms : [];

		// Récupérer les albums Piwigo
		$piwigo_service = new \EG_MEDIA\Services\Piwigo();
		$piwigo_albums = $piwigo_service->get_albums();

		?>
		<div class="eg-album-metabox">
			<?php if ( 'publish' === $post->post_status || 'draft' === $post->post_status || 'pending' === $post->post_status ) : ?>
				<div style="background: #f0f6fc; border-left: 4px solid #007cba; padding: 12px; margin-bottom: 20px; border-radius: 0 4px 4px 0;">
					<p style="margin: 0; font-size: 13px; color: #1d2327;">
						<strong>Code court à insérer dans votre page :</strong> 
						<code style="background: #fff; padding: 3px 6px; border: 1px solid #ccd0d4; border-radius: 3px; font-family: monospace; font-size: 13px;">[eg_media_album id="<?php echo esc_attr( (string) $post->ID ); ?>"]</code>
					</p>
				</div>
			<?php endif; ?>

			<div class="eg-album-row">
				<div class="eg-album-col">
					<label for="eg_media_album_sort">Mode de Tri des Galeries</label>
					<select name="eg_media_album_sort" id="eg_media_album_sort">
						<option value="manual" <?php selected( $sort_mode, 'manual' ); ?>>Tri manuel (Glisser-Déposer)</option>
						<option value="alphabetical" <?php selected( $sort_mode, 'alphabetical' ); ?>>Tri automatique par ordre alphabétique</option>
					</select>
				</div>
			</div>

			<hr style="margin: 15px 0;" />

			<div class="eg-album-row">
				<div class="eg-album-col" style="flex: 2;">
					<label for="eg_media_add_local_gallery">Ajouter une Galerie Locale</label>
					<select id="eg_media_add_local_gallery">
						<option value="">-- Sélectionner une galerie locale --</option>
						<?php foreach ( $local_galleries as $gallery ) : ?>
							<?php if ( $gallery instanceof \WP_Term ) : ?>
								<option value="<?php echo esc_attr( (string) $gallery->term_id ); ?>" data-name="<?php echo esc_attr( $gallery->name ); ?>">
									<?php echo esc_html( $gallery->name ); ?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="eg-album-col" style="flex: 0;">
					<button type="button" class="eg-album-btn" id="eg_media_btn_add_local">Ajouter</button>
				</div>
			</div>

			<div class="eg-album-row">
				<div class="eg-album-col" style="flex: 2;">
					<label for="eg_media_add_piwigo_album">Ajouter un Album Piwigo</label>
					<select id="eg_media_add_piwigo_album">
						<option value="">-- Sélectionner un album Piwigo --</option>
						<?php foreach ( $piwigo_albums as $p_album ) : ?>
							<option value="<?php echo esc_attr( (string) $p_album['id'] ); ?>" data-name="<?php echo esc_attr( $p_album['name'] ); ?>">
								<?php echo esc_html( $p_album['name'] ); ?> (Piwigo)
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="eg-album-col" style="flex: 0;">
					<button type="button" class="eg-album-btn" id="eg_media_btn_add_piwigo">Ajouter</button>
				</div>
			</div>

			<h3 style="margin-top: 20px; margin-bottom: 5px;">Galeries contenues dans l'Album</h3>
			<p class="description" id="eg_media_sort_desc">
				<?php echo 'manual' === $sort_mode ? "Faites glisser les éléments pour réorganiser l'ordre d'affichage." : "Le tri automatique est activé. L'ordre ci-dessous n'a pas d'influence."; ?>
			</p>

			<div class="eg-album-items-list" id="eg_media_album_items_container">
				<!-- Généré par JS -->
			</div>

			<input type="hidden" name="eg_media_album_items" id="eg_media_album_items_input" value="<?php echo esc_attr( (string) $items_meta ); ?>" />
		</div>
		<?php
	}

	/**
	 * Enregistre les métadonnées lors de la sauvegarde du post.
	 *
	 * @param int      $post_id ID de l'album en cours d'enregistrement.
	 * @param \WP_Post $post    Objet du post courant.
	 * @return void
	 */
	public function save_album_meta_box( int $post_id, \WP_Post $post ): void {
		// Vérification du nonce
		if ( ! isset( $_POST['eg_media_album_nonce'] ) || ! wp_verify_nonce( $_POST['eg_media_album_nonce'], 'eg_media_save_album_meta' ) ) {
			return;
		}

		// Vérification des droits
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sauvegarde du mode de tri
		if ( isset( $_POST['eg_media_album_sort'] ) ) {
			$sort_mode = sanitize_text_field( $_POST['eg_media_album_sort'] );
			update_post_meta( $post_id, '_eg_media_album_sort', $sort_mode );
		}

		// Sauvegarde des éléments
		if ( isset( $_POST['eg_media_album_items'] ) ) {
			$items_raw = wp_unslash( (string) $_POST['eg_media_album_items'] );
			$items_decoded = json_decode( $items_raw, true );

			if ( is_array( $items_decoded ) ) {
				// Sanitization des entrées
				$sanitized_items = [];
				foreach ( $items_decoded as $item ) {
					if ( isset( $item['type'], $item['id'], $item['name'] ) ) {
						$sanitized_items[] = [
							'type' => sanitize_text_field( (string) $item['type'] ),
							'id'   => (int) $item['id'],
							'name' => sanitize_text_field( (string) $item['name'] ),
						];
					}
				}
				update_post_meta( $post_id, '_eg_media_album_items', wp_json_encode( $sanitized_items ) );
			} else {
				update_post_meta( $post_id, '_eg_media_album_items', wp_json_encode( [] ) );
			}
		}
	}
}
