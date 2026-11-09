<?php
/**
 * Responsive image srcset filtering, ported from the Reference Plugin.
 *
 * No strict_types: see Url.php.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Filters wp_calculate_image_srcset.
 */
final class Srcset {

	/**
	 * Replace each srcset URL with its Tachyon equivalent.
	 *
	 * @param array  $sources       Image URLs and widths.
	 * @param array  $size_array    Requested width and height.
	 * @param string $image_src     Current image URL.
	 * @param array  $image_meta    Image metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @return array
	 */
	public static function filter( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		$upload_base_url = Facts::get()->upload_base_url();

		foreach ( $sources as $i => $source ) {
			if ( ! Images::validate_image_url( $source['url'] ) ) {
				continue;
			}

			$url                    = $source['url'];
			list( $width, $height ) = Images::parse_dimensions_from_filename( $url );

			// It's quicker to get the full size with the data we have already, if available.
			if ( isset( $image_meta['file'] ) ) {
				$url = trailingslashit( $upload_base_url ) . $image_meta['file'];
			} else {
				$url = Images::strip_image_dimensions_maybe( $url );
			}

			$args = [];
			if ( 'w' === $source['descriptor'] ) {
				if ( $height && ( intval( $source['value'] ) === intval( $width ) ) ) {
					$args['resize'] = $width . ',' . $height;
				} else {
					$args['w'] = $source['value'];
				}
			}

			// If the image_src is a Tachyon URL, add its gravity to the srcset images too.
			if ( strpos( $image_src, TACHYON_URL ) === 0 ) {
				parse_str( parse_url( $image_src, PHP_URL_QUERY ) ?? '', $image_src_args );
				$args = array_merge( $args, array_intersect_key( $image_src_args, [ 'gravity' => true ] ) );
			}

			/**
			 * Filter the array of Tachyon arguments added to a srcset image.
			 *
			 * @param array $args    Array of Tachyon Arguments.
			 * @param array $details Image details: source, image_meta, width, height, attachment_id.
			 */
			$args = apply_filters( 'tachyon_srcset_image_args', $args, compact( 'source', 'image_meta', 'width', 'height', 'attachment_id' ) );

			$sources[ $i ]['url'] = Url::build( $url, $args );
		}

		return $sources;
	}
}
