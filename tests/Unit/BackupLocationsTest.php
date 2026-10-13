<?php
/**
 * Tests unitaires pour le service d'emplacements de sauvegarde (Locations).
 *
 * @package EG_MEDIA\Tests\Unit
 */

declare(strict_types=1);

namespace EG_MEDIA\Tests\Unit;

use EG_MEDIA\Services\Backup\Locations;
use PHPUnit\Framework\TestCase;

/**
 * Class BackupLocationsTest
 */
final class BackupLocationsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wp_test_options;
		$wp_test_options = array();
	}

	public function test_get_originals_dir(): void {
		$originals_dir = Locations::get_originals_dir();

		$this->assertStringContainsString( 'eg-media/originals', $originals_dir );
	}

	public function test_ensure_directory_exists(): void {
		$created = Locations::ensure_directory_exists();

		$this->assertTrue( $created );
		$this->assertDirectoryExists( Locations::get_originals_dir() );
		$this->assertFileExists( Locations::get_originals_dir() . '/index.php' );
	}

	public function test_register_backup_locations_when_disabled(): void {
		update_option( 'eg_media_backup_originals', false );

		$locations_service = new Locations();
		$initial_locations = array(
			'other_plugin' => array(
				'id' => 'other_plugin',
			),
		);

		$result = $locations_service->register_backup_locations( $initial_locations );

		// Ne doit rien ajouter si la sauvegarde des originaux est désactivée.
		$this->assertArrayNotHasKey( 'eg_media_originals', $result );
		$this->assertArrayNotHasKey( 'eg_media_data', $result );
		$this->assertSame( $initial_locations, $result );
	}

	public function test_register_backup_locations_when_enabled(): void {
		update_option( 'eg_media_backup_originals', true );
		Locations::ensure_directory_exists();

		$locations_service = new Locations();
		$initial_locations = array(
			'other_plugin' => array(
				'id'          => 'other_plugin',
				'label'       => 'Other Plugin',
				'plugin_name' => 'Other',
				'path'        => '/tmp/other',
			),
		);

		$result = $locations_service->register_backup_locations( $initial_locations );

		// Vérifier que les emplacements tiers sont préservés.
		$this->assertArrayHasKey( 'other_plugin', $result );

		// Vérifier l'enregistrement unique de eg_media_originals.
		$this->assertArrayHasKey( 'eg_media_originals', $result );
		$originals = $result['eg_media_originals'];
		$this->assertSame( 'eg_media_originals', $originals['id'] );
		$this->assertSame( 'EG Media Manager', $originals['plugin_name'] );
		$this->assertSame( 'media', $originals['category'] );
		$this->assertTrue( $originals['recommended'] );
		$this->assertStringContainsString( 'originals', $originals['path'] );
		$this->assertIsArray( $originals['exclude'] );
		$this->assertContains( '*.tmp', $originals['exclude'] );

		// Aucun doublon eg_media_data ne doit exister.
		$this->assertArrayNotHasKey( 'eg_media_data', $result );
	}
}
