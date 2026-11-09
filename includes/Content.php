<?php
/**
 * Post content and gallery filtering, ported from the Reference Plugin.
 *
 * No strict_types: see Url.php.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Rewrites images in HTML.
 */
final class Content {

	/**
	 * Identify images in post content, and if images are local, pass them through Tachyon.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter( $content ) {
		$images = Images::parse_images_from_html( $content );

		if ( empty( $images ) ) {
			return $content;
		}

		$facts           = Facts::get();
		$content_width   = $facts->content_width();
		$image_sizes     = $facts->sizes();
		$upload_base_url = $facts->upload_base_url();
		$attachment_ids  = [];

		foreach ( $images[0] as $tag ) {
			if ( preg_match( '/wp-image-([0-9]+)/i', $tag, $class_id ) && absint( $class_id[1] ) ) {
				// Overwrite the ID when the same image is included more than once.
				$attachment_ids[ $class_id[1] ] = true;
			}
		}

		if ( count( $attachment_ids ) > 1 ) {
			// Warm the object cache with post and meta information for all found images.
			$facts->prime_attachments( array_keys( $attachment_ids ) );
		}

		foreach ( $images[0] as $index => $tag ) {
			/**
			 * Allow specific images to be skipped by Tachyon.
			 *
			 * @param bool   false Should Tachyon ignore this image. Default to false.
			 * @param string $src  Image URL.
			 * @param string $tag  Image Tag (Image HTML output).
			 */
			if ( apply_filters( 'tachyon_skip_image', false, $images['img_url'][ $index ], $tag ) ) {
				continue;
			}

			[ $src, $src_orig, $placeholder_src ] = self::image_source( $images, $index );

