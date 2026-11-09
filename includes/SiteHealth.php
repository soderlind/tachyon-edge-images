<?php
/**
 * Site Health checks and debug information.
 *
 * @package TachyonEdgeImages
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Adds a Site Health test and a debug section.
 */
final class SiteHealth {

	/**
	 * Test and section ID.
	 */
	public const ID = 'tachyon_edge_images';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', [ self::class, 'tests' ] );
		add_filter( 'debug_information', [ self::class, 'debug_information' ] );
	}

	/**
	 * Add the test.
	 *
	 * @param array<string, mixed> $tests Site Health tests.
	 * @return array<string, mixed>
	 */
	public static function tests( mixed $tests ): mixed {
		if ( is_array( $tests ) ) {
			$tests['direct'][ self::ID ] = [
				'label' => __( 'Tachyon Edge Images', 'tachyon-edge-images' ),
				'test'  => [ self::class, 'test' ],
			];
		}

		return $tests;
	}

	/**
	 * Run the test: Stand-down, TACHYON_URL and a probe of the Tachyon Server.
	 *
	 * @return array<string, mixed>
	 */
	public static function test(): array {
		if ( null !== StandDown::current() ) {
			return self::result(
				'recommended',
				__( 'Tachyon Edge Images is inactive while Tachyon (tachyon-plugin) is loaded', 'tachyon-edge-images' ),
				__( 'Image URLs are rewritten by Tachyon (tachyon-plugin). Deactivate or remove it to switch to Tachyon Edge Images.', 'tachyon-edge-images' )
			);
		}

		if ( ! defined( 'TACHYON_URL' ) || ! TACHYON_URL ) {
			return self::result(
				'critical',
				__( 'TACHYON_URL is not set', 'tachyon-edge-images' ),
				__( 'Define TACHYON_URL in wp-config.php so image URLs are rewritten to your Tachyon server.', 'tachyon-edge-images' )
			);
		}

		$probe = self::probe();
		if ( null === $probe ) {
			return self::result(
				'good',
				__( 'Tachyon Edge Images is active', 'tachyon-edge-images' ),
				__( 'TACHYON_URL is set. There are no image uploads yet, so the Tachyon server was not probed.', 'tachyon-edge-images' )
			);
		}

		if ( true !== $probe ) {
			return self::result(
				'critical',
				__( 'The Tachyon server did not return an image', 'tachyon-edge-images' ),
				/* translators: %s: error details. */
				sprintf( __( 'A resized copy of the latest image upload could not be fetched: %s', 'tachyon-edge-images' ), $probe )
			);
		}

		return self::result(
			'good',
			__( 'Tachyon Edge Images is active and the Tachyon server responds', 'tachyon-edge-images' ),
			__( 'A resized copy of the latest image upload was fetched from the Tachyon server.', 'tachyon-edge-images' )
		);
	}

	/**
	 * Fetch a small variant of the latest image upload.
	 *
	 * @return true|string|null True on success, an error message, or null when there is nothing to probe.
	 */
	public static function probe(): true|string|null {
		$ids = get_posts(
			[
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		$url = $ids ? wp_get_attachment_url( (int) $ids[0] ) : false;
		if ( ! is_string( $url ) || '' === $url || ! function_exists( 'tachyon_url' ) ) {
			return null;
		}

		$probe_url = tachyon_url( $url, [ 'w' => 16 ] );
		if ( $probe_url === $url ) {
			return null;
		}

		// TACHYON_URL is admin-configured and often local in development, which the "safe" variant rejects.
		$response = wp_remote_get( $probe_url, [ 'timeout' => 5 ] );
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$type   = (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( 200 !== $status || ! str_starts_with( strtolower( $type ), 'image/' ) ) {
			/* translators: 1: HTTP status code, 2: content type. */
			return sprintf( __( 'HTTP %1$d, content type "%2$s".', 'tachyon-edge-images' ), $status, $type );
		}

		return true;
	}

	/**
	 * Add a debug section.
	 *
	 * @param array<string, mixed> $info Debug information.
	 * @return array<string, mixed>
	 */
	public static function debug_information( mixed $info ): mixed {
		if ( ! is_array( $info ) ) {
			return $info;
		}

		$not_set = __( 'Not set', 'tachyon-edge-images' );
		$fields  = [
			'version'        => [
				'label' => __( 'Version', 'tachyon-edge-images' ),
				'value' => VERSION,
			],
			'stand_down'     => [
				'label' => __( 'Stand-down', 'tachyon-edge-images' ),
				'value' => StandDown::current() ?? __( 'No', 'tachyon-edge-images' ),
				'debug' => StandDown::current() ?? 'no',
			],
			'tachyon_url'    => [
				'label' => 'TACHYON_URL',
				'value' => defined( 'TACHYON_URL' ) && TACHYON_URL ? (string) TACHYON_URL : $not_set,
			],
			'server_version' => [
				'label' => 'tachyon_server_version()',
				'value' => Plugin::is_active() && function_exists( 'tachyon_server_version' ) ? ( (string) tachyon_server_version() ?: $not_set ) : $not_set,
			],
			'extend_urls'    => [
				'label' => 'TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS',
				'value' => defined( 'TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS' ) && TACHYON_EDGE_IMAGES_EXTEND_TACHYON_URLS ? 'true' : 'false',
			],
		];

		foreach ( Deviations::ALL as $number => $description ) {
			$fields[ 'deviation_' . $number ] = [
				/* translators: %d: Deviation number. */
				'label' => sprintf( __( 'Deviation %d', 'tachyon-edge-images' ), $number ),
				'value' => $description,
			];
		}

		$info[ self::ID ] = [
			'label'  => __( 'Tachyon Edge Images', 'tachyon-edge-images' ),
			'fields' => $fields,
		];

		return $info;
	}

	/**
	 * Build a Site Health result.
	 *
	 * @param string $status      good, recommended or critical.
	 * @param string $label       Result label.
	 * @param string $description Result description.
	 * @return array<string, mixed>
	 */
	private static function result( string $status, string $label, string $description ): array {
		return [
			'label'       => $label,
			'status'      => $status,
			'badge'       => [
				'label' => __( 'Performance', 'tachyon-edge-images' ),
				'color' => 'blue',
			],
			'description' => sprintf( '<p>%s</p>', esc_html( $description ) ),
			'test'        => self::ID,
		];
	}
}
