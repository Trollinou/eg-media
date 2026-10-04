<?php
/**
 * Tests unitaires pour Image_Settings DTO.
 *
 * @package EG_MEDIA\Tests\Unit
 */

declare(strict_types=1);

namespace EG_MEDIA\Tests\Unit;

use EG_MEDIA\DTO\Image_Settings;
use EG_MEDIA\Enums\Png_Compression;
use PHPUnit\Framework\TestCase;

/**
 * Class ImageSettingsTest
 */
final class ImageSettingsTest extends TestCase {

	public function test_image_settings_instantiation(): void {
		$settings = new Image_Settings(
			max_width: 1920,
			compression_quality: 85,
			png_compression: 'forte',
			use_unsharp_mask: true,
			use_auto_orient: true,
			use_chrominance: false,
			use_interlace: true
		);

		$this->assertSame( 1920, $settings->max_width );
		$this->assertSame( 85, $settings->compression_quality );
		$this->assertSame( 'forte', $settings->png_compression );
		$this->assertSame( Png_Compression::HIGH, $settings->get_png_compression_enum() );
		$this->assertTrue( $settings->use_unsharp_mask );
		$this->assertTrue( $settings->use_auto_orient );
		$this->assertFalse( $settings->use_chrominance );
		$this->assertTrue( $settings->use_interlace );
	}

	public function test_default_constants(): void {
		$this->assertSame( 2000, Image_Settings::DEFAULT_MAX_WIDTH );
		$this->assertSame( 80, Image_Settings::DEFAULT_COMPRESSION_QUALITY );
		$this->assertSame( 'moyenne', Image_Settings::DEFAULT_PNG_COMPRESSION );
		$this->assertTrue( Image_Settings::DEFAULT_UNSHARP_MASK );
		$this->assertTrue( Image_Settings::DEFAULT_AUTO_ORIENT );
		$this->assertFalse( Image_Settings::DEFAULT_CHROMINANCE );
		$this->assertTrue( Image_Settings::DEFAULT_INTERLACE );
	}
}
