<?php
/**
 * Public API: the Reference Plugin's global functions.
 *
 * Loaded only outside Stand-down, so it never collides with the Reference Plugin.
 * Like the Reference Plugin, these exist even when TACHYON_URL is not defined.
 *
 * phpcs:disable HM.Functions.NamespacedFunctions.MissingNamespace
 *
 * @package TachyonEdgeImages
 */

use Soderlind\Plugin\TachyonEdgeImages\Url;

/**
 * Generates a Tachyon URL.
 *
 * @param string       $image_url URL to the publicly accessible image you want to manipulate.
 * @param array|string $args      An array of arguments, i.e. array( 'w' => '300', 'resize' => array( 123, 456 ) ), or in string form (w=123&h=456).
 * @param string|null  $scheme    One of http or https.
 * @return string The raw final URL. You should run this through esc_url() before displaying it.
 */
function tachyon_url( $image_url, $args = [], $scheme = null ) {
	return Url::build( $image_url, $args, $scheme );
}

/**
 * Get the version of the Tachyon Server.
 *
 * This is not always known, so it may return null.
 *
 * @return string|null
 */
function tachyon_server_version() {
	return Url::server_version();
}
