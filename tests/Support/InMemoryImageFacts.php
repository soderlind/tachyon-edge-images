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
	 * Proportional fit, or a crop with a missing side from the aspect ratio, never upscaling. False when the
	 * result would not be smaller than the original, like WordPress.
	 */
	public function resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop = false ) {
		if ( $crop ) {
			$new_w = min( (int) $dest_w, (int) $orig_w );
			$new_h = min( (int) $dest_h, (int) $orig_h );
			$new_w = $new_w ?: (int) round( $new_h * $orig_w / $orig_h );
			$new_h = $new_h ?: (int) round( $new_w * $orig_h / $orig_w );
		} else {
			$ratios = array_filter( [ $dest_w ? $dest_w / $orig_w : null, $dest_h ? $dest_h / $orig_h : null ] );
			$ratio  = $ratios ? min( 1, min( $ratios ) ) : 1;
			$new_w  = (int) round( $orig_w * $ratio );
			$new_h  = (int) round( $orig_h * $ratio );
		}

		if ( $new_w >= $orig_w && $new_h >= $orig_h ) {
			return false;
		}

		return [ 0, 0, 0, 0, $new_w, $new_h, $new_w, $new_h ];
	}

	public function constrain( $width, $height, $size, $context = null ) {
		return $this->constrain ? ( $this->constrain )( $width, $height, $size, $context ) : [ $width, $height ];
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
