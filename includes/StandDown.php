<?php
/**
 * Stand-down: do nothing while the Reference Plugin is active (ADR 0002).
 *
 * @package TachyonEdgeImages
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages;

/**
 * Detects the Reference Plugin and lets an admin deactivate it.
 */
final class StandDown {

	/**
	 * The Reference Plugin's symbols are already declared (mu-plugin, network, Composer, earlier plugin).
	 */
	public const LOADED = 'loaded';

	/**
	 * The Reference Plugin is active and will load after us.
	 */
	public const ACTIVE = 'active';

	/**
	 * admin-post action that deactivates the Reference Plugin.
	 */
	public const ACTION = 'tachyon_edge_images_deactivate_reference';

	/**
	 * Why we stand down, if we do.
	 */
	private static ?string $reason = null;

	/**
	 * Why Tachyon Edge Images must stand down, or null when it may load.
	 */
	public static function reason(): ?string {
		if ( function_exists( 'tachyon_url' ) || class_exists( 'Tachyon', false ) ) {
			return self::LOADED;
		}

		return null !== self::reference_basename() ? self::ACTIVE : null;
	}

	/**
	 * The current Stand-down reason, or null when not standing down.
	 */
	public static function current(): ?string {
		return self::$reason;
	}

	/**
	 * Enter Stand-down: register the notice and the deactivate action.
	 *
	 * @param string $reason self::LOADED or self::ACTIVE.
	 */
	public static function register( string $reason ): void {
		self::$reason = $reason;

		add_action( 'admin_notices', [ self::class, 'notice' ] );
		add_action( 'network_admin_notices', [ self::class, 'notice' ] );
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle_deactivate' ] );
	}

	/**
	 * Basename of the active Reference Plugin (any folder, main file tachyon.php).
	 */
	public static function reference_basename(): ?string {
		$plugins = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$plugins = array_merge( array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ), $plugins );
		}

		foreach ( $plugins as $plugin ) {
			if ( is_string( $plugin ) && str_contains( $plugin, '/' ) && 'tachyon.php' === basename( $plugin ) ) {
				return $plugin;
			}
		}

		return null;
	}

	/**
	 * Whether the Reference Plugin is network-activated.
	 *
	 * @param string $basename Plugin basename.
	 */
	public static function is_network_active( string $basename ): bool {
		return is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', [] ) )[ $basename ] );
	}

	/**
	 * Admin notice, with a deactivate button for those allowed to use it.
	 */
	public static function notice(): void {
		$basename = self::reference_basename();
		$network  = null !== $basename && self::is_network_active( $basename );
		$cap      = $network ? 'manage_network_plugins' : 'activate_plugins';

		if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( $cap ) ) {
			return;
		}

		if ( null === $basename ) {
			$message = __( 'Tachyon Edge Images is inactive because Tachyon (tachyon-plugin) is loaded as a must-use plugin or by Composer. Remove it to switch to Tachyon Edge Images.', 'tachyon-edge-images' );
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
			return;
		}

		$message = __( 'Tachyon Edge Images is inactive while Tachyon (tachyon-plugin) is active. Deactivate Tachyon to switch; image URLs stay compatible.', 'tachyon-edge-images' );
		$button  = '';

		if ( current_user_can( $cap ) && $network === is_network_admin() ) {
			$url    = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
			$button = sprintf(
				' <a class="button button-primary" href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Deactivate Tachyon', 'tachyon-edge-images' )
			);
		} elseif ( $network ) {
			$message .= ' ' . __( 'Tachyon is network-activated, so a network administrator must deactivate it.', 'tachyon-edge-images' );
		}

		printf( '<div class="notice notice-warning"><p>%s%s</p></div>', esc_html( $message ), $button ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $button is built from escaped parts.
	}

	/**
	 * Deactivate the Reference Plugin after a confirmed admin request.
	 */
	public static function handle_deactivate(): void {
		$basename = self::reference_basename();
		$network  = null !== $basename && self::is_network_active( $basename );

		if ( ! current_user_can( $network ? 'manage_network_plugins' : 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to deactivate plugins.', 'tachyon-edge-images' ), 403 );
		}

		check_admin_referer( self::ACTION );

		if ( null !== $basename ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins( $basename, false, $network );
		}

		wp_safe_redirect( $network ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) );
		exit;
	}
}
