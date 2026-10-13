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

	public function test_get_base_dir_and_originals_dir(): void {
		$base_dir      = Locations::get_base_dir();
		$originals_dir = Locations::get_originals_dir();

		$this->assertStringContainsString( 'eg-media', $base_dir );
		$this->assertStringContainsString( 'eg-media/originals', $originals_dir );
	}

	public function test_register_backup_locations(): void {
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

		// Vérifier que les emplacements existants sont conservés
		$this->assertArrayHasKey( 'other_plugin', $result );

		// Vérifier l'enregistrement de eg_media_originals
		$this->assertArrayHasKey( 'eg_media_originals', $result );
		$originals = $result['eg_media_originals'];
		$this->assertSame( 'eg_media_originals', $originals['id'] );
		$this->assertSame( 'EG Media Manager', $originals['plugin_name'] );
		$this->assertSame( 'media', $originals['category'] );
		$this->assertTrue( $originals['recommended'] );
		$this->assertStringContainsString( 'originals', $originals['path'] );
		$this->assertIsArray( $originals['exclude'] );
		$this->assertContains( '*.tmp', $originals['exclude'] );

		// Vérifier l'enregistrement de eg_media_data
		$this->assertArrayHasKey( 'eg_media_data', $result );
		$data = $result['eg_media_data'];
		$this->assertSame( 'eg_media_data', $data['id'] );
		$this->assertSame( 'EG Media Manager', $data['plugin_name'] );
		$this->assertSame( 'documents', $data['category'] );
		$this->assertTrue( $data['recommended'] );
		$this->assertStringContainsString( 'eg-media', $data['path'] );
		$this->assertIsArray( $data['exclude'] );
		$this->assertContains( '*.tmp', $data['exclude'] );
		$this->assertContains( 'transients', $data['exclude'] );
	}
}
