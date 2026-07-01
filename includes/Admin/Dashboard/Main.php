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

		global $wpdb;

		// Suppression de la clé _eg_media_optimized pour tous les médias.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_eg_media_optimized'
			)
		);

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

		// Redirection vers l'onglet de configuration.
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'eg-media-dashboard',
					'tab'  => 'config',
				],
				admin_url( 'upload.php' )
			)
		);
		exit;
	}

	/**
	 * Charge les assets CSS et JS pour la page du tableau de bord.
	 *
	 * @param string $hook Le nom de la page courante dans le back-office.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ) : void {
		if ( 'media_page_eg-media-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'eg-media-admin-dashboard',
			plugins_url( 'assets/js/admin-dashboard.js', dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/eg-media.php' ),
			[],
			EG_MEDIA_VERSION,
			true
		);

		$bulk_processor = new \EG_MEDIA\Services\Image\BulkProcessor();
		$unoptimized_count = $bulk_processor->get_unoptimized_count();

		wp_localize_script(
			'eg-media-admin-dashboard',
			'egMediaBulk',
			[
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'eg-media-bulk-nonce' ),
				'unoptimizedCount' => $unoptimized_count,
				'action'           => 'eg_media_process_bulk_batch',
			]
		);
	}

	/**
	 * Enregistre la sous-page de tableau de bord sous le menu "Médias".
	 *
	 * @return void
	 */
	public function add_dashboard_page(): void {
		add_submenu_page(
			'upload.php',
			'EG Media Manager',
			'EG Media Manager',
			'manage_options',
			'eg-media-dashboard',
			[ $this, 'render_dashboard' ]
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

		// Récupérer l'onglet actif.
		$active_tab = isset( $_GET['tab'] ) && 'config' === $_GET['tab'] ? 'config' : 'stats';

		// Récupérer et afficher les notifications persistées.
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

		?>
		<div class="wrap">
			<h1>EG Media Manager</h1>
			<p class="description">Gestionnaire de Média et optimisation des images.</p>

			<hr class="wp-header-end">

			<?php settings_errors( 'eg_media_messages' ); ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'upload.php?page=eg-media-dashboard&tab=stats' ) ); ?>" class="nav-tab <?php echo 'stats' === $active_tab ? 'nav-tab-active' : ''; ?>">Statistiques</a>
				<a href="<?php echo esc_url( admin_url( 'upload.php?page=eg-media-dashboard&tab=config' ) ); ?>" class="nav-tab <?php echo 'config' === $active_tab ? 'nav-tab-active' : ''; ?>">Configuration</a>
			</h2>

			<div class="tab-content" style="margin-top: 20px;">
				<?php
				if ( 'config' === $active_tab ) {
					$this->config_tab->render();
				} else {
					$this->stats_tab->render();
				}
				?>
			</div>
		</div>
		<?php
	}
}
