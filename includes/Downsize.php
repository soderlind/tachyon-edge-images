<?php
/**
 * Core image retrieval (image_downsize), ported from the Reference Plugin.
 *
 * No strict_types: see Url.php.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Filters image_downsize.
 */
final class Downsize {

	/**
	 * Filter post thumbnail image retrieval, passing images through Tachyon.
	 *
	 * @param array|bool       $image         Image array or false.
	 * @param int              $attachment_id The attachment ID.
	 * @param string|int|array $size          The target image size.
	 * @return array|bool
	 */
	public static function filter( $image, $attachment_id, $size ) {
		/**
		 * Provide plugins a way of enable use of Tachyon in the admin context.
		 *
		 * @param bool  true    Disable the use of Tachyon in the admin.
		 * @param array $args   Image details: image, attachment_id, size.
		 */
		$disable_in_admin = is_admin() && apply_filters( 'tachyon_disable_in_admin', true, compact( 'image', 'attachment_id', 'size' ) );

		/**
		 * Provide plugins a way of preventing Tachyon from being applied to images retrieved from WordPress Core.
		 *
		 * @param bool  false Stop Tachyon from being applied to the image. Default to false.
		 * @param array $args Image details: image, attachment_id, size.
		 */
		$override_image_downsize = apply_filters( 'tachyon_override_image_downsize', false, compact( 'image', 'attachment_id', 'size' ) );

		if ( $disable_in_admin || $override_image_downsize ) {
			return $image;
		}

		// Get the image URL and proceed with Tachyon-ification if successful.
		$image_url       = wp_get_attachment_url( $attachment_id );
		$full_size_meta  = wp_get_attachment_metadata( $attachment_id );
		$is_intermediate = false;

		if ( $image_url ) {
			// Check if image URL should be used with Tachyon.
			if ( ! Images::validate_image_url( $image_url ) ) {
				return $image;
			}

			// If an image is requested with a size known to WordPress, use that size's settings with Tachyon.
			if ( ! empty( $full_size_meta ) && ( is_string( $size ) || is_int( $size ) ) && array_key_exists( $size, Images::image_sizes() ) ) {
				$image_args = Images::image_sizes()[ $size ];

				$tachyon_args = [];

				$image_meta = image_get_intermediate_size( $attachment_id, $size );

				// 'full' is a special case: We need consistent data regardless of the requested size.
				if ( 'full' === $size ) {
					$image_meta = $full_size_meta;
				} elseif ( ! $image_meta ) {
					// If we still don't have any image meta at this point, it's probably from a custom thumbnail size
					// for an image that was uploaded before the custom image was added to the theme. Try to determine the size manually.
					$image_meta = $full_size_meta;
					if ( isset( $image_meta['width'] ) && isset( $image_meta['height'] ) ) {
						$image_resized = image_resize_dimensions( $image_meta['width'], $image_meta['height'], $image_args['width'], $image_args['height'], $image_args['crop'] );
						if ( $image_resized ) { // This could be false when the requested image size is larger than the full-size image.
							$image_meta['width']  = $image_resized[6];
							$image_meta['height'] = $image_resized[7];
							$is_intermediate      = true;
						}
					}
				} else {
					$is_intermediate = true;
				}

				$transform = $image_args['crop'] ? 'resize' : 'fit';

				// If the size has no width or height, use the image metadata, so a
				// 300x300 uncropped size stays `fit=300,300` rather than `resize=300,225`.
				if ( empty( $image_args['width'] ) && 'resize' !== $transform ) {
					$image_args['width'] = isset( $image_meta['width'] ) ? $image_meta['width'] : 0;
				}

				if ( empty( $image_args['height'] ) && 'resize' !== $transform ) {
					$image_args['height'] = isset( $image_meta['height'] ) ? $image_meta['height'] : 0;
				}

				// Prevent upscaling.
				$image_args['width']  = min( (int) $image_args['width'], (int) $full_size_meta['width'] );
				$image_args['height'] = min( (int) $image_args['height'], (int) $full_size_meta['height'] );

				// Respect $content_width settings.
				list( $width, $height ) = image_constrain_size_for_editor( $image_meta['width'], $image_meta['height'], $size, 'display' );

				// Check specified image dimensions and account for possible zero values; tachyon fails to resize if a dimension is zero.
				if ( ( 0 === $image_args['width'] || 0 === $image_args['height'] ) && 'fit' !== $transform ) {
					if ( 0 === $image_args['width'] && 0 < $image_args['height'] ) {
						$tachyon_args['h'] = $image_args['height'];
					} elseif ( 0 === $image_args['height'] && 0 < $image_args['width'] ) {
						$tachyon_args['w'] = $image_args['width'];
					}
				} else {
					// Fit accepts a zero value for either dimension so we allow that.
					// Both width & height are required, image args should be exact dimensions.
					if ( 'resize' === $transform ) {
						$image_args['width']  = $image_args['width'] ?: $width;
						$image_args['height'] = $image_args['height'] ?: $height;
					}

					$is_intermediate = ( $image_args['width'] < $full_size_meta['width'] || $image_args['height'] < $full_size_meta['height'] );

					// Add transform args if size is intermediate.
					if ( $is_intermediate ) {
						$tachyon_args[ $transform ] = $image_args['width'] . ',' . $image_args['height'];
					}

					if ( $is_intermediate && 'resize' === $transform && is_array( $image_args['crop'] ) ) {
						$tachyon_args['gravity'] = Content::gravity( $image_args['crop'] );
					}
				}

				/**
				 * Filter the Tachyon Arguments added to an image when that image size is a string.
				 *
				 * @param array $tachyon_args Array of Tachyon arguments.
				 * @param array $args         Image details: image_args, image_url, attachment_id, size, transform.
				 */
				$tachyon_args = apply_filters( 'tachyon_image_downsize_string', $tachyon_args, compact( 'image_args', 'image_url', 'attachment_id', 'size', 'transform' ) );

				// Generate Tachyon URL.
				$image = [
					tachyon_url( $image_url, $tachyon_args ),
					$width,
					$height,
					$is_intermediate,
				];
			} elseif ( is_array( $size ) ) {
				// Pull width and height values from the provided array, if possible.
				$width  = isset( $size[0] ) ? (int) $size[0] : false;
				$height = isset( $size[1] ) ? (int) $size[1] : false;

				// Don't bother if necessary parameters aren't passed.
				if ( ! $width || ! $height ) {
					return $image;
				}

				$image_meta = wp_get_attachment_metadata( $attachment_id );
				if ( isset( $image_meta['width'] ) && isset( $image_meta['height'] ) ) {
					$image_resized = image_resize_dimensions( $image_meta['width'], $image_meta['height'], $width, $height );
					if ( $image_resized ) {
						// Use the resized image dimensions.
						$width           = $image_resized[6];
						$height          = $image_resized[7];
						$is_intermediate = true;
					} else {
						// Resized image would be larger than original.
						$width  = $image_meta['width'];
						$height = $image_meta['height'];
					}
				}

				list( $width, $height ) = image_constrain_size_for_editor( $width, $height, $size );

				$tachyon_args = [];

				if ( $is_intermediate ) {
					$tachyon_args['fit'] = $width . ',' . $height;
				}

				/**
				 * Filter the Tachyon Arguments added to an image when the image size is an array of width and height.
				 *
				 * @param array $tachyon_args Array of Tachyon arguments.
				 * @param array $args         Image details: width, height, image_url, attachment_id.
				 */
				$tachyon_args = apply_filters( 'tachyon_image_downsize_array', $tachyon_args, compact( 'width', 'height', 'image_url', 'attachment_id' ) );

				// Generate Tachyon URL.
				$image = [
					tachyon_url( $image_url, $tachyon_args ),
					$width,
					$height,
					$is_intermediate,
				];
			}
		}

		return $image;
	}
}
