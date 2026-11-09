<?php
/**
 * Downsize sizing path, driven through Downsize::filter() with in-memory Image Facts.
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Soderlind\Plugin\TachyonEdgeImages\Downsize;
use Soderlind\Plugin\TachyonEdgeImages\Tests\Support\SizingTestCase;

final class DownsizeTest extends SizingTestCase {

	private const ID = 7;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'is_admin' )->justReturn( false );

		$this->facts->urls[ self::ID ]     = self::UPLOADS . '/2024/01/photo.jpg';
		$this->facts->metadata[ self::ID ] = [ 'file' => '2024/01/photo.jpg', 'width' => 2000, 'height' => 1000 ];
	}

	private function tachyon( string $query = '' ): string {
		return self::TACHYON . '/2024/01/photo.jpg' . ( '' === $query ? '' : '?' . $query );
	}

	public function test_cropped_named_size_resizes(): void {
		$this->facts->intermediates[ self::ID . ':thumb' ] = [ 'width' => 150, 'height' => 150 ];

		$this->assertSame( [ $this->tachyon( 'resize=150%2C150' ), 150, 150, true ], Downsize::filter( false, self::ID, 'thumb' ) );
	}

	public function test_uncropped_named_size_fits_to_the_registered_box(): void {
		$this->facts->intermediates[ self::ID . ':medium' ] = [ 'width' => 300, 'height' => 150 ];

		$this->assertSame( [ $this->tachyon( 'fit=300%2C300' ), 300, 150, true ], Downsize::filter( false, self::ID, 'medium' ) );
	}

	public function test_missing_intermediate_is_sized_by_resize_dimensions(): void {
		// No intermediate file, e.g. a size registered after upload.
		$this->assertSame( [ $this->tachyon( 'fit=1024%2C1000' ), 1024, 512, true ], Downsize::filter( false, self::ID, 'large' ) );
	}

	public function test_missing_intermediate_is_constrained_from_the_source_crop_size(): void {
		// A Plugin Quirk: Downsize reads offsets 6 and 7 (the source crop size), not 4 and 5.
		$seen                   = [];
		$this->facts->constrain = static function ( $w, $h ) use ( &$seen ) {
			$seen = [ $w, $h ];
			return [ $w, $h ];
		};

		Downsize::filter( false, self::ID, 'large' );

		$this->assertSame( [ 2000, 1000 ], $seen );
	}

	public function test_does_not_upscale(): void {
		$this->facts->metadata[ self::ID ] = [ 'file' => '2024/01/photo.jpg', 'width' => 800, 'height' => 400 ];

		$this->assertSame( [ $this->tachyon(), 800, 400, false ], Downsize::filter( false, self::ID, 'large' ) );
	}

	public function test_full_uses_full_metadata(): void {
		$this->assertSame( [ $this->tachyon(), 2000, 1000, false ], Downsize::filter( false, self::ID, 'full' ) );
	}

	public function test_crop_position_becomes_gravity(): void {
		$this->facts->sizes['hero'] = [ 'width' => 600, 'height' => 300, 'crop' => [ 'left', 'top' ] ];

		$this->assertSame(
			[ $this->tachyon( 'resize=600%2C300&gravity=northwest' ), 600, 300, true ],
			Downsize::filter( false, self::ID, 'hero' )
		);
	}

	public function test_cropped_size_with_zero_height_uses_width_only(): void {
		$this->facts->sizes['wide'] = [ 'width' => 500, 'height' => 0, 'crop' => true ];

		$this->assertSame( [ $this->tachyon( 'w=500' ), 500, 250, true ], Downsize::filter( false, self::ID, 'wide' ) );
	}

	public function test_string_path_constrains_for_display(): void {
		$this->facts->intermediates[ self::ID . ':medium' ] = [ 'width' => 300, 'height' => 150 ];
		$this->facts->constrain = static fn ( $w, $h, $size, $context ) => 'display' === $context ? [ 200, 100 ] : [ $w, $h ];

		$this->assertSame( [ $this->tachyon( 'fit=300%2C300' ), 200, 100, true ], Downsize::filter( false, self::ID, 'medium' ) );
	}

	public function test_string_filter_receives_image_details(): void {
		$this->facts->intermediates[ self::ID . ':thumb' ] = [ 'width' => 150, 'height' => 150 ];

		Filters\expectApplied( 'tachyon_image_downsize_string' )
			->once()
			->with(
				[ 'resize' => '150,150' ],
				[
					'image_args'    => [ 'width' => 150, 'height' => 150, 'crop' => true ],
					'image_url'     => self::UPLOADS . '/2024/01/photo.jpg',
					'attachment_id' => self::ID,
					'size'          => 'thumb',
					'transform'     => 'resize',
				]
			)
			->andReturn( [ 'w' => 42 ] );

		$this->assertSame( $this->tachyon( 'w=42' ), Downsize::filter( false, self::ID, 'thumb' )[0] );
	}

	public function test_array_size_fits(): void {
		$this->assertSame( [ $this->tachyon( 'fit=400%2C200' ), 400, 200, true ], Downsize::filter( false, self::ID, [ 400, 400 ] ) );
	}

	public function test_array_size_larger_than_original_is_not_resized(): void {
		$this->assertSame( [ $this->tachyon(), 2000, 1000, false ], Downsize::filter( false, self::ID, [ 4000, 4000 ] ) );
	}

	public function test_array_size_needs_both_dimensions(): void {
		$this->assertFalse( Downsize::filter( false, self::ID, [ 400 ] ) );
	}

	public function test_array_path_constrains_without_context(): void {
		$contexts                = [];
		$this->facts->constrain = static function ( $w, $h, $size, $context ) use ( &$contexts ) {
			$contexts[] = $context;
			return [ $w, $h ];
		};

		Downsize::filter( false, self::ID, [ 400, 400 ] );

		$this->assertSame( [ null ], $contexts );
	}

	public function test_array_filter_receives_image_details(): void {
		Filters\expectApplied( 'tachyon_image_downsize_array' )
			->once()
			->with(
				[ 'fit' => '400,200' ],
				[
					'width'         => 400,
					'height'        => 200,
					'image_url'     => self::UPLOADS . '/2024/01/photo.jpg',
					'attachment_id' => self::ID,
				]
			)
			->andReturn( [] );

		$this->assertSame( $this->tachyon(), Downsize::filter( false, self::ID, [ 400, 400 ] )[0] );
	}

	public function test_unknown_attachment_passes_image_through(): void {
		$this->assertSame( [ 'x' ], Downsize::filter( [ 'x' ], 99, 'thumb' ) );
	}

	public function test_external_url_passes_image_through(): void {
		$this->facts->urls[ self::ID ] = 'https://elsewhere.test/photo.jpg';

		$this->assertSame( [ 'x' ], Downsize::filter( [ 'x' ], self::ID, 'thumb' ) );
	}

	public function test_disabled_in_admin_by_default(): void {
		Functions\when( 'is_admin' )->justReturn( true );

		$this->assertSame( [ 'x' ], Downsize::filter( [ 'x' ], self::ID, 'thumb' ) );
	}

	public function test_override_filter_passes_image_through(): void {
		Filters\expectApplied( 'tachyon_override_image_downsize' )->andReturn( true );

		$this->assertSame( [ 'x' ], Downsize::filter( [ 'x' ], self::ID, 'thumb' ) );
	}
}
