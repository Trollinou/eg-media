<?php
/**
 * Onglet Statistiques du tableau de bord.
 *
 * @package    EG_MEDIA
 * @subpackage Admin/Dashboard/Tabs
 * @author     EG
 */

declare(strict_types=1);

namespace EG_MEDIA\Admin\Dashboard\Tabs;

/**
 * Classe Stats.
 */
class Stats {

	/**
	 * Rendu HTML de l'onglet.
	 *
	 * @return void
	 */
	public function render(): void {
		$is_imagick_active = class_exists( '\Imagick' );

		$processed_count = (int) get_option( 'eg_media_processed_count', 0 );
		$bytes_saved     = (int) get_option( 'eg_media_bytes_saved', 0 );
		$formatted_saved = $this->format_bytes( $bytes_saved );

		$bulk_processor    = new \EG_MEDIA\Services\Image\BulkProcessor();
		$unoptimized_count = $bulk_processor->get_unoptimized_count();
		?>
		<!-- Statut du Serveur -->
		<h2 class="title">État du Serveur</h2>
		<?php if ( $is_imagick_active ) : ?>
			<div class="notice notice-success inline">
				<p>
					<span class="dashicons dashicons-yes-alt" style="color: #46b450; vertical-align: middle; margin-right: 5px;"></span>
					<strong>Imagick :</strong> Actif sur le serveur.
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-error inline">
				<p>
					<span class="dashicons dashicons-dismiss" style="color: #dc3232; vertical-align: middle; margin-right: 5px;"></span>
					<strong>Imagick :</strong> Absent ou non activé. Le traitement et l'optimisation des images ne pourront pas fonctionner.
				</p>
			</div>
		<?php endif; ?>

		<!-- Zone de Statistiques & Métriques -->
		<h2 class="title" style="margin-top: 30px;">Statistiques de traitement</h2>
		<div class="welcome-panel" style="padding: 20px; margin-top: 10px; max-width: 800px;">
			<div class="welcome-panel-column-container" style="display: flex; gap: 20px; flex-wrap: wrap;">
				<div class="welcome-panel-column" style="flex: 1; min-width: 250px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<span class="dashicons dashicons-images-alt2" style="font-size: 32px; width: 32px; height: 32px; color: #135e96; margin-bottom: 10px;"></span>
					<h3>Fichiers optimisés</h3>
					<p style="font-size: 24px; font-weight: 600; margin: 5px 0 0; color: #1d2327;">
						<?php echo esc_html( (string) $processed_count ); ?>
					</p>
				</div>
				<div class="welcome-panel-column" style="flex: 1; min-width: 250px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<span class="dashicons dashicons-admin-media" style="font-size: 32px; width: 32px; height: 32px; color: #135e96; margin-bottom: 10px;"></span>
					<h3>Espace disque économisé</h3>
					<p style="font-size: 24px; font-weight: 600; margin: 5px 0 0; color: #1d2327;">
						<?php echo esc_html( $formatted_saved ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Optimisation de l'existant -->
		<h2 class="title" style="margin-top: 30px;">Optimisation de l'existant</h2>
		<div class="welcome-panel" style="padding: 20px; margin-top: 10px; max-width: 800px;">
			<p>Vous pouvez optimiser en masse toutes les images JPEG, PNG et WebP existantes de la bibliothèque de médias.</p>
			<div style="margin: 20px 0;">
				<p>
					<strong>Images non optimisées restantes (JPEG, PNG, WebP) :</strong>
					<span id="eg-media-bulk-count" style="font-weight: 600; font-size: 16px;"><?php echo esc_html( (string) $unoptimized_count ); ?></span>
				</p>
				<progress id="eg-media-bulk-progress" value="0" max="<?php echo esc_html( (string) $unoptimized_count ); ?>" style="width: 100%; height: 24px; display: none; margin-top: 10px;"></progress>
				<div id="eg-media-bulk-status" style="margin-top: 10px; font-weight: 600; color: #135e96;"></div>
			</div>
			<?php if ( $is_imagick_active ) : ?>
				<button id="eg-media-bulk-start" class="button button-primary" <?php echo 0 === $unoptimized_count ? 'disabled' : ''; ?>>
					Lancer l'optimisation
				</button>
			<?php else : ?>
				<button class="button" disabled>Lancer l'optimisation (Imagick requis)</button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Formate un nombre d'octets de façon lisible avec unité (Ko, Mo, Go).
	 *
	 * @param int $bytes Nombre d'octets.
	 * @return string Version formatée.
	 */
	private function format_bytes( int $bytes ): string {
		if ( $bytes <= 0 ) {
			return '0 Ko';
		}

		$units = [ 'Octets', 'Ko', 'Mo', 'Go', 'To' ];
		$power = floor( log( $bytes, 1024 ) );
		$power = min( $power, count( $units ) - 1 );

		$value = $bytes / pow( 1024, $power );

		if ( 0.0 === $power ) {
			return sprintf( '%d %s', $value, $units[(int) $power] );
		}

		return sprintf( '%.2f %s', $value, $units[(int) $power] );
	}
}
