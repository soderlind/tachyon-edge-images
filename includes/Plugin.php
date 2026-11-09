<?php
/**
 * Bootstrap.
 *
 * @package TachyonEdgeImages
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Loads the Public API, or enters Stand-down.
 */
final class Plugin {

	/**
	 * Whether boot() has run.
	 */
	private static bool $booted = false;

	/**
	 * Whether the Public API is ours (not in Stand-down).
	 */
	private static bool $active = false;

	/**
	 * Boot once, at plugin load time.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		SiteHealth::register();

		$reason = StandDown::reason();
		if ( null !== $reason ) {
			StandDown::register( $reason );
			return;
		}

		self::$active = true;

		// Like the Reference Plugin, the functions exist even without TACHYON_URL.
		require_once __DIR__ . '/api.php';

		if ( ! defined( 'TACHYON_URL' ) || ! TACHYON_URL ) {
			return;
		}

		require_once __DIR__ . '/class-tachyon.php';
		\Tachyon::instance();

		Editor::register();
	}

	/**
	 * Whether Tachyon Edge Images provides the Public API in this request.
	 */
	public static function is_active(): bool {
		return self::$active;
	}
}
