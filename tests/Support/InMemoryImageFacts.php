<?php
/**
 * In-memory Image Facts for unit tests.
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests\Support;

use Soderlind\Plugin\TachyonEdgeImages\ImageFacts;

/**
 * The test adapter: every fact is set up by the test.
 */
final class InMemoryImageFacts implements ImageFacts {

	/** @var array<string, array{width: int|null, height: int|null, crop: bool|array}> */
	public array $sizes = [
		'thumb'        => [ 'width' => 150, 'height' => 150, 'crop' => true ],
		'medium'       => [ 'width' => 300, 'height' => 300, 'crop' => false ],
		'medium_large' => [ 'width' => 768, 'height' => 0, 'crop' => false ],
		'large'        => [ 'width' => 1024, 'height' => 1024, 'crop' => false ],
		'full'         => [ 'width' => null, 'height' => null, 'crop' => false ],
		'thumbnail'    => [ 'width' => 150, 'height' => 150, 'crop' => true ],
	];

	/** @var array<int, array> */
	public array $metadata = [];

	/** @var array<int, string> */
	public array $urls = [];

	/** @var array<string, array> Keyed by "id:size" or "id:WxH". */
	public array $sources = [];

	/** @var array<string, array> Keyed by "id:size". */
	public array $intermediates = [];

	public mixed $content_width = false;

	public string $upload_base_url = 'https://example.com/wp-content/uploads';

	/** @var int[] */
	public array $primed = [];

	/** @var callable|null fn( int $w, int $h, string|array $size, ?string $context ): array */
	public $constrain = null;

	public function sizes() {
		return $this->sizes;
	}

	public function metadata( $id ) {
		return $this->metadata[ (int) $id ] ?? false;
	}

	public function url( $id ) {
		return $this->urls[ (int) $id ] ?? false;
	}

	public function source( $id, $size ) {
		return $this->sources[ $id . ':' . ( is_array( $size ) ? implode( 'x', $size ) : $size ) ] ?? false;
	}

	public function is_attachment( $id ) {
		return isset( $this->metadata[ (int) $id ] ) || isset( $this->urls[ (int) $id ] );
	}

	public function intermediate( $id, $size ) {
		return $this->intermediates[ $id . ':' . $size ] ?? false;
	}

	/**
	 * WordPress's geometry: [ 0, 0, src_x, src_y, new_w, new_h, crop_w, crop_h ],
	 * or false when the result would not be smaller than the original.
	 */
	public function resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop = false ) {
		if ( $crop ) {
			$new_w = min( (int) $dest_w, (int) $orig_w );
			$new_h = min( (int) $dest_h, (int) $orig_h );
			$new_w = $new_w ?: (int) round( $new_h * $orig_w / $orig_h );
			$new_h = $new_h ?: (int) round( $new_w * $orig_h / $orig_w );

			$ratio  = max( $new_w / $orig_w, $new_h / $orig_h );
			$crop_w = (int) round( $new_w / $ratio );
			$crop_h = (int) round( $new_h / $ratio );
			$src_x  = (int) floor( ( $orig_w - $crop_w ) / 2 );
			$src_y  = (int) floor( ( $orig_h - $crop_h ) / 2 );
		} else {
			[ $new_w, $new_h ] = self::fit( (int) $orig_w, (int) $orig_h, (int) $dest_w, (int) $dest_h );

			$crop_w = (int) $orig_w;
			$crop_h = (int) $orig_h;
			$src_x  = 0;
			$src_y  = 0;
		}

		if ( $new_w >= $orig_w && $new_h >= $orig_h ) {
			return false;
		}

		return [ 0, 0, $src_x, $src_y, $new_w, $new_h, $crop_w, $crop_h ];
	}

	/**
	 * Like image_constrain_size_for_editor(): fit inside the array size or the
	 * registered size's box. Set $constrain to model $content_width or filters.
	 */
	public function constrain( $width, $height, $size, $context = null ) {
		if ( $this->constrain ) {
			return ( $this->constrain )( $width, $height, $size, $context );
		}

		if ( is_array( $size ) ) {
			[ $max_w, $max_h ] = [ (int) ( $size[0] ?? 0 ), (int) ( $size[1] ?? 0 ) ];
		} elseif ( isset( $this->sizes[ $size ] ) ) {
			[ $max_w, $max_h ] = [ (int) $this->sizes[ $size ]['width'], (int) $this->sizes[ $size ]['height'] ];
		} else {
			return [ $width, $height ];
		}

		return self::fit( (int) $width, (int) $height, $max_w, $max_h );
	}

	/**
	 * Like wp_constrain_dimensions(): shrink to fit, never grow; 0 means no limit.
	 *
	 * @return int[]
	 */
	private static function fit( int $w, int $h, int $max_w, int $max_h ): array {
		$ratios = [];
		if ( $max_w && $w > $max_w ) {
			$ratios[] = $max_w / $w;
		}
		if ( $max_h && $h > $max_h ) {
			$ratios[] = $max_h / $h;
		}
		if ( ! $ratios ) {
			return [ $w, $h ];
		}

		$ratio = min( $ratios );

		return [ max( 1, (int) round( $w * $ratio ) ), max( 1, (int) round( $h * $ratio ) ) ];
	}

	public function content_width() {
		return $this->content_width;
	}

	public function upload_base_url() {
		return $this->upload_base_url;
	}

	public function prime_attachments( array $ids ) {
		$this->primed = $ids;
	}
}
