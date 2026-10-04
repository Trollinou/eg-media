<?php
/**
 * Tests unitaires pour les Enums de EG Media Manager.
 *
 * @package EG_MEDIA\Tests\Unit
 */

declare(strict_types=1);

namespace EG_MEDIA\Tests\Unit;

use EG_MEDIA\Enums\Media_Status;
use EG_MEDIA\Enums\Png_Compression;
use EG_MEDIA\Enums\Viewer_Layout;
use PHPUnit\Framework\TestCase;

/**
 * Class EnumsTest
 */
final class EnumsTest extends TestCase {

	public function test_media_status_values(): void {
		$this->assertSame( 'pending', Media_Status::PENDING->value );
		$this->assertSame( 'approved', Media_Status::APPROVED->value );
		$this->assertSame( 'rejected', Media_Status::REJECTED->value );
	}

	public function test_png_compression_levels(): void {
		$this->assertSame( 3, Png_Compression::LOW->to_imagick_level() );
		$this->assertSame( 6, Png_Compression::MEDIUM->to_imagick_level() );
		$this->assertSame( 9, Png_Compression::HIGH->to_imagick_level() );
	}

	public function test_viewer_layouts(): void {
		$this->assertSame( 'viewer', Viewer_Layout::VIEWER->value );
		$this->assertSame( 'justified', Viewer_Layout::JUSTIFIED->value );
		$this->assertNotEmpty( Viewer_Layout::VIEWER->label() );
		$this->assertNotEmpty( Viewer_Layout::JUSTIFIED->label() );
	}
}
