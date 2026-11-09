<?php
/**
 * Srcset sizing path, driven through Srcset::filter() with in-memory Image Facts.
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests;

use Brain\Monkey\Filters;
use Soderlind\Plugin\TachyonEdgeImages\Srcset;
use Soderlind\Plugin\TachyonEdgeImages\Tests\Support\SizingTestCase;

final class SrcsetTest extends SizingTestCase {

	private const META = [ 'file' => '2024/01/photo.jpg', 'width' => 2000, 'height' => 1000 ];

	private static function source( string $url, int $value ): array {
		return [ 'url' => $url, 'descriptor' => 'w', 'value' => $value ];
	}

	public function test_intermediate_with_matching_width_resizes_from_original(): void {
		$sources = [ 300 => self::source( self::UPLOADS . '/2024/01/photo-300x150.jpg', 300 ) ];

		$out = Srcset::filter( $sources, [ 300, 150 ], self::UPLOADS . '/2024/01/photo-300x150.jpg', self::META, 7 );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=300%2C150', $out[300]['url'] );
	}

	public function test_width_descriptor_without_dimensions_uses_w(): void {
		$sources = [ 2000 => self::source( self::UPLOADS . '/2024/01/photo.jpg', 2000 ) ];

		$out = Srcset::filter( $sources, [ 2000, 1000 ], self::UPLOADS . '/2024/01/photo.jpg', self::META, 7 );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?w=2000', $out[2000]['url'] );
	}

	public function test_original_comes_from_upload_base_url_fact(): void {
		$this->facts->upload_base_url = 'https://cdn.example.com/media';
		$sources                      = [ 768 => self::source( self::UPLOADS . '/2024/01/photo-768x384.jpg', 768 ) ];

		$out = Srcset::filter( $sources, [ 768, 384 ], '', self::META, 7 );

		$this->assertSame( 'https://cdn.example.com/media/2024/01/photo.jpg', $out[768]['url'] );
	}

	public function test_without_metadata_file_strips_dimensions(): void {
		$sources = [ 640 => self::source( self::UPLOADS . '/2024/01/photo-300x150.jpg', 640 ) ];

		$out = Srcset::filter( $sources, [ 640, 320 ], '', [], 7 );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?w=640', $out[640]['url'] );
	}

	public function test_external_source_is_left_alone(): void {
		$sources = [ 300 => self::source( 'https://elsewhere.test/photo-300x150.jpg', 300 ) ];

		$this->assertSame( $sources, Srcset::filter( $sources, [ 300, 150 ], '', self::META, 7 ) );
	}

	public function test_gravity_is_copied_from_a_tachyon_image_src(): void {
		$sources = [ 300 => self::source( self::UPLOADS . '/2024/01/photo-300x150.jpg', 300 ) ];

		$out = Srcset::filter( $sources, [ 300, 150 ], TACHYON_URL . '/2024/01/photo.jpg?resize=300%2C150&gravity=north&quality=80', self::META, 7 );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=300%2C150&gravity=north', $out[300]['url'] );
	}

	public function test_args_filter_receives_details(): void {
		$source  = self::source( self::UPLOADS . '/2024/01/photo-300x150.jpg', 300 );
		$details = [ 'source' => $source, 'image_meta' => self::META, 'width' => 300, 'height' => 150, 'attachment_id' => 7 ];

		Filters\expectApplied( 'tachyon_srcset_image_args' )
			->once()
			->with( [ 'resize' => '300,150' ], $details )
			->andReturn( [ 'w' => 42 ] );

		$out = Srcset::filter( [ 300 => $source ], [ 300, 150 ], '', self::META, 7 );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?w=42', $out[300]['url'] );
	}
}
