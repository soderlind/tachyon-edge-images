<?php
/**
 * Plugin Name:       Tachyon Edge Images
 * Plugin URI:        https://github.com/soderlind/tachyon-edge-images
 * Description:       Compatible replacement for the Tachyon plugin: rewrites image URLs to a Tachyon server, with fixes for content, editor and URL bugs.
 * Version:           0.2.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            Per Soderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tachyon-edge-images
 *
 * Based on Tachyon by Human Made Limited and Automattic Inc.
 *
 * @package TachyonEdgeImages
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This file must not declare tachyon_url() or class Tachyon: the Reference Plugin
// declares them unguarded, so both loading at once would fatal (ADR 0002).

const VERSION = '0.2.0';
const FILE    = __FILE__;

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( str_starts_with( $class, $prefix ) ) {
			$file = __DIR__ . '/includes/' . substr( $class, strlen( $prefix ) ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	}
);

// Boot at load time, like the Reference Plugin, so themes and plugins loaded
// later see tachyon_url() exactly as before.
Plugin::boot();
