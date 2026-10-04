<?php
/**
 * Orchestrateur principal et cycle de vie du plugin EG Media Manager.
 *
 * @package EG_MEDIA\Core
 */

declare(strict_types=1);

namespace EG_MEDIA\Core;

use EG_MEDIA\Admin\AlbumMetabox;
use EG_MEDIA\Admin\Dashboard\Main as DashboardMain;
use EG_MEDIA\Admin\MediaFields;
use EG_MEDIA\Admin\MediaFilter;
use EG_MEDIA\Admin\MediaUpload;
use EG_MEDIA\API\Piwigo as PiwigoAPI;
use EG_MEDIA\Blocks\Viewer as ViewerBlock;
use EG_MEDIA\CPT\Albums;
use EG_MEDIA\CPT\Galleries;
use EG_MEDIA\Services\Image\BulkProcessor;
use EG_MEDIA\Services\Image\Processor as ImageProcessor;
use EG_MEDIA\Shortcodes\Album as AlbumShortcode;

/**
 * Class Plugin
 *
 * Gère l'initialisation modulaire et le chargement contextuel (lazy-loading) des composants.
 */
final class Plugin {

	/**
	 * Instance unique (Singleton).
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Version du plugin.
	 */
	public const string VERSION = '1.1.5';

	/**
	 * Constructeur privé pour le Singleton.
	 */
	private function __construct() {}

	/**
	 * Point d'entrée principal pour démarrer le plugin.
	 *
	 * @return self
	 */
	public static function boot(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Initialise les hooks WordPress et orchestre les différents contextes.
	 *
	 * @return void
	 */
	public function init(): void {
		// 1. Augmenter la limite de mémoire pour le traitement d'images
		add_filter( 'image_memory_limit', static fn(): string => '512M' );

		// 2. Services globaux de traitement média (nécessaires lors de tout upload)
		$this->register_media_services();

		// 3. Initialisation principale au déclenchement de plugins_loaded
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );

		// 4. Enregistrement des assets publics
		add_action( 'wp_enqueue_scripts', [ $this, 'register_public_assets' ] );
	}

	/**
	 * Enregistre les services de traitement d'images.
	 *
	 * @return void
	 */
	private function register_media_services(): void {
		$image_processor = new ImageProcessor();
		$image_processor->register();

		$bulk_processor = new BulkProcessor();
		$bulk_processor->register();
	}

	/**
	 * Déclenché sur le hook 'plugins_loaded' pour initialiser les modules selon le contexte.
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		// CPT et Taxonomies (toujours nécessaires pour les requêtes WP)
		( new Galleries() )->register();
		( new Albums() )->register();

		// Blocs Gutenberg & Block Bindings
		( new ViewerBlock() )->register();
		( new BlockBindings() )->register();

		// Shortcodes
		( new AlbumShortcode() )->register();

		// Context: Administration WordPress uniquement
		if ( is_admin() ) {
			$this->init_admin_modules();
		}

		// Context: REST API
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
	}

	/**
	 * Initialise les modules d'administration.
	 *
	 * @return void
	 */
	private function init_admin_modules(): void {
		( new DashboardMain() )->init();
		( new AlbumMetabox() )->register();
		( new MediaFields() )->register();
		( new MediaFilter() )->register();
		( new MediaUpload() )->register();
	}

	/**
	 * Initialise les routes de l'API REST.
	 *
	 * @return void
	 */
	public function init_rest_api(): void {
		( new PiwigoAPI() )->register_routes();
	}

	/**
	 * Enregistre les scripts et styles publics.
	 *
	 * @return void
	 */
	public function register_public_assets(): void {
		$plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/eg-media.php' );

		wp_register_style(
			'eg-media-public-album',
			$plugin_url . 'assets/css/public-album.css',
			[],
			self::VERSION
		);

		wp_register_script(
			'eg-media-public-album',
			$plugin_url . 'assets/js/public-album.js',
			[],
			self::VERSION,
			true
		);
	}
}
