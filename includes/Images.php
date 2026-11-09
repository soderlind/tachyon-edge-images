<?php
/**
 * Image helpers shared by the content, downsize and srcset filters.
 *
 * Ported from the Reference Plugin. No strict_types: see Url.php.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Image helpers.
 */
final class Images {

	/**
	 * Extensions Tachyon handles.
	 *
	 * @var string[]
	 */
	public const EXTENSIONS = [
		'gif',
		'jpg',
		'jpeg',
		'png',
		'webp',
		'avif',
	];

	/**
	 * Cached image sizes. Like the Reference Plugin, filled on first use.
	 *
	 * @var array|null
	 */
	private static $image_sizes = null;

	/**
	 * Match all images and any relevant <a> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @return array
	 */
	public static function parse_images_from_html( $content ) {
		$images = [];

		if ( preg_match_all( '#(?:<a[^>]+?href=["|\'](?P<link_url>[^\s]+?)["|\'][^>]*?>\s*)?(?P<img_tag><img[^>]+?src=["|\'](?P<img_url>[^\s]+?)["|\'].*?>){1}(?:\s*</a>)?#is', $content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible, mostly for confirming test results.
				if ( is_numeric( $key ) && $key > 0 ) {
					unset( $images[ $key ] );
				}
			}

			return $images;
		}

		return [];
	}

	/**
	 * Try to determine height and width from strings WP appends to resized image filenames.
	 *
	 * @param string $src The image URL.
	 * @return array An array consisting of width and height.
	 */
	public static function parse_dimensions_from_filename( $src ) {
		$width_height_string = [];

		if ( preg_match( '#-(\d+)x(\d+)\.(?:' . implode( '|', self::EXTENSIONS ) . '){1}$#i', $src, $width_height_string ) ) {
			$width  = (int) $width_height_string[1];
			$height = (int) $width_height_string[2];

			if ( $width && $height ) {
				return [ $width, $height ];
			}
		}

		return [ false, false ];
	}

	/**
	 * Ensure image URL is valid for Tachyon.
	 *
	 * @param string $url An image URL.
	 * @return bool
	 */
	public static function validate_image_url( $url ) {
		$parsed_url = @parse_url( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $parsed_url ) {
			return false;
		}

		// Only replace URLs with supported file extensions.
		if ( ! in_array( strtolower( pathinfo( $parsed_url['path'] ?? '', PATHINFO_EXTENSION ) ), self::EXTENSIONS, true ) ) {
			return false;
		}

		$upload_dir     = wp_upload_dir();
		$upload_baseurl = $upload_dir['baseurl'];

		if ( is_multisite() ) {
			$upload_baseurl = preg_replace( '#/sites/[\d]+#', '', $upload_baseurl );
		}

		if ( strpos( $url, $upload_baseurl ) !== 0 ) {
			return false;
		}

		return apply_filters( 'tachyon_validate_image_url', true, $url, $parsed_url );
	}

	/**
	 * Remove WP's -WxH suffix so the original image is passed to Tachyon.
	 *
	 * @param string $src The image URL.
	 * @return string
	 */
	public static function strip_image_dimensions_maybe( $src ) {
		if ( preg_match( '#(-\d+x\d+)\.(' . implode( '|', self::EXTENSIONS ) . '){1}$#i', $src, $src_parts ) ) {
			$src = str_replace( $src_parts[1], '', $src );
		}

		return $src;
	}

	/**
	 * Available image sizes and their dimensions.
	 *
	 * @return array
	 */
	public static function image_sizes() {
		if ( null === self::$image_sizes ) {
			global $_wp_additional_image_sizes;

			// Populate an array matching the data structure of $_wp_additional_image_sizes so we have a consistent structure for image sizes.
			$images = [
				'thumb'        => [
					'width'  => intval( get_option( 'thumbnail_size_w' ) ),
					'height' => intval( get_option( 'thumbnail_size_h' ) ),
					'crop'   => (bool) get_option( 'thumbnail_crop' ),
				],
				'medium'       => [
					'width'  => intval( get_option( 'medium_size_w' ) ),
					'height' => intval( get_option( 'medium_size_h' ) ),
					'crop'   => false,
				],
				'medium_large' => [
					'width'  => intval( get_option( 'medium_large_size_w' ) ),
					'height' => intval( get_option( 'medium_large_size_h' ) ),
					'crop'   => false,
				],
				'large'        => [
					'width'  => intval( get_option( 'large_size_w' ) ),
					'height' => intval( get_option( 'large_size_h' ) ),
					'crop'   => false,
				],
				'full'         => [
					'width'  => null,
					'height' => null,
					'crop'   => false,
				],
			];

			// Compatibility mapping as found in wp-includes/media.php.
			$images['thumbnail'] = $images['thumb'];

			if ( is_array( $_wp_additional_image_sizes ) && ! empty( $_wp_additional_image_sizes ) ) {
				self::$image_sizes = array_merge( $images, $_wp_additional_image_sizes );
			} else {
				self::$image_sizes = $images;
			}
		}

		return is_array( self::$image_sizes ) ? self::$image_sizes : [];
	}

	/**
	 * Forget cached image sizes. For tests.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$image_sizes = null;
	}
}
