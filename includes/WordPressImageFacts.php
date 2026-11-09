<?php
/**
 * Image Facts from WordPress.
 *
 * No strict_types: see Url.php.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * The production adapter: each method asks WordPress.
 */
final class WordPressImageFacts implements ImageFacts {

	/**
	 * Registered sizes, read once per adapter.
	 *
	 * @var array|null
	 */
	private $sizes = null;

	/**
	 * {@inheritDoc}
	 */
	public function sizes() {
		if ( null === $this->sizes ) {
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
				$this->sizes = array_merge( $images, $_wp_additional_image_sizes );
			} else {
				$this->sizes = $images;
			}
		}

		return is_array( $this->sizes ) ? $this->sizes : [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function metadata( $id ) {
		return wp_get_attachment_metadata( $id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function url( $id ) {
		return wp_get_attachment_url( $id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function source( $id, $size ) {
		return wp_get_attachment_image_src( $id, $size );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_attachment( $id ) {
		$post = get_post( $id );

		return is_object( $post ) && ! is_wp_error( $post ) && 'attachment' === $post->post_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function intermediate( $id, $size ) {
		return image_get_intermediate_size( $id, $size );
	}

	/**
	 * {@inheritDoc}
	 */
	public function resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop = false ) {
		return image_resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop );
	}

	/**
	 * {@inheritDoc}
	 */
	public function constrain( $width, $height, $size, $context = null ) {
		return image_constrain_size_for_editor( $width, $height, $size, $context );
	}

	/**
	 * {@inheritDoc}
	 */
	public function content_width() {
		return isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function upload_base_url() {
		return wp_upload_dir()['baseurl'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function prime_attachments( array $ids ) {
		_prime_post_caches( $ids, false, true );
	}
}
