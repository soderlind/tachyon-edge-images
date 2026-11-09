<?php
/**
 * Tachyon URL generation, ported from the Reference Plugin's tachyon_url().
 *
 * No strict_types: the ported logic relies on the Reference Plugin's loose
 * scalar handling, and callers may pass any type.
 *
 * @package TachyonEdgeImages
 */

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Builds Tachyon URLs.
 */
final class Url {

	/**
	 * Generate a Tachyon URL. See tachyon_url().
	 *
	 * @param string       $image_url Image URL.
	 * @param array|string $args      Tachyon arguments.
	 * @param string|null  $scheme    Scheme.
	 * @return string
	 */
	public static function build( $image_url, $args = [], $scheme = null ) {
		$upload_dir     = wp_upload_dir();
		$upload_baseurl = $upload_dir['baseurl'];

		if ( is_multisite() ) {
			$upload_baseurl = preg_replace( '#/sites/[\d]+#', '', $upload_baseurl );
		}

		$image_url      = trim( $image_url );
		// An upload URL is never treated as a Tachyon URL, even when TACHYON_URL equals or prefixes the uploads URL.
		$is_tachyon_url = self::is_tachyon_url( $image_url ) && 0 !== strpos( $image_url, $upload_baseurl );

		// The Reference Plugin returns an existing Tachyon URL unchanged and drops
		// $args. Deviation 6: it is never encoded or rewritten a second time.
		if ( $is_tachyon_url && ! self::extends_tachyon_urls( $image_url, $args ) ) {
			return $image_url;
		}

		if ( ! $is_tachyon_url ) {
			$image_file = basename( (string) parse_url( $image_url, PHP_URL_PATH ) );
			// Deviation 6: an already-encoded basename is not encoded again.
			if ( rawurldecode( $image_file ) === $image_file ) {
				$image_url = str_replace( $image_file, urlencode( $image_file ), $image_url );
			}

			if ( strpos( $image_url, $upload_baseurl ) !== 0 ) {
				return $image_url;
			}
		}

		if ( false !== apply_filters( 'tachyon_skip_for_url', false, $image_url, $args, $scheme ) ) {
			return $image_url;
		}

		$image_url = apply_filters( 'tachyon_pre_image_url', $image_url, $args, $scheme );

		// If the image URL has X-Amz params for signed requests, we need to add them to the Tachyon URL
		// under a `presign` param. However, only do this if we're on a version of Tachyon that supports it.
		if ( ! $is_tachyon_url && tachyon_server_version() && version_compare( tachyon_server_version(), '3.0.0', '>=' ) && stripos( $image_url, 'X-Amz-' ) !== false ) {
			$params  = [];
			$presign = [];
			$query   = parse_url( $image_url, PHP_URL_QUERY );
			parse_str( (string) $query, $params );
			foreach ( $params as $key => $value ) {
				if ( stripos( $key, 'X-Amz-' ) === 0 ) {
					$presign[ $key ] = $value;
					$image_url       = remove_query_arg( $key, $image_url );
				}
			}
			$image_url = add_query_arg( 'presign', urlencode( http_build_query( $presign ) ), $image_url );
		}

		$args = apply_filters( 'tachyon_pre_args', $args, $image_url, $scheme );

		// Deviation 3: without TACHYON_URL the Reference Plugin fatals here.
		if ( ! defined( 'TACHYON_URL' ) || ! TACHYON_URL ) {
			return $image_url;
		}

		$tachyon_url = $is_tachyon_url ? $image_url : str_replace( $upload_baseurl, TACHYON_URL, $image_url );
		if ( $args ) {
			if ( is_array( $args ) ) {
				// URL encode all param values, as this is not handled by add_query_arg.
				$tachyon_url = add_query_arg( array_map( 'rawurlencode', $args ), $tachyon_url );
			} else {
				// You can pass a query string for complicated requests but where you still want CDN subdomain help, etc.
				$tachyon_url = self::append_query_string( $tachyon_url, (string) $args );
			}
		}

		/**
		 * Allows a final modification of the generated tachyon URL.
		 *
		 * @param string $tachyon_url The final tachyon image URL including query args.
		 * @param string $image_url   The image URL without query args.
		 * @param array  $args        A key value array of the query args appended to $image_url.
		 */
		return apply_filters( 'tachyon_url', $tachyon_url, $image_url, $args );
	}

	/**
	 * Get the version of the Tachyon Server. See tachyon_server_version().
	 *
	 * @return string|null
	 */
	public static function server_version() {
		$version = null;
		if ( defined( 'TACHYON_SERVER_VERSION' ) ) {
			$version = TACHYON_SERVER_VERSION;
		}

		return apply_filters( 'tachyon_server_version', $version );
	}

	/**
	 * Whether a URL already points at the Tachyon Server.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_tachyon_url( $url ) {
		if ( ! defined( 'TACHYON_URL' ) || ! TACHYON_URL ) {
			return false;
		}

		$base   = rtrim( TACHYON_URL, '/' );
		$length = strlen( $base );

		return '' !== $base && 0 === strpos( $url, $base ) &&
			( strlen( $url ) === $length || in_array( substr( $url, $length, 1 ), [ '/', '?', '#' ], true ) );
	}

	/**
	 * Whether arguments are added to URLs that already point at the Tachyon Server.
	 *
	 * Opt-in: the Reference Plugin drops them, and sites may rely on that.
	 *
	 * @param string       $image_url Tachyon URL.
	 * @param array|string $args      Tachyon arguments.
	 * @return bool
	 */
	private static function extends_tachyon_urls( $image_url, $args ) {
		$default = defined( 'TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS' ) && TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS;

		/**
		 * Filters whether tachyon_url() adds arguments to an existing Tachyon URL.
		 *
		 * @param bool         $extend    Default false, or TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS.
		 * @param string       $image_url The existing Tachyon URL.
		 * @param array|string $args      The arguments that would be added.
		 */
		return (bool) apply_filters( 'tachyon_edge_images_extend_tachyon_urls', $default, $image_url, $args );
	}

	/**
	 * Append a query string, keeping the URL well formed.
	 *
	 * Deviations 4 and 5: the Reference Plugin always appends `?`, even after an
	 * existing query, and puts the arguments after any #fragment.
	 *
	 * @param string $url   URL.
	 * @param string $query Query string without a leading separator.
	 * @return string
	 */
	private static function append_query_string( $url, $query ) {
		$fragment = '';
		$position = strpos( $url, '#' );
		if ( false !== $position ) {
			$fragment = substr( $url, $position );
			$url      = substr( $url, 0, $position );
		}

		$separator = false === strpos( $url, '?' ) ? '?' : '&';
		if ( in_array( substr( $url, -1 ), [ '?', '&' ], true ) ) {
			$separator = '';
		}

		return $url . $separator . $query . $fragment;
	}
}
