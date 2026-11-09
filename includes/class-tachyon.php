<?php
/**
 * Public API: the Reference Plugin's Tachyon class.
 *
 * A facade with the same members and hook registrations as the Reference
 * Plugin, so code that calls Tachyon or removes its hooks keeps working.
 * Loaded only outside Stand-down, and only when TACHYON_URL is set.
 *
 * phpcs:disable HM.Functions.NamespacedFunctions.MissingNamespace
 * phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 *
 * @package TachyonEdgeImages
 */

use Soderlind\Plugin\TachyonEdgeImages\Content;
use Soderlind\Plugin\TachyonEdgeImages\Downsize;
use Soderlind\Plugin\TachyonEdgeImages\Images;
use Soderlind\Plugin\TachyonEdgeImages\Rest;
use Soderlind\Plugin\TachyonEdgeImages\Srcset;

/**
 * Plugin singleton class.
 */
class Tachyon {
	/**
	 * The singleton.
	 *
	 * @var Tachyon|null
	 */
	private static $__instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Allowed extensions.
	 *
	 * @var array
	 */
	protected static $extensions = Images::EXTENSIONS;

	/**
	 * Image sizes. Use self::image_sizes().
	 *
	 * @var array|null
	 */
	protected static $image_sizes = null;

	/**
	 * Singleton implementation.
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			$class            = get_called_class();
			self::$__instance = new $class();
			self::$__instance->setup();
		}

		return self::$__instance;
	}

	/**
	 * Silence is golden.
	 */
	private function __construct() {}

	/**
	 * Register actions and filters exactly as the Reference Plugin does.
	 *
	 * @return void
	 */
	private function setup() {
		if ( ! function_exists( 'tachyon_url' ) ) {
			return;
		}

		// Images in post content and galleries.
		add_filter( 'the_content', [ __CLASS__, 'filter_the_content' ], 999999 );
		add_filter( 'get_post_galleries', [ __CLASS__, 'filter_the_galleries' ], 999999 );

		// Core image retrieval.
		add_filter( 'image_downsize', [ $this, 'filter_image_downsize' ], 10, 3 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'should_rest_image_downsize' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'cleanup_rest_image_downsize' ] );

		// Responsive image srcset substitution.
		add_filter( 'wp_calculate_image_srcset', [ $this, 'filter_srcset_array' ], 10, 5 );
	}

	/**
	 * Match all images and any relevant <a> tags in a block of HTML.
	 *
	 * @param string $content Some HTML.
	 * @return array
	 */
	public static function parse_images_from_html( $content ) {
		return Images::parse_images_from_html( $content );
	}

	/**
	 * Try to determine height and width from strings WP appends to resized image filenames.
	 *
	 * @param string $src The image URL.
	 * @return array An array consisting of width and height.
	 */
	public static function parse_dimensions_from_filename( $src ) {
		return Images::parse_dimensions_from_filename( $src );
	}

	/**
	 * Identify images in post content, and if images are local, pass them through Tachyon.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_the_content( $content ) {
		return Content::filter( $content );
	}

	/**
	 * Ensure galleries use Tachyon.
	 *
	 * @param array $galleries The post's galleries.
	 * @return array
	 */
	public static function filter_the_galleries( $galleries ) {
		return Content::filter_galleries( $galleries );
	}

	/**
	 * Filter post thumbnail image retrieval, passing images through Tachyon.
	 *
	 * @param array|bool       $image         Image array or false.
	 * @param int              $attachment_id The attachment ID.
	 * @param string|int|array $size          The target image size.
	 * @return array|bool
	 */
	public function filter_image_downsize( $image, $attachment_id, $size ) {
		return Downsize::filter( $image, $attachment_id, $size );
	}

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
	public function filter_srcset_array( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		return Srcset::filter( $sources, $size_array, $image_src, $image_meta, $attachment_id );
	}

	/**
	 * Ensure image URL is valid for Tachyon.
	 *
	 * @param string $url An image URL.
	 * @return bool
	 */
	public static function validate_image_url( $url ) {
		return Images::validate_image_url( $url );
	}

	/**
	 * Remove WP's -WxH suffix so the original image is passed to Tachyon.
	 *
	 * @param string $src The image URL.
	 * @return string
	 */
	protected static function strip_image_dimensions_maybe( $src ) {
		return Images::strip_image_dimensions_maybe( $src );
	}

	/**
	 * Available image sizes and their dimensions.
	 *
	 * @return array
	 */
	protected static function image_sizes() {
		return Images::image_sizes();
	}

	/**
	 * Determine if image_downsize should use Tachyon in this REST request.
	 *
	 * @param null|WP_Error|WP_REST_Response $response      Result to send to the client.
	 * @param array                          $endpoint_data Route handler used for the request.
	 * @param WP_REST_Request                $request       Request used to generate the response.
	 * @return null|WP_Error|WP_REST_Response The original response without modification.
	 */
	public function should_rest_image_downsize( $response, $endpoint_data, $request ) {
		return Rest::shared()->before( $response, [ $this, '_override_image_downsize_in_rest_edit_context' ], $request );
	}

	/**
	 * Remove the override added in ::should_rest_image_downsize().
	 *
	 * @param mixed $response The result to send to the client.
	 * @return mixed Unchanged $response
	 */
	public function cleanup_rest_image_downsize( $response ) {
		return Rest::shared()->after( $response, [ $this, '_override_image_downsize_in_rest_edit_context' ] );
	}

	/**
	 * Used internally by ::should_rest_image_downsize(). MUST NOT be used anywhere else.
	 *
	 * @internal
	 * @return true
	 */
	public function _override_image_downsize_in_rest_edit_context() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		return true;
	}
}
