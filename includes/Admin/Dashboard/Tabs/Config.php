<?php
/**
 * Onglet Configuration du tableau de bord.
 *
 * @package    EG_MEDIA
 * @subpackage Admin/Dashboard/Tabs
 * @author     EG
 */

declare(strict_types=1);

namespace EG_MEDIA\Admin\Dashboard\Tabs;

/**
 * Classe Config.
 */
class Config {

	/**
	 * Initialise les réglages via l'API Settings de WordPress.
	 *
	 * @return void
	 */
	public function init_settings(): void {
		register_setting(
			'eg_media_settings_group',
			'eg_media_resize_max_width',
			[
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
				'default'           => \EG_MEDIA\DTO\Image_Settings::DEFAULT_MAX_WIDTH,
			]
		);

		register_setting(
			'eg_media_settings_group',
			'eg_media_compression_quality',
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_quality' ],
				'default'           => \EG_MEDIA\DTO\Image_Settings::DEFAULT_COMPRESSION_QUALITY,
			]
		);

		register_setting(
			'eg_media_settings_group',
			'eg_media_png_compression',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_png_compression' ],
				'default'           => \EG_MEDIA\DTO\Image_Settings::DEFAULT_PNG_COMPRESSION,
			]
		);

		register_setting(
			'eg_media_settings_group',
			'eg_media_unsharp_mask',
			[
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'default'           => \EG_MEDIA\DTO\Image_Settings::DEFAULT_UNSHARP_MASK,
			]
		);

		register_setting(
			'eg_media_settings_group',
			'eg_media_auto_orient',
			[
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'default'           => \EG_MEDIA\DTO\Image_Settings::DEFAULT_AUTO_ORIENT,
			]
		);

		register_setting(
			'eg_media_settings_group',
			'eg_media_chrominance',
			[
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'default'           => \EG_MEDIA\DTO\Image_Settings::DEFAULT_CHROMINANCE,
			]
		);

		register_setting(
			'eg_media_settings_group',
			'eg_media_interlace',
			[
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'default'           => \EG_MEDIA\DTO\Image_Settings::DEFAULT_INTERLACE,
			]
		);

		add_settings_section(
			'eg_media_main_section',
			'Paramètres Imagick',
			'__return_false',
			'eg-media-dashboard-config'
		);

		add_settings_field(
			'eg_media_resize_max_width',
			'Largeur Max (Resize)',
			[ $this, 'render_max_width_field' ],
			'eg-media-dashboard-config',
			'eg_media_main_section'
		);

		add_settings_field(
			'eg_media_compression_quality',
			'Qualité JPEG/WebP',
			[ $this, 'render_quality_field' ],
			'eg-media-dashboard-config',
			'eg_media_main_section'
		);

		add_settings_field(
			'eg_media_png_compression',
			'Compression PNG',
			[ $this, 'render_png_compression_field' ],
			'eg-media-dashboard-config',
			'eg_media_main_section'
		);

		add_settings_field(
			'eg_media_unsharp_mask',
			'Améliorer le piqué (Unsharp Mask)',
			[ $this, 'render_checkbox_field' ],
			'eg-media-dashboard-config',
			'eg_media_main_section',
			[ 'label_for' => 'eg_media_unsharp_mask' ]
		);

		add_settings_field(
			'eg_media_auto_orient',
			'Redressement automatique (Auto-orient)',
			[ $this, 'render_checkbox_field' ],
			'eg-media-dashboard-config',
			'eg_media_main_section',
			[ 'label_for' => 'eg_media_auto_orient' ]
		);

		add_settings_field(
			'eg_media_chrominance',
			'Compression Couleur (Chrominance 4:2:0)',
			[ $this, 'render_checkbox_field' ],
			'eg-media-dashboard-config',
			'eg_media_main_section',
			[ 'label_for' => 'eg_media_chrominance' ]
		);

		add_settings_field(
			'eg_media_interlace',
			'Mode Progressif (Interlace)',
			[ $this, 'render_checkbox_field' ],
			'eg-media-dashboard-config',
			'eg_media_main_section',
			[ 'label_for' => 'eg_media_interlace' ]
		);
	}

	/**
	 * Nettoie la valeur de qualité (entre 1 et 100).
	 *
	 * @param mixed $value Valeur brute.
	 * @return int Valeur nettoyée.
	 */
	public function sanitize_quality( mixed $value ): int {
		$int_val = intval( $value );
		if ( $int_val < 1 ) {
			return 1;
		}
		if ( $int_val > 100 ) {
			return 100;
		}
		return $int_val;
	}

	/**
	 * Nettoie la valeur pour la compression PNG.
	 *
	 * @param mixed $value Valeur brute.
	 * @return string Valeur autorisée.
	 */
	public function sanitize_png_compression( mixed $value ): string {
		$allowed = [ 'faible', 'moyenne', 'forte' ];
		if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
			return $value;
		}
		return 'moyenne';
	}

	/**
	 * Nettoie une valeur booléenne.
	 *
	 * @param mixed $value Valeur brute.
	 * @return bool
	 */
	public function sanitize_boolean( mixed $value ): bool {
		return ! ! $value;
	}

	/**
	 * Rendu du champ Largeur Max.
	 *
	 * @return void
	 */
	public function render_max_width_field(): void {
		$value = (int) get_option( 'eg_media_resize_max_width', \EG_MEDIA\DTO\Image_Settings::DEFAULT_MAX_WIDTH );
		?>
		<select name="eg_media_resize_max_width">
			<option value="0" <?php selected( $value, 0 ); ?>>Sans redimensionnement</option>
			<option value="2560" <?php selected( $value, 2560 ); ?>>2560 px (Grande bannière / Écran Retina)</option>
			<option value="2000" <?php selected( $value, 2000 ); ?>>2000 px (Plein écran standard - Recommandé)</option>
			<option value="1920" <?php selected( $value, 1920 ); ?>>1920 px (Haute définition Web)</option>
			<option value="1200" <?php selected( $value, 1200 ); ?>>1200 px (Image d'illustration / Blog)</option>
			<option value="800" <?php selected( $value, 800 ); ?>>800 px (Taille moyenne / Catalogue)</option>
		</select>
		<p class="description">Largeur maximale autorisée pour le redimensionnement automatique des images.</p>
		<?php
	}

	/**
	 * Rendu du champ Qualité JPEG/WebP.
	 *
	 * @return void
	 */
	public function render_quality_field(): void {
		$value = (int) get_option( 'eg_media_compression_quality', \EG_MEDIA\DTO\Image_Settings::DEFAULT_COMPRESSION_QUALITY );
		?>
		<input type="number" name="eg_media_compression_quality" value="<?php echo esc_attr( (string) $value ); ?>" class="regular-text" min="1" max="100" step="1" />
		<p class="description">Qualité de compression finale, valeur de 1 à 100.</p>
		<?php
	}

	/**
	 * Rendu de la liste déroulante Compression PNG.
	 *
	 * @return void
	 */
	public function render_png_compression_field(): void {
		$value = (string) get_option( 'eg_media_png_compression', \EG_MEDIA\DTO\Image_Settings::DEFAULT_PNG_COMPRESSION );
		?>
		<select name="eg_media_png_compression">
			<option value="faible" <?php selected( $value, 'faible' ); ?>>Faible</option>
			<option value="moyenne" <?php selected( $value, 'moyenne' ); ?>>Moyenne</option>
			<option value="forte" <?php selected( $value, 'forte' ); ?>>Forte</option>
		</select>
		<p class="description">Niveau de compression pour les fichiers PNG.</p>
		<?php
	}

	/**
	 * Rendu générique pour les cases à cocher.
	 *
	 * @param array<string, mixed> $args Arguments contenant l'ID pour l'option.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$id = $args['label_for'] ?? '';
		if ( ! is_string( $id ) || '' === $id ) {
			return;
		}

		$default = match ( $id ) {
			'eg_media_chrominance'  => \EG_MEDIA\DTO\Image_Settings::DEFAULT_CHROMINANCE,
			'eg_media_unsharp_mask' => \EG_MEDIA\DTO\Image_Settings::DEFAULT_UNSHARP_MASK,
			'eg_media_auto_orient'  => \EG_MEDIA\DTO\Image_Settings::DEFAULT_AUTO_ORIENT,
			'eg_media_interlace'    => \EG_MEDIA\DTO\Image_Settings::DEFAULT_INTERLACE,
			default                 => true,
		};

		$value = (bool) get_option( $id, $default );
		?>
		<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="1" <?php checked( $value ); ?> />
		<?php
	}

	/**
	 * Rendu HTML de l'onglet.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<form action="options.php" method="post" style="max-width: 800px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
			<?php
			settings_fields( 'eg_media_settings_group' );
			do_settings_sections( 'eg-media-dashboard-config' );
			submit_button();
			?>
		</form>

		<div class="card" style="max-width: 800px; margin-top: 30px; box-sizing: border-box; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h2 class="title" style="color: #dc3232;">Zone de danger</h2>
			<p>Si vous souhaitez optimiser à nouveau l'ensemble des images de votre bibliothèque de médias (par exemple après avoir modifié la qualité ou les paramètres d'Imagick), vous pouvez réinitialiser le statut d'optimisation.</p>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 15px;">
				<input type="hidden" name="action" value="eg_media_reset_optimization_status" />
				<?php wp_nonce_field( 'eg_media_reset_opt_action', 'eg_media_reset_nonce' ); ?>
				<?php submit_button( 'Réinitialiser le statut d\'optimisation', 'destructive', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
