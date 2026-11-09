<?php
/**
 * Base for tests that drive a sizing path through its interface.
 */

declare(strict_types=1);

namespace Soderlind\Plugin\TachyonEdgeImages\Tests\Support;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Soderlind\Plugin\TachyonEdgeImages\Facts;
use Soderlind\Plugin\TachyonEdgeImages\Url;

/**
 * Swaps in InMemoryImageFacts. Url is a real collaborator, not faked.
 */
abstract class SizingTestCase extends TestCase {

	protected const UPLOADS = 'https://example.com/wp-content/uploads';

	protected const TACHYON = 'https://images.example.com/uploads';

	protected InMemoryImageFacts $facts;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->facts                  = new InMemoryImageFacts();
		$this->facts->upload_base_url = self::UPLOADS;
		Facts::swap( $this->facts );

		// Url and image URL validation stay outside the Image Facts seam.
		Functions\when( 'wp_upload_dir' )->justReturn( [ 'baseurl' => self::UPLOADS ] );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'tachyon_server_version' )->alias( [ Url::class, 'server_version' ] );
		Functions\when( 'add_query_arg' )->alias( [ QueryArgs::class, 'add_query_arg' ] );
		Functions\when( 'remove_query_arg' )->alias( [ QueryArgs::class, 'remove_query_arg' ] );
		Functions\stubs(
			[
				'esc_url'         => static fn( $url ) => $url,
				'trailingslashit' => static fn( $s ) => rtrim( $s, '/' ) . '/',
				'absint'          => static fn( $n ) => abs( (int) $n ),
			]
		);
	}

	protected function tearDown(): void {
		Facts::swap( null );
		Monkey\tearDown();
		parent::tearDown();
	}
}
