<?php
/**
 * Image Facts: what WordPress knows about the site's images.
 *
 * No strict_types: see Url.php. Parameters are untyped on purpose, so callers
 * get the same coercion and failure values as the WordPress functions behind them.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * The seam between the sizing paths (Content, Downsize, Srcset) and WordPress. See ADR 0003.
 */
interface ImageFacts {

	/**
	 * Registered image sizes, including `thumb`, `thumbnail` and `full`.
	 *
	 * @return array<string, array{width: int|null, height: int|null, crop: bool|array}>
	 */
	public function sizes();

	/**
	 * Attachment metadata, as wp_get_attachment_metadata().
	 *
	 * @param int $id Attachment ID.
	 * @return array|false
	 */
	public function metadata( $id );

	/**
	 * Attachment URL, as wp_get_attachment_url().
	 *
	 * @param int $id Attachment ID.
	 * @return string|false
	 */
	public function url( $id );

	/**
	 * Image source for a size, as wp_get_attachment_image_src(). In WordPress this
	 * runs the image_downsize filter, so it can return a Tachyon URL.
	 *
	 * @param int          $id   Attachment ID.
	 * @param string|int[] $size Size name or [ width, height ].
	 * @return array|false [ url, width, height, is_intermediate ].
	 */
	public function source( $id, $size );

	/**
	 * Whether the ID belongs to an attachment post.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function is_attachment( $id );

	/**
	 * Intermediate size data, as image_get_intermediate_size().
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $size Size name.
	 * @return array|false
	 */
	public function intermediate( $id, $size );

	/**
	 * Resize geometry, as image_resize_dimensions().
	 *
	 * @param int        $orig_w Original width.
	 * @param int        $orig_h Original height.
	 * @param int        $dest_w Requested width.
	 * @param int        $dest_h Requested height.
	 * @param bool|array $crop   Crop setting.
	 * @return array|false Index 4 and 5 hold the new width and height, index 6 and 7 the
	 *                     source crop size (the original size when not cropping).
	 */
	public function resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop = false );

	/**
	 * Dimensions limited for display, as image_constrain_size_for_editor().
	 *
	 * @param int          $width   Width.
	 * @param int          $height  Height.
	 * @param string|int[] $size    Size name or [ width, height ].
	 * @param string|null  $context Context, e.g. 'display'.
	 * @return int[] [ width, height ].
	 */
	public function constrain( $width, $height, $size, $context = null );

	/**
	 * The raw $content_width global, or false when it is not set.
	 *
	 * @return mixed
	 */
	public function content_width();

	/**
	 * Base URL of the uploads directory.
	 *
	 * @return string
	 */
	public function upload_base_url();

	/**
	 * Warm caches for attachments that are about to be looked up.
	 *
	 * @param int[] $ids Attachment IDs.
	 * @return void
	 */
	public function prime_attachments( array $ids );
}
