<?php
/**
 * Classe principale pour la gestion du tableau de bord d'administration.
 *
 * @package    EG_MEDIA
 * @subpackage Admin/Dashboard
 * @author     EG
 */

declare(strict_types=1);

namespace EG_MEDIA\Admin\Dashboard;

use EG_MEDIA\Admin\Dashboard\Tabs\Stats;
use EG_MEDIA\Admin\Dashboard\Tabs\Config;
use EG_MEDIA\Repositories\MediaRepository;

/**
 * Classe Main du tableau de bord.
 */
class Main {

	/**
	 * Instance de la classe Stats.
	 *
	 * @var Stats
	 */
	private Stats $stats_tab;

	/**
	 * Instance de la classe Config.
	 *
	 * @var Config
	 */
	private Config $config_tab;

	/**
	 * Constructeur de la classe Main.
	 */
	public function __construct() {
		$this->stats_tab  = new Stats();
		$this->config_tab = new Config();
	}

	/**
	 * Initialise les hooks WordPress pour le tableau de bord.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_dashboard_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this->config_tab, 'init_settings' ] );
		add_action( 'admin_post_eg_media_reset_optimization_status', [ $this, 'handle_reset_optimization_status' ] );
		add_action( 'admin_post_eg_media_clear_piwigo_cache', [ $this, 'handle_clear_piwigo_cache' ] );
	}

	/**
	 * Traite la réinitialisation globale du statut d'optimisation.
	 *
	 * @return void
	 */
	public function handle_reset_optimization_status(): void {
		// Vérification de sécurité des capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( "Vous n'avez pas les permissions nécessaires pour effectuer cette action." ) );
		}

		// Vérification du nonce.
		check_admin_referer( 'eg_media_reset_opt_action', 'eg_media_reset_nonce' );

		// Suppression de la clé _eg_media_optimized pour tous les médias via le repository.
		$repository = new MediaRepository();
		$repository->reset_all_optimization_meta();

		// Invalider le cache temporaire (transient) du nombre de médias restants à optimiser.
		delete_transient( 'eg_media_unoptimized_count' );

		// Réinitialiser les statistiques globales de traitement.
		delete_option( 'eg_media_processed_count' );
		delete_option( 'eg_media_bytes_saved' );

		// Ajouter un message de notification à afficher lors de la redirection.
		add_settings_error(
			'eg_media_messages',
			'eg_media_reset_success',
			'Le statut d\'optimisation a été réinitialisé. Vous pouvez à nouveau optimiser l\'ensemble de vos médias existants.',
			'success'
		);

		// Sauvegarde des erreurs de réglages de façon temporaire (transient) pour persistance lors du redirect.
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		// Redirection vers la page de réglages.
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'eg-media-settings',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Traite le nettoyage manuel du cache Piwigo.
	 *
	 * @return void
	 */
	public function handle_clear_piwigo_cache(): void {
		// Vérification de sécurité des capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( "Vous n'avez pas les permissions nécessaires pour effectuer cette action." ) );
		}

		// Vérification du nonce.
		check_admin_referer( 'eg_media_clear_piwigo_cache_action', 'eg_media_clear_piwigo_cache_nonce' );

		$piwigo_service = new \EG_MEDIA\Services\Piwigo();
		$piwigo_service->clear_cache();

		// Ajouter un message de notification à afficher lors de la redirection.
		add_settings_error(
			'eg_media_messages',
			'eg_media_clear_piwigo_cache_success',
			'Le cache de Piwigo (albums et images) a été vidé avec succès.',
			'success'
		);

