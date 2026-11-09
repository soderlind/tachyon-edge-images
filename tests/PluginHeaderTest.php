<?php

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests;

use PHPUnit\Framework\TestCase;

final class PluginHeaderTest extends TestCase {

	private static function main_file(): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/tachyon-edge-images.php' );
	}

	private static function header( string $name ): ?string {
		return preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':[ \t]*([^\r\n]+)/mi', self::main_file(), $m ) ? trim( $m[1] ) : null;
	}

	public function test_version_header_matches_constant(): void {
		$this->assertSame( 1, preg_match( "/const VERSION\s*=\s*'([^']+)'/", self::main_file(), $m ) );
		$this->assertSame( $m[1], self::header( 'Version' ) );
	}

	public function test_requirements(): void {
		$this->assertSame( '8.3', self::header( 'Requires PHP' ) );
		$this->assertSame( 'tachyon-edge-images', self::header( 'Text Domain' ) );
	}

	public function test_does_not_require_the_reference_plugin(): void {
		$this->assertNull( self::header( 'Requires Plugins' ) );
	}

	public function test_main_file_declares_no_public_api(): void {
		// The Public API is declared later, and only when not standing down (ADR 0002).
		$code = '';
		foreach ( token_get_all( self::main_file() ) as $token ) {
			if ( ! is_array( $token ) || ! in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
				$code .= is_array( $token ) ? $token[1] : $token;
			}
		}
		$this->assertDoesNotMatchRegularExpression( '/function\s+tachyon_|class\s+Tachyon\b/i', $code );
	}

	public function test_readme_stable_tag_matches_version(): void {
		$readme = dirname( __DIR__ ) . '/readme.txt';
		if ( ! is_file( $readme ) ) {
			$this->markTestSkipped( 'readme.txt not written yet.' );
		}
		$this->assertSame( 1, preg_match( '/^Stable tag:\s*(\S+)/mi', (string) file_get_contents( $readme ), $m ) );
		$this->assertSame( self::header( 'Version' ), $m[1] );
	}
}
