<?php
/**
 * Block editor middleware loader (Deviation 7).
 *
 * This is the client half of Deviation 7. Rest is the server half: it gives
 * requests marked with Rest::EDITOR_HEADER original image URLs.
 *
 * @package TachyonEdgeImages
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Enqueues the apiFetch middleware that marks editor REST requests.
 */
final class Editor {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'tachyon-edge-images-editor';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueue the middleware.
	 */
	public static function enqueue(): void {
		$path = dirname( FILE ) . '/assets/js/editor.js';

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'assets/js/editor.js', FILE ),
			[ 'wp-api-fetch' ],
			(string) ( filemtime( $path ) ?: VERSION ),
			false
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.tachyonEdgeImagesRestUrl = ' . wp_json_encode( rest_url() ) . ';',
			'before'
		);
	}
}
