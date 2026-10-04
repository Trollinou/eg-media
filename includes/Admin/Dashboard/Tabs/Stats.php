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
		<div class="eg-media-dashboard__server-status">
			<?php if ( $is_imagick_active ) : ?>
				<div class="notice notice-success inline">
					<p>
						<span class="dashicons dashicons-yes-alt dashicons--success"></span>
						<strong>Imagick :</strong> Actif sur le serveur.
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-error inline">
					<p>
						<span class="dashicons dashicons-dismiss dashicons--error"></span>
						<strong>Imagick :</strong> Absent ou non activé. Le traitement et l'optimisation des images ne pourront pas fonctionner.
					</p>
				</div>
			<?php endif; ?>
		</div>

		<!-- Zone de Statistiques & Métriques -->
		<h2 class="title">Statistiques de traitement</h2>
		<div class="welcome-panel eg-media-dashboard__panel">
			<div class="welcome-panel-column-container eg-media-dashboard__stats-grid">
				<div class="welcome-panel-column eg-media-dashboard__stat-card">
					<span class="dashicons dashicons-images-alt2"></span>
					<h3>Fichiers optimisés</h3>
					<p class="eg-media-dashboard__stat-card-value">
						<?php echo esc_html( (string) $processed_count ); ?>
					</p>
				</div>
				<div class="welcome-panel-column eg-media-dashboard__stat-card">
					<span class="dashicons dashicons-admin-media"></span>
					<h3>Espace disque économisé</h3>
					<p class="eg-media-dashboard__stat-card-value">
						<?php echo esc_html( $formatted_saved ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Optimisation de l'existant -->
		<h2 class="title">Optimisation de l'existant</h2>
		<div class="welcome-panel eg-media-dashboard__panel">
			<p>Vous pouvez optimiser en masse toutes les images JPEG, PNG et WebP existantes de la bibliothèque de médias.</p>
			<div class="eg-media-dashboard__bulk-progress-wrap">
				<p>
					<strong>Images non optimisées restantes (JPEG, PNG, WebP) :</strong>
					<span id="eg-media-bulk-count" class="eg-media-dashboard__bulk-count"><?php echo esc_html( (string) $unoptimized_count ); ?></span>
				</p>
				<progress id="eg-media-bulk-progress" value="0" max="<?php echo esc_html( (string) $unoptimized_count ); ?>" class="eg-media-dashboard__progress-bar"></progress>
				<div id="eg-media-bulk-status" class="eg-media-dashboard__bulk-status"></div>
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

		$units = array( 'Octets', 'Ko', 'Mo', 'Go', 'To' );
		$power = floor( log( $bytes, 1024 ) );
		$power = min( $power, count( $units ) - 1 );

		$value = $bytes / pow( 1024, $power );

		if ( 0.0 === $power ) {
			return sprintf( '%d %s', $value, $units[ (int) $power ] );
		}

		return sprintf( '%.2f %s', $value, $units[ (int) $power ] );
	}
}
