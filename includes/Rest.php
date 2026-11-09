<?php
/**
 * Disables Tachyon for REST requests used to edit content.
 *
 * Deviation 7: the Reference Plugin only covered media requests in the edit
 * context, and a nested REST request switched Tachyon back on too early. The
 * block editor also loads media in the view context, so it showed resized images.
 *
 * This is the server half of Deviation 7. Editor enqueues the client half,
 * assets/js/editor.js, which marks the editor's requests with EDITOR_HEADER.
 *
 * @package TachyonEdgeImages
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Tracks REST requests that must return original image URLs.
 */
final class Rest {

	/**
	 * Header the editor middleware adds to its requests.
	 */
	public const EDITOR_HEADER = 'x-wp-api-fetch-from-editor';

	/**
	 * The instance the facade uses. Tachyon cannot hold it, because its members
	 * must match the Reference Plugin's.
	 */
	private static ?self $shared = null;

	/**
	 * One entry per REST request in progress; true when it disabled Tachyon.
	 *
	 * @var bool[]
	 */
	private array $stack = [];

	/**
	 * The instance the facade uses for this page load.
	 */
	public static function shared(): self {
		return self::$shared ??= new self();
	}

	/**
	 * Before a REST request runs: decide whether it gets original URLs.
	 *
	 * @param mixed    $response Result to send to the client.
	 * @param callable $override The facade's override callback.
	 * @param mixed    $request  The request.
	 * @return mixed Unchanged $response.
	 */
	public function before( mixed $response, callable $override, mixed $request ): mixed {
		$this->stack[] = false;

		if ( ! $request instanceof \WP_REST_Request || is_wp_error( $response ) ) {
			return $response;
		}

		if ( self::is_media_edit_request( $request ) || self::is_editor_request( $request ) ) {
			$this->stack[ array_key_last( $this->stack ) ] = true;
			// Late priority, as in the Reference Plugin, to win over other plugins.
			add_filter( 'tachyon_override_image_downsize', $override, 999999 );
		}

		return $response;
	}

	/**
	 * After a REST request: remove the override once no enclosing request needs it.
	 *
	 * @param mixed    $response Result to send to the client.
	 * @param callable $override The facade's override callback.
	 * @return mixed Unchanged $response.
	 */
	public function after( mixed $response, callable $override ): mixed {
		array_pop( $this->stack );
		if ( ! in_array( true, $this->stack, true ) ) {
			remove_filter( 'tachyon_override_image_downsize', $override, 999999 );
		}

		return $response;
	}

	/**
	 * Media requested in the edit context, as in the Reference Plugin.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	private static function is_media_edit_request( \WP_REST_Request $request ): bool {
		return str_contains( $request->get_route(), 'wp/v2/media' ) && 'edit' === $request['context'];
	}

	/**
	 * A request from the block editor: marked by its middleware, or preloaded on an editor screen.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	private static function is_editor_request( \WP_REST_Request $request ): bool {
		// Several middlewares may combine values into one header.
		$header = (string) $request->get_header( self::EDITOR_HEADER );
		if ( '' !== $header && in_array( 'true', array_map( 'trim', explode( ',', $header ) ), true ) ) {
			return true;
		}

		return is_admin() && function_exists( 'wp_should_load_block_editor_scripts_and_styles' ) && wp_should_load_block_editor_scripts_and_styles();
	}
}
