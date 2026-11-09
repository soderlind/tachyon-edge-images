<?php
/**
 * Content sizing path, driven through Content::filter() with in-memory Image Facts.
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Soderlind\Plugin\TachyonEdgeImages\Content;
use Soderlind\Plugin\TachyonEdgeImages\Tests\Support\SizingTestCase;

final class ContentTest extends SizingTestCase {

	private const ID = 7;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_list_filter' )->alias( [ self::class, 'wp_list_filter' ] );

		$this->facts->urls[ self::ID ]     = self::UPLOADS . '/2024/01/photo.jpg';
		$this->facts->metadata[ self::ID ] = [ 'file' => '2024/01/photo.jpg', 'width' => 2000, 'height' => 1000 ];
	}

	/**
	 * The src of the first image in some HTML.
	 */
	private static function src( string $html ): string {
		preg_match( '#<img[^>]+src="([^"]+)"#', $html, $m );

		return html_entity_decode( $m[1] );
	}

	private static function img( string $file, string $attrs = '' ): string {
		return '<img src="' . self::UPLOADS . '/2024/01/' . $file . '"' . ( '' === $attrs ? '' : ' ' . $attrs ) . '>';
	}

	public function test_dimensions_from_attributes_resize(): void {
		$out = Content::filter( self::img( 'photo.jpg', 'width="400" height="200"' ) );

		$this->assertSame( '<img src="' . self::TACHYON . '/2024/01/photo.jpg?resize=400%2C200"  data-recalc-dims="1">', $out );
	}

	public function test_dimensions_from_filename_resize_the_original(): void {
		$out = Content::filter( self::img( 'photo-300x150.jpg' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=300%2C150', self::src( $out ) );
	}

	public function test_wider_than_content_width_scales_down(): void {
		$this->facts->content_width = 200;

		$out = Content::filter( self::img( 'photo.jpg', 'width="400" height="200"' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=200%2C100', self::src( $out ) );
	}

	public function test_content_width_supplies_a_missing_width(): void {
		$this->facts->content_width = 640;

		$out = Content::filter( self::img( 'photo.jpg' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?w=640', self::src( $out ) );
	}

	public function test_relative_dimensions_are_dropped(): void {
		$out = Content::filter( self::img( 'photo.jpg', 'width="50%" height="50%"' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg', self::src( $out ) );
	}

	public function test_attachment_size_comes_from_metadata_file(): void {
		$this->facts->metadata[ self::ID ]['sizes'] = [
			'thumbnail' => [ 'file' => 'photo-150x150.jpg', 'width' => 150, 'height' => 150 ],
		];

		$this->expect_post_image_args( [ 'resize' => '150,150' ], self::ID, 'thumbnail' );

		$out = Content::filter( self::img( 'photo-150x150.jpg', 'class="wp-image-7"' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=150%2C150', self::src( $out ) );
	}

	public function test_attachment_size_comes_from_matching_source(): void {
		$this->facts->sources[ self::ID . ':medium_large' ] = [ self::TACHYON . '/2024/01/photo.jpg?fit=768,768', 768, 384, true ];

		$this->expect_post_image_args( [ 'fit' => '768,384' ], self::ID, 'medium_large' );

		$out = Content::filter( self::img( 'photo-768x384.jpg', 'class="wp-image-7"' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?fit=768%2C384', self::src( $out ) );
	}

	public function test_local_attachment_source_replaces_src_and_caps_dimensions(): void {
		$this->facts->sources[ self::ID . ':medium' ] = [ self::UPLOADS . '/2024/01/photo-300x150.jpg', 300, 150, true ];

		$out = Content::filter( self::img( 'photo-300x150.jpg', 'class="wp-image-7 size-medium" width="600" height="300"' ) );

		// The intermediate file is kept, and the attributes are capped at its size.
		$this->assertSame( self::TACHYON . '/2024/01/photo-300x150.jpg?fit=300%2C150', self::src( $out ) );
	}

	public function test_crop_position_becomes_gravity(): void {
		$this->facts->sizes['hero'] = [ 'width' => 600, 'height' => 300, 'crop' => [ 'center', 'bottom' ] ];

		$out = Content::filter( self::img( 'photo-600x300.jpg', 'class="wp-image-7 size-hero"' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=600%2C300&gravity=south', self::src( $out ) );
	}

	public function test_unknown_attachment_id_is_ignored(): void {
		// The size name is the first registered size matching either dimension.
		$this->expect_post_image_args( [ 'resize' => '300,150' ], false, 'thumb' );

		$out = Content::filter( self::img( 'photo-300x150.jpg', 'class="wp-image-99"' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=300%2C150', self::src( $out ) );
	}

	public function test_attachment_must_be_under_upload_base_url(): void {
		$this->facts->upload_base_url = 'https://cdn.example.com/media';

		$this->expect_post_image_args( [ 'resize' => '300,150' ], false, 'thumb' );

		$out = Content::filter( self::img( 'photo-300x150.jpg', 'class="wp-image-7"' ) );

		$this->assertSame( self::TACHYON . '/2024/01/photo.jpg?resize=300%2C150', self::src( $out ) );
	}

	public function test_primes_attachments_only_for_several_ids(): void {
		Content::filter( self::img( 'photo.jpg', 'class="wp-image-7"' ) );
		$this->assertSame( [], $this->facts->primed );

		Content::filter( self::img( 'photo.jpg', 'class="wp-image-7"' ) . self::img( 'other.jpg', 'class="wp-image-8"' ) . self::img( 'photo.jpg', 'class="wp-image-7"' ) );
		$this->assertSame( [ 7, 8 ], $this->facts->primed );
	}

	public function test_link_to_original_is_rewritten(): void {
		$html = '<a href="' . self::UPLOADS . '/2024/01/photo.jpg">' . self::img( 'photo-300x150.jpg' ) . '</a>';

		$out = Content::filter( $html );

		$this->assertStringStartsWith( '<a href="' . self::TACHYON . '/2024/01/photo.jpg">', $out );
	}

	public function test_lazy_load_placeholder_goes_through_tachyon(): void {
		$html = '<img src="' . self::UPLOADS . '/2024/01/blank.png" data-lazy-src="' . self::UPLOADS . '/2024/01/photo-300x150.jpg">';

		$out = Content::filter( $html );

		$this->assertStringContainsString( 'src="' . self::TACHYON . '/2024/01/blank.png"', $out );
		$this->assertStringContainsString( 'data-lazy-src="' . self::TACHYON . '/2024/01/photo.jpg?resize=300%2C150"', $out );
	}

	public function test_external_image_is_left_alone(): void {
		$html = '<img src="https://elsewhere.test/photo.jpg" width="400" height="200">';

		$this->assertSame( $html, Content::filter( $html ) );
	}

	public function test_skip_filter_leaves_image_alone(): void {
		Filters\expectApplied( 'tachyon_skip_image' )->andReturn( true );
		$html = self::img( 'photo.jpg', 'width="400" height="200"' );

		$this->assertSame( $html, Content::filter( $html ) );
	}

	/**
	 * Expect tachyon_post_image_args with these args, attachment ID and size.
	 *
	 * @param array             $args          Expected Tachyon args.
	 * @param int|false         $attachment_id Expected attachment ID.
	 * @param string|array|null $size          Expected size.
	 */
	private function expect_post_image_args( array $args, $attachment_id, $size ): void {
		Filters\expectApplied( 'tachyon_post_image_args' )
			->once()
			->with(
				$args,
				\Mockery::on(
					static fn ( $details ) => $attachment_id === $details['attachment_id'] && $size === $details['size']
				)
			)
			->andReturnFirstArg();
	}

	/**
	 * Minimal wp_list_filter(): AND or OR match on key/value pairs.
	 */
	public static function wp_list_filter( array $list, array $args = [], string $operator = 'AND' ): array {
		return array_filter(
			$list,
			static function ( $item ) use ( $args, $operator ) {
				$matched = count( array_intersect_assoc( array_intersect_key( (array) $item, $args ), $args ) );

				return 'OR' === $operator ? $matched > 0 : count( $args ) === $matched;
			}
		);
	}
}