			if ( Images::validate_image_url( $src ) ) {
				[ $width, $height, $transform, $size, $attachment_id, $src, $fullsize_url ] = self::dimensions( $images, $index, $src, $image_sizes, $upload_base_url, $facts );
				[ $width, $height, $transform ] = self::fit_content_width( $width, $height, $transform, $content_width );

				// Detect if image source is for a custom-cropped thumbnail and prevent further URL manipulation.
				if ( ! $fullsize_url && preg_match_all( '#-e[a-z0-9]+(-\d+x\d+)?\.(' . implode( '|', Images::EXTENSIONS ) . '){1}$#i', basename( $src ), $filename ) ) {
					$fullsize_url = true;
				}

				// Build URL, first maybe removing WP's resized string so we pass the original image to Tachyon.
				if ( ! $fullsize_url ) {
					$src = Images::strip_image_dimensions_maybe( $src );
				}

				[ $args, $size ] = self::tachyon_args( $width, $height, $transform, $size, $image_sizes );

				/**
				 * Filter the array of Tachyon arguments added to an image when it goes through Tachyon.
				 *
				 * @param array $args    Array of Tachyon Arguments.
				 * @param array $details Image details: tag, src, src_orig, width, height, attachment_id, size.
				 */
				$args = apply_filters( 'tachyon_post_image_args', $args, compact( 'tag', 'src', 'src_orig', 'width', 'height', 'attachment_id', 'size' ) );

				$tachyon_url = Url::build( $src, $args );

				// Modify image tag if Tachyon function provides a URL.
				// Copy and modify the matched tag, then replace the entire tag with the modified version.
				if ( $src !== $tachyon_url ) {
					$content = str_replace( $tag, self::rewrite_tag( $tag, $images['link_url'][ $index ], $src_orig, $tachyon_url, $placeholder_src ), $content );
				}
			} elseif ( preg_match( '#^http(s)?://i[\d]{1}.wp.com#', $src ) && ! empty( $images['link_url'][ $index ] ) && Images::validate_image_url( $images['link_url'][ $index ] ) ) {
				$new_tag = self::replace_link_url( $tag, $images['link_url'][ $index ] );

				$content = str_replace( $tag, $new_tag, $content );
			}
		}

		return $content;
	}

	/**
	 * The image URL to pass through Tachyon, with lazy-load support.
	 *
	 * @param array $images Parsed images.
	 * @param int   $index  Image index.
	 * @return array [ src (decoded), src_orig (as in the HTML), lazy-load placeholder src or null ].
	 */
	private static function image_source( $images, $index ) {
		$src             = $images['img_url'][ $index ];
		$placeholder_src = null;

		// Support Automattic's Lazy Load plugin.
		if (
			preg_match( '#data-lazy-src=["|\'](.+?)["|\']#i', $images['img_tag'][ $index ], $lazy_load_src ) ||
			preg_match( '#data-lazy-original=["|\'](.+?)["|\']#i', $images['img_tag'][ $index ], $lazy_load_src )
		) {
			$placeholder_src = $src;
			$src             = $lazy_load_src[1];
		}

		// Image src coming from HTML is expected to be htmlentityencoded.
		return [ html_entity_decode( $src ), $src, $placeholder_src ];
	}

	/**
	 * Work out the image's dimensions and registered size from its tag and attachment.
	 *
	 * @param array      $images          Parsed images.
	 * @param int        $index           Image index.
	 * @param string     $src             Image URL.
	 * @param array      $image_sizes     Registered image sizes.
	 * @param string     $upload_base_url Uploads base URL.
	 * @param ImageFacts $facts           Image Facts.
	 * @return array [ width, height, transform, size or null, attachment_id or false, src, fullsize_url ].
	 */
	private static function dimensions( $images, $index, $src, $image_sizes, $upload_base_url, ImageFacts $facts ) {
		$tag = $images[0][ $index ];

		// Default to resize, though fit may be used in certain cases where a dimension cannot be ascertained.
		$transform     = 'resize';
		$size          = null;
		$attachment_id = false;
		$fullsize_url  = false;
		$width         = false;
		$height        = false;

		// First, check the image tag.
		if ( preg_match( '#width=["|\']?([\d%]+)["|\']?#i', $images['img_tag'][ $index ], $width_string ) ) {
			$width = $width_string[1];
		}

		if ( preg_match( '#height=["|\']?([\d%]+)["|\']?#i', $images['img_tag'][ $index ], $height_string ) ) {
			$height = $height_string[1];
		}

		// If image tag lacks width or height arguments, try to determine from strings WP appends to resized image filenames.
		if ( ! $width || ! $height ) {
			$size_from_file = Images::parse_dimensions_from_filename( $src );
			$width          = $width ?: $size_from_file[0];
			$height         = $height ?: $size_from_file[1];
		}

		// Can't pass both a relative width and height, so unset the height in favor of not breaking the horizontal layout.
		if ( false !== strpos( (string) $width, '%' ) && false !== strpos( (string) $height, '%' ) ) {
			$width  = false;
			$height = false;
		}

		// Detect WP registered image size from HTML class (a Plugin Quirk: $attachment_id is still false here).
		if ( preg_match( '#class=["|\']?[^"\']*size-([^"\'\s]+)[^"\']*["|\']?#i', $images['img_tag'][ $index ], $matches ) ) {
			$size = array_pop( $matches );

			if ( false === $width && false === $height && array_key_exists( $size, $image_sizes ) ) {
				$size_from_wp = $facts->source( $attachment_id, $size );
				// Deviation 3: the Reference Plugin read offsets on false (a warning) and got null.
				// Keep null, so later branches and filter arguments match.
				$width     = is_array( $size_from_wp ) ? $size_from_wp[1] : null;
				$height    = is_array( $size_from_wp ) ? $size_from_wp[2] : null;
				$transform = $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
			}
		}

		// WP Attachment ID, if uploaded to this site.
		if (
			! preg_match( '#class=["|\']?[^"\']*wp-image-([\d]+)[^"\']*["|\']?#i', $images['img_tag'][ $index ], $class_attachment_id ) ||
			! (
				0 === strpos( $src, $upload_base_url ) ||
				/**
				 * Filter whether an image using an attachment ID in its class has to be uploaded to the local site to go through Tachyon.
				 *
				 * @param bool  false Was the image uploaded to the local site. Default to false.
				 * @param array $args  Image details: src, tag, images, index.
				 */
				apply_filters( 'tachyon_image_is_local', false, compact( 'src', 'tag', 'images', 'index' ) )
			)
		) {
			return [ $width, $height, $transform, $size, $attachment_id, $src, $fullsize_url ];
		}

		$class_attachment_id = intval( array_pop( $class_attachment_id ) );

		if ( ! $class_attachment_id || ! $facts->is_attachment( $class_attachment_id ) ) {
			return [ $width, $height, $transform, $size, $attachment_id, $src, $fullsize_url ];
		}

		$attachment_id = $class_attachment_id;

		// If we still don't have a size for the image, use the attachment_id
		// to lookup the size for the image in the URL.
		if ( null === $size ) {
			$meta = $facts->metadata( $attachment_id );
			// Deviation 3: guard against missing or invalid size metadata.
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				$sizes = wp_list_filter( $meta['sizes'], [ 'file' => basename( $src ) ] );
				if ( $sizes ) {
					$size_names = array_keys( $sizes );
					$size       = array_pop( $size_names );
				}
			}
		}

		// If we still don't have a size for the image but know the dimensions,
		// use the attachment sources to determine the size.
		if ( null === $size && $width && $height ) {
			foreach ( array_keys( $image_sizes ) as $candidate ) {
				$size_per_wp = $facts->source( $attachment_id, $candidate );
				// Deviation 3: guard against a missing image source.
				if ( is_array( $size_per_wp ) && isset( $size_per_wp[1], $size_per_wp[2] ) && $width === $size_per_wp[1] && $height === $size_per_wp[2] ) {
					$size      = $candidate;
					$transform = $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
					break;
				}
			}
		}

		if ( null !== $size && false === $width && false === $height && array_key_exists( $size, $image_sizes ) ) {
			$width     = (int) $image_sizes[ $size ]['width'];
			$height    = (int) $image_sizes[ $size ]['height'];
			$transform = $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
		}

		/*
		 * If size is still not set the dimensions were not provided by either
		 * a class or by parsing the URL. Only the full sized image should return
		 * no dimensions when returning the URL so it's safe to assume the $size is full.
		 */
		$size = $size ?? 'full';

		$src_per_wp = $facts->source( $attachment_id, $size );

		// Deviation 3: guard against a missing image source.
		if ( is_array( $src_per_wp ) && isset( $src_per_wp[0], $src_per_wp[1], $src_per_wp[2] ) && Images::validate_image_url( $src_per_wp[0] ) ) {
			$src          = $src_per_wp[0];
			$fullsize_url = true;

			// Prevent image distortion if a detected dimension exceeds the image's natural dimensions.
			if ( ( false !== $width && $width > $src_per_wp[1] ) || ( false !== $height && $height > $src_per_wp[2] ) ) {
				$width  = false === $width ? false : min( $width, $src_per_wp[1] );
				$height = false === $height ? false : min( $height, $src_per_wp[2] );
			}

			// If no width and height are found, max out at source image's natural dimensions.
			// Otherwise, respect registered image sizes' cropping setting.
			if ( false === $width && false === $height ) {
				$width     = $src_per_wp[1];
				$height    = $src_per_wp[2];
				$transform = 'fit';
			} elseif ( array_key_exists( $size, $image_sizes ) && isset( $image_sizes[ $size ]['crop'] ) ) {
				$transform = (bool) $image_sizes[ $size ]['crop'] ? 'resize' : 'fit';
			}
		}

		return [ $width, $height, $transform, $size, $attachment_id, $src, $fullsize_url ];
	}

	/**
	 * Constrain the dimensions to $content_width.
	 *
	 * @param int|string|false|null $width         Width.
	 * @param int|string|false|null $height        Height.
	 * @param string                $transform     resize or fit.
	 * @param mixed                 $content_width The $content_width global, or false.
	 * @return array [ width, height, transform ].
	 */
	private static function fit_content_width( $width, $height, $transform, $content_width ) {
		// If width is available, constrain to $content_width.
		if ( false !== $width && false === strpos( (string) $width, '%' ) && is_numeric( $content_width ) ) {
			if ( $width > $content_width && false !== $height && false === strpos( (string) $height, '%' ) ) {
				$height = round( ( $content_width * $height ) / $width );
				$width  = $content_width;
			} elseif ( $width > $content_width ) {
				$width = $content_width;
			}
		}

		// Set a width if none is found and $content_width is available.
		// If width is set in this manner and height is available, use `fit` instead of `resize` to prevent skewing.
		if ( false === $width && is_numeric( $content_width ) ) {
			$width = (int) $content_width;

			if ( false !== $height ) {
				$transform = 'fit';
			}
		}

		return [ $width, $height, $transform ];
	}

	/**
	 * Build the Tachyon arguments, and name the size when it is still unknown.
	 *
	 * @param int|string|false|null $width       Width.
	 * @param int|string|false|null $height      Height.
	 * @param string                $transform   resize or fit.
	 * @param string|null           $size        Registered size name, or null.
	 * @param array                 $image_sizes Registered image sizes.
	 * @return array [ args, size ].
	 */
	private static function tachyon_args( $width, $height, $transform, $size, $image_sizes ) {
		$args = [];

		// Deviation 3: null dimensions from a failed size lookup gave `resize=,` in the Reference Plugin.
		if ( null === $width && null === $height ) {
			// No dimensions known; send the image without resize arguments.
		} elseif ( false !== $width && false !== $height && false === strpos( (string) $width, '%' ) && false === strpos( (string) $height, '%' ) ) {
			if ( 'full' !== $size ) {
				$args[ $transform ] = $width . ',' . $height;
			}

			// Set the gravity from the registered image size.
			if ( 'resize' === $transform && null !== $size && 'full' !== $size && array_key_exists( $size, $image_sizes ) && is_array( $image_sizes[ $size ]['crop'] ) ) {
				$args['gravity'] = Images::gravity( $image_sizes[ $size ]['crop'] );
			}
		} elseif ( false !== $width ) {
			$args['w'] = $width;
		} elseif ( false !== $height ) {
			$args['h'] = $height;
		}

		// Final logic check to determine the size for an unknown attachment ID.
		// Deviation 2: the Reference Plugin carried $filter over from the previous image.
		if ( null === $size ) {
			$filter = [];
			if ( $width ) {
				$filter['width'] = $width;
			}
			if ( $height ) {
				$filter['height'] = $height;
			}

			if ( ! empty( $filter ) ) {
				$sizes = wp_list_filter( $image_sizes, $filter );
				if ( empty( $sizes ) ) {
					$sizes = wp_list_filter( $image_sizes, $filter, 'OR' );
				}
				if ( ! empty( $sizes ) ) {
					// Deviation 2: the Reference Plugin used the size's settings, not its name.
					reset( $sizes );
					$size = key( $sizes );
				}
			}
		}

		if ( null === $size ) {
			// Custom size, send an array.
			$size = [ $width, $height ];
		}

		return [ $args, $size ];
	}

	/**
	 * Point the tag at Tachyon: link, src and lazy-load placeholder, then drop size attributes.
	 *
	 * @param string      $tag             Matched tag, with any surrounding link.
	 * @param string      $link_url        Link URL, or empty.
	 * @param string      $src_orig        Image URL as in the HTML.
	 * @param string      $tachyon_url     Tachyon URL for the image.
	 * @param string|null $placeholder_src Lazy-load placeholder URL, or null.
	 * @return string
	 */
	private static function rewrite_tag( $tag, $link_url, $src_orig, $tachyon_url, $placeholder_src ) {
		$new_tag = $tag;

		// If present, replace the link href with a Tachyoned URL for the full-size image.
		if ( ! empty( $link_url ) && Images::validate_image_url( $link_url ) ) {
			$new_tag = self::replace_link_url( $new_tag, $link_url );
		}

		// Supplant the original source value with our Tachyon URL.
		$new_tag = str_replace( $src_orig, esc_url( $tachyon_url ), $new_tag );

		// If Lazy Load is in use, pass placeholder image through Tachyon.
		if ( null !== $placeholder_src && Images::validate_image_url( $placeholder_src ) ) {
			$tachyon_placeholder = Url::build( $placeholder_src );

			if ( $tachyon_placeholder !== $placeholder_src ) {
				$new_tag = str_replace( $placeholder_src, esc_url( $tachyon_placeholder ), $new_tag );
			}
		}

		// Remove the width and height arguments from the tag to prevent distortion.
		if ( apply_filters( 'tachyon_remove_size_attributes', true ) ) {
			$new_tag = preg_replace( '#(?<=\s)(width|height)=["|\']?[\d%]+["|\']?\s?#i', '', $new_tag );
		}

		// Tag an image for dimension checking.
		return preg_replace( '#(\s?/)?>(\s*</a>)?$#i', ' data-recalc-dims="1"\1>\2', $new_tag );
	}

	/**
	 * Ensure galleries use Tachyon.
	 *
	 * @param array $galleries The post's galleries.
	 * @return array
	 */
	public static function filter_galleries( $galleries ) {
		if ( empty( $galleries ) || ! is_array( $galleries ) ) {
			return $galleries;
		}

		foreach ( $galleries as &$this_gallery ) {
			if ( is_string( $this_gallery ) ) {
				$this_gallery = \Tachyon::filter_the_content( $this_gallery );
			}
		}
		unset( $this_gallery );

		return $galleries;
	}

	/**
	 * Replace an image link URL with its Tachyon equivalent.
	 *
	 * Deviation 1: the Reference Plugin used the URL unescaped in the regex and
	 * the replacement, so URLs with special characters broke or were mangled.
	 *
	 * @param string $tag      Image tag or linked image markup.
	 * @param string $link_url Original link URL.
	 * @return string
	 */
	private static function replace_link_url( $tag, $link_url ) {
		$pattern = '#(href=["|\'])' . preg_quote( $link_url, '#' ) . '(["|\'])#i';

		return preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $link_url ) {
				return $matches[1] . Url::build( $link_url ) . $matches[2];
			},
			$tag,
			1
		);
	}
}