		// Sauvegarde des erreurs de réglages de façon temporaire (transient) pour persistance lors du redirect.
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		// Redirection vers la page de réglages.
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'eg-media-settings',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Charge les assets CSS et JS pour les pages d'administration EG Media.
	 *
	 * @param string $hook Le nom de la page courante dans le back-office.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_eg-media-dashboard' !== $hook && 'eg-media_page_eg-media-settings' !== $hook ) {
			return;
		}

		wp_register_style(
			'eg-media-admin-dashboard',
			EG_MEDIA_URL . 'assets/css/admin-dashboard.css',
			[],
			EG_MEDIA_VERSION
		);

		wp_enqueue_style( 'eg-media-admin-dashboard' );

		// Enqueue des scripts JS et localisation uniquement sur le tableau de bord.
		if ( 'toplevel_page_eg-media-dashboard' !== $hook ) {
			return;
		}

		wp_register_script(
			'eg-media-admin-dashboard',
			EG_MEDIA_URL . 'assets/js/admin-dashboard.js',
			[],
			EG_MEDIA_VERSION,
			[
				'strategy'  => 'defer',
				'in_footer' => true,
			]
		);

		wp_enqueue_script( 'eg-media-admin-dashboard' );

		$bulk_processor    = new \EG_MEDIA\Services\Image\BulkProcessor();
		$unoptimized_count = $bulk_processor->get_unoptimized_count();

		wp_localize_script(
			'eg-media-admin-dashboard',
			'eg_media_bulk_data',
			[
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'eg-media-bulk-nonce' ),
				'unoptimizedCount' => $unoptimized_count,
				'action'           => 'eg_media_process_bulk_batch',
			]
		);
	}

	/**
	 * Enregistre le menu principal et les sous-pages d'administration.
	 *
	 * @return void
	 */
	public function add_dashboard_page(): void {
		add_menu_page(
			'Tableau de bord ‹ EG Media',
			'EG Media',
			'manage_options',
			'eg-media-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-format-gallery',
			30.6
		);

		add_submenu_page(
			'eg-media-dashboard',
			'Tableau de bord ‹ EG Media',
			'Tableau de bord',
			'manage_options',
			'eg-media-dashboard',
			[ $this, 'render_dashboard' ],
			0
		);

		add_submenu_page(
			'eg-media-dashboard',
			'Galeries ‹ EG Media',
			'Galeries',
			'manage_categories',
			'edit-tags.php?taxonomy=eg_media_gallery&post_type=attachment',
			'',
			50
		);

		add_submenu_page(
			'eg-media-dashboard',
			'Réglages ‹ EG Media',
			'Réglages',
			'manage_options',
			'eg-media-settings',
			[ $this, 'render_settings' ],
			100
		);
	}

	/**
	 * Rendu HTML de la page de tableau de bord.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		// Vérification de sécurité des capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( "Vous n'avez pas les permissions nécessaires pour accéder à cette page." ) );
		}

		$this->display_persisted_errors();
		?>
		<div class="wrap eg-media-dashboard">
			<h1><?php esc_html_e( 'Tableau de bord', 'eg-media' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Statistiques et optimisation des médias.', 'eg-media' ); ?></p>

			<hr class="wp-header-end">

			<?php settings_errors( 'eg_media_messages' ); ?>

			<div class="eg-media-dashboard__tab-content">
				<?php $this->stats_tab->render(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Rendu HTML de la page de réglages.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		// Vérification de sécurité des capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( "Vous n'avez pas les permissions nécessaires pour accéder à cette page." ) );
		}

		$this->display_persisted_errors();
		?>
		<div class="wrap eg-media-dashboard">
			<h1><?php esc_html_e( 'Réglages EG Media', 'eg-media' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Configuration des formats d\'image et des intégrations.', 'eg-media' ); ?></p>

			<hr class="wp-header-end">

			<?php settings_errors( 'eg_media_messages' ); ?>

			<div class="eg-media-dashboard__tab-content">
				<?php $this->config_tab->render(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Récupère et réinjecte les erreurs de réglages persistées dans un transient.
	 *
	 * @return void
	 */
	private function display_persisted_errors(): void {
		$errors = get_transient( 'settings_errors' );
		if ( is_array( $errors ) ) {
			// Ré-injection des erreurs dans le flux courant.
			foreach ( $errors as $error ) {
				if ( isset( $error['setting'], $error['code'], $error['message'], $error['type'] ) ) {
					add_settings_error( $error['setting'], $error['code'], $error['message'], $error['type'] );
				}
			}
			delete_transient( 'settings_errors' );
		}
	}
}
